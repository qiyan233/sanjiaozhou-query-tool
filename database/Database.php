<?php
/**
 * 数据库操作类
 * 封装常用的数据库操作方法
 */

require_once __DIR__ . '/ConnectionPool.php';

class Database {
    // 连接池实例
    private $pool;
    
    // 当前使用的连接
    private $connection = null;
    
    /**
     * 构造函数
     */
    public function __construct() {
        $this->pool = ConnectionPool::getInstance();
    }
    
    /**
     * 获取数据库连接
     * @return mysqli 数据库连接对象
     */
    private function getConnection() {
        if ($this->connection === null) {
            $this->connection = $this->pool->getConnection();
        }
        return $this->connection;
    }
    
    /**
     * 释放数据库连接
     */
    private function releaseConnection() {
        if ($this->connection !== null) {
            $this->pool->releaseConnection($this->connection);
            $this->connection = null;
        }
    }
    
    /**
     * 执行SQL查询
     * @param string $sql SQL查询语句
     * @param array $params 参数数组
     * @param string $types 参数类型字符串
     * @return mysqli_result|bool 查询结果
     */
    public function query($sql, $params = [], $types = '') {
        $connection = $this->getConnection();
        
        try {
            // 如果有参数，使用预处理语句
            if (!empty($params)) {
                $stmt = $connection->prepare($sql);
                if (!$stmt) {
                    throw new Exception("预处理语句失败: " . $connection->error);
                }
                
                // 如果没有指定参数类型，自动检测
                if (empty($types)) {
                    $types = $this->detectParamTypes($params);
                }
                
                // 绑定参数
                $stmt->bind_param($types, ...$params);
                
                // 执行语句
                if (!$stmt->execute()) {
                    throw new Exception("查询执行失败: " . $stmt->error);
                }
                
                // 获取结果
                $result = $stmt->get_result();
                $stmt->close();
                
                return $result;
            } else {
                // 直接执行SQL
                $result = $connection->query($sql);
                if (!$result && $connection->error) {
                    throw new Exception("查询执行失败: " . $connection->error);
                }
                return $result;
            }
        } catch (Exception $e) {
            // 记录错误
            $errorMsg = "查询错误: " . $e->getMessage() . "\nSQL: $sql";
            error_log($errorMsg, 3, $this->pool->getConfig()['error_log'] ?? __DIR__ . '/../logs/db_error.log');
            
            // 检查连接是否仍然有效
            if (!$connection->ping()) {
                // 连接已断开，移除并获取新连接
                $this->pool->removeConnection($connection);
                $this->connection = null;
            }
            
            throw $e;
        } finally {
            // 释放连接
            $this->releaseConnection();
        }
    }
    
    /**
     * 执行SQL查询并返回所有结果
     * @param string $sql SQL查询语句
     * @param array $params 参数数组
     * @param string $types 参数类型字符串
     * @return array 查询结果数组
     */
    public function fetchAll($sql, $params = [], $types = '') {
        $result = $this->query($sql, $params, $types);
        
        if (!$result || is_bool($result)) {
            return [];
        }
        
        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
        
        $result->free();
        return $rows;
    }
    
    /**
     * 执行SQL查询并返回单行结果
     * @param string $sql SQL查询语句
     * @param array $params 参数数组
     * @param string $types 参数类型字符串
     * @return array|null 查询结果行
     */
    public function fetchOne($sql, $params = [], $types = '') {
        $result = $this->query($sql, $params, $types);
        
        if (!$result || is_bool($result)) {
            return null;
        }
        
        $row = $result->fetch_assoc();
        $result->free();
        return $row;
    }
    
    /**
     * 执行SQL查询并返回单列结果
     * @param string $sql SQL查询语句
     * @param array $params 参数数组
     * @param string $types 参数类型字符串
     * @return array 单列结果数组
     */
    public function fetchColumn($sql, $params = [], $types = '') {
        $result = $this->query($sql, $params, $types);
        
        if (!$result || is_bool($result)) {
            return [];
        }
        
        $column = [];
        while ($row = $result->fetch_row()) {
            $column[] = $row[0];
        }
        
        $result->free();
        return $column;
    }
    
