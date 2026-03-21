<?php
/**
 * 操作日志页面
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

// 初始化参数
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$pageSize = 20;
$offset = ($page - 1) * $pageSize;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filterOperator = isset($_GET['operator']) ? trim($_GET['operator']) : '';
$filterType = isset($_GET['type']) ? trim($_GET['type']) : '';
$dateRange = isset($_GET['date_range']) ? trim($_GET['date_range']) : '';
$sortBy = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'created_at';
$sortOrder = isset($_GET['sort_order']) && $_GET['sort_order'] === 'asc' ? 'asc' : 'desc';

// 构建查询条件
$conditions = [];
if (!empty($search)) {
    $conditions[] = "(description LIKE '%{$search}%' OR ip_address LIKE '%{$search}%')";
}

if (!empty($filterOperator)) {
    $conditions[] = "operator = '{$filterOperator}'";
}

if (!empty($filterType)) {
    $conditions[] = "operation_type = '{$filterType}'";
}

if (!empty($dateRange)) {
    // 解析日期范围，格式为：2023-01-01 - 2023-01-31
    $dateParts = explode('-', $dateRange);
    if (count($dateParts) === 2) {
        $startDate = trim($dateParts[0]);
        $endDate = trim($dateParts[1]);
        
        // 验证日期格式
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate) && 
            preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
            $conditions[] = "created_at BETWEEN '{$startDate} 00:00:00' AND '{$endDate} 23:59:59'";
        }
    }
}

// 查询数据
$database = new Database();

// 获取总数
$totalSql = "SELECT COUNT(*) as count FROM operation_logs";
if (!empty($conditions)) {
    $totalSql .= " WHERE " . implode(' AND ', $conditions);
}
$totalResult = $database->fetchOne($totalSql);
$totalCount = $totalResult['count'] ?? 0;
$totalPages = ceil($totalCount / $pageSize);

// 获取数据列表
$sql = "SELECT 
    id, 
    operation_type, 
    operator, 
    ip_address,
    description,
    created_at
FROM operation_logs";

if (!empty($conditions)) {
    $sql .= " WHERE " . implode(' AND ', $conditions);
}

$sql .= " ORDER BY {$sortBy} {$sortOrder} LIMIT {$pageSize} OFFSET {$offset}";
$logs = $database->fetchAll($sql);

// 获取所有操作员列表
$operatorsSql = "SELECT DISTINCT operator FROM operation_logs ORDER BY operator";
$operators = $database->fetchAll($operatorsSql);

// 获取所有操作类型列表
$typesSql = "SELECT DISTINCT operation_type FROM operation_logs ORDER BY operation_type";
$types = $database->fetchAll($typesSql);

// 导出数据
if (isset($_GET['action']) && $_GET['action'] === 'export') {
    // 构建导出SQL
    $exportSql = "SELECT 
        id, 
        operation_type, 
        operator, 
        ip_address,
        description,
        created_at
    FROM operation_logs";
    
    if (!empty($conditions)) {
        $exportSql .= " WHERE " . implode(' AND ', $conditions);
    }
    
    $exportSql .= " ORDER BY {$sortBy} {$sortOrder}";
    $exportData = $database->fetchAll($exportSql);
    
    // 设置响应头
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="operation_logs_export_"' . date('Ymd_His') . '.csv');
    
    // 创建文件流
    $output = fopen('php://output', 'w');
    
    // 添加BOM以支持Excel正确识别UTF-8
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
    
    // 写入表头
    fputcsv($output, ['ID', '操作类型', '操作员', 'IP地址', '描述', '创建时间'], ',');
    
    // 写入数据
    foreach ($exportData as $row) {
        fputcsv($output, [
            $row['id'],
            $row['operation_type'],
            $row['operator'],
            $row['ip_address'],
            $row['description'],
            $row['created_at']
        ], ',');
    }
    
    // 关闭文件流
    fclose($output);
    exit;
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>账号管理系统 - 操作日志</title>
    <style>
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
                <li><a href="accounts.php">账号管理</a></li>
                <li><a href="operation_logs.php" class="active">操作日志</a></li>
                <li><a href="admin_management.php">管理员管理</a></li>
                <!-- 系统设置已移除 -->
            </ul>
        </aside>
        
        <!-- 内容区域 -->
        <main class="content">
            <!-- 操作栏 -->
            <div class="action-bar">
                <h1>操作日志</h1>
                <div>
                    <a href="?action=export" class="btn" onclick="return confirm('确定要导出所有日志数据吗？')">
                        导出日志
                    </a>
                </div>
            </div>
            
            <!-- 搜索和筛选 -->
            <div class="search-filter">
                <div class="search-group">
                    <label for="search">搜索 (描述/IP地址)</label>
                    <input type="text" id="search" name="search" class="search-input" 
                           placeholder="输入搜索关键词" value="<?php echo $search; ?>">
                </div>
                
                <div class="search-group">
                    <label for="operator">操作员</label>
                    <select id="operator" name="operator" class="search-input">
                        <option value="">全部操作员</option>
                        <?php foreach ($operators as $operator): ?>
                            <option value="<?php echo $operator['operator']; ?>" 
                                <?php echo $filterOperator === $operator['operator'] ? 'selected' : ''; ?>>
                                <?php echo $operator['operator']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="search-group">
                    <label for="type">操作类型</label>
                    <select id="type" name="type" class="search-input">
                        <option value="">全部类型</option>
                        <?php foreach ($types as $type): ?>
                            <option value="<?php echo $type['operation_type']; ?>" 
                                <?php echo $filterType === $type['operation_type'] ? 'selected' : ''; ?>>
                                <?php echo $type['operation_type']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="search-group">
                    <label for="date_range">日期范围</label>
                    <input type="text" id="date_range" name="date_range" class="search-input" 
                           placeholder="YYYY-MM-DD - YYYY-MM-DD" value="<?php echo $dateRange; ?>">
                </div>
                
                <div>
                    <button type="submit" form="search-form" class="btn">搜索</button>
                </div>
                
                <div>
                    <a href="operation_logs.php" class="btn btn-secondary">重置</a>
                </div>
            </div>
            
            <!-- 数据表格 -->
            <form id="search-form" method="get">
                <input type="hidden" name="sort_by" value="<?php echo $sortBy; ?>">
                <input type="hidden" name="sort_order" value="<?php echo $sortOrder; ?>">
            </form>
            
            <div class="data-table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>
                                <a href="?search=<?php echo $search; ?>&operator=<?php echo $filterOperator; ?>&type=<?php echo $filterType; ?>&date_range=<?php echo $dateRange; ?>&sort_by=id&sort_order=<?php echo $sortOrder === 'asc' ? 'desc' : 'asc'; ?>">
                                    ID
                                    <span><?php echo $sortBy === 'id' ? ($sortOrder === 'asc' ? '↑' : '↓') : ''; ?></span>
                                </a>
                            </th>
                            <th>
                                <a href="?search=<?php echo $search; ?>&operator=<?php echo $filterOperator; ?>&type=<?php echo $filterType; ?>&date_range=<?php echo $dateRange; ?>&sort_by=operation_type&sort_order=<?php echo $sortOrder === 'asc' ? 'desc' : 'asc'; ?>">
                                    操作类型
                                    <span><?php echo $sortBy === 'operation_type' ? ($sortOrder === 'asc' ? '↑' : '↓') : ''; ?></span>
                                </a>
                            </th>
                            <th>
                                <a href="?search=<?php echo $search; ?>&operator=<?php echo $filterOperator; ?>&type=<?php echo $filterType; ?>&date_range=<?php echo $dateRange; ?>&sort_by=operator&sort_order=<?php echo $sortOrder === 'asc' ? 'desc' : 'asc'; ?>">
                                    操作员
                                    <span><?php echo $sortBy === 'operator' ? ($sortOrder === 'asc' ? '↑' : '↓') : ''; ?></span>
                                </a>
                            </th>
                            <th>
                                <a href="?search=<?php echo $search; ?>&operator=<?php echo $filterOperator; ?>&type=<?php echo $filterType; ?>&date_range=<?php echo $dateRange; ?>&sort_by=ip_address&sort_order=<?php echo $sortOrder === 'asc' ? 'desc' : 'asc'; ?>">
                                    IP地址
                                    <span><?php echo $sortBy === 'ip_address' ? ($sortOrder === 'asc' ? '↑' : '↓') : ''; ?></span>
                                </a>
                            </th>
                            <th>描述</th>
                            <th>
                                <a href="?search=<?php echo $search; ?>&operator=<?php echo $filterOperator; ?>&type=<?php echo $filterType; ?>&date_range=<?php echo $dateRange; ?>&sort_by=created_at&sort_order=<?php echo $sortOrder === 'asc' ? 'desc' : 'asc'; ?>">
                                    创建时间
                                    <span><?php echo $sortBy === 'created_at' ? ($sortOrder === 'asc' ? '↑' : '↓') : ''; ?></span>
                                </a>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($logs)): ?>
                            <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td><?php echo $log['id']; ?></td>
                                    <td><?php echo $log['operation_type']; ?></td>
                                    <td><?php echo $log['operator']; ?></td>
                                    <td><?php echo $log['ip_address']; ?></td>
                                    <td><?php echo $log['description']; ?></td>
                                    <td><?php echo $log['created_at']; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" style="text-align: center; color: #999; padding: 40px;">暂无日志记录</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                
                <!-- 分页 -->
                <div class="pagination">
                    <a href="?search=<?php echo $search; ?>&operator=<?php echo $filterOperator; ?>&type=<?php echo $filterType; ?>&date_range=<?php echo $dateRange; ?>&sort_by=<?php echo $sortBy; ?>&sort_order=<?php echo $sortOrder; ?>&page=1" 
                       class="<?php echo $page <= 1 ? 'disabled' : ''; ?>">首页</a>
                    <a href="?search=<?php echo $search; ?>&operator=<?php echo $filterOperator; ?>&type=<?php echo $filterType; ?>&date_range=<?php echo $dateRange; ?>&sort_by=<?php echo $sortBy; ?>&sort_order=<?php echo $sortOrder; ?>&page=<?php echo $page - 1; ?>" 
                       class="<?php echo $page <= 1 ? 'disabled' : ''; ?>">上一页</a>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                        <a href="?search=<?php echo $search; ?>&operator=<?php echo $filterOperator; ?>&type=<?php echo $filterType; ?>&date_range=<?php echo $dateRange; ?>&sort_by=<?php echo $sortBy; ?>&sort_order=<?php echo $sortOrder; ?>&page=<?php echo $i; ?>" 
                           class="<?php echo $i === $page ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                    
                    <a href="?search=<?php echo $search; ?>&operator=<?php echo $filterOperator; ?>&type=<?php echo $filterType; ?>&date_range=<?php echo $dateRange; ?>&sort_by=<?php echo $sortBy; ?>&sort_order=<?php echo $sortOrder; ?>&page=<?php echo $page + 1; ?>" 
                       class="<?php echo $page >= $totalPages ? 'disabled' : ''; ?>">下一页</a>
                    <a href="?search=<?php echo $search; ?>&operator=<?php echo $filterOperator; ?>&type=<?php echo $filterType; ?>&date_range=<?php echo $dateRange; ?>&sort_by=<?php echo $sortBy; ?>&sort_order=<?php echo $sortOrder; ?>&page=<?php echo $totalPages; ?>" 
                       class="<?php echo $page >= $totalPages ? 'disabled' : ''; ?>">末页</a>
                    
                    <span style="color: #666;">共 <?php echo $totalCount; ?> 条记录，第 <?php echo $page; ?> / <?php echo $totalPages; ?> 页</span>
                </div>
            </div>
        </main>
    </div>
</body>
</html>