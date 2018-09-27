<?php

namespace Demo\Consumer;

class RedisConsumer
{
    private $topic = 'example';
    /**
     * redis instance
     */
    private $redis;

    /**
     * redis key
     * @var string
     */
    private static $key = 'process:manager';

    public function __construct()
    {
        $this->redis = new \Redis();
        $this->redis->connect('docker_redis_1', '6379');
    }

    public function deal($workerName)
    {
        while (true) {
            $value = $this->redis->rpop(self::$key);

            error_log('worker: ' . $workerName . "\t" . 'topic: ' . $this->topic . "\t" . $value . "\n", 3, '/var/www/error.log');
            usleep(1);
        }
    }

    public static function checkStack()
    {
        $redis = new \Redis();
        $redis->connect('docker_redis_1', '6379');
        return $redis->llen(self::$key);
    }
}


