<?php
declare(strict_types=1);

namespace Thb\Concurrency;

use Workerman\Coroutine\Parallel;
use Workerman\Http\Client as HttpClient;

/**
 * 并发调用客户端：按配置端口列表轮询，Parallel 不阻塞
 *
 * 用法：
 *   $client = new \Thb\Concurrency\Client();
 *   $results = $client->requestArray([
 *       ['class' => SomeLogic::class, 'method' => 'foo', 'arguments' => [1], 'options' => []],
 *   ]);
 */
class Client
{
    protected static ?HttpClient $http = null;

    protected static int $rr = 0;

    protected array $clientOptions;

    protected string $path;

    /** @var list<string> */
    protected array $addresses;

    public function __construct(?array $clientOptions = null)
    {
        $app = config('plugin.thb.concurrency.app') ?: [];
        if (empty($app['enable'])) {
            throw new ConcurrencyException('plugin thb/concurrency 未启用');
        }

        $defaults = [
            'max_conn_per_addr' => 128,
            'keepalive_timeout' => 0,
            'connect_timeout'   => 30,
            'timeout'           => 30,
            'connection'        => 'close',
        ];
        $cfg = array_merge($defaults, (array) ($app['client'] ?? []), $clientOptions ?? []);
        $this->clientOptions = $cfg;
        $this->path = (string) ($app['path'] ?? '/async');
        $this->addresses = self::buildAddresses($app);

        if (!self::$http) {
            $pool = $cfg;
            unset($pool['connection']);
            self::$http = new HttpClient($pool);
        }
    }

    /**
     * 根据配置生成地址列表
     *
     * @param array $app
     * @return list<string>
     */
    public static function buildAddresses(array $app): array
    {
        $host = $app['host'] ?? '127.0.0.1';
        $basePort = (int) ($app['base_port'] ?? 8680);
        $listenCount = max(1, (int) ($app['listen_count'] ?? 8));
        $list = [];
        for ($i = 1; $i <= $listenCount; $i++) {
            $list[] = 'http://' . $host . ':' . ($basePort + $i);
        }
        return $list;
    }

    /**
     * 当前可用地址
     *
     * @return list<string>
     */
    public function addresses(): array
    {
        return $this->addresses;
    }

    /**
     * 轮询下一个地址
     */
    protected function nextAddress(): string
    {
        $n = count($this->addresses);
        if ($n < 1) {
            throw new ConcurrencyException('concurrency 地址列表为空');
        }
        $addr = $this->addresses[self::$rr % $n];
        self::$rr++;
        return $addr;
    }

    /**
     * 批量并发请求
     *
     * @param array<int|string, array{class?:mixed,method?:string,arguments?:array,options?:array}> $arr
     * @return array
     * @throws ConcurrencyException
     */
    public function requestArray(array $arr): array
    {
        foreach ($arr as $key => $value) {
            $class = $value['class'] ?? '';
            $method = $value['method'] ?? '';
            if ($method === '') {
                throw new ConcurrencyException('下标' . $key . '的方法不能为空');
            }
            $class = is_object($class) ? get_class($class) : (string) $class;
            if (!class_exists($class)) {
                throw new ConcurrencyException('下标' . $key . '的类不存在：' . $class);
            }
            if (!method_exists($class, $method)) {
                throw new ConcurrencyException("下标{$key}的类（{$class}）不存在方法：{$method}");
            }
        }

        $connection = (string) ($this->clientOptions['connection'] ?? 'close');
        $path = $this->path;
        $parallel = new Parallel();

        foreach ($arr as $key => $value) {
            $base = $this->nextAddress();
            $parallel->add(static function () use ($base, $path, $value, $connection) {
                $response = self::$http->request($base . $path, [
                    'method'  => 'GET',
                    'version' => '1.1',
                    'headers' => ['Connection' => $connection],
                    'data'    => $value,
                ]);
                return (string) $response->getBody();
            }, (string) $key);
        }

        $result = $parallel->wait();
        $exceptions = $parallel->getExceptions();
        if ($exceptions) {
            $first = reset($exceptions);
            throw new ConcurrencyException('并发请求失败：' . $first->getMessage(), (int) $first->getCode(), $first);
        }
        return $result;
    }

    /**
     * 单次请求
     *
     * @param array{class:mixed,method:string,arguments?:array,options?:array} $payload
     */
    public function request(array $payload): string
    {
        $results = $this->requestArray([$payload]);
        return (string) reset($results);
    }
}
