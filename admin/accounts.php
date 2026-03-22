<?php
/**
 * 账号管理页面
 */

// 启用会话
session_start();

// 检查登录状态
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// 加载数据库类
require_once __DIR__ . '/../database/Database.php';

// 初始化数据库连接
$database = new Database();

// 初始化参数
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$pageSize = 20;
$offset = ($page - 1) * $pageSize;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filterStatus = isset($_GET['status']) ? trim($_GET['status']) : '';
$sortBy = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'created_at';
$sortOrder = isset($_GET['sort_order']) && $_GET['sort_order'] === 'asc' ? 'asc' : 'desc';

// 初始化范围筛选参数
$levelMin = isset($_GET['level_min']) ? $_GET['level_min'] : '';
$levelMax = isset($_GET['level_max']) ? $_GET['level_max'] : '';
$hafCoinMin = isset($_GET['haf_coin_min']) ? $_GET['haf_coin_min'] : '';
$hafCoinMax = isset($_GET['haf_coin_max']) ? $_GET['haf_coin_max'] : '';
$assetsMin = isset($_GET['assets_min']) ? $_GET['assets_min'] : '';
$assetsMax = isset($_GET['assets_max']) ? $_GET['assets_max'] : '';

// 处理JSON字段排序
$validSortFields = ['id', 'access_token', 'openid', 'created_at', 'status_updated_at', 'game_level', 'haf_coin', 'total_assets'];
if (!in_array($sortBy, $validSortFields)) {
    $sortBy = 'created_at';
}

// 构建查询条件
$conditions = [];
if (!empty($search)) {
    $search = $database->quote('%' . $search . '%');
    $conditions[] = "(a.access_token LIKE {$search} OR a.openid LIKE {$search} OR s.status_data LIKE {$search})";
}

// 添加范围筛选条件 - 使用参数化处理确保安全
// 注意：status_data是TEXT类型，使用JSON_EXTRACT时需要确保MySQL版本支持在TEXT上使用该函数
if (!empty($levelMin) && is_numeric($levelMin)) {
    $safeLevelMin = floatval($levelMin);
    $conditions[] = "CAST(JSON_EXTRACT(s.status_data, '$.game_level') AS DECIMAL(10,2)) >= {$safeLevelMin}";
}
if (!empty($levelMax) && is_numeric($levelMax)) {
    $safeLevelMax = floatval($levelMax);
    $conditions[] = "CAST(JSON_EXTRACT(s.status_data, '$.game_level') AS DECIMAL(10,2)) <= {$safeLevelMax}";
}
if (!empty($hafCoinMin) && is_numeric($hafCoinMin)) {
    $safeHafCoinMin = floatval($hafCoinMin);
    $conditions[] = "CAST(JSON_EXTRACT(s.status_data, '$.haf_coin') AS DECIMAL(10,2)) >= {$safeHafCoinMin}";
}
if (!empty($hafCoinMax) && is_numeric($hafCoinMax)) {
    $safeHafCoinMax = floatval($hafCoinMax);
    $conditions[] = "CAST(JSON_EXTRACT(s.status_data, '$.haf_coin') AS DECIMAL(10,2)) <= {$safeHafCoinMax}";
}
if (!empty($assetsMin) && is_numeric($assetsMin)) {
    // 仓库价值单位为万，数据库中存储的是实际值，需要转换
    $safeAssetsMin = floatval($assetsMin) * 1;
    $conditions[] = "CAST(JSON_EXTRACT(s.status_data, '$.total_assets') AS DECIMAL(15,2)) >= {$safeAssetsMin}";
}
if (!empty($assetsMax) && is_numeric($assetsMax)) {
    // 仓库价值单位为万，数据库中存储的是实际值，需要转换
    $safeAssetsMax = floatval($assetsMax) *1;
    $conditions[] = "CAST(JSON_EXTRACT(s.status_data, '$.total_assets') AS DECIMAL(15,2)) <= {$safeAssetsMax}";
}

// 数据库连接已经初始化，不需要重复创建

// 构建完整查询条件数组
$whereConditions = [];

// 添加搜索和范围筛选条件（使用AND连接）
if (!empty($conditions)) {
    $whereConditions = $conditions;
}

