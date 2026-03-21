<?php
/**
 * 数据处理服务类
 * 实现数据验证、清洗和结构化处理功能
 */

class DataProcessor {
    // 错误信息
    private $errors = [];
    
    /**
     * 验证access_token和openid格式
     * @param string $accessToken access_token
     * @param string $openId openid
     * @return bool 是否有效
     */
    public function validateTokenAndOpenId($accessToken, $openId) {
        $this->errors = [];
        
        if (empty($accessToken) || !is_string($accessToken)) {
            $this->errors[] = "access_token不能为空且必须是字符串";
            return false;
        }
        
        if (empty($openId) || !is_string($openId)) {
            $this->errors[] = "openid不能为空且必须是字符串";
            return false;
        }
        
        // 验证access_token格式（通常为字母数字组合）
        if (!preg_match('/^[a-zA-Z0-9_\-\.]+$/', $accessToken)) {
            $this->errors[] = "access_token格式无效";
            return false;
        }
        
        // 验证openid格式
        if (!preg_match('/^[a-zA-Z0-9_\-\.]+$/', $openId)) {
            $this->errors[] = "openid格式无效";
            return false;
        }
        
        return true;
    }
    
    /**
     * 从回调数据中提取access_token和openid
     * @param string $callbackData 回调数据
     * @return array|null 提取的信息数组或null
     */
    public function extractTokenAndOpenId($callbackData) {
        if (!preg_match('/access_token=([^&]+)/', $callbackData, $tokenMatch) ||
            !preg_match('/openid=([^&]+)/', $callbackData, $openidMatch)) {
            $this->errors[] = "无法从回调数据中提取access_token和openid";
            return null;
        }
        
        $accessToken = $tokenMatch[1];
        $openId = $openidMatch[1];
        
        // 解码URL编码的参数
        $accessToken = urldecode($accessToken);
        $openId = urldecode($openId);
        
        // 验证提取的参数
        if (!$this->validateTokenAndOpenId($accessToken, $openId)) {
            return null;
        }
        
        return [
            'access_token' => $accessToken,
            'openid' => $openId
        ];
    }
    
    /**
     * 清洗数据，去除无效字符和多余空格
     * @param string $data 待清洗的数据
     * @return string 清洗后的数据
     */
    public function cleanData($data) {
        // 去除首尾空格
        $data = trim($data);
        
        // 去除控制字符（保留换行符）
        $data = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $data);
        
        // 规范化换行符
        $data = preg_replace('/\r\n|\r/', '\n', $data);
        
        // 去除连续的空行
        $data = preg_replace('/\n\s*\n\s*\n/', "\n\n", $data);
        
        return $data;
    }
    
    /**
     * 结构化处理原始账号数据
     * @param string $rawData 原始数据
     * @param string $accessToken access_token
     * @param string $openId openid
     * @return array 结构化的数据
     */
    public function structureRawData($rawData, $accessToken, $openId) {
        $cleanData = $this->cleanData($rawData);
        
        return [
            'access_token' => $accessToken,
            'openid' => $openId,
            'raw_data' => $cleanData,
            'created_at' => date('Y-m-d H:i:s')
        ];
    }
    
    /**
     * 结构化处理账号状态数据
     * @param string $statusData 状态数据
     * @param int $accountId 账号ID
     * @return array 结构化的数据
     */
    public function structureStatusData($statusData, $accountId) {
        $cleanData = $this->cleanData($statusData);
        
        // 尝试从状态数据中提取状态信息
        $status = $this->extractStatus($cleanData);
        
        return [
            'account_id' => $accountId,
            'status_data' => $cleanData,
            'status' => $status,
            'created_at' => date('Y-m-d H:i:s')
        ];
    }
    
    /**
     * 从状态数据中提取状态信息
     * @param string $statusData 状态数据
     * @return string 状态信息
     */
    private function extractStatus($statusData) {
        // 这里可以根据实际的数据格式实现状态提取逻辑
        // 例如，从文本中识别特定关键词
        
        $statusKeywords = [
            '正常' => ['normal', '正常', 'active'],
            '异常' => ['error', '异常', 'invalid', 'failed'],
            '冻结' => ['frozen', '冻结', 'locked'],
            '过期' => ['expired', '过期']
        ];
        
        foreach ($statusKeywords as $status => $keywords) {
            foreach ($keywords as $keyword) {
                if (stripos($statusData, $keyword) !== false) {
                    return $status;
                }
            }
        }
        
        return 'unknown';
    }
    
    /**
     * 验证数据完整性
     * @param array $data 待验证的数据
     * @param array $requiredFields 必需字段列表
     * @return bool 是否完整
     */
    public function validateDataIntegrity($data, $requiredFields) {
        $this->errors = [];
        
        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                $this->errors[] = "必需字段 '{$field}' 缺失或为空";
            }
        }
        
        return empty($this->errors);
    }
    
    /**
     * 批量处理账号数据
     * @param array $dataList 数据列表
     * @return array 处理结果
     */
    public function processBatchData($dataList) {
        $results = [
            'success' => [],
            'failed' => [],
            'total' => count($dataList),
            'success_count' => 0,
            'failed_count' => 0
        ];
        
        foreach ($dataList as $index => $data) {
            $extracted = $this->extractTokenAndOpenId($data);
            
            if ($extracted) {
                $structured = $this->structureRawData(
                    $data,
                    $extracted['access_token'],
                    $extracted['openid']
                );
                
                $results['success'][] = [
                    'index' => $index,
                    'data' => $structured
                ];
                $results['success_count']++;
            } else {
                $results['failed'][] = [
                    'index' => $index,
                    'data' => $data,
                    'errors' => $this->errors
                ];
                $results['failed_count']++;
            }
        }
        
        return $results;
    }
    
    /**
     * 获取错误信息
     * @return array 错误信息数组
     */
    public function getErrors() {
        return $this->errors;
    }
    
    /**
     * 记录处理日志
     * @param string $message 日志消息
     * @param string $level 日志级别
     */
    public function logProcessing($message, $level = 'info') {
        $logDir = __DIR__ . '/../logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        $logFile = $logDir . '/data_processing.log';
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] [$level] $message\n";
        
        error_log($logMessage, 3, $logFile);
    }
}