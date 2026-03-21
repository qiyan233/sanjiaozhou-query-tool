<?php
// 文件下载脚本
require_once 'config.php';

// 检查是否提供了文件名参数
if (!isset($_GET['file'])) {
    header('HTTP/1.1 400 Bad Request');
    echo '错误: 缺少文件参数';
    exit;
}

// 获取文件名并进行安全验证
$filename = $_GET['file'];

// 安全检查，防止路径遍历攻击
if (strpos($filename, '..') !== false || strpos($filename, '/') !== false || strpos($filename, '\\') !== false) {
    header('HTTP/1.1 403 Forbidden');
    echo '错误: 无效的文件路径';
    exit;
}

// 构建完整的文件路径
$file_path = DOWNLOAD_DIR . $filename;

// 检查文件是否存在
if (!file_exists($file_path)) {
    header('HTTP/1.1 404 Not Found');
    echo '错误: 文件不存在';
    exit;
}

// 设置下载头信息
header('Content-Description: File Transfer');
header('Content-Type: text/plain');
header('Content-Disposition: attachment; filename="' . basename($file_path) . '"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($file_path));

// 清理输出缓冲区
ob_clean();
flush();

// 发送文件内容
readfile($file_path);
exit;