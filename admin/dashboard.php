<?php
/**
 * 后台管理系统仪表盘页面
 */

// 启用会话
session_start();

// 检查登录状态
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// 加载必要的类
require_once __DIR__ . '/../services/SilentStorageService.php';
require_once __DIR__ . '/../database/Database.php';

// 获取统计信息
$statistics = [];
try {
    $storageService = new SilentStorageService();
    $statistics = $storageService->getStatistics();
} catch (Exception $e) {
    $error = '获取统计信息失败';
    error_log('获取统计信息异常: ' . $e->getMessage());
}

// 获取最近的操作日志
$recentLogs = [];
try {
    $database = new Database();
    $recentLogs = $database->select(
        'operation_logs',
        ['*'],
        [],
        [],
        ['created_at' => 'DESC'],
        10
    );
} catch (Exception $e) {
    error_log('获取操作日志异常: ' . $e->getMessage());
}

// 退出登录
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    // 记录退出日志
    try {
        $database = new Database();
        $database->insert('operation_logs', [
            'operation_type' => 'admin_logout',
            'operator' => $_SESSION['admin_username'],
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'description' => "管理员退出: {$_SESSION['admin_username']}"
        ]);
    } catch (Exception $e) {
        // 忽略日志记录错误
    }
    
    // 销毁会话
    session_destroy();
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>账号管理系统 - 仪表盘</title>
    <style>
        /* 全局样式 */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Roboto', 'Oxygen', 'Ubuntu', 'Cantarell', sans-serif;
            background: #f5f7fa;
            color: #333;
            line-height: 1.6;
        }
        
        /* 顶部导航 */
        .header {
            background: white;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 20px;
        }
        
        .header .logo {
            font-size: 20px;
            font-weight: 600;
            color: #4facfe;
            text-decoration: none;
        }
        
        .header .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .header .user-name {
            font-weight: 500;
        }
        
        .header .logout-btn {
            color: #666;
            text-decoration: none;
            font-size: 14px;
            transition: color 0.3s;
        }
        
        .header .logout-btn:hover {
            color: #ff4757;
        }
        
        /* 主容器 */
        .main-container {
            display: flex;
            margin-top: 60px;
            min-height: calc(100vh - 60px);
        }
        
        /* 侧边栏 */
        .sidebar {
            width: 220px;
            background: #2c3e50;
            color: white;
            position: fixed;
            left: 0;
            top: 60px;
            bottom: 0;
            overflow-y: auto;
            transition: transform 0.3s;
        }
        
        .sidebar ul {
            list-style: none;
            padding: 20px 0;
        }
        
        .sidebar li {
            margin-bottom: 5px;
        }
        
        .sidebar a {
            display: block;
            padding: 12px 20px;
            color: #ecf0f1;
            text-decoration: none;
            transition: background 0.3s;
        }
        
        .sidebar a:hover {
            background: #34495e;
        }
        
        .sidebar a.active {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
        }
        
        /* 内容区域 */
        .content {
            flex: 1;
            margin-left: 220px;
            padding: 30px;
        }
        
        /* 统计卡片 */
        .stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
        }
        
        .stat-card h3 {
            font-size: 14px;
            color: #7f8c8d;
            margin-bottom: 15px;
            font-weight: 500;
        }
        
        .stat-card .value {
            font-size: 36px;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 10px;
        }
        
        .stat-card .icon {
            font-size: 48px;
            float: right;
            opacity: 0.1;
        }
        
        /* 状态分布 */
        .status-distribution {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            margin-bottom: 30px;
        }
        
        .status-distribution h2 {
            font-size: 20px;
            margin-bottom: 20px;
            color: #2c3e50;
        }
        
        .status-list {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .status-item {
            background: #f8f9fa;
            padding: 15px 20px;
            border-radius: 8px;
            flex: 1;
            min-width: 150px;
        }
        
        .status-item .status-label {
            font-size: 14px;
            color: #666;
            margin-bottom: 8px;
        }
        
        .status-item .status-count {
            font-size: 24px;
            font-weight: 600;
            color: #2c3e50;
        }
        
        /* 最近日志 */
        .recent-logs {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }
        
        .recent-logs h2 {
            font-size: 20px;
            margin-bottom: 20px;
            color: #2c3e50;
        }
        
        .logs-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .logs-table th,
        .logs-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .logs-table th {
            font-weight: 600;
            color: #666;
            font-size: 14px;
        }
        
        .logs-table td {
            font-size: 14px;
            color: #333;
        }
        
        .logs-table tr:hover {
            background: #f8f9fa;
        }
        
        /* 响应式设计 */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .content {
                margin-left: 0;
                padding: 20px;
            }
            
            .stats-cards {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- 顶部导航 -->
    <header class="header">
        <a href="dashboard.php" class="logo">账号管理系统</a>
        <div class="user-info">
            <span class="user-name">欢迎，<?php echo $_SESSION['admin_username']; ?></span>
            <a href="?action=logout" class="logout-btn">退出登录</a>
        </div>
    </header>
    
    <!-- 主容器 -->
    <div class="main-container">
        <!-- 侧边栏 -->
        <aside class="sidebar">
            <ul>
                <li><a href="dashboard.php" class="active">仪表盘</a></li>
                <li><a href="accounts.php">账号管理</a></li>
                <li><a href="operation_logs.php">操作日志</a></li>
                <li><a href="admin_management.php">管理员管理</a></li>
                <!-- 系统设置已移除 -->
            </ul>
        </aside>
        
        <!-- 内容区域 -->
        <main class="content">
            <!-- 统计卡片 -->
            <div class="stats-cards">
                <div class="stat-card">
                    <span class="icon">📊</span>
                    <h3>总账号数</h3>
                    <div class="value">
                        <?php echo isset($statistics['total_accounts']) ? $statistics['total_accounts'] : 0; ?>
                    </div>
                </div>
                

                
                <div class="stat-card">
                    <span class="icon">👤</span>
                    <h3>管理员数</h3>
                    <div class="value">
                        <?php 
                        try {
                            $database = new Database();
                            $adminCount = $database->selectOne('admins', ['COUNT(*) as count'], ['status' => 1])['count'];
                            echo $adminCount;
                        } catch (Exception $e) {
                            echo 0;
                        }
                        ?>
                    </div>
                </div>
                

            </div>
            
            <!-- 状态分布 -->
            <div class="status-distribution">
                <h2>账号状态分布</h2>
                <div class="status-list">
                    <?php if (isset($statistics['status_distribution']) && is_array($statistics['status_distribution'])): ?>
                        <?php foreach ($statistics['status_distribution'] as $item): ?>
                            <div class="status-item">
                                <div class="status-label">
                                    <?php 
                                    switch ($item['status']) {
                                        case 'online': echo '在线'; break;
                                        case 'offline': echo '离线'; break;
                                        case 'banned': echo '封禁'; break;
                                        case 'frozen': echo '冻结'; break;
                                        case 'unknown': echo '未知'; break;
                                        default: echo $item['status'] ? $item['status'] : '未知';
                                    }
                                    ?>
                                </div>
                                <div class="status-count"><?php echo $item['count']; ?></div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="status-item">
                            <div class="status-label">暂无数据</div>
                            <div class="status-count">0</div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- 最近日志 -->
            <div class="recent-logs">
                <h2>最近操作日志</h2>
                <table class="logs-table">
                    <thead>
                        <tr>
                            <th>时间</th>
                            <th>操作类型</th>
                            <th>操作者</th>
                            <th>IP地址</th>
                            <th>描述</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($recentLogs)): ?>
                            <?php foreach ($recentLogs as $log): ?>
                                <tr>
                                    <td><?php echo $log['created_at']; ?></td>
                                    <td><?php echo $log['operation_type']; ?></td>
                                    <td><?php echo $log['operator']; ?></td>
                                    <td><?php echo $log['ip_address']; ?></td>
                                    <td><?php echo $log['description']; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" style="text-align: center; color: #999;">暂无日志记录</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
</body>
</html>