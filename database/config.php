<?php
/**
 * 数据库配置文件（开源安全版）
 *
 * 优先级：
 * 1. database/config.local.php（本地私有文件，不提交）
 * 2. 环境变量
 * 3. 下方安全默认值/占位值
 */

$localConfig = __DIR__ . '/config.local.php';
if (file_exists($localConfig)) {
    return require $localConfig;
}

$env = function ($key, $default) {
    $value = getenv($key);
    return ($value !== false && $value !== '') ? $value : $default;
};

return [
    'host' => $env('DB_HOST', '127.0.0.1'),
    'port' => intval($env('DB_PORT', 3306)),
    'dbname' => $env('DB_NAME', 'delta_account_manager'),
    'username' => $env('DB_USER', 'root'),
    'password' => $env('DB_PASSWORD', 'CHANGE_ME'),
    'charset' => $env('DB_CHARSET', 'utf8mb4'),
    'pool' => [
        'min_connections' => intval($env('DB_POOL_MIN', 1)),
        'max_connections' => intval($env('DB_POOL_MAX', 10)),
        'connection_timeout' => intval($env('DB_CONN_TIMEOUT', 10)),
        'max_idle_time' => intval($env('DB_MAX_IDLE', 300)),
        'retry_attempts' => intval($env('DB_RETRY_ATTEMPTS', 3)),
        'retry_interval' => intval($env('DB_RETRY_INTERVAL', 1000)),
    ],
    'error_log' => __DIR__ . '/../logs/db_error.log',
];
