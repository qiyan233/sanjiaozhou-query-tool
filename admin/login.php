<?php
/**
 * 后台管理系统登录页面
 */

// 启用会话
session_start();

// 如果用户已登录，重定向到首页
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: dashboard.php');
    exit;
}

// 错误信息
$error = '';

// 处理登录请求
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/../database/Database.php';
    
    try {
        $database = new Database();
        $username = trim($_POST['username']);
        $password = $_POST['password'];
        
        // 验证输入
        if (empty($username) || empty($password)) {
            $error = '用户名和密码不能为空';
        } else {
            // 查询用户
            $admin = $database->selectOne(
                'admins',
                ['*'],
                ['username' => $username, 'status' => 1]
            );
            
            if ($admin && password_verify($password, $admin['password_hash'])) {
                // 登录成功
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin_username'] = $admin['username'];
                $_SESSION['admin_role'] = $admin['role'];
                
                // 更新登录信息
                $database->update(
                    'admins',
                    [
                        'last_login_at' => date('Y-m-d H:i:s'),
                        'last_login_ip' => $_SERVER['REMOTE_ADDR']
                    ],
                    ['id' => $admin['id']]
                );
                
                // 记录登录日志
                $database->insert('operation_logs', [
                    'operation_type' => 'admin_login',
                    'operator' => $username,
                    'ip_address' => $_SERVER['REMOTE_ADDR'],
                    'description' => "管理员登录: {$username}"
                ]);
                
                header('Location: dashboard.php');
                exit;
            } else {
                $error = '用户名或密码错误';
            }
        }
    } catch (Exception $e) {
        $error = '登录失败，请稍后重试';
        error_log('登录异常: ' . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>账号管理系统 - 登录</title>
    <style>
        /* 全局样式 */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Roboto', 'Oxygen', 'Ubuntu', 'Cantarell', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        /* 登录容器 */
        .login-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            width: 100%;
            max-width: 400px;
        }
        
        /* 登录头部 */
        .login-header {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
            padding: 30px 20px;
            text-align: center;
        }
        
        .login-header h1 {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 10px;
        }
        
        .login-header p {
            font-size: 14px;
            opacity: 0.9;
        }
        
        /* 登录表单 */
        .login-form {
            padding: 30px 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            font-size: 14px;
            color: #333;
            margin-bottom: 8px;
            font-weight: 500;
        }
        
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s ease;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #4facfe;
        }
        
        /* 按钮 */
        .btn {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: opacity 0.3s ease;
        }
        
        .btn:hover {
            opacity: 0.9;
        }
        
        .btn:active {
            transform: translateY(1px);
        }
        
        /* 错误信息 */
        .error-message {
            background: #ffebee;
            color: #c62828;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 14px;
            border-left: 4px solid #c62828;
        }
        
        /* 响应式设计 */
        @media (max-width: 480px) {
            .login-container {
                max-width: 100%;
            }
            
            .login-header,
            .login-form {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1>账号管理系统</h1>
            <p>请使用管理员账号登录</p>
        </div>
        
        <div class="login-form">
            <?php if (!empty($error)): ?>
                <div class="error-message">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <form method="post" action="">
                <div class="form-group">
                    <label for="username">用户名</label>
                    <input type="text" id="username" name="username" class="form-control" placeholder="请输入用户名" value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                </div>
                
                <div class="form-group">
                    <label for="password">密码</label>
                    <input type="password" id="password" name="password" class="form-control" placeholder="请输入密码">
                </div>
                
                <button type="submit" class="btn">登录</button>
            </form>
        </div>
    </div>
</body>
</html>