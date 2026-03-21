-- 数据库表结构初始化脚本

-- 创建账号原始数据表
CREATE TABLE IF NOT EXISTS `account_raw_data` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `access_token` VARCHAR(255) NOT NULL,
  `openid` VARCHAR(255) NOT NULL,
  `raw_data` TEXT NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `unique_token_openid` (`access_token`, `openid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 创建账号状态数据表
CREATE TABLE IF NOT EXISTS `account_status_data` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `account_id` INT NOT NULL,
  `status_data` TEXT NOT NULL,
  `status` VARCHAR(50) DEFAULT 'unknown',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`account_id`) REFERENCES `account_raw_data`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 创建操作日志表
CREATE TABLE IF NOT EXISTS `operation_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `operation_type` VARCHAR(50) NOT NULL,
  `operator` VARCHAR(100) DEFAULT 'system',
  `ip_address` VARCHAR(50),
  `description` TEXT,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 创建管理员表
CREATE TABLE IF NOT EXISTS `admins` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(100) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `role` VARCHAR(50) DEFAULT 'user',
  `status` TINYINT DEFAULT 1,
  `last_login_at` DATETIME,
  `last_login_ip` VARCHAR(50),
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 创建索引以提高查询性能
CREATE INDEX `idx_account_raw_created_at` ON `account_raw_data` (`created_at`);
CREATE INDEX `idx_account_status_created_at` ON `account_status_data` (`created_at`);
CREATE INDEX `idx_operation_logs_created_at` ON `operation_logs` (`created_at`);
CREATE INDEX `idx_operation_logs_type` ON `operation_logs` (`operation_type`);

-- 系统设置表已移除