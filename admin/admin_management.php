<?php
/**
 * 管理员管理页面
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
$sortBy = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'id';
// 确保排序字段存在于数据库表中
$allowedSortFields = ['id', 'username', 'role', 'status', 'created_at', 'updated_at', 'last_login_at'];
if (!in_array($sortBy, $allowedSortFields)) {
    $sortBy = 'id';
}
$sortOrder = isset($_GET['sort_order']) && $_GET['sort_order'] === 'asc' ? 'asc' : 'desc';
// 确保排序方向是有效的
if (!in_array($sortOrder, ['asc', 'desc'])) {
    $sortOrder = 'desc';
}

// 查询数据
$database = new Database();

// 构建查询条件
$conditions = [];
if (!empty($search)) {
    $safeSearch = $database->quote('%' . $search . '%');
    $conditions[] = "username LIKE {$safeSearch}";
}

// 获取总数
$totalSql = "SELECT COUNT(*) as count FROM admins";
if (!empty($conditions)) {
    $totalSql .= " WHERE " . implode(' AND ', $conditions);
}
$totalResult = $database->fetchOne($totalSql);
$totalCount = $totalResult['count'] ?? 0;
$totalPages = ceil($totalCount / $pageSize);

// 获取数据列表
$sql = "SELECT 
    id, 
    username, 
    role,
    status,
    created_at,
    updated_at,
    last_login_at,
    last_login_ip
FROM admins";

if (!empty($conditions)) {
    $sql .= " WHERE " . implode(' AND ', $conditions);
}

$sql .= " ORDER BY {$sortBy} {$sortOrder} LIMIT {$pageSize} OFFSET {$offset}";
$admins = $database->fetchAll($sql);

// 处理删除操作
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $adminId = intval($_GET['id']);
    
    // 检查是否是当前登录用户
    if ($adminId == $_SESSION['admin_id']) {
        $error = '不能删除当前登录的管理员账号';
    } else {
        try {
            // 删除管理员
            $database->delete('admins', ['id' => $adminId]);
            
            // 记录操作日志
            $database->insert('operation_logs', [
                'operation_type' => 'delete_admin',
                'operator' => $_SESSION['admin_username'],
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'description' => "删除管理员账号: ID={$adminId}"
            ]);
            
            // 重定向回列表
            header('Location: admin_management.php?message=删除成功');
            exit;
        } catch (Exception $e) {
            $error = '删除失败: ' . $e->getMessage();
        }
    }
}

// 处理状态变更
if (isset($_GET['action']) && $_GET['action'] === 'toggle_status' && isset($_GET['id'])) {
    $adminId = intval($_GET['id']);
    $currentStatus = isset($_GET['status']) ? intval($_GET['status']) : 1;
    $newStatus = $currentStatus === 1 ? 0 : 1;
    
    // 检查是否是当前登录用户
    if ($adminId == $_SESSION['admin_id'] && $newStatus === 0) {
        $error = '不能禁用当前登录的管理员账号';
    } else {
        try {
            // 更新状态
            $database->update('admins', ['status' => $newStatus], ['id' => $adminId]);
            
            // 记录操作日志
            $statusText = $newStatus === 1 ? '启用' : '禁用';
            $database->insert('operation_logs', [
                'operation_type' => 'toggle_admin_status',
                'operator' => $_SESSION['admin_username'],
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'description' => "{$statusText}管理员账号: ID={$adminId}"
            ]);
            
            // 重定向回列表
            header('Location: admin_management.php?message=状态更新成功');
            exit;
        } catch (Exception $e) {
            $error = '状态更新失败: ' . $e->getMessage();
        }
    }
}

// 处理添加管理员
if (isset($_POST['action']) && $_POST['action'] === 'add_admin') {
    $username = trim($_POST['username']);
    // 邮箱字段在数据库中不存在，暂时注释掉
    $password = $_POST['password'];
    $role = $_POST['role'];
    
    // 简单验证
    if (empty($username) || empty($password)) {
        $error = '请填写所有必填字段';
    } else {
        // 检查用户名是否已存在
        $existingUser = $database->selectOne('admins', ['id'], ['username' => $username]);
        if (!empty($existingUser)) {
            $error = '用户名已存在';
        } else {
            try {
                // 创建管理员
                $database->insert('admins', [
                    'username' => $username,
                    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                    'role' => $role,
                    'status' => 1,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
                
                // 记录操作日志
                $database->insert('operation_logs', [
                    'operation_type' => 'add_admin',
                    'operator' => $_SESSION['admin_username'],
                    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                    'description' => "添加管理员账号: {$username}"
                ]);
                
                // 重定向回列表
                header('Location: admin_management.php?message=管理员添加成功');
                exit;
            } catch (Exception $e) {
                $error = '添加失败: ' . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>账号管理系统 - 管理员管理</title>
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
        
        .btn-danger {
            background: #dc3545;
        }
        
        .btn-success {
            background: #28a745;
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
        
        .data-table .status-active {
            background: #d4edda;
            color: #155724;
        }
        
        .data-table .status-inactive {
            background: #e2e3e5;
            color: #383d41;
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
        
        /* 模态框 */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 2000;
            justify-content: center;
            align-items: center;
        }
        
        .modal.show {
            display: flex;
        }
        
        .modal-content {
            background: white;
            border-radius: 10px;
            width: 90%;
            max-width: 600px;
            box-shadow: 0 5px 30px rgba(0, 0, 0, 0.2);
        }
        
        .modal-header {
            padding: 20px;
            border-bottom: 1px solid #e0e0e0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-header h2 {
            font-size: 20px;
            color: #2c3e50;
        }
        
        .modal-close {
            background: none;
            border: none;
            font-size: 24px;
            color: #999;
            cursor: pointer;
        }
        
        .modal-body {
            padding: 20px;
        }
        
        .modal-footer {
            padding: 20px;
            border-top: 1px solid #e0e0e0;
            text-align: right;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
        
        /* 表单样式 */
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #333;
        }
        
        .form-control {
            width: 100%;
            padding: 10px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            font-size: 14px;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #4facfe;
        }
        
        .form-control::placeholder {
            color: #999;
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
                <li><a href="operation_logs.php">操作日志</a></li>
                <li><a href="admin_management.php" class="active">管理员管理</a></li>
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
                <h1>管理员管理</h1>
                <div>
                    <button class="btn" onclick="showAddModal()">添加管理员</button>
                </div>
            </div>
            
            <!-- 搜索和筛选 -->
            <div class="search-filter">
                <div class="search-group">
                    <label for="search">搜索 (用户名/邮箱)</label>
                    <input type="text" id="search" name="search" class="search-input" 
                           placeholder="输入搜索关键词" value="<?php echo $search; ?>">
                </div>
                
                <div>
                    <button type="submit" form="search-form" class="btn">搜索</button>
                </div>
                
                <div>
                    <a href="admin_management.php" class="btn btn-secondary">重置</a>
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
                                <a href="?search=<?php echo $search; ?>&sort_by=id&sort_order=<?php echo $sortOrder === 'asc' ? 'desc' : 'asc'; ?>">
                                    ID
                                    <span><?php echo $sortBy === 'id' ? ($sortOrder === 'asc' ? '↑' : '↓') : ''; ?></span>
                                </a>
                            </th>
                            <th>
                                <a href="?search=<?php echo $search; ?>&sort_by=username&sort_order=<?php echo $sortOrder === 'asc' ? 'desc' : 'asc'; ?>">
                                    用户名
                                    <span><?php echo $sortBy === 'username' ? ($sortOrder === 'asc' ? '↑' : '↓') : ''; ?></span>
                                </a>
                            </th>
                            <!-- 邮箱字段在数据库中不存在，暂时注释掉 -->
                            <th>
                                <a href="?search=<?php echo $search; ?>&sort_by=role&sort_order=<?php echo $sortOrder === 'asc' ? 'desc' : 'asc'; ?>">
                                    角色
                                    <span><?php echo $sortBy === 'role' ? ($sortOrder === 'asc' ? '↑' : '↓') : ''; ?></span>
                                </a>
                            </th>
                            <th>
                                <a href="?search=<?php echo $search; ?>&sort_by=status&sort_order=<?php echo $sortOrder === 'asc' ? 'desc' : 'asc'; ?>">
                                    状态
                                    <span><?php echo $sortBy === 'status' ? ($sortOrder === 'asc' ? '↑' : '↓') : ''; ?></span>
                                </a>
                            </th>
                            <th>
                                <a href="?search=<?php echo $search; ?>&sort_by=created_at&sort_order=<?php echo $sortOrder === 'asc' ? 'desc' : 'asc'; ?>">
                                    创建时间
                                    <span><?php echo $sortBy === 'created_at' ? ($sortOrder === 'asc' ? '↑' : '↓') : ''; ?></span>
                                </a>
                            </th>
                            <th>
                                <a href="?search=<?php echo $search; ?>&sort_by=last_login_at&sort_order=<?php echo $sortOrder === 'asc' ? 'desc' : 'asc'; ?>">
                                    最后登录
                                    <span><?php echo $sortBy === 'last_login_at' ? ($sortOrder === 'asc' ? '↑' : '↓') : ''; ?></span>
                                </a>
                            </th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($admins)): ?>
                            <?php foreach ($admins as $admin): ?>
                                <tr>
                                    <td><?php echo $admin['id']; ?></td>
                                    <td><?php echo $admin['username']; ?></td>
                                    <!-- <td><?php echo $admin['email']; ?></td> --> <!-- 邮箱字段在数据库中不存在 -->
                                    <td><?php echo $admin['role']; ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo intval($admin['status']) === 1 ? 'active' : 'inactive'; ?>">
                                            <?php echo intval($admin['status']) === 1 ? '启用' : '禁用'; ?>
                                        </span>
                                    </td>
                                    <td><?php echo $admin['created_at']; ?></td>
                                    <td><?php echo $admin['last_login_at'] ? $admin['last_login_at'] : '-'; ?></td>
                                    <td>
                                        <?php if ($admin['id'] == $_SESSION['admin_id']): ?>
                                            <span style="color: #999; font-size: 12px;">当前账号</span>
                                        <?php else: ?>
                                            <a href="?action=toggle_status&id=<?php echo $admin['id']; ?>&status=<?php echo intval($admin['status']); ?>" 
                                               class="btn btn-sm <?php echo intval($admin['status']) === 1 ? 'btn-secondary' : 'btn-success'; ?>" 
                                               onclick="return confirm('确定要<?php echo intval($admin['status']) === 1 ? '禁用' : '启用'; ?>这个管理员吗？');">
                                                <?php echo intval($admin['status']) === 1 ? '禁用' : '启用'; ?>
                                            </a>
                                            <a href="?action=delete&id=<?php echo $admin['id']; ?>" class="btn btn-sm btn-danger" 
                                               onclick="return confirm('确定要删除这个管理员吗？此操作不可恢复。');">删除</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" style="text-align: center; color: #999; padding: 40px;">暂无管理员数据</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                
                <!-- 分页 -->
                <div class="pagination">
                    <a href="?search=<?php echo $search; ?>&sort_by=<?php echo $sortBy; ?>&sort_order=<?php echo $sortOrder; ?>&page=1" 
                       class="<?php echo $page <= 1 ? 'disabled' : ''; ?>">首页</a>
                    <a href="?search=<?php echo $search; ?>&sort_by=<?php echo $sortBy; ?>&sort_order=<?php echo $sortOrder; ?>&page=<?php echo $page - 1; ?>" 
                       class="<?php echo $page <= 1 ? 'disabled' : ''; ?>">上一页</a>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                        <a href="?search=<?php echo $search; ?>&sort_by=<?php echo $sortBy; ?>&sort_order=<?php echo $sortOrder; ?>&page=<?php echo $i; ?>" 
                           class="<?php echo $i === $page ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                    
                    <a href="?search=<?php echo $search; ?>&sort_by=<?php echo $sortBy; ?>&sort_order=<?php echo $sortOrder; ?>&page=<?php echo $page + 1; ?>" 
                       class="<?php echo $page >= $totalPages ? 'disabled' : ''; ?>">下一页</a>
                    <a href="?search=<?php echo $search; ?>&sort_by=<?php echo $sortBy; ?>&sort_order=<?php echo $sortOrder; ?>&page=<?php echo $totalPages; ?>" 
                       class="<?php echo $page >= $totalPages ? 'disabled' : ''; ?>">末页</a>
                    
                    <span style="color: #666;">共 <?php echo $totalCount; ?> 条记录，第 <?php echo $page; ?> / <?php echo $totalPages; ?> 页</span>
                </div>
            </div>
        </main>
    </div>
    
    <!-- 添加管理员模态框 -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <form id="addAdminForm" method="post" action="admin_management.php">
                <input type="hidden" name="action" value="add_admin">
                <div class="modal-header">
                    <h2>添加管理员</h2>
                    <button type="button" class="modal-close" onclick="closeModal()">×</button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label for="username">用户名</label>
                        <input type="text" id="username" name="username" class="form-control" 
                               placeholder="输入用户名" required>
                    </div>
                    <!-- 邮箱字段在数据库中不存在，暂时注释掉 -->
                        <!-- <div class="form-group">
                            <label for="email">邮箱</label>
                            <input type="email" id="email" name="email" class="form-control"
                                   placeholder="请输入邮箱" required>
                        </div> -->
                    </div>
                    <div class="form-group">
                        <label for="password">密码</label>
                        <input type="password" id="password" name="password" class="form-control" 
                               placeholder="输入密码" required>
                    </div>
                    <div class="form-group">
                        <label for="role">角色</label>
                        <select id="role" name="role" class="form-control" required>
                            <option value="admin">管理员</option>
                            <option value="editor">编辑</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">取消</button>
                    <button type="submit" class="btn btn-success">添加</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // 显示添加管理员模态框
        function showAddModal() {
            document.getElementById('addModal').classList.add('show');
            document.body.style.overflow = 'hidden';
        }
        
        // 关闭模态框
        function closeModal() {
            document.getElementById('addModal').classList.remove('show');
            document.body.style.overflow = 'auto';
        }
        
        // 点击模态框外部关闭
        window.addEventListener('click', function(event) {
            const modal = document.getElementById('addModal');
            if (event.target === modal) {
                closeModal();
            }
        });
        
        // ESC键关闭模态框
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape' && document.getElementById('addModal').classList.contains('show')) {
                closeModal();
            }
        });
    </script>
</body>
</html>