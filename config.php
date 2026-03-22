<?php
if (!defined('DISABLE_SSL_VERIFY')) {
    define('DISABLE_SSL_VERIFY', false);
}



// 文件存储配置
define('DOWNLOAD_DIR', 'downloads/');

// API配置
define('API_URL', 'https://comm.aci.game.qq.com/main');
//define('BACKEND_URL', 'http://localhost:8500/api/submit_data');

// 请求超时设置
define('REQUEST_TIMEOUT', 10);

// 创建下载目录（如果不存在）
if (!is_dir(DOWNLOAD_DIR)) {
    mkdir(DOWNLOAD_DIR, 0777, true);
}

// 设置错误报告
ini_set('display_errors', 0);
error_reporting(E_ALL);

// 设置默认时区
date_default_timezone_set('Asia/Shanghai');