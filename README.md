# 三角洲查询工具

<div align="center">

![PHP](https://img.shields.io/badge/PHP-777BB4?style=flat&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-4479A1?style=flat&logo=mysql&logoColor=white)
![License](https://img.shields.io/badge/License-MIT-green)

一个用于批量查询《三角洲行动》游戏账号信息的Web工具

[功能特性](#功能特性) • [快速开始](#快速开始) • [使用说明](#使用说明) • [项目结构](#项目结构)

</div>

## 功能特性

### 前台功能
- 📊 **批量查询** - 支持无上限数量的账号批量查询
- 🔍 **智能解析** - 自动提取access_token和openid
- 📋 **一键复制** - 快速复制查询结果
- 💾 **数据导出** - 支持查询结果下载

### 后台管理
- 📈 **数据仪表盘** - 账号统计、状态分布可视化
- 👥 **账号管理** - 搜索、筛选、排序、删除、导出
- 📝 **操作日志** - 完整的操作记录和日志导出
- 👤 **管理员管理** - 多管理员权限控制

### 账号信息展示
- 角色名称、角色编号、游戏等级
- 哈夫币、道具价值、仓库价值
- 在线状态、登录状态
- 禁言状态、封号状态
- 最后登录/登出时间

## 快速开始

### 环境要求

- PHP >= 7.0
- MySQL >= 5.6
- Web服务器 (Apache/Nginx)

### 安装步骤

1. **克隆项目**
```bash
git clone https://github.com/yourusername/sanjiaozhou-query-tool.git
cd sanjiaozhou-query-tool
```

2. **配置数据库**
```bash
# 复制配置文件示例
cp database/config.example.php database/config.php

# 编辑配置文件，填入数据库信息
```

3. **初始化数据库**
```bash
# 访问初始化页面
http://your-domain/init_database.php
```

4. **登录后台**
```
默认管理员账号: admin
默认管理员密码: admin123

⚠️ 请在首次登录后立即修改密码！
```

## 使用说明

### 前台查询

1. 访问首页 `index.php`
2. 在文本框中输入账号数据，格式为：
   ```
   access_token=xxx&openid=yyy
   ```
3. 支持批量输入，每行一个账号
4. 点击"批量查询"按钮

### 后台管理

1. 访问后台 `admin/login.php`
2. 使用管理员账号登录
3. 在仪表盘查看统计数据
4. 在账号管理中管理查询记录

## 项目结构

```
├── index.php              # 前台查询页面
├── config.php             # 应用配置
├── functions.php          # 核心函数库
├── batch_submit.php       # 批量查询处理
├── download.php           # 文件下载
├── init_database.php      # 数据库初始化脚本
│
├── admin/                 # 后台管理
│   ├── login.php          # 登录页面
│   ├── dashboard.php      # 仪表盘
│   ├── accounts.php       # 账号管理
│   ├── operation_logs.php # 操作日志
│   └── admin_management.php # 管理员管理
│
├── database/              # 数据库相关
│   ├── config.example.php # 配置文件示例
│   ├── Database.php       # 数据库操作类
│   ├── ConnectionPool.php # 连接池管理
│   └── schema.sql         # 数据库结构
│
├── services/              # 服务层
│   ├── DataProcessor.php  # 数据处理服务
│   └── SilentStorageService.php # 存储服务
│
├── css/                   # 样式文件
├── js/                    # JavaScript文件
├── downloads/             # 下载目录
└── logs/                  # 日志目录
```

## 技术栈

- **后端**: PHP
- **数据库**: MySQL
- **前端**: HTML/CSS/JavaScript
- **UI框架**: Tailwind CSS + Bootstrap Icons

## 数据库表结构

| 表名 | 说明 |
|------|------|
| `account_raw_data` | 账号原始数据 |
| `account_status_data` | 账号状态数据 |
| `operation_logs` | 操作日志 |
| `admins` | 管理员账户 |

## 许可证

本项目基于 [MIT License](LICENSE) 开源。

## 免责声明

本工具仅供学习和研究使用，请勿用于任何商业用途。使用本工具产生的一切后果由使用者自行承担。

## 致谢

感谢所有为本项目做出贡献的开发者。

---

<div align="center">

**如果觉得这个项目有帮助，请给一个 ⭐ Star！**

</div>
