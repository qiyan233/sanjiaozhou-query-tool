<?php
// 主页面
// 启动会话以获取处理结果
session_start();

require_once 'config.php';

// 检查是否有错误信息或处理结果
$error = '';
$formatted_file = '';
$success = false;
$callback_data = '';
$formatted_data = '';

// 从会话中获取处理结果
if (isset($_SESSION['query_result'])) {
    $result = $_SESSION['query_result'];
    
    // 获取错误信息
    if (isset($result['error'])) {
        $error = $result['error'];
    }
    
    // 获取文件信息、数据内容和成功状态
    if (isset($result['success']) && $result['success'] == 1) {
        $success = true;
        // 直接从会话中获取原数据内容
        if (isset($result['callback_data'])) {
            $callback_data = $result['callback_data'];
        }
        // 获取状态数据文件路径
        if (isset($result['formatted_file'])) {
            $formatted_file = $result['formatted_file'];
            // 从文件读取状态数据
            $formatted_file_path = DOWNLOAD_DIR . $formatted_file;
            if (file_exists($formatted_file_path)) {
                $formatted_data = file_get_contents($formatted_file_path);
            }
        }
    }
    
    // 清除会话中的结果，避免重复显示
    unset($_SESSION['query_result']);
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>三角洲查询工具</title>
    <link rel="stylesheet" href="css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100 font-sans antialiased">
    <header class="bg-blue-600 text-white py-6 shadow-lg">
        <div class="container mx-auto text-center">
            <h1 class="text-3xl font-bold">三角洲查询工具</h1>
            <p class="mt-2 text-sm">快速查询账号信息，支持批量处理(可同时过滤数量无上限)</p>
        </div>
    </header>
    <main class="container mx-auto py-8 px-4">
        <section class="bg-white rounded-lg shadow-md p-6 mb-8">
            <h2 class="text-xl font-semibold mb-4 text-gray-700">输入账号数据</h2>
            <p class="text-gray-600 mb-4">请在下方文本框中粘贴您的账号数据，每行一条，格式为：<code>access_token=xxx&openid=yyy</code>。</p>
            <form action="batch_submit.php" method="POST" id="queryForm">
                <textarea id="callback_data_list" name="callback_data_list" class="w-full h-40 p-3 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="每行输入一条数据，例如：access_token=xxx&openid=yyy"></textarea>
                <button type="submit" class="w-full mt-4 bg-blue-600 text-white py-3 rounded-md hover:bg-blue-700 transition duration-300">开始查询</button>
            </form>
        </section>

        <?php if (!empty($callback_data) || !empty($formatted_data)): ?>
        <section class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-lg font-semibold mb-4 text-gray-700">账号原数据</h2>
                <textarea id="callback-data-content" readonly class="w-full h-64 p-3 bg-gray-50 border rounded-md"><?php echo htmlspecialchars($callback_data); ?></textarea>
                <button type="button" onclick="copyToClipboard('callback-data-content')" class="block mt-4 bg-blue-600 text-white text-center py-2 rounded-md hover:bg-blue-700 transition duration-300">复制原数据</button>
            </div>
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-lg font-semibold mb-4 text-gray-700">账号状态数据</h2>
                <textarea id="formatted-data-content" readonly class="w-full h-64 p-3 bg-gray-50 border rounded-md"><?php echo htmlspecialchars($formatted_data); ?></textarea>
                <button type="button" onclick="copyToClipboard('formatted-data-content')" class="block mt-4 bg-blue-600 text-white text-center py-2 rounded-md hover:bg-blue-700 transition duration-300">复制状态数据</button>
            </div>
        </section>
        <?php endif; ?>
    </main>
    <footer class="bg-gray-800 text-white py-4 text-center">
        <p>© 2025 三角洲查询工具</p>
    </footer>
    <script src="js/main.js"></script>
</body>
</html>