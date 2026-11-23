<?php
/**
 * thb/concurrency 配置
 *
 * listen_count = 监听端口数量（每个端口一组进程）
 * base_port + i (i=1..listen_count) = 实际端口，如 base_port=8680 → 8681..8688
 * worker_count = 每个端口的进程数
 */
return [
    'enable' => true,
    // 监听地址
    'host' => '127.0.0.1',
    // 起始端口（实际端口 = base_port + 1 .. base_port + listen_count）
    'base_port' => 8680,
    // 端口数量（可对应 .env ASYNC_COUNT）
    'listen_count' => (int) (function_exists('env') ? env('ASYNC_COUNT', 8) : 8),
    // 每个端口的 worker 进程数
    'worker_count' => 2,
    // 是否 SO_REUSEPORT（单端口多进程时用；多端口建议 false）
    'reuse_port' => false,
    // 路由路径
    'path' => '/async',
    // 事件循环
    'event_loop' => \Workerman\Events\Fiber::class,
    // Http\Client 连接池
    'client' => [
        'max_conn_per_addr' => 128,
        'keepalive_timeout' => 0,
        'connect_timeout'   => 30,
        'timeout'           => 30,
        // close=每次新连接更均匀；keep-alive=更快但易粘连
        'connection'        => 'close',
    ],
];
