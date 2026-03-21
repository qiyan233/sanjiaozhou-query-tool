<?php
/**
 * 静默数据存储服务类
 * 实现数据接收、验证、清洗和存储功能
 */

require_once __DIR__ . '/DataProcessor.php';
require_once __DIR__ . '/../database/ConnectionPool.php';
require_once __DIR__ . '/../database/Database.php';

class SilentStorageService {
    // 数据处理器实例
    private $dataProcessor;
    // 数据库操作实例
    private $database;
    // 配置参数
    private $config;
    
    /**
     * 构造函数
     */
    public function __construct() {
        $this->dataProcessor = new DataProcessor();
        $this->database = new Database();
        $this->config = [
            'max_batch_size' => 100, // 最大批处理数量
            'transaction_timeout' => 30, // 事务超时时间（秒）
            'log_errors' => true // 是否记录错误
        ];
    }
    
    /**
     * 接收并处理原始账号数据
     * @param string $callbackData 回调数据
     * @return array 处理结果
     */
    public function receiveAndStoreRawData($callbackData) {
        try {
            // 提取access_token和openid
            $extractedInfo = $this->dataProcessor->extractTokenAndOpenId($callbackData);
            
            if (!$extractedInfo) {
                $errors = $this->dataProcessor->getErrors();
                $errorMsg = implode(', ', $errors);
                $this->logError("原始数据验证失败: {$errorMsg}", $callbackData);
                return [
                    'status' => 'error',
                    'message' => "数据验证失败",
                    'errors' => $errors
                ];
            }
            
            // 结构化数据
            $structuredData = $this->dataProcessor->structureRawData(
                $callbackData,
                $extractedInfo['access_token'],
                $extractedInfo['openid']
            );
            
            // 检查是否已存在相同的access_token和openid
            $existing = $this->database->selectOne(
                'account_raw_data',
                ['id'],
                ['access_token' => $extractedInfo['access_token'], 'openid' => $extractedInfo['openid']]
            );
            
            $accountId = null;
            
            // 开始事务
            $this->database->beginTransaction();
            
            try {
                if ($existing) {
                    // 更新现有数据
                    $this->database->update(
                        'account_raw_data',
                        ['raw_data' => $structuredData['raw_data'], 'updated_at' => $structuredData['created_at']],
                        ['id' => $existing['id']]
                    );
                    $accountId = $existing['id'];
                } else {
                    // 插入新数据
                    $accountId = $this->database->insert('account_raw_data', $structuredData);
                }
                
                // 记录操作日志
                $this->logOperation('raw_data_received', 'system', $_SERVER['REMOTE_ADDR'] ?? 'unknown', 
                    "接收到账号数据: access_token={$extractedInfo['access_token']}, openid={$extractedInfo['openid']}");
                
                // 提交事务
                $this->database->commit();
                
                $this->dataProcessor->logProcessing("成功存储原始账号数据，ID: {$accountId}", 'info');
                
                return [
                    'status' => 'success',
                    'message' => "数据存储成功",
                    'account_id' => $accountId
                ];
            } catch (Exception $e) {
                // 回滚事务
                $this->database->rollback();
                throw $e;
            }
        } catch (Exception $e) {
            $errorMsg = "存储原始数据时出错: " . $e->getMessage();
            $this->logError($errorMsg, $callbackData);
            return [
                'status' => 'error',
                'message' => "数据处理失败",
                'error_details' => $errorMsg
            ];
        }
    }
    
    /**
     * 接收并处理账号状态数据
     * @param string $statusData 状态数据
     * @param int $accountId 账号ID
     * @return array 处理结果
     */
    public function receiveAndStoreStatusData($statusData, $accountId) {
        try {
            // 验证accountId
            if (!is_numeric($accountId) || $accountId <= 0) {
                $errorMsg = "无效的账号ID: {$accountId}";
                $this->logError($errorMsg, $statusData);
                return [
                    'status' => 'error',
                    'message' => $errorMsg
                ];
            }
            
            // 检查账号是否存在
            $accountExists = $this->database->selectOne(
                'account_raw_data',
                ['id'],
                ['id' => $accountId]
            );
            
            if (!$accountExists) {
                $errorMsg = "账号ID不存在: {$accountId}";
                $this->logError($errorMsg, $statusData);
                return [
                    'status' => 'error',
                    'message' => $errorMsg
                ];
            }
            
            // 结构化数据
            $structuredData = $this->dataProcessor->structureStatusData($statusData, $accountId);
            
            // 开始事务
            $this->database->beginTransaction();
            
            try {
                // 检查是否已存在状态数据
                $existingStatus = $this->database->selectOne(
                    'account_status_data',
                    ['id'],
                    ['account_id' => $accountId]
                );
                
                if ($existingStatus) {
                    // 更新现有状态数据
                    $this->database->update(
                        'account_status_data',
                        ['status_data' => $structuredData['status_data'], 
                         'status' => $structuredData['status'], 
                         'updated_at' => $structuredData['created_at']],
                        ['id' => $existingStatus['id']]
                    );
                    $statusId = $existingStatus['id'];
                } else {
                    // 插入新状态数据
                    $statusId = $this->database->insert('account_status_data', $structuredData);
                }
                
                // 记录操作日志
                $this->logOperation('status_data_received', 'system', $_SERVER['REMOTE_ADDR'] ?? 'unknown', 
                    "接收到账号状态数据: account_id={$accountId}, status={$structuredData['status']}");
                
                // 提交事务
                $this->database->commit();
                
                $this->dataProcessor->logProcessing("成功存储账号状态数据，ID: {$statusId}", 'info');
                
                return [
                    'status' => 'success',
                    'message' => "状态数据存储成功",
                    'status_id' => $statusId,
                    'status' => $structuredData['status']
                ];
            } catch (Exception $e) {
                // 回滚事务
                $this->database->rollback();
                throw $e;
            }
        } catch (Exception $e) {
            $errorMsg = "存储状态数据时出错: " . $e->getMessage();
            $this->logError($errorMsg, $statusData);
            return [
                'status' => 'error',
                'message' => "状态数据处理失败",
                'error_details' => $errorMsg
            ];
        }
    }
    
