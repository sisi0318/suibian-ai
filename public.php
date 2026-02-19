<?php
header('Access-Control-Allow-Origin: *'); // *代表允许任何网址请求
header('Access-Control-Allow-Methods: POST,GET'); // 允许请求的类型
set_time_limit(0); //设置不超时，程序一直运行。

// ignore_user_abort(true); //即使Client断开(如关掉浏览器),PHP脚本也可以继续执行.

define('__root__', __DIR__);
define('_Utils_', __DIR__ . '/Utils/');
define('__temp__', __DIR__ . '/temp/');

define('__loger', __root__ . '/loger.log');

require_once __root__ . '/config.php';

// 初始化自动加载
spl_autoload_register(function ($class) {
    // 忽略 Plugin 文件夹
    if (strpos($class, 'Plugin') === 0) {
        return;
    }
    $dir = str_replace('\\', '/', __DIR__);

    $file = $dir . '/' . str_replace('\\', '/', $class) . '.php';
    if (file_exists($file)) {
        // echo $file.PHP_EOL;
        require $file;
    } else {
        error_log("自动加载失败：文件 $file 不存在");
    }
});

// 初始化日志级别
if (class_exists('Utils\\Logger') && defined('Config::LOG_LEVEL')) {
    Utils\Logger::setLogLevel(Config::LOG_LEVEL);

    if (Config::LOG_LEVEL === 'INFO') {
        error_reporting(0); // 关闭错误报告
    }
}

