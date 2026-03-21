<?php
/**
 * 数据库配置文件示例
 * 
 * 使用说明：
 * 1. 复制此文件为 config.php
 * 2. 填入您的实际数据库配置信息
 * 3. config.php 文件已在 .gitignore 中，不会提交到仓库
 */

return [
    // 数据库主机地址
    'host' => 'localhost',
    
    // 数据库端口
    'port' => 3306,
    
    // 数据库名称
    'dbname' => 'your_database_name',
    
    // 数据库用户名
    'username' => 'your_database_username',
    
    // 数据库密码
    'password' => 'your_database_password',
    
    // 数据库字符集
    'charset' => 'utf8mb4',
    
    // 连接池配置
    'pool' => [
        // 最小连接数
        'min_connections' => 5,
        
        // 最大连接数
        'max_connections' => 20,
        
        // 连接超时时间（秒）
        'connection_timeout' => 10,
        
        // 连接最大空闲时间（秒）
        'max_idle_time' => 300,
        
        // 自动重连次数
        'retry_attempts' => 3,
        
        // 重连间隔时间（毫秒）
        'retry_interval' => 1000,
    ],
    
    // 错误日志文件路径
    'error_log' => __DIR__ . '/../logs/db_error.log',
];