    /**
     * 批量处理账号数据
     * @param array $dataList 数据列表
     * @return array 处理结果
     */
    public function batchProcessAndStore($dataList) {
        // 验证批处理大小
        if (count($dataList) > $this->config['max_batch_size']) {
            return [
                'status' => 'error',
                'message' => "批量处理数量超出限制",
                'max_batch_size' => $this->config['max_batch_size']
            ];
        }
        
        try {
            // 使用数据处理器进行批处理
            $processResults = $this->dataProcessor->processBatchData($dataList);
            
            // 开始事务
            $this->database->beginTransaction();
            
            try {
                // 处理成功的数据
                foreach ($processResults['success'] as $item) {
                    $structuredData = $item['data'];
                    
                    // 检查是否已存在
                    $existing = $this->database->selectOne(
                        'account_raw_data',
                        ['id'],
                        ['access_token' => $structuredData['access_token'], 'openid' => $structuredData['openid']]
                    );
                    
                    if ($existing) {
                        $this->database->update(
                            'account_raw_data',
                            ['raw_data' => $structuredData['raw_data'], 'updated_at' => $structuredData['created_at']],
                            ['id' => $existing['id']]
                        );
                    } else {
                        $this->database->insert('account_raw_data', $structuredData);
                    }
                }
                
                // 提交事务
                $this->database->commit();
                
                // 记录批处理日志
                $this->logOperation('batch_data_processed', 'system', $_SERVER['REMOTE_ADDR'] ?? 'unknown', 
                    "批量处理数据完成，成功: {$processResults['success_count']}, 失败: {$processResults['failed_count']}");
                
                $this->dataProcessor->logProcessing("批量处理完成，总数: {$processResults['total']}, 成功: {$processResults['success_count']}", 'info');
                
                return [
                    'status' => 'success',
                    'message' => "批量处理完成",
                    'results' => $processResults
                ];
            } catch (Exception $e) {
                // 回滚事务
                $this->database->rollback();
                throw $e;
            }
        } catch (Exception $e) {
            $errorMsg = "批量处理时出错: " . $e->getMessage();
            $this->logError($errorMsg, json_encode($dataList));
            return [
                'status' => 'error',
                'message' => "批量处理失败",
                'error_details' => $errorMsg
            ];
        }
    }
    
    /**
     * 记录操作日志
     * @param string $operationType 操作类型
     * @param string $operator 操作者
     * @param string $ipAddress IP地址
     * @param string $description 描述
     */
    private function logOperation($operationType, $operator, $ipAddress, $description) {
        $logData = [
            'operation_type' => $operationType,
            'operator' => $operator,
            'ip_address' => $ipAddress,
            'description' => $description
        ];
        
        try {
            $this->database->insert('operation_logs', $logData);
        } catch (Exception $e) {
            // 如果日志记录失败，记录到文件日志
            $this->dataProcessor->logProcessing(
                "数据库日志记录失败: " . $e->getMessage() . ", 日志内容: " . json_encode($logData),
                'error'
            );
        }
    }
    
    /**
     * 记录错误日志
     * @param string $message 错误消息
     * @param mixed $data 相关数据
     */
    private function logError($message, $data) {
        if ($this->config['log_errors']) {
            $dataStr = is_string($data) ? $data : json_encode($data);
            $this->dataProcessor->logProcessing(
                "{$message}, 数据: {$dataStr}",
                'error'
            );
        }
    }
    
    /**
     * 设置配置
     * @param array $config 配置数组
     */
    public function setConfig($config) {
        $this->config = array_merge($this->config, $config);
    }
    
    /**
     * 获取系统统计信息
     * @return array 统计信息
     */
    public function getStatistics() {
        try {
            $totalAccounts = $this->database->selectOne(
                'account_raw_data',
                ['COUNT(*) as count']
            )['count'];
            
            $totalStatusRecords = $this->database->selectOne(
                'account_status_data',
                ['COUNT(*) as count']
            )['count'];
            
            $statusDistribution = $this->database->select(
                'account_status_data',
                ['status', 'COUNT(*) as count'],
                [],
                ['status']
            );
            
            $recentLogs = $this->database->select(
                'operation_logs',
                ['*'],
                [],
                [],
                ['created_at' => 'DESC'],
                10
            );
            
            return [
                'total_accounts' => $totalAccounts,
                'total_status_records' => $totalStatusRecords,
                'status_distribution' => $statusDistribution,
                'recent_logs' => $recentLogs,
                'status' => 'success'
            ];
        } catch (Exception $e) {
            $this->logError("获取统计信息失败: " . $e->getMessage(), []);
            return [
                'status' => 'error',
                'message' => "获取统计信息失败",
                'error_details' => $e->getMessage()
            ];
        }
    }
}