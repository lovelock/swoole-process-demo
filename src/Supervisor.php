<?php

namespace Demo;

/**
 * 用于管理消费队列的进程数
 */
class Supervisor
{
    const STATUS_EXIT = 0;
    const STATUS_RUNNING = 1;

    /**
     * container of workers
     * @var array
     */
    private $workers = [];

    /**
     * status of main process
     * @var int
     */
    private $status;

    /**
     * ticker timer interval
     * @var int (ms)
     */
    private $queueTickTimer = 1000 * 5;

    /**
     * container of topic config
     * @var array
     */
    private static $configBag = [
        'example' => [
            'name' => 'example',
            'num_max_processes' => 20,
            'num_min_processes' => 2,
            'threshold' => 100,
        ],
    ];

    /**
     * topic config
     * @var array
     */
    private $config;

    /**
     * workers alive
     * @var int
     */
    private $numWorkers;

    public function __construct($topic)
    {
        $this->numWorkers = 0;
        $this->config = self::$configBag[$topic];
    }

    public function start()
    {
        $this->status = self::STATUS_RUNNING;

        $numMinProcesses = $this->config['num_min_processes'];
        $numMaxProcesses = $this->config['num_max_processes'];
        $topic = $this->config['name'];

        $consumer = new Consumer\RedisConsumer();
        for ($i = 0; $i < $numMinProcesses; $i++) {
            $this->spawnProcess($topic, $consumer, $i);
        }

        $this->registerTimer();
    }

    private function registerTimer()
    {
        \Swoole\Timer::tick($this->queueTickTimer, function ($timerId) {
            $stack = Consumer\RedisConsumer::checkStack();
            echo 'there are ' . $stack  . ' messages left to consume, timerId: ' . $timerId . "\n";

            if ($stack > $this->config['threshold']) {
                $reserved = $this->config['num_max_processes'] - $this->numWorkers ;
                $topic = $this->config['name'];
                $consumer = new Consumer\RedisConsumer();
                while (--$reserved > 0) {
                    $num = $this->numWorkers + $reserved;
                    $this->spawnProcess($topic, $consumer, $num);
                }
            }
        });
    }

    private function spawnProcess($topic, $consumer, $num)
    {
        $proc = new \Swoole\Process(function (\Swoole\Process $worker) use ($topic, $consumer, $num) {
            try {
                swoole_set_process_name($topic . '_' . $num);
                $consumer->deal($worker->name);
            } catch (\Throwable $e) {
                error_log($e->getMessage(), 3, '/var/www/error.log');
            }
        });

        $pid = $proc->start();
        if ($pid > 0) {
            echo 'process ' . $pid . ' spawned' . "\n";
            $this->workers[] = $pid;
        }
        ++$this->numWorkers;
    }
}
