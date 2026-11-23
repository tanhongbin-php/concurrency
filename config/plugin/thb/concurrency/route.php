<?php

use support\Request;
use Webman\Route;

$path = config('plugin.thb.concurrency.app.path', '/async');

Route::get($path, function (Request $request) {
    $class = $request->get('class', '');
    $method = $request->get('method', '');
    $arguments = $request->get('arguments', []);
    $options = $request->get('options', []);
    if ($class === '' || $method === '') {
        return response('class/method required', 400);
    }
    $ref = new \ReflectionMethod($class, $method);
    $target = $ref->isStatic() ? $class : \support\Container::make($class, (array) $options);
    $result = [$target, $method](...(array) $arguments);
    $body = is_array($result) ? json_encode($result, JSON_UNESCAPED_UNICODE) : (string) $result;
    return response($body);
});
