<div align="center">

# 🌟 BKCS - 多功能个人门户系统
### (Personal Portal System)

一个基于原生 PHP 开发的轻量级、现代化个人综合门户。
集成了博客、相册、聊天室、恋爱空间及 AI 能力，内置 WAF 防护与 Redis 高性能缓存。

[![PHP](https://img.shields.io/badge/PHP-7.4%2B-777BB4?style=flat-square&logo=php&logoColor=white)](https://www.php.net/)
[![MySQL](https://img.shields.io/badge/MySQL-5.7%2B-4479A1?style=flat-square&logo=mysql&logoColor=white)](https://www.mysql.com/)
[![Redis](https://img.shields.io/badge/Redis-Enabled-DC382D?style=flat-square&logo=redis&logoColor=white)](https://redis.io/)
[![License](https://img.shields.io/badge/License-MIT-green?style=flat-square)](LICENSE)

[🖥️ 查看前台演示](https://gz.jx1314.cc/) &nbsp;&nbsp;|&nbsp;&nbsp; [🐞 提交 Issue](https://github.com/你的用户名/仓库名/issues)

</div>

---

## 📸 界面概览 (Screenshots)

### 后台仪表盘
![Admin Dashboard](https://github.com/user-attachments/assets/b8226002-c781-4356-ab25-e24c5e3f173c)
*(极简主义设计，集成资源监控与数据可视化)*

---

## ✨ 主要功能 (Features)

### 🖥️ 前台体验 (Frontend)
- **📝 内容管理**：沉浸式文章阅读，支持分类浏览与 `Prism.js` 代码高亮。
- **🎨 多媒体中心**：
  - **相册 (Gallery)**：采用瀑布流布局，优雅展示摄影作品。
  - **音乐 (Music)**：全站悬浮播放器，支持歌单管理。
- **💬 互动社区**：
  - **聊天室 (Chatroom)**：基于轮询/WebSocket 的实时交流大厅。
  - **许愿墙 (Wishes)**：访客留言与祝福互动。
- **💖 特色功能**：
  - **恋爱空间**：记录相恋天数、纪念日与甜蜜日志。
  - **AI 实验室**：内置 AI 内容生成接口，支持自动写作辅助。

### ⚙️ 后台管理 (Backend)
- **📊 仪表盘**：服务器负载 (CPU/RAM)、流量统计图表一目了然。
- **✍️ 创作工具**：集成 `WangEditor` 富文本编辑器，所见即所得。
- **🛡️ 安全中心**：内置 **WAF 防火墙**，实时拦截 SQL 注入与恶意请求，记录安全日志。
- **🔧 系统配置**：全站参数热更新，无需修改代码。

### 🚀 核心技术 (Tech Stack)
- **架构**：纯原生 PHP + MySQL，无臃肿框架，轻量且易于二次开发。
- **性能**：底层集成 **Redis** 缓存驱动 (`includes/redis_helper.php`)，秒级响应。
- **存储**：支持本地存储及 **腾讯云 COS** 对象存储 (`includes/cos_helper.php`)。

---

## 🛠️ 环境要求 (Requirements)

| 组件 | 要求 | 备注 |
| :--- | :--- | :--- |
| **OS** | Linux / Windows | 推荐 Debian/Ubuntu |
| **Web Server** | Nginx / Apache | 需配置伪静态 |
| **PHP** | 7.4 - 8.2 | 扩展: `redis`, `gd`, `mysqli`, `curl`, `fileinfo` |
| **Database** | MySQL 5.7+ | 推荐 utf8mb4 编码 |

---

## 🚀 快速部署 (Installation)

### 1. 上传文件
将项目所有文件上传至网站根目录，并赋予以下目录写入权限 (`755` 或 `777`)：
- `/install/`
- `/assets/uploads/`
- `/user.ini` (如果存在)

### 2. 运行安装向导
访问 `http://你的域名/install/`，按照页面提示填写数据库信息并完成安装。

### 3. 配置伪静态 (Nginx) **[重要]**
为了确保路由正常工作，请在 Nginx 配置文件 (`server` 块内) 添加以下规则：

```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}
