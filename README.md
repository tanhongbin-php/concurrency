# thb/concurrency

Webman 插件：多端口 HTTP 并发进程 + 客户端轮询（请求级尽量均分）。

## 安装

```bash
composer require thb/concurrency
```

## 配置

`config/plugin/thb/concurrency/app.php`：

| 项 | 说明 | 默认 |
|---|---|---|
| `enable` | 是否启用 | `true` |
| `host` | 监听/请求主机 | `127.0.0.1` |
| `base_port` | 起始端口，实际=`base_port+1`… | `8680` |
| `listen_count` | 端口数量（可用 `ASYNC_COUNT`） | `8` |
| `worker_count` | 每个端口进程数 | `2` |
| `path` | 调用路径 | `/async` |
| `client.connection` | `close` 更均匀 / `keep-alive` 更快 | `close` |

## 用法

```php
use Thb\Concurrency\Client;

$client = new Client();
$results = $client->requestArray([
    ['class' => \app\logic\CeshiLogic::class, 'method' => 'ceshi', 'arguments' => [], 'options' => []],
    ['class' => \app\logic\CeshiLogic::class, 'method' => 'ceshi'],
]);
```

进程会按配置启动：`8681`…`8680+listen_count`，客户端按顺序轮询这些端口。
