<?php
// admin/header.php
/**
                _ _                     ____  _                             
               | (_) __ _ _ __   __ _  / ___|| |__  _   _  ___              
            _  | | |/ _` | '_ \ / _` | \___ \| '_ \| | | |/ _ \             
           | |_| | | (_| | | | | (_| |  ___) | | | | |_| | (_) |            
            \___/|_|\__,_|_| |_|\__, | |____/|_| |_|\__,_|\___/             
   ____   _____          _  __  |___/   _____   _   _  _          ____ ____ 
  / ___| |__  /         | | \ \/ / / | |___ /  / | | || |        / ___/ ___|
 | |  _    / /       _  | |  \  /  | |   |_ \  | | | || |_      | |  | |    
 | |_| |  / /_   _  | |_| |  /  \  | |  ___) | | | |__   _|  _  | |__| |___ 
  \____| /____| (_)  \___/  /_/\_\ |_| |____/  |_|    |_|   (_)  \____\____|
                                                                            
                               追求极致的美学                               
**/
// 获取当前页面文件名，用于菜单高亮
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>Aether Admin</title>
    <!-- 引入 FontAwesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- 引入自定义全局样式 -->
    <link href="assets/css/header.css" rel="stylesheet">
</head>
<body>

    <!-- 侧边栏遮罩层 -->
    <div class="sidebar-overlay" onclick="toggleSidebar()"></div>

    <div class="app-window">
        <nav class="sidebar" id="sidebar">
            <div class="window-controls">
                <div class="win-btn close" title="Close"></div>
                <div class="win-btn min" title="Minimize"></div>
                <div class="win-btn max" title="Maximize"></div>
            </div>
            
            <div class="nav-group">
                <div class="nav-title">Dashboard</div>
                <a href="index.php" class="nav-link <?= $current_page == 'index.php' ? 'active' : '' ?>">
                    <i class="fas fa-chart-pie"></i> 概览
                </a>
            </div>

            <div class="nav-group">
                <div class="nav-title">Content</div>
                <a href="articles.php" class="nav-link <?= $current_page == 'articles.php' ? 'active' : '' ?>">
                    <i class="fas fa-pen-nib"></i> 文章管理
                </a>
                <a href="categories.php" class="nav-link <?= $current_page == 'categories.php' ? 'active' : '' ?>">
                    <i class="fas fa-layer-group"></i> 分类管理
                </a>
                <a href="albums.php" class="nav-link <?= $current_page == 'albums.php' ? 'active' : '' ?>">
                    <i class="fas fa-images"></i> 相册管理
                </a>
                <a href="photos.php" class="nav-link <?= $current_page == 'photos.php' ? 'active' : '' ?>">
                    <i class="fas fa-camera"></i> 照片管理
                </a>
            </div>

            <div class="nav-group">
                <div class="nav-title">Interaction</div>
                <a href="chat_manage.php" class="nav-link <?= $current_page == 'chat_manage.php' ? 'active' : '' ?>">
                    <i class="fas fa-comments"></i> 聊天室
                </a>
                <a href="wishes.php" class="nav-link <?= $current_page == 'wishes.php' ? 'active' : '' ?>">
                    <i class="fas fa-envelope-open-text"></i> 祝福留言
                </a>
                <a href="love.php" class="nav-link <?= $current_page == 'love.php' ? 'active' : '' ?>">
                    <i class="fas fa-heart" style="color: <?= $current_page == 'love.php' ? 'inherit' : '#ec4899' ?>;"></i> 情侣空间
                </a>
                <a href="friends.php" class="nav-link <?= $current_page == 'friends.php' ? 'active' : '' ?>">
                    <i class="fas fa-link"></i> 友情链接
                </a>
            </div>
            
            <div class="nav-group">
                <div class="nav-title">System</div>
                <a href="users.php" class="nav-link <?= $current_page == 'users.php' ? 'active' : '' ?>">
                    <i class="fas fa-users-gear"></i> 用户管理
                </a>
                <a href="settings.php" class="nav-link <?= $current_page == 'settings.php' ? 'active' : '' ?>">
                    <i class="fas fa-sliders"></i> 网站设置
                </a>
            </div>

            <div class="user-bar">
                <img src="https://ui-avatars.com/api/?name=Admin&background=4f46e5&color=fff&bold=true" style="width: 40px; height: 40px; border-radius: 10px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
                <div style="flex: 1;">
                    <div style="font-size: 13px; font-weight: 600; color: var(--text-main);">Administrator</div>
                    <div style="font-size: 11px; color: var(--text-tertiary);">Super User</div>
                </div>
                <a href="../logout.php" style="color: var(--text-tertiary); transition: 0.2s; padding: 5px;" title="退出"><i class="fas fa-sign-out-alt"></i></a>
            </div>
        </nav>

        <main class="main-content">
            <header class="header">
                <div class="breadcrumb-area">
                    <!-- 汉堡菜单触发器 -->
                    <div class="menu-toggle" onclick="toggleSidebar()">
                        <i class="fas fa-bars"></i>
                    </div>
                    <div class="breadcrumb">
                        <span>Aether OS /</span> <?= $current_page == 'index.php' ? 'Dashboard' : ucfirst(str_replace('.php','',$current_page)) ?>
                    </div>
                </div>
                <div class="header-tools">
                    <a href="../index.php" target="_blank" class="tool-btn" title="查看首页"><i class="fas fa-rocket"></i></a>
                    <a href="#" class="tool-btn"><i class="far fa-bell"></i></a>
                </div>
            </header>

            <div class="grid-wrapper">
                <!-- 
                   页面特定内容将从这里开始 
                -->

<!-- 引入头部交互逻辑 JS -->
<script src="assets/js/header.js"></script>
