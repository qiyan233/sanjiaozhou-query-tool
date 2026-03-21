<?php
/**
 * 数据库连接池管理类
 * 实现连接池管理、自动重连、连接生命周期管理等功能
 */

class ConnectionPool {
    // 连接池实例（单例模式）
    private static $instance;
    
    // 数据库配置
    private $config;
    
    // 连接池数组
    private $connections = [];
    
    // 连接状态数组
    private $connectionStatus = [];
    
    // 连接池统计信息
    private $stats = [
        'total_connections' => 0,
        'active_connections' => 0,
        'idle_connections' => 0,
        'connection_attempts' => 0,
        'connection_failures' => 0,
    ];
    
    /**
     * 构造函数
     * @param array $config 数据库配置
     */
    private function __construct($config) {
        $this->config = $config;
        // 初始化日志目录
        $this->initLogDirectory();
        // 初始化连接池
        $this->initializePool();
    }
    
    /**
     * 获取连接池实例（单例模式）
     * @param array $config 数据库配置
     * @return ConnectionPool 连接池实例
     */
    public static function getInstance($config = null) {
        if (!self::$instance) {
            if (!$config) {
                $config = require __DIR__ . '/config.php';
            }
            self::$instance = new self($config);
        }
        return self::$instance;
    }
    
    /**
     * 初始化日志目录
     */
    private function initLogDirectory() {
        $logDir = dirname($this->config['error_log']);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
    }
    
    /**
     * 初始化连接池
     */
    private function initializePool() {
        try {
            $minConnections = $this->config['pool']['min_connections'];
            for ($i = 0; $i < $minConnections; $i++) {
                $connection = $this->createConnection();
                if ($connection) {
                    $this->connections[] = $connection;
                    $this->connectionStatus[] = [
                        'created_at' => time(),
                        'last_used' => time(),
                        'in_use' => false,
                    ];
                    $this->stats['total_connections']++;
                    $this->stats['idle_connections']++;
                }
            }
            $this->logMessage("连接池初始化完成，创建了 {$this->stats['total_connections']} 个连接");
        } catch (Exception $e) {
            $this->logError("连接池初始化失败: " . $e->getMessage());
        }
    }
    
    /**
     * 创建数据库连接
     * @return mysqli|null 数据库连接对象
     */
    private function createConnection() {
        $this->stats['connection_attempts']++;
        $retryAttempts = $this->config['pool']['retry_attempts'];
        $retryInterval = $this->config['pool']['retry_interval'];
        
        for ($attempt = 0; $attempt < $retryAttempts; $attempt++) {
            try {
                $mysqli = new mysqli(
                    $this->config['host'],
                    $this->config['username'],
                    $this->config['password'],
                    $this->config['dbname'],
                    $this->config['port']
                );
                
                if ($mysqli->connect_error) {
                    throw new Exception("数据库连接失败: " . $mysqli->connect_error);
                }
                
                // 设置字符集
                $mysqli->set_charset($this->config['charset']);
                
                // 设置连接超时
                $mysqli->options(MYSQLI_OPT_CONNECT_TIMEOUT, $this->config['pool']['connection_timeout']);
                
                return $mysqli;
            } catch (Exception $e) {
                $this->logError("连接创建失败 (尝试 {$attempt}/{$retryAttempts}): " . $e->getMessage());
                if ($attempt < $retryAttempts - 1) {
                    usleep($retryInterval * 1000); // 毫秒转换为微秒
                }
            }
        }
        
        $this->stats['connection_failures']++;
        return null;
    }
    
