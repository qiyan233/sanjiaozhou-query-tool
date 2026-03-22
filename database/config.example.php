<?php
/**
 * 数据库配置示例文件
 *
 * 使用方式：
 * 1. 复制为 database/config.local.php
 * 2. 填入你自己的数据库信息
 *
 * 该文件可以提交到 GitHub；config.local.php 不要提交。
 */

return [
    'host' => '127.0.0.1',
    'port' => 3306,
    'dbname' => 'delta_account_manager',
    'username' => 'root',
    'password' => 'CHANGE_ME',
    'charset' => 'utf8mb4',
    'pool' => [
        'min_connections' => 1,
        'max_connections' => 10,
        'connection_timeout' => 10,
        'max_idle_time' => 300,
        'retry_attempts' => 3,
        'retry_interval' => 1000,
    ],
    'error_log' => __DIR__ . '/../logs/db_error.log',
];