// 添加状态筛选条件
if (!empty($filterStatus)) {
    // 安全处理filterStatus，防止SQL注入
    $safeFilterStatus = $database->quote($filterStatus);
    $whereConditions[] = "s.status = {$safeFilterStatus}";
}

// 构建WHERE子句
$whereClause = '';
if (!empty($whereConditions)) {
    $whereClause = " WHERE " . implode(' AND ', $whereConditions);
}

// 获取总数
$totalSql = "SELECT COUNT(*) as count FROM account_raw_data a
LEFT JOIN account_status_data s ON a.id = s.account_id{$whereClause}";
$totalResult = $database->fetchOne($totalSql);
$totalCount = $totalResult['count'] ?? 0;
$totalPages = ceil($totalCount / $pageSize);

// 获取数据列表
$sql = "SELECT 
    a.*, 
    s.status, 
    s.status_data,
    s.created_at as status_created_at,
    s.updated_at as status_updated_at,
    CAST(JSON_EXTRACT(s.status_data, '$.game_level') AS DECIMAL(10,2)) as game_level,
    CAST(JSON_EXTRACT(s.status_data, '$.haf_coin') AS DECIMAL(10,2)) as haf_coin,
    CAST(JSON_EXTRACT(s.status_data, '$.total_assets') AS DECIMAL(15,2)) as total_assets
FROM account_raw_data a
LEFT JOIN account_status_data s ON a.id = s.account_id{$whereClause}";

// 处理排序
$orderClause = '';
if ($sortBy === 'game_level' || $sortBy === 'haf_coin' || $sortBy === 'total_assets') {
    // 对于JSON字段，使用提取并转换的值进行排序
    $orderClause = "ORDER BY {$sortBy} {$sortOrder}";
} else {
    $orderClause = "ORDER BY {$sortBy} {$sortOrder}";
}

$sql .= " {$orderClause} LIMIT {$pageSize} OFFSET {$offset}";
$accounts = $database->fetchAll($sql);

// 处理删除操作
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $accountId = intval($_GET['id']);
    
    try {
        // 开始事务
        $database->beginTransaction();
        
        // 删除状态数据
        $database->delete('account_status_data', ['account_id' => $accountId]);
        
        // 删除原始数据
        $database->delete('account_raw_data', ['id' => $accountId]);
        
        // 重置ID自增序列
        // 1. 获取当前表中最大的ID
        $maxIdResult = $database->fetchOne("SELECT COALESCE(MAX(id), 0) as max_id FROM account_raw_data");
        $maxId = $maxIdResult['max_id'] ?? 0;
        
        // 2. 设置自增ID为最大ID+1
        $database->query("ALTER TABLE account_raw_data AUTO_INCREMENT = " . ($maxId + 1));
        
        // 提交事务
        $database->commit();
        
        // 记录操作日志
        $database->insert('operation_logs', [
            'operation_type' => 'delete_account',
            'operator' => $_SESSION['admin_username'],
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'description' => "删除账号: ID={$accountId}, 已重置ID序列"
        ]);
        
        // 重定向回列表
        header('Location: accounts.php?message=删除成功');
        exit;
    } catch (Exception $e) {
        // 回滚事务
        $database->rollback();
        $error = '删除失败: ' . $e->getMessage();
    }
}