    /**
     * 从表中查询多条记录
     * @param string $table 表名
     * @param array $fields 要查询的字段
     * @param array $where 条件数组
     * @param array $groupBy 分组字段
     * @param array $orderBy 排序字段
     * @param int $limit 限制数量
     * @param int $offset 偏移量
     * @return array 查询结果数组
     */
    public function select($table, $fields = ['*'], $where = [], $groupBy = [], $orderBy = [], $limit = 0, $offset = 0) {
        // 构建字段列表
        $fieldList = implode(', ', $fields);
        
        // 构建SQL语句
        $sql = "SELECT {$fieldList} FROM {$table}";
        $params = [];
        $types = '';
        
        // 添加WHERE条件
        if (!empty($where)) {
            $conditions = [];
            foreach ($where as $field => $value) {
                // 处理大于、小于等条件
                if (strpos($field, '>=') !== false || strpos($field, '<=') !== false || 
                    strpos($field, '>') !== false || strpos($field, '<') !== false) {
                    // 如果字段名包含比较运算符
                    $conditions[] = "{$field} ?";
                } else {
                    $conditions[] = "{$field} = ?";
                }
                $params[] = $value;
            }
            $sql .= " WHERE " . implode(' AND ', $conditions);
        }
        
        // 添加GROUP BY
        if (!empty($groupBy)) {
            $groupByStr = implode(', ', $groupBy);
            $sql .= " GROUP BY {$groupByStr}";
        }
        
        // 添加ORDER BY
        if (!empty($orderBy)) {
            $orderClauses = [];
            foreach ($orderBy as $field => $direction) {
                $orderClauses[] = "{$field} {$direction}";
            }
            $orderClause = implode(', ', $orderClauses);
            $sql .= " ORDER BY {$orderClause}";
        }
        
        // 添加LIMIT和OFFSET
        if ($limit > 0) {
            $sql .= " LIMIT {$limit}";
            if ($offset > 0) {
                $sql .= " OFFSET {$offset}";
            }
        }
        
        // 调用fetchAll方法执行查询
        return $this->fetchAll($sql, $params, $types);
    }
    
    /**
     * 从表中查询单条记录
     * @param string $table 表名
     * @param array $fields 要查询的字段
     * @param array $where 条件数组
     * @return array|null 查询结果
     */
    public function selectOne($table, $fields = ['*'], $where = []) {
        // 构建字段列表
        $fieldList = implode(', ', $fields);
        
        // 构建SQL语句
        $sql = "SELECT {$fieldList} FROM {$table}";
        $params = [];
        $types = '';
        
        // 添加WHERE条件
        if (!empty($where)) {
            $conditions = [];
            foreach ($where as $field => $value) {
                $conditions[] = "{$field} = ?";
                $params[] = $value;
            }
            $sql .= " WHERE " . implode(' AND ', $conditions);
        }
        
        // 添加LIMIT
        $sql .= " LIMIT 1";
        
        // 调用fetchOne方法执行查询
        return $this->fetchOne($sql, $params, $types);
    }
    
    /**
     * 执行插入操作
     * @param string $table 表名
     * @param array $data 数据数组
     * @return int 插入的记录ID
     */
    public function insert($table, $data) {
        $fields = array_keys($data);
        $values = array_values($data);
        $placeholders = implode(', ', array_fill(0, count($fields), '?'));
        $fieldList = implode(', ', $fields);
        
        $sql = "INSERT INTO {$table} ({$fieldList}) VALUES ({$placeholders})";
        
        $connection = $this->getConnection();
        try {
            $stmt = $connection->prepare($sql);
            if (!$stmt) {
                throw new Exception("预处理语句失败: " . $connection->error);
            }
            
            $types = $this->detectParamTypes($values);
            $stmt->bind_param($types, ...$values);
            
            if (!$stmt->execute()) {
                throw new Exception("插入失败: " . $stmt->error);
            }
            
            $insertId = $stmt->insert_id;
            $stmt->close();
            
            return $insertId;
        } catch (Exception $e) {
            // 记录错误
            $errorMsg = "插入错误: " . $e->getMessage() . "\n表: $table";
            error_log($errorMsg, 3, $this->pool->getConfig()['error_log'] ?? __DIR__ . '/../logs/db_error.log');
            
            // 检查连接是否仍然有效
            if (!$connection->ping()) {
                $this->pool->removeConnection($connection);
                $this->connection = null;
            }
            
            throw $e;
        } finally {
            $this->releaseConnection();
        }
    }
    
