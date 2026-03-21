<?php
// 账号数据更新处理程序

// 启动会话
@session_start();

// 加载配置和函数文件
require_once '../config.php';
require_once '../functions.php';

// 设置响应头为JSON
header('Content-Type: application/json');

// 验证请求方法
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => '无效的请求方法']);
    exit;
}

// 获取请求参数
$access_token = isset($_POST['access_token']) ? trim($_POST['access_token']) : '';
$openid = isset($_POST['openid']) ? trim($_POST['openid']) : '';
$account_id = isset($_POST['account_id']) ? intval($_POST['account_id']) : 0;

// 验证参数
if (empty($access_token) || empty($openid) || $account_id <= 0) {
    echo json_encode(['success' => false, 'error' => '参数不完整']);
    exit;
}

try {
    // 调用与前台相同的函数获取账号数据
    $data = fetch_account_data($access_token, $openid);
    
    if (isset($data['error'])) {
        // 获取数据失败
        echo json_encode(['success' => false, 'error' => '获取账号数据失败：' . $data['error']]);
        exit;
    }
    
    // 格式化数据
    list($callback_info, $formatted_info) = format_account_data($data, $access_token, $openid);
    
    // 加载数据库类
    require_once __DIR__ . '/../database/Database.php';
    
    // 初始化数据库连接
    $database = new Database();
    
    // 开始事务
    $database->beginTransaction();
    
    try {
        // 构建完整的账号数据
        $account_data = [
            'access_token' => $access_token,
            'openid' => $openid,
            'raw_data' => $formatted_info,
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        // 更新原始数据
        $database->update('account_raw_data', $account_data, ['id' => $account_id]);
        
        // 解析账号状态信息 - 提取更完整的字段
        $status_info = [];
        $status_info['raw_data'] = $formatted_info;
        $status_info['timestamp'] = date('Ymd_His');
        
        // 提取所有关键信息（与functions.php中的send_to_backend函数保持一致）
        if (preg_match('/角色名称：([^|]+)\|/', $formatted_info, $match)) {
            $status_info['role_name'] = trim($match[1]);
            $status_info['charac_name'] = trim($match[1]); // 保留旧键名以保持兼容性
        }
        
        if (preg_match('/角色编号：([^|]+)\|/', $formatted_info, $match)) {
            $status_info['charac_no'] = trim($match[1]);
        }
        
        if (preg_match('/游戏等级：([^|]+)\|/', $formatted_info, $match)) {
            $status_info['game_level'] = trim($match[1]);
            $status_info['level'] = trim($match[1]); // 保留旧键名以保持兼容性
        }
        
        if (preg_match('/哈夫币：([^|]+)\|/', $formatted_info, $match)) {
            $status_info['haf_coin'] = trim($match[1]);
            $status_info['hafcoinnum'] = trim($match[1]); // 保留旧键名以保持兼容性
        }
        
        if (preg_match('/道具价值：([^|]+)\|/', $formatted_info, $match)) {
            $status_info['propcapital'] = trim($match[1]);
        }
        
        if (preg_match('/仓库价值：([^|]+)\|/', $formatted_info, $match)) {
            $status_info['total_assets'] = trim($match[1]);
        }
        
        if (preg_match('/在线：([^|]+)\|/', $formatted_info, $match)) {
            $status_info['is_online'] = trim($match[1]);
        }
        
        if (preg_match('/今日登录：([^|]+)\|/', $formatted_info, $match)) {
            $status_info['logintoday'] = trim($match[1]);
        }
        
        if (preg_match('/特定模式等级：([^|]+)\|/', $formatted_info, $match)) {
            $status_info['tdmlevel'] = trim($match[1]);
        }
        
        if (preg_match('/禁言：([^|]+)\|/', $formatted_info, $match)) {
            $status_info['isbanspeak'] = trim($match[1]);
        }
        
        if (preg_match('/封号：([^|]+)/', $formatted_info, $match)) {
            $status_info['isbanuser'] = trim($match[1]);
        }
        
        if (preg_match('/最后登录时间：([^|]+)/', $formatted_info, $match)) {
            $status_info['lastlogintime'] = trim($match[1]);
        }
        
        if (preg_match('/最后登出时间：([^|\n]+)/', $formatted_info, $match)) {
            $status_info['lastlogouttime'] = trim($match[1]);
        }
        
        // 设置状态（默认在线）
        $status = 'online'; // 默认设置为在线
        if (isset($status_info['is_online']) && $status_info['is_online'] === '否') {
            $status = 'offline';
        }
        
        // 将status添加到status_info数组中，以便前端使用
        $status_info['status'] = $status;
        
        // 检查是否已有状态数据
        $existing_status = $database->selectOne('account_status_data', ['id'], [
            'account_id' => $account_id
        ]);
        
        $status_data = [
            'status_data' => json_encode($status_info, JSON_UNESCAPED_UNICODE),
            'status' => $status,
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        if ($existing_status) {
            // 更新现有状态数据
            $database->update('account_status_data', $status_data, ['account_id' => $account_id]);
        } else {
            // 插入新状态数据
            $status_data['account_id'] = $account_id;
            $status_data['created_at'] = date('Y-m-d H:i:s');
            $database->insert('account_status_data', $status_data);
        }
        
        // 记录操作日志
        $database->insert('operation_logs', [
            'operation_type' => 'admin_data_update',
            'operator' => 'admin',
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'description' => "管理员更新账号数据，账号ID: {$account_id}",
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        // 提交事务
        $database->commit();
        
        // 返回成功响应
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        // 回滚事务
        $database->rollback();
        throw $e;
    }
} catch (Exception $e) {
    // 记录错误日志
    error_log('账号数据更新失败: ' . $e->getMessage(), 3, __DIR__ . '/../logs/error.log');
    // 返回错误响应
    echo json_encode(['success' => false, 'error' => '数据更新失败：' . $e->getMessage()]);
}