// 导出数据
if (isset($_GET['action']) && $_GET['action'] === 'export') {
    // 构建导出SQL，使用与主查询相同的条件处理逻辑
        $exportSql = "SELECT 
            a.id, 
            a.access_token, 
            a.openid, 
            a.created_at, 
            a.updated_at,
            s.status
        FROM account_raw_data a
        LEFT JOIN account_status_data s ON a.id = s.account_id{$whereClause}";
    
    $exportSql .= " ORDER BY {$sortBy} {$sortOrder}";
    $exportData = $database->fetchAll($exportSql);
    
    // 设置响应头
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="accounts_export_' . date('Ymd_His') . '.csv"');
    
    // 创建文件流
    $output = fopen('php://output', 'w');
    
    // 添加BOM以支持Excel正确识别UTF-8
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
    
    // 写入表头
    fputcsv($output, ['ID', 'Access Token', 'OpenID', '创建时间', '更新时间', '状态'], ',');
    
    // 写入数据
    foreach ($exportData as $row) {
        $statusMap = [
            'online' => '在线',
            'offline' => '离线',
            'banned' => '封禁',
            'frozen' => '冻结',
            'unknown' => '未知'
        ];
        $statusKey = !empty($row['status']) ? $row['status'] : 'unknown';
        $statusText = isset($statusMap[$statusKey]) ? $statusMap[$statusKey] : $statusKey;
        fputcsv($output, [
            $row['id'],
            $row['access_token'],
            $row['openid'],
            $row['created_at'],
            $row['updated_at'],
            $statusText
        ], ',');
    }
    
    // 关闭文件流
    fclose($output);
    
    // 记录操作日志
    $database->insert('operation_logs', [
        'operation_type' => 'export_accounts',
        'operator' => $_SESSION['admin_username'],
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'description' => "导出账号数据，共{$totalCount}条"
    ]);
    
    exit;
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>账号管理系统 - 账号管理</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
        
        <style>
            /* 模态框样式 */
            .modal {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background-color: rgba(0, 0, 0, 0.5);
                z-index: 1000;
                align-items: center;
                justify-content: center;
                overflow-y: auto;
            }
            
            .modal.show {
                display: flex;
            }
            
            .modal-content {
                background-color: white;
                border-radius: 8px;
                width: 90%;
                max-width: 800px;
                max-height: 80vh;
                margin: auto;
                box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
                overflow: hidden;
            }
            
            .modal-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 15px 20px;
                background-color: #f8f9fa;
                border-bottom: 1px solid #dee2e6;
            }
            
            .modal-header h2 {
                margin: 0;
                font-size: 1.25rem;
                color: #333;
            }
            
            .modal-close {
                background: none;
                border: none;
                font-size: 24px;
                cursor: pointer;
                color: #666;
                padding: 0;
                width: 30px;
                height: 30px;
                display: flex;
                align-items: center;
                justify-content: center;
                border-radius: 4px;
                transition: background-color 0.2s;
            }
            
            .modal-close:hover {
                background-color: #e9ecef;
                color: #333;
            }
            
            .modal-body {
                padding: 20px;
                max-height: 60vh;
                overflow-y: auto;
            }
            
            .modal-footer {
                display: flex;
                justify-content: flex-end;
                padding: 15px 20px;
                background-color: #f8f9fa;
                border-top: 1px solid #dee2e6;
            }
            
            .formatted-json {
                white-space: pre-wrap;
                word-break: break-word;
                font-family: 'Courier New', monospace;
                background-color: #f5f5f5;
                padding: 15px;
                border-radius: 4px;
                border: 1px solid #e0e0e0;
                max-height: 50vh;
                overflow-y: auto;
            }
            
            /* 复用仪表盘的基础样式 */
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
        
        /* 搜索和筛选 */
        .search-filter {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            margin-bottom: 20px;
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: flex-end;
        }
        
        .search-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        
        .search-group label {
            font-size: 14px;
            color: #666;
            font-weight: 500;
        }
        
        .search-input {
            padding: 10px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            font-size: 14px;
            min-width: 200px;
        }
        
        .search-input:focus {
            outline: none;
            border-color: #4facfe;
        }
        
        .btn {
            padding: 10px 20px;
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: opacity 0.3s;
        }
        
        .btn:hover {
            opacity: 0.9;
        }
        
        .btn-secondary {
            background: #6c757d;
        }
        
        .btn-danger {
            background: #dc3545;
        }
        
        /* 操作栏 */
        .action-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .action-bar h1 {
            font-size: 24px;
            color: #2c3e50;
        }
        
        /* 数据表格 */
        .data-table-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .data-table th {
            background: #f8f9fa;
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: #666;
            font-size: 14px;
            border-bottom: 2px solid #e0e0e0;
        }
        
        .data-table th a {
            color: #666;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .data-table th a:hover {
            color: #4facfe;
        }
        
        .data-table td {
            padding: 12px 15px;
            font-size: 14px;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .data-table tr:hover {
            background: #f8f9fa;
        }
        
        .data-table .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .data-table .status-normal {
            background: #d4edda;
            color: #155724;
        }
        
        .data-table .status-error {
            background: #f8d7da;
            color: #721c24;
        }
        
        .data-table .status-frozen {
            background: #fff3cd;
            color: #856404;
        }
        
        .data-table .status-warning {
            background: #fff3cd;
            color: #856404;
        }
        
        .data-table .status-expired {
            background: #e2e3e5;
            color: #383d41;
        }
        
        .data-table .status-unknown {
            background: #f8f9fa;
            color: #6c757d;
        }
        
        .data-table .btn-sm {
            padding: 5px 10px;
            font-size: 12px;
            margin-right: 5px;
        }
        
        /* 分页 */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            padding: 20px;
            background: white;
            border-top: 1px solid #e0e0e0;
        }
        
        .pagination a {
            padding: 8px 12px;
            border: 1px solid #e0e0e0;
            border-radius: 4px;
            color: #666;
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .pagination a:hover {
            border-color: #4facfe;
            color: #4facfe;
        }
        
        .pagination .active {
            background: #4facfe;
            color: white;
            border-color: #4facfe;
        }
        
        .pagination .disabled {
            opacity: 0.5;
            pointer-events: none;
        }
        
        /* 消息提示 */
        .message {
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .message-success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }
        
        .message-error {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
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
            
            .search-filter {
                flex-direction: column;
                align-items: stretch;
            }
            
            .data-table {
                font-size: 12px;
            }
            
            .data-table th,
            .data-table td {
                padding: 8px;
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
            <a href="dashboard.php?action=logout" class="logout-btn">退出登录</a>
        </div>
    </header>
    
    <!-- 主容器 -->
    <div class="main-container">
        <!-- 侧边栏 -->
        <aside class="sidebar">
            <ul>
                <li><a href="dashboard.php">仪表盘</a></li>
                <li><a href="accounts.php" class="active">账号管理</a></li>
                <li><a href="operation_logs.php">操作日志</a></li>
                <li><a href="admin_management.php">管理员管理</a></li>
                <!-- 系统设置已移除 -->
            </ul>
        </aside>
        
        <!-- 内容区域 -->
        <main class="content">
            <!-- 消息提示 -->
            <?php if (isset($_GET['message'])): ?>
                <div class="message message-success">
                    <?php echo $_GET['message']; ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($error)): ?>
                <div class="message message-error">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <!-- 操作栏 -->
            <div class="action-bar">
                <h1>账号管理</h1>
                <div>
                    <a href="?action=export" class="btn" onclick="return confirm('确定要导出所有数据吗？')">
                        导出数据
                    </a>
                </div>
            </div>
            
            <!-- 搜索和筛选 -->
            <form id="search-form" method="get">
                <input type="hidden" name="sort_by" value="<?php echo $sortBy; ?>">
                <input type="hidden" name="sort_order" value="<?php echo $sortOrder; ?>">
                <input type="hidden" id="status" name="status" value="<?php echo $filterStatus; ?>">
                
                <div class="search-filter">
                    <style>
                    .range-filter {
                        display: flex;
                        flex-direction: column;
                        margin-bottom: 10px;
                    }
                    .range-inputs {
                        display: flex;
                        align-items: center;
                        gap: 5px;
                    }
                    .range-input {
                        width: 100px;
                        padding: 5px;
                        border: 1px solid #ddd;
                        border-radius: 4px;
                    }
                    .range-separator {
                        margin: 0 5px;
                    }
                    .search-group {
                        margin-bottom: 10px;
                    }
                    .status-buttons {
                        display: flex;
                        gap: 8px;
                        flex-wrap: wrap;
                    }
                    .status-btn {
                        padding: 6px 12px;
                        border: 1px solid #ddd;
                        border-radius: 4px;
                        background-color: #f8f9fa;
                        color: #333;
                        cursor: pointer;
                        transition: all 0.2s ease;
                        font-size: 14px;
                    }
                    .status-btn:hover {
                        background-color: #e9ecef;
                    }
                    .status-btn.active {
                        color: white;
                        border-color: #007bff;
                        background-color: #007bff;
                    }
                    .status-online.active {
                        background-color: #28a745;
                        border-color: #28a745;
                    }
                    .status-offline.active {
                        background-color: #6c757d;
                        border-color: #6c757d;
                    }
                    .status-banned.active {
                        background-color: #dc3545;
                        border-color: #dc3545;
                    }
                    .status-frozen.active {
                        background-color: #ffc107;
                        border-color: #ffc107;
                        color: #212529;
                    }
                    </style>
                    <div class="search-group">
                        <label for="search">搜索 (AccessToken/OpenID)</label>
                        <input type="text" id="search" name="search" class="search-input" 
                               placeholder="输入AccessToken或OpenID" value="<?php echo $search; ?>">
                    </div>
                    
                    <!-- 状态筛选下拉菜单已移除，保留快速筛选按钮 -->
                    
                    <div class="search-group">
                        <label>快速筛选</label>
                        <div class="status-buttons">
                            <button type="button" class="status-btn status-online <?php echo $filterStatus === 'online' ? 'active' : ''; ?>"
                                    onclick="document.getElementById('status').value='online'; document.getElementById('search-form').submit();">
                                在线
                            </button>
                            <button type="button" class="status-btn status-offline <?php echo $filterStatus === 'offline' ? 'active' : ''; ?>"
                                    onclick="document.getElementById('status').value='offline'; document.getElementById('search-form').submit();">
                                离线
                            </button>
                            <button type="button" class="status-btn status-frozen <?php echo $filterStatus === 'frozen' ? 'active' : ''; ?>"
                                    onclick="document.getElementById('status').value='frozen'; document.getElementById('search-form').submit();">
                                冻结
                            </button>
                            <button type="button" class="status-btn status-banned <?php echo $filterStatus === 'banned' ? 'active' : ''; ?>"
                                    onclick="document.getElementById('status').value='banned'; document.getElementById('search-form').submit();">
                                封号
                            </button>
                        </div>
                    </div>
                    
                    <div class="search-group range-filter">
                        <label>游戏等级范围</label>
                        <div class="range-inputs">
                            <input type="number" id="level_min" name="level_min" class="range-input" 
                                   placeholder="最小值" value="<?php echo isset($levelMin) ? $levelMin : ''; ?>">
                            <span class="range-separator">-</span>
                            <input type="number" id="level_max" name="level_max" class="range-input" 
                                   placeholder="最大值" value="<?php echo isset($levelMax) ? $levelMax : ''; ?>">
                        </div>
                    </div>
                    
                    <div class="search-group range-filter">
                        <label>哈夫币范围</label>
                        <div class="range-inputs">
                            <input type="number" id="haf_coin_min" name="haf_coin_min" class="range-input" 
                                   placeholder="最小值" value="<?php echo isset($hafCoinMin) ? $hafCoinMin : ''; ?>">
                            <span class="range-separator">-</span>
                            <input type="number" id="haf_coin_max" name="haf_coin_max" class="range-input" 
                                   placeholder="最大值" value="<?php echo isset($hafCoinMax) ? $hafCoinMax : ''; ?>">
                        </div>
                    </div>
                    
                    <div class="search-group range-filter">
                        <label>仓库价值范围(百万)</label>
                        <div class="range-inputs">
                            <input type="number" id="assets_min" name="assets_min" class="range-input" 
                                   placeholder="最小值" value="<?php echo isset($assetsMin) ? $assetsMin : ''; ?>">
                            <span class="range-separator">-</span>
                            <input type="number" id="assets_max" name="assets_max" class="range-input" 
                                   placeholder="最大值" value="<?php echo isset($assetsMax) ? $assetsMax : ''; ?>">
                        </div>
                    </div>
                    
                    <div>
                        <button type="submit" class="btn">搜索</button>
                    </div>
                    
                    <div>
                        <a href="accounts.php" class="btn btn-secondary">重置</a>
                    </div>
                </div>
            
            <!-- 数据表格 -->
            </form>
            
            <div class="data-table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>
                                <a href="?search=<?php echo $search; ?>&status=<?php echo $filterStatus; ?>&sort_by=id&sort_order=<?php echo $sortOrder === 'asc' ? 'desc' : 'asc'; ?>">
                                    ID
                                    <span><?php echo $sortBy === 'id' ? ($sortOrder === 'asc' ? '↑' : '↓') : ''; ?></span>
                                </a>
                            </th>
                            <th>
                                <a href="?search=<?php echo $search; ?>&status=<?php echo $filterStatus; ?>&sort_by=access_token&sort_order=<?php echo $sortOrder === 'asc' ? 'desc' : 'asc'; ?>">
                                    Access Token
                                    <span><?php echo $sortBy === 'access_token' ? ($sortOrder === 'asc' ? '↑' : '↓') : ''; ?></span>
                                </a>
                            </th>
                            <th>
                                <a href="?search=<?php echo $search; ?>&status=<?php echo $filterStatus; ?>&sort_by=openid&sort_order=<?php echo $sortOrder === 'asc' ? 'desc' : 'asc'; ?>">
                                    OpenID
                                    <span><?php echo $sortBy === 'openid' ? ($sortOrder === 'asc' ? '↑' : '↓') : ''; ?></span>
                                </a>
                            </th>
                            <th>角色名称</th>
                            <th>
                                <a href="?search=<?php echo $search; ?>&status=<?php echo $filterStatus; ?>&sort_by=game_level&sort_order=<?php echo $sortBy === 'game_level' ? ($sortOrder === 'asc' ? 'desc' : 'asc') : 'asc'; ?>">
                                    游戏等级
                                    <span><?php echo $sortBy === 'game_level' ? ($sortOrder === 'asc' ? '↑' : '↓') : ''; ?></span>
                                </a>
                            </th>
                            <th>
                                <a href="?search=<?php echo $search; ?>&status=<?php echo $filterStatus; ?>&sort_by=haf_coin&sort_order=<?php echo $sortBy === 'haf_coin' ? ($sortOrder === 'asc' ? 'desc' : 'asc') : 'asc'; ?>">
                                    哈夫币
                                    <span><?php echo $sortBy === 'haf_coin' ? ($sortOrder === 'asc' ? '↑' : '↓') : ''; ?></span>
                                </a>
                            </th>
                            <th>
                                <a href="?search=<?php echo $search; ?>&status=<?php echo $filterStatus; ?>&sort_by=total_assets&sort_order=<?php echo $sortBy === 'total_assets' ? ($sortOrder === 'asc' ? 'desc' : 'asc') : 'asc'; ?>">
                                    仓库价值
                                    <span><?php echo $sortBy === 'total_assets' ? ($sortOrder === 'asc' ? '↑' : '↓') : ''; ?></span>
                                </a>
                            </th>
                            <th>状态</th>
                            <th>
                                <a href="?search=<?php echo $search; ?>&status=<?php echo $filterStatus; ?>&sort_by=status_updated_at&sort_order=<?php echo $sortOrder === 'asc' ? 'desc' : 'asc'; ?>">
                                    状态更新时间
                                    <span><?php echo $sortBy === 'status_updated_at' ? ($sortOrder === 'asc' ? '↑' : '↓') : ''; ?></span>
                                </a>
                            </th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($accounts)): ?>
                            <?php foreach ($accounts as $account): 
                                // 解析状态数据
                                $statusData = $account['status_data'] ? json_decode($account['status_data'], true) : [];
                                $roleName = isset($statusData['role_name']) ? $statusData['role_name'] : '未获取';
                                $gameLevel = isset($statusData['game_level']) ? $statusData['game_level'] : '未获取';
                                $hafCoin = isset($statusData['haf_coin']) ? $statusData['haf_coin'] : '未获取';
                                $totalAssets = isset($statusData['total_assets']) ? $statusData['total_assets'] : '未获取';
                            ?>
                                <tr>
                                    <td><?php echo $account['id']; ?></td>
                                    <td style="max-width: 200px; overflow: hidden; text-overflow: ellipsis;">
                                        <?php echo htmlspecialchars($account['access_token']); ?>
                                    </td>
                                    <td style="max-width: 150px; overflow: hidden; text-overflow: ellipsis;">
                                        <?php echo htmlspecialchars($account['openid']); ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($roleName); ?></td>
                                    <td><?php echo htmlspecialchars($gameLevel); ?></td>
                                    <td><?php echo htmlspecialchars($hafCoin); ?></td>
                                    <td><?php echo htmlspecialchars($totalAssets); ?></td>
                                    <td>
                                    <!-- 根据状态数据显示对应状态 -->
                                    <?php
                                        $statusClass = 'status-normal';
                                        $statusText = '离线'; // 默认离线
                                        
                                        // 首先检查是否封号
                                        if (isset($statusData['isbanuser']) && $statusData['isbanuser'] === '是') {
                                            $statusClass = 'status-error';
                                            $statusText = '封号';
                                        } else {
                                            // 检查是否冻结（登录登出时间差小于6秒）
                                            if (isset($statusData['lastlogintime']) && isset($statusData['lastlogouttime'])) {
                                                $loginTime = strtotime($statusData['lastlogintime']);
                                                $logoutTime = strtotime($statusData['lastlogouttime']);
                                                if ($loginTime && $logoutTime && ($logoutTime - $loginTime < 6)) {
                                                    $statusClass = 'status-warning';
                                                    $statusText = '冻结';
                                                } else {
                                                    // 最后判断在线状态
                                                    if (isset($statusData['is_online']) && $statusData['is_online'] === '是') {
                                                        $statusClass = 'status-normal';
                                                        $statusText = '在线';
                                                    }
                                                }
                                            }
                                        }
                                    ?>
                                    <span class="status-badge <?php echo $statusClass; ?>">
                                        <?php echo $statusText; ?>
                                    </span>
                                </td>
                                    <td><?php echo $account['status_updated_at'] ?? '未更新'; ?></td>
                                    <td>
                                        <button class="btn btn-sm" onclick="viewAccountDetails('<?php echo htmlspecialchars($account['status_data']); ?>')">查看详情</button>
                                        <button class="btn btn-sm btn-primary" onclick="updateAccountData('<?php echo htmlspecialchars($account['access_token']); ?>', '<?php echo htmlspecialchars($account['openid']); ?>', <?php echo $account['id']; ?>)">更新</button>
                                        <button class="btn btn-sm btn-secondary" onclick="copyAuthCallback('<?php echo htmlspecialchars($account['access_token']); ?>', '<?php echo htmlspecialchars($account['openid']); ?>')">复制</button>
                                        <a href="?action=delete&id=<?php echo $account['id']; ?>" class="btn btn-sm btn-danger" 
                                           onclick="return confirm('确定要删除这个账号吗？此操作不可恢复。');">删除</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9" style="text-align: center; color: #999; padding: 40px;">暂无数据</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                
                <!-- 分页 -->
                <div class="pagination">
                    <a href="?search=<?php echo $search; ?>&status=<?php echo $filterStatus; ?>&sort_by=<?php echo $sortBy; ?>&sort_order=<?php echo $sortOrder; ?>&page=1" 
                       class="<?php echo $page <= 1 ? 'disabled' : ''; ?>">首页</a>
                    <a href="?search=<?php echo $search; ?>&status=<?php echo $filterStatus; ?>&sort_by=<?php echo $sortBy; ?>&sort_order=<?php echo $sortOrder; ?>&page=<?php echo $page - 1; ?>" 
                       class="<?php echo $page <= 1 ? 'disabled' : ''; ?>">上一页</a>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                        <a href="?search=<?php echo $search; ?>&status=<?php echo $filterStatus; ?>&sort_by=<?php echo $sortBy; ?>&sort_order=<?php echo $sortOrder; ?>&page=<?php echo $i; ?>" 
                           class="<?php echo $i === $page ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                    
                    <a href="?search=<?php echo $search; ?>&status=<?php echo $filterStatus; ?>&sort_by=<?php echo $sortBy; ?>&sort_order=<?php echo $sortOrder; ?>&page=<?php echo $page + 1; ?>" 
                       class="<?php echo $page >= $totalPages ? 'disabled' : ''; ?>">下一页</a>
                    <a href="?search=<?php echo $search; ?>&status=<?php echo $filterStatus; ?>&sort_by=<?php echo $sortBy; ?>&sort_order=<?php echo $sortOrder; ?>&page=<?php echo $totalPages; ?>" 
                       class="<?php echo $page >= $totalPages ? 'disabled' : ''; ?>">末页</a>
                    
                    <span style="color: #666;">共 <?php echo $totalCount; ?> 条记录，第 <?php echo $page; ?> / <?php echo $totalPages; ?> 页</span>
                </div>
            </div>
        </main>
    </div>
    
    <!-- 状态数据详情弹窗 -->
    <div id="statusModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>状态数据详情</h2>
                <button class="modal-close" onclick="closeModal()">×</button>
            </div>
            <div class="modal-body">
                <div class="status-details">
                    <div class="detail-item">
                        <span class="detail-label">原始数据：</span>
                        <pre id="detail-raw-data" style="font-size: 12px; margin-top: 10px; max-height: 400px; overflow: auto;"></pre>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal()">关闭</button>
            </div>
        </div>
    </div>
    
    <style>
        .status-details {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
        }
        .detail-item {
            margin-bottom: 15px;
            display: flex;
            align-items: flex-start;
        }
        .detail-label {
            font-weight: 600;
            color: #2c3e50;
            width: 120px;
            flex-shrink: 0;
        }
        .detail-item span:not(.detail-label) {
            color: #666;
        }
        .detail-item pre {
            margin: 0;
            padding: 10px;
            background: #fff;
            border-radius: 4px;
            border: 1px solid #e0e0e0;
            width: 100%;
            font-family: 'Courier New', monospace;
        }
    </style>
    
    <script>
        // 打开状态数据详情弹窗
        function viewAccountDetails(statusDataJson) {
            // 直接显示原始数据，不做解析
            document.getElementById('detail-raw-data').textContent = statusDataJson;
            
            // 显示弹窗
            document.getElementById('statusModal').classList.add('show');
            
            // 阻止背景滚动
            document.body.style.overflow = 'hidden';
        }
        
        // 关闭弹窗
        function closeModal() {
            document.getElementById('statusModal').classList.remove('show');
            document.body.style.overflow = 'auto';
        }
        
        // 点击弹窗外部关闭
        window.addEventListener('click', function(event) {
            const modal = document.getElementById('statusModal');
            if (event.target === modal) {
                closeModal();
            }
        });
        
        // ESC键关闭弹窗
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape' && document.getElementById('statusModal').classList.contains('show')) {
                closeModal();
            }
        });
        
        // 复制授权回调字符串
        function copyAuthCallback(accessToken, openid) {
            // 构建授权回调字符串
            const callbackStr = `_Callback({"ret": 0,"url": "auth://tauth.qq.com/?#access_token=${accessToken}&expires_in=604800&openid=${openid}&pay_token=274B0EDCC18100E1094463F2DFAF87D0&ret=0&pf=desktop_m_qq-10000144-android-2002-&pfkey=86c9155735699365efa99f4ec0d1f6f7&auth_time=1716609564&page_type=0"});`;
            
            // 复制到剪贴板
            navigator.clipboard.writeText(callbackStr).then(() => {
                // 显示复制成功提示（简单的alert）
                alert('复制成功！');
            }).catch(err => {
                console.error('复制失败:', err);
                alert('复制失败，请手动复制。');
            });
        }
        // 更新账号数据
        function updateAccountData(accessToken, openid, accountId) {
            // 显示加载状态
            if (!confirm('确定要更新此账号的数据吗？')) {
                return;
            }
            
            // 创建临时的加载提示
            const loadingElement = document.createElement('div');
            loadingElement.style.position = 'fixed';
            loadingElement.style.top = '50%';
            loadingElement.style.left = '50%';
            loadingElement.style.transform = 'translate(-50%, -50%)';
            loadingElement.style.backgroundColor = 'rgba(0, 0, 0, 0.7)';
            loadingElement.style.color = 'white';
            loadingElement.style.padding = '20px';
            loadingElement.style.borderRadius = '8px';
            loadingElement.style.zIndex = '9999';
            loadingElement.textContent = '正在更新数据，请稍候...';
            document.body.appendChild(loadingElement);
            
            // 创建FormData对象
            const formData = new FormData();
            formData.append('access_token', accessToken);
            formData.append('openid', openid);
            formData.append('account_id', accountId);
            
            // 发送AJAX请求到更新处理程序
            fetch('update_account.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                // 移除加载提示
                document.body.removeChild(loadingElement);
                
                if (data.success) {
                    alert('数据更新成功！');
                    // 刷新当前页面以显示更新后的数据
                    location.reload();
                } else {
                    alert('数据更新失败：' + (data.error || '未知错误'));
                }
            })
            .catch(error => {
                // 移除加载提示
                document.body.removeChild(loadingElement);
                console.error('更新请求失败:', error);
                alert('网络请求失败，请稍后重试。');
            });
        }
    </script>
</body>
</html>