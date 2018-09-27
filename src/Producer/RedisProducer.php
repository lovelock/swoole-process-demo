<?php

namespace Demo\Producer;

/**
 * 每秒向redis写入100条数据
 */
class RedisProducer
{
    private $processName = 'redis_producer';

    public function run()
    {
        $process = new \Swoole\Process(function (\Swoole\Process $worker) {
            swoole_set_process_name($this->processName);

            $redis = new \Redis();
            $redis->connect('docker_redis_1', '6379');
            $capacity = 100000;
            while (--$capacity > 0) {
                $redis->lpush('process:manager', $capacity);
                usleep(10);
            }
        });

        $process->start();
    }
}