    /**
     * 从连接池获取连接
     * @return mysqli|null 数据库连接对象
     */
    public function getConnection() {
        // 检查并清理过期连接
        $this->cleanExpiredConnections();
        
        // 查找空闲连接
        foreach ($this->connectionStatus as $index => $status) {
            if (!$status['in_use'] && $this->isConnectionValid($this->connections[$index])) {
                $this->connectionStatus[$index]['in_use'] = true;
                $this->connectionStatus[$index]['last_used'] = time();
                $this->stats['active_connections']++;
                $this->stats['idle_connections']--;
                return $this->connections[$index];
            }
        }
        
        // 如果没有空闲连接且未达到最大连接数，创建新连接
        if ($this->stats['total_connections'] < $this->config['pool']['max_connections']) {
            $connection = $this->createConnection();
            if ($connection) {
                $this->connections[] = $connection;
                $this->connectionStatus[] = [
                    'created_at' => time(),
                    'last_used' => time(),
                    'in_use' => true,
                ];
                $this->stats['total_connections']++;
                $this->stats['active_connections']++;
                return $connection;
            }
        }
        
        // 如果所有连接都在使用中，等待一小段时间后重试
        usleep(10000); // 10毫秒
        return $this->getConnection();
    }
    
    /**
     * 检查连接是否有效
     * @param mysqli $connection 数据库连接对象
     * @return bool 连接是否有效
     */
    private function isConnectionValid($connection) {
        try {
            return $connection->ping();
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * 归还连接到连接池
     * @param mysqli $connection 数据库连接对象
     */
    public function releaseConnection($connection) {
        foreach ($this->connections as $index => &$conn) {
            if ($conn === $connection) {
                $this->connectionStatus[$index]['in_use'] = false;
                $this->connectionStatus[$index]['last_used'] = time();
                $this->stats['active_connections']--;
                $this->stats['idle_connections']++;
                break;
            }
        }
    }
    
    /**
     * 关闭并移除连接
     * @param mysqli $connection 数据库连接对象
     */
    public function removeConnection($connection) {
        foreach ($this->connections as $index => &$conn) {
            if ($conn === $connection) {
                try {
                    $conn->close();
                } catch (Exception $e) {
                    // 忽略关闭异常
                }
                unset($this->connections[$index]);
                unset($this->connectionStatus[$index]);
                
                // 重新索引数组
                $this->connections = array_values($this->connections);
                $this->connectionStatus = array_values($this->connectionStatus);
                
                $this->stats['total_connections']--;
                if (isset($this->connectionStatus[$index]) && $this->connectionStatus[$index]['in_use']) {
                    $this->stats['active_connections']--;
                } else {
                    $this->stats['idle_connections']--;
                }
                break;
            }
        }
    }
    
    /**
     * 清理过期连接
     */
    private function cleanExpiredConnections() {
        $maxIdleTime = $this->config['pool']['max_idle_time'];
        $currentTime = time();
        
        foreach ($this->connectionStatus as $index => $status) {
            if (!$status['in_use'] && ($currentTime - $status['last_used'] > $maxIdleTime)) {
                // 只保留最小连接数
                if ($this->stats['idle_connections'] > $this->config['pool']['min_connections']) {
                    $this->removeConnection($this->connections[$index]);
                }
            }
        }
    }
    
    /**
     * 获取连接池统计信息
     * @return array 统计信息
     */
    public function getStats() {
        return $this->stats;
    }
    
    /**
     * 获取数据库配置
     * @return array 数据库配置
     */
    public function getConfig() {
        return $this->config;
    }
    
    /**
     * 关闭所有连接
     */
    public function closeAllConnections() {
        foreach ($this->connections as &$connection) {
            try {
                $connection->close();
            } catch (Exception $e) {
                // 忽略关闭异常
            }
        }
        
        $this->connections = [];
        $this->connectionStatus = [];
        $this->stats = [
            'total_connections' => 0,
            'active_connections' => 0,
            'idle_connections' => 0,
            'connection_attempts' => $this->stats['connection_attempts'],
            'connection_failures' => $this->stats['connection_failures'],
        ];
    }
    
    /**
     * 记录普通日志
     * @param string $message 日志消息
     */
    private function logMessage($message) {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] [INFO] $message\n";
        error_log($logMessage, 3, $this->config['error_log']);
    }
    
    /**
     * 记录错误日志
     * @param string $message 错误消息
     */
    private function logError($message) {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] [ERROR] $message\n";
        error_log($logMessage, 3, $this->config['error_log']);
    }
    
    /**
     * 析构函数
     */
    public function __destruct() {
        $this->closeAllConnections();
    }
}