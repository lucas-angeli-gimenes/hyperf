<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://doc.hyperf.io
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf-cloud/hyperf/blob/master/LICENSE
 */

namespace Hyperf\ConfigEtcd\Process;

use Hyperf\ConfigEtcd\ClientInterface;
use Hyperf\ConfigEtcd\PipeMessage;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Process\AbstractProcess;
use Hyperf\Process\Annotation\Process;
use Psr\Container\ContainerInterface;
use Swoole\Server;

/**
 * @Process(name="etcd-config-fetcher")
 */
class ConfigFetcherProcess extends AbstractProcess
{
    /**
     * @var Server
     */
    private $server;

    /**
     * @var ClientInterface
     */
    private $client;

    /**
     * @var ConfigInterface
     */
    private $config;

    /**
     * @var array
     */
    private $cacheConfig;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);
        $this->client = $container->get(ClientInterface::class);
        $this->config = $container->get(ConfigInterface::class);
    }

    public function bind(Server $server): void
    {
        $this->server = $server;
        parent::bind($server);
    }

    public function isEnable(): bool
    {
        return $this->config->get('etcd.enable', false);
    }

    public function handle(): void
    {
        while (true) {
            $config = $this->client->pull();
            if ($config !== $this->cacheConfig) {
                if ($this->cacheConfig !== null) {
                    $diff = array_diff($this->cacheConfig ?? [], $config);
                } else {
                    $diff = $config;
                }
                $this->cacheConfig = $config;
                $workerCount = $this->server->setting['worker_num'] + $this->server->setting['task_worker_num'] - 1;
                for ($workerId = 0; $workerId <= $workerCount; ++$workerId) {
                    $this->server->sendMessage(new PipeMessage($diff), $workerId);
                }
            }

            sleep($this->config->get('aliyun_acm.interval', 5));
        }
    }
}