    /**
     * 执行更新操作
     * @param string $table 表名
     * @param array $data 数据数组
     * @param array $where 条件数组
     * @return int 受影响的行数
     */
    public function update($table, $data, $where = []) {
        $setClauses = [];
        $params = [];
        
        foreach ($data as $field => $value) {
            $setClauses[] = "{$field} = ?";
            $params[] = $value;
        }
        
        $whereClauses = [];
        foreach ($where as $field => $value) {
            $whereClauses[] = "{$field} = ?";
            $params[] = $value;
        }
        
        $setClause = implode(', ', $setClauses);
        $whereClause = !empty($whereClauses) ? "WHERE " . implode(' AND ', $whereClauses) : '';
        
        $sql = "UPDATE {$table} SET {$setClause} {$whereClause}";
        
        $connection = $this->getConnection();
        try {
            $stmt = $connection->prepare($sql);
            if (!$stmt) {
                throw new Exception("预处理语句失败: " . $connection->error);
            }
            
            $types = $this->detectParamTypes($params);
            $stmt->bind_param($types, ...$params);
            
            if (!$stmt->execute()) {
                throw new Exception("更新失败: " . $stmt->error);
            }
            
            $affectedRows = $stmt->affected_rows;
            $stmt->close();
            
            return $affectedRows;
        } catch (Exception $e) {
            // 记录错误
            $errorMsg = "更新错误: " . $e->getMessage() . "\n表: $table";
            error_log($errorMsg, 3, $this->pool->getConfig()['error_log'] ?? __DIR__ . '/../logs/db_error.log');
            
            // 检查连接是否仍然有效
            if (!$connection->ping()) {
                $this->pool->removeConnection($connection);
                $this->connection = null;
            }
            
            throw $e;
        } finally {
            $this->releaseConnection();
        }
    }
    
    /**
     * 执行删除操作
     * @param string $table 表名
     * @param array $where 条件数组
     * @return int 受影响的行数
     */
    public function delete($table, $where = []) {
        $whereClauses = [];
        $params = [];
        
        foreach ($where as $field => $value) {
            $whereClauses[] = "{$field} = ?";
            $params[] = $value;
        }
        
        $whereClause = !empty($whereClauses) ? "WHERE " . implode(' AND ', $whereClauses) : '';
        
        $sql = "DELETE FROM {$table} {$whereClause}";
        
        $connection = $this->getConnection();
        try {
            $stmt = $connection->prepare($sql);
            if (!$stmt) {
                throw new Exception("预处理语句失败: " . $connection->error);
            }
            
            if (!empty($params)) {
                $types = $this->detectParamTypes($params);
                $stmt->bind_param($types, ...$params);
            }
            
            if (!$stmt->execute()) {
                throw new Exception("删除失败: " . $stmt->error);
            }
            
            $affectedRows = $stmt->affected_rows;
            $stmt->close();
            
            return $affectedRows;
        } catch (Exception $e) {
            // 记录错误
            $errorMsg = "删除错误: " . $e->getMessage() . "\n表: $table";
            error_log($errorMsg, 3, $this->pool->getConfig()['error_log'] ?? __DIR__ . '/../logs/db_error.log');
            
            // 检查连接是否仍然有效
            if (!$connection->ping()) {
                $this->pool->removeConnection($connection);
                $this->connection = null;
            }
            
            throw $e;
        } finally {
            $this->releaseConnection();
        }
    }
    
    /**
     * 自动检测参数类型
     * @param array $params 参数数组
     * @return string 参数类型字符串
     */
    private function detectParamTypes($params) {
        $types = '';
        foreach ($params as $param) {
            if (is_int($param)) {
                $types .= 'i';
            } elseif (is_float($param)) {
                $types .= 'd';
            } elseif (is_string($param)) {
                $types .= 's';
            } else {
                $types .= 'b';
            }
        }
        return $types;
    }
    
    /**
     * 开始事务
     */
    public function beginTransaction() {
        $connection = $this->getConnection();
        return $connection->begin_transaction();
    }
    
    /**
     * 提交事务
     */
    public function commit() {
        try {
            $connection = $this->getConnection();
            return $connection->commit();
        } finally {
            $this->releaseConnection();
        }
    }
    
    /**
     * 回滚事务
     */
    public function rollback() {
        try {
            $connection = $this->getConnection();
            return $connection->rollback();
        } finally {
            $this->releaseConnection();
        }
    }
    
    /**
     * 获取连接池统计信息
     * @return array 统计信息
     */
    public function getStats() {
        return $this->pool->getStats();
    }
    
    /**
     * 清理连接池
     */
    public function cleanup() {
        $this->pool->closeAllConnections();
    }
    
    /**
     * 转义字符串，防止SQL注入
     * @param string $str 要转义的字符串
     * @return string 转义后的字符串
     */
    public function quote($str) {
        $connection = $this->getConnection();
        try {
            return "'" . $connection->real_escape_string($str) . "'";
        } finally {
            $this->releaseConnection();
        }
    }
}