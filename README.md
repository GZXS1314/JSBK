# BKCS - 多功能个人门户系统 (Personal Portal System)

这是一个基于原生 PHP 开发的轻量级、多功能个人门户系统。集成了博客管理、相册展示、在线聊天、恋爱空间记录以及音乐播放等功能。系统内置了安全防护（WAF）、Redis 缓存支持以及对象存储（COS）集成。
前端演示站：https://gz.jx1314.cc/
后端演示图片：
![首页截图](https://img.cdn1.vip/i/6996fd3750c44_1771502903.webp)
## ✨ 主要功能 (Features)

### 🖥️ 前台功能
- **内容管理**：文章阅读、分类浏览、代码高亮 (Prism.js)。
- **多媒体中心**：
  - **相册 (Album)**：瀑布流展示照片。
  - **音乐播放器 (Music)**：全站音乐播放支持。
- **互动社区**：
  - **聊天室 (Chatroom)**：支持实时交流。
  - **许愿墙 (Wishes)**：访客留言互动。
  - **友链 (Friends)**：友情链接展示。
- **特色功能**：
  - **恋爱空间 (Love)**：记录纪念日与甜蜜时刻。
  - **AI 集成**：内置 AI 内容生成接口 (`api/ai_generate.php`)。

### ⚙️ 后台管理 (Admin Dashboard)
- **仪表盘**：直观的数据概览。
- **内容发布**：集成 WangEditor 富文本编辑器。
- **文件管理**：支持图片上传与管理。
- **系统设置**：全局配置、用户管理、安全日志查看。

### 🛡️ 核心技术与安全
- **架构**：纯 PHP + MySQL，结构清晰，易于二开。
- **性能优化**：内置 Redis 缓存支持 (`includes/redis_helper.php`)。
- **存储扩展**：支持腾讯云 COS 对象存储 (`includes/cos_helper.php`)。
- **安全防护**：内置 WAF 防火墙、安全日志记录、SQL 注入防御。

## 🛠️ 环境要求 (Requirements)

- **OS**: Linux (Debian/Ubuntu/CentOS) / Windows
- **Web Server**: Nginx / Apache
- **PHP**: 7.4 或 8.0+ (建议安装 `redis`, `gd`, `mysqli`, `curl` 扩展)
- **Database**: MySQL 5.7+ / MariaDB
- **Optional**: Redis 服务（用于高性能缓存）

## 🚀 安装指南 (Installation)

### 方法：自动安装（推荐）
1. 将所有文件上传至网站根目录。
2. 确保 `install/` 目录具有写入权限。
3. 访问 `http://你的域名/install/`。
4. 按照页面提示填写数据库信息并完成安装。
5. 设置网站伪静态
```text
location / {
    try_files $uri $uri/ /index.php?$query_string;
}


## ⚙️ 配置说明 (Configuration)

- **云存储配置**：在后台设置或 `includes/cos_helper.php` 中配置腾讯云 COS 密钥。
- **Redis 配置**：在 `includes/redis_helper.php` 中开启并配置 Redis 连接信息。
- **邮件服务**：系统使用 SMTP 发送邮件，需在后台配置发信邮箱。

## 📂 目录结构 (Directory Structure)

```text
/
├── admin/          # 后台管理系统
├── api/            # API 接口 (AI, 聊天, 验证码)
├── assets/         # 公共静态资源 (上传文件存储于 uploads)
├── includes/       # 核心类库 (配置, WAF, COS, Redis)
├── install/        # 自动安装程序
├── pages/          # 前台页面逻辑 (Home, Love, Chat, Music)
├── index.php       # 入口文件
└── ...
