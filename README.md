<div align="center">

# 🌟 BKCS - 多功能个人门户系统
### (Personal Portal System v1.0.0)

**追求极致美学的轻量级个人全栈解决方案**
原生 PHP 开发，集博客、相册、动态、恋爱空间与 AI 实验室于一体，内置安全 WAF 与在线更新引擎。

[![PHP](https://img.shields.io/badge/PHP-7.4%2B-777BB4?style=flat-square&logo=php&logoColor=white)](https://www.php.net/)
[![MySQL](https://img.shields.io/badge/MySQL-5.7%2B-4479A1?style=flat-square&logo=mysql&logoColor=white)](https://www.mysql.com/)
[![Redis](https://img.shields.io/badge/Redis-Enabled-DC382D?style=flat-square&logo=redis&logoColor=white)](https://redis.io/)
[![HotUpdate](https://img.shields.io/badge/Update-Online-FF9900?style=flat-square&logo=icloud&logoColor=white)](#)
[![License](https://img.shields.io/badge/License-MIT-green?style=flat-square)](LICENSE)

[🖥️ 查看前台演示](https://gz.jx1314.cc/) &nbsp;&nbsp;|&nbsp;&nbsp; [📖 开发文档](#) &nbsp;&nbsp;|&nbsp;&nbsp; [🐞 提交 Issue](https://github.com/你的用户名/仓库名/issues)

</div>

---

## 📸 界面预览 (Gallery)

### 🚀 现代化仪表盘 (V3 重构版)
![Admin Dashboard](<img width="1635" height="1309" alt="image" src="https://github.com/user-attachments/assets/1fe174bb-b54f-4f5c-b080-0026592b40c0" />
)
![微信交流群](<img width="1146" height="1611" alt="ca25404acbb04c7a5d42cab323d864c8" src="https://github.com/user-attachments/assets/ca164a27-5689-4fc0-a3bb-8fd4f5367abc" />
)
> **全新毛玻璃 (Glassmorphism) UI**：集成实时资源监控（CPU/内存/磁盘）、数据发布趋势及**在线版本管理面板**。

---

## ✨ 核心特性 (Features)

### 🎨 极致视觉与体验
- **现代化 UI**：全站采用自适应设计，完美兼容 PC/平板/移动端。
- **内容创作**：支持多图/视频文章发布，集成 `Prism.js` 顶级代码高亮。
- **多媒体流**：瀑布流摄影相册 + 全站无刷新悬浮音乐播放器。

### ⚙️ 智能化运维控制
- **🛰️ 在线热更新**：后台一键检测新版本，全自动下载、解压覆盖，支持 **数据库同步升级**。
- **🛡️ 军工级防护**：内置 **自研 WAF 防护系统**，实时拦截 SQL 注入、XSS 攻击，支持安全日志追踪。
- **⚡ 高性能支撑**：深度集成 **Redis 缓存**，支持 **腾讯云 COS** 对象存储，大幅提升全球访问速度。

### 💖 情感与互动
- **恋爱空间**：专属情侣板块，记录纪念日、相恋天数及私密甜蜜日志。
- **互动社区**：实时聊天室大厅、许愿墙留言，支持多维身份展示。
- **AI 赋能**：集成主流 AI 接口，支持后台一键生成文章摘要与内容辅助。

---

## 🚀 快速起步 (Quick Start)

### 1. 环境准备
| 组件 | 推荐配置 | 备注 |
| :--- | :--- | :--- |
| **PHP** | 7.4 - 8.2 | 需开启 `pdo_mysql`, `curl`, `gd`, `zip` 扩展 |
| **MySQL** | 5.7+ | 需支持 `utf8mb4` 字符集 |
| **Redis** | 6.0+ | (可选) 开启后显著提升响应速度 |

### 2. 一键安装
1. 将源码上传至 Web 根目录。
2. 访问 `http://你的域名/install` 进入图形化安装向导。
3. 按照提示完成数据库对接，系统将**自动生成** `config.php` 并锁定安装目录。

🔄 持续维护 (Maintenance)
本项目已接入 Online Update 网络。

手动更新：在后台首页作者卡片点击“系统版本”即可触发检测。

自动更新：登录后台时系统将静默比对云端 version.json，发现新版将自动弹窗提醒。

开发者打包：发布新版本只需上传 ZIP 包与 update.sql 至云存储，客户端即可实现无损升级。

🤝 贡献与感谢
作者: 江硕 (Jiang Shuo)

UI 参考:
Aether Design / Glassmorphism Framework / https://blog.anheyu.com/(安和鱼)

鸣谢: 感谢所有为 BKCS 提出建议的开发者。
### 3. Nginx 伪静态配置
```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}

