<?php
/**
 * 数据库初始化脚本
 * 自动创建数据库、导入表结构并初始化默认管理员账户
 */

echo '<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>数据库初始化 - 静默数据存储和管理系统</title>
    <style>
        body {
            font-family: "Microsoft YaHei", Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        h1 {
            color: #2c3e50;
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 2px solid #3498db;
        }
        .container {
            background-color: white;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        .step {
            margin-bottom: 20px;
            padding: 15px;
            border-radius: 5px;
            background-color: #ecf0f1;
            position: relative;
        }
        .step-title {
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 10px;
            font-size: 18px;
        }
        .step-content {
            padding-left: 15px;
        }
        .success {
            color: #27ae60;
            background-color: #e8f8f5;
            border-left: 4px solid #27ae60;
        }
        .error {
            color: #e74c3c;
            background-color: #fdedec;
            border-left: 4px solid #e74c3c;
        }
        .warning {
            color: #f39c12;
            background-color: #fef9e7;
            border-left: 4px solid #f39c12;
        }
        .loading {
            animation: pulse 1.5s infinite;
        }
        @keyframes pulse {
            0% { opacity: 0.6; }
            50% { opacity: 1; }
            100% { opacity: 0.6; }
        }
        pre {
            background-color: #f8f9fa;
            padding: 10px;
            border-radius: 5px;
            overflow-x: auto;
            font-family: "Courier New", monospace;
            font-size: 14px;
        }
        .button {
            display: inline-block;
            padding: 10px 20px;
            background-color: #3498db;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            transition: background-color 0.3s;
        }
        .button:hover {
            background-color: #2980b9;
        }
        .button-container {
            text-align: center;
            margin-top: 30px;
        }
    </style>
</head>
<body>
    <h1>数据库初始化</h1>
    <div class="container">
';

// 输出函数，用于显示步骤进度
echo '<div class="step loading">
        <div class="step-title">开始初始化...</div>
        <div class="step-content">正在准备数据库初始化流程</div>
      </div>';

// 检查PHP版本
if (version_compare(PHP_VERSION, '7.0.0') < 0) {
    echo '<div class="step error">
            <div class="step-title">PHP版本检查失败</div>
            <div class="step-content">
                <p>当前PHP版本过低，请升级到PHP 7.0或更高版本。</p>
                <p>当前版本: ' . PHP_VERSION . '</p>
            </div>
          </div>';
    exit;
}

echo '<div class="step success">
        <div class="step-title">PHP版本检查通过</div>
        <div class="step-content">
            当前PHP版本: ' . PHP_VERSION . '
        </div>
      </div>';

// 检查MySQL扩展
if (!extension_loaded('mysqli')) {
    echo '<div class="step error">
            <div class="step-title">MySQL扩展检查失败</div>
            <div class="step-content">
                <p>未找到MySQLi扩展，请在PHP配置中启用。</p>
            </div>
          </div>';
    exit;
}

echo '<div class="step success">
        <div class="step-title">MySQL扩展检查通过</div>
        <div class="step-content">
            MySQLi扩展已安装
        </div>
      </div>';

// 加载数据库配置
try {
    echo '<div class="step loading">
            <div class="step-title">加载数据库配置</div>
            <div class="step-content">正在读取配置文件...</div>
          </div>';
    
    if (!file_exists(__DIR__ . '/database/config.php')) {
        throw new Exception('数据库配置文件不存在');
    }
    
    $config = require __DIR__ . '/database/config.php';
    
    echo '<div class="step success">
            <div class="step-title">数据库配置加载成功</div>
            <div class="step-content">
                数据库主机: ' . $config['host'] . ':' . $config['port'] . '<br>
                数据库名称: ' . $config['dbname'] . '<br>
                用户名: ' . $config['username'] . '
            </div>
          </div>';
} catch (Exception $e) {
    echo '<div class="step error">
            <div class="step-title">数据库配置加载失败</div>
            <div class="step-content">
                错误: ' . $e->getMessage() . '
            </div>
          </div>';
    exit;
}

// 连接到MySQL服务器
try {
    echo '<div class="step loading">
            <div class="step-title">连接MySQL服务器</div>
            <div class="step-content">正在连接到MySQL服务器...</div>
          </div>';
    
    // 先连接到MySQL服务器（不指定数据库）
    $mysql = new mysqli($config['host'], $config['username'], $config['password'], '', $config['port']);
    
    if ($mysql->connect_error) {
        throw new Exception('连接MySQL服务器失败: ' . $mysql->connect_error);
    }
    
    // 设置字符集
    $mysql->set_charset($config['charset']);
    
    echo '<div class="step success">
            <div class="step-title">MySQL服务器连接成功</div>
            <div class="step-content">
                已成功连接到MySQL服务器
            </div>
          </div>';
} catch (Exception $e) {
    echo '<div class="step error">
            <div class="step-title">MySQL服务器连接失败</div>
            <div class="step-content">
                错误: ' . $e->getMessage() . '<br>
                <p>请检查数据库服务器地址、用户名和密码是否正确。</p>
            </div>
          </div>';
    exit;
}

// 创建数据库（如果不存在）
try {
    echo '<div class="step loading">
            <div class="step-title">创建数据库</div>
            <div class="step-content">正在检查并创建数据库...</div>
          </div>';
    
    // 创建数据库查询，处理可能缺失的collation配置
    $charset = isset($config['charset']) ? $mysql->real_escape_string($config['charset']) : 'utf8mb4';
    $collation = isset($config['collation']) && !empty($config['collation']) ? $mysql->real_escape_string($config['collation']) : $charset . '_general_ci';
    
    $createDbQuery = "CREATE DATABASE IF NOT EXISTS `" . $mysql->real_escape_string($config['dbname']) . "` CHARACTER SET `" . $charset . "`";
    
    // 只有当collation不为空时才添加到查询中
    if (!empty($collation)) {
        $createDbQuery .= " COLLATE `" . $collation . "`";
    }
    
    if (!$mysql->query($createDbQuery)) {
        // 如果带有collation的创建失败，尝试不带collation创建
        $createDbQuery = "CREATE DATABASE IF NOT EXISTS `" . $mysql->real_escape_string($config['dbname']) . "` CHARACTER SET `" . $charset . "`";
        if (!$mysql->query($createDbQuery)) {
            throw new Exception('创建数据库失败: ' . $mysql->error);
        } else {
            echo '<div class="step warning">
                    <div class="step-title">数据库创建成功（已忽略排序规则）</div>
                    <div class="step-content">
                        由于排序规则配置问题，数据库已成功创建但未设置排序规则。
                    </div>
                  </div>';
        }
    }
    
    // 选择数据库
    if (!$mysql->select_db($config['dbname'])) {
        throw new Exception('选择数据库失败: ' . $mysql->error);
    }
    
    echo '<div class="step success">
            <div class="step-title">数据库创建/选择成功</div>
            <div class="step-content">
                数据库 `' . $config['dbname'] . '` 已准备就绪
            </div>
          </div>';
} catch (Exception $e) {
    echo '<div class="step error">
            <div class="step-title">数据库创建失败</div>
            <div class="step-content">
                错误: ' . $e->getMessage() . '<br>
                <p>请确保数据库用户有创建数据库的权限。</p>
            </div>
          </div>';
    $mysql->close();
    exit;
}

// 导入表结构
try {
    echo '<div class="step loading">
            <div class="step-title">导入表结构</div>
            <div class="step-content">正在读取并执行SQL文件...</div>
          </div>';
    
    // 读取schema.sql文件
    $schemaFile = __DIR__ . '/database/schema.sql';
    if (!file_exists($schemaFile)) {
        throw new Exception('数据库表结构文件不存在');
    }
    
    $schemaContent = file_get_contents($schemaFile);
    if ($schemaContent === false) {
        throw new Exception('读取数据库表结构文件失败');
    }
    
    // 使用multi_query执行多个SQL语句
    $mysql->multi_query($schemaContent);
    
    // 处理所有结果集，确保没有错误
    do {
        if ($result = $mysql->store_result()) {
            $result->free();
        }
    } while ($mysql->more_results() && $mysql->next_result());
    
    // 检查是否有错误
    if ($mysql->errno) {
        throw new Exception('执行SQL语句失败: ' . $mysql->error);
    }
    
    // 系统设置初始化代码已移除
    
    echo '<div class="step success">
            <div class="step-title">表结构导入成功</div>
            <div class="step-content">
                已成功创建所有表和索引
            </div>
          </div>';
} catch (Exception $e) {
    echo '<div class="step error">
            <div class="step-title">表结构导入失败</div>
            <div class="step-content">
                错误: ' . $e->getMessage() . '
            </div>
          </div>';
    $mysql->close();
    exit;
}

// 创建默认管理员账户
try {
    echo '<div class="step loading">
            <div class="step-title">创建默认管理员账户</div>
            <div class="step-content">正在检查并创建管理员账户...</div>
          </div>';
    
    // 检查是否已有管理员账户
    $checkAdmin = $mysql->prepare("SELECT COUNT(*) FROM admins");
    $checkAdmin->execute();
    $checkAdmin->bind_result($adminCount);
    $checkAdmin->fetch();
    $checkAdmin->close();
    
    if ($adminCount == 0) {
        // 创建默认管理员账户
        $username = 'admin';
        $password = 'admin123'; // 默认密码
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $role = 'admin';
        $status = 1;
        
        $createAdmin = $mysql->prepare("INSERT INTO admins (username, password_hash, role, status) VALUES (?, ?, ?, ?)");
        $createAdmin->bind_param('sssi', $username, $passwordHash, $role, $status);
        
        if (!$createAdmin->execute()) {
            throw new Exception('创建管理员账户失败: ' . $mysql->error);
        }
        
        $createAdmin->close();
        
        echo '<div class="step success">
                <div class="step-title">默认管理员账户创建成功</div>
                <div class="step-content">
                    <p><strong>用户名:</strong> ' . $username . '</p>
                    <p><strong>密码:</strong> ' . $password . '</p>
                    <p class="warning" style="margin-top: 10px;">请在首次登录后立即修改密码!</p>
                </div>
              </div>';
    } else {
        echo '<div class="step warning">
                <div class="step-title">管理员账户已存在</div>
                <div class="step-content">
                    系统中已存在 ' . $adminCount . ' 个管理员账户，跳过创建默认账户
                </div>
              </div>';
    }
} catch (Exception $e) {
    echo '<div class="step error">
            <div class="step-title">管理员账户创建失败</div>
            <div class="step-content">
                错误: ' . $e->getMessage() . '
            </div>
          </div>';
    $mysql->close();
    exit;
}

// 关闭数据库连接
$mysql->close();

// 显示完成消息
echo '<div class="step success">
        <div class="step-title">数据库初始化完成!</div>
        <div class="step-content">
            <p>所有初始化步骤已成功完成。</p>
            <p>您现在可以登录到管理后台开始使用系统。</p>
        </div>
      </div>';

echo '<div class="button-container">
        <a href="admin/login.php" class="button">前往登录页面</a>
      </div>';

echo '</div>
</body>
</html>';

// 记录初始化日志
try {
    // 确保logs目录存在
    $logDir = __DIR__ . '/logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $logFile = $logDir . '/init_log.txt';
    $logMessage = '[' . date('Y-m-d H:i:s') . '] 数据库初始化成功 - 数据库: ' . $config['dbname'] . PHP_EOL;
    error_log($logMessage, 3, $logFile);
} catch (Exception $e) {
    // 忽略日志记录错误
}
?>