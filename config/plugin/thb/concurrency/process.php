<?php

use support\Log;
use support\Request;
use app\process\Http;

$app = config('plugin.thb.concurrency.app', []);

if (empty($app['enable'])) {
    return [];
}

$host = $app['host'] ?? '127.0.0.1';
$basePort = (int) ($app['base_port'] ?? 8680);
$listenCount = max(1, (int) ($app['listen_count'] ?? 8));
$workerCount = max(1, (int) ($app['worker_count'] ?? 2));
$reusePort = (bool) ($app['reuse_port'] ?? false);
$eventLoop = $app['event_loop'] ?? \Workerman\Events\Fiber::class;

$processes = [];
for ($i = 1; $i <= $listenCount; $i++) {
    $port = $basePort + $i;
    $processes['worker' . $i] = [
        'handler' => Http::class,
        'listen' => 'http://' . $host . ':' . $port,
        'count' => $workerCount,
        'reusePort' => $reusePort,
        'eventLoop' => $eventLoop,
        'context' => [],
        'constructor' => [
            'requestClass' => Request::class,
            'logger' => Log::channel('default'),
            'appPath' => app_path(),
            'publicPath' => public_path(),
        ],
    ];
}

return $processes;
