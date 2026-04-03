<?php
// admin/header.php
/**
 * [主题重构版] 包含外观主题管理的后台头部
 **/
// 获取当前页面文件名，用于菜单高亮
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>JS · Admin</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/header.css?v=<?= time() ?>" rel="stylesheet">
</head>
<body>

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
                <a href="friends.php" class="nav-link <?= $current_page == 'friends.php' ? 'active' : '' ?>">
                    <i class="fas fa-link"></i> 友情链接
                </a>
            </div>
            
            <div class="nav-group">
                <div class="nav-title">Finance</div>
                <a href="orders.php" class="nav-link <?= $current_page == 'orders.php' ? 'active' : '' ?>">
                    <i class="fas fa-file-invoice-dollar"></i> 充值订单
                </a>
                <a href="points_log.php" class="nav-link <?= $current_page == 'points_log.php' ? 'active' : '' ?>">
                    <i class="fas fa-coins"></i> 收支明细
                </a>
            </div>

            <div class="nav-group">
                <div class="nav-title">Appearance</div>
                <a href="themes.php" class="nav-link <?= $current_page == 'themes.php' ? 'active' : '' ?>" style="<?= $current_page == 'themes.php' ? 'color: #a18cd1;' : '' ?>">
                    <i class="fas fa-palette"></i> 外观主题
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
                    <div class="menu-toggle" onclick="toggleSidebar()">
                        <i class="fas fa-bars"></i>
                    </div>
                    <div class="breadcrumb">
                        <span>JS Blog /</span> <?= $current_page == 'index.php' ? 'Dashboard' : ucfirst(str_replace('.php','',$current_page)) ?>
                    </div>
                </div>
                <div class="header-tools">
                    <a href="../index.php" target="_blank" class="tool-btn" title="查看首页"><i class="fas fa-rocket"></i></a>
                    
                    <div class="tool-btn" id="bellBtn" title="通知中心" onclick="toggleNotification(event)">
                        <i class="far fa-bell"></i>
                        <span class="bell-badge" id="bellBadge"></span> <div class="notification-dropdown" id="notiDropdown" onclick="event.stopPropagation()">
                            <div class="noti-header">
                                系统通知 <i class="fas fa-check-double" style="color:#94a3b8; cursor:pointer;" title="全部标为已读"></i>
                            </div>
                            <div class="noti-list">
                                <div class="noti-empty" id="notiEmpty">
                                    <i class="fas fa-inbox" style="font-size: 24px; margin-bottom: 8px; opacity: 0.5;"></i><br>暂无新通知
                                </div>
                                
                                <div class="noti-item-security" id="notiSecurity">
                                    <h4><i class="fas fa-shield-halved"></i> 安全规则未配置</h4>
                                    <p>检测到物理 PHP 文件仍可被外部访问，请在 Nginx 中追加以下防盗链规则：</p>
                                    <code>if ($request_uri ~* ^/(themes|includes|install)/.*\.php) {return 403;}</code>
                                    <div class="noti-action-bar">
                                        <button class="noti-btn noti-btn-check" onclick="checkSecurityRules(true)">重新检测</button>
                                        <button class="noti-btn noti-btn-ignore" onclick="dismissSecurityNotice()">忽略</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <div class="grid-wrapper">
                <style>
    .update-modal-overlay {
        position: fixed; top: 0; left: 0; right: 0; bottom: 0;
        background: rgba(0, 0, 0, 0.5); backdrop-filter: blur(5px);
        display: none; justify-content: center; align-items: center; z-index: 9999;
        opacity: 0; transition: opacity 0.3s ease;
    }
    .update-modal-overlay.show { display: flex; opacity: 1; }
    .update-modal {
        background: white; border-radius: 16px; padding: 24px; width: 90%; max-width: 400px;
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        transform: translateY(20px); transition: transform 0.3s ease;
    }
    .update-modal-overlay.show .update-modal { transform: translateY(0); }
    .update-header { display: flex; align-items: center; gap: 12px; margin-bottom: 16px; }
    .update-icon { font-size: 24px; color: #3b82f6; background: #eff6ff; padding: 12px; border-radius: 50%; }
    .update-title { font-size: 18px; font-weight: 600; color: #1e293b; margin: 0; }
    .update-version { font-size: 14px; color: #10b981; font-weight: 500; }
    .update-desc { font-size: 14px; color: #64748b; line-height: 1.5; margin-bottom: 20px; background: #f8fafc; padding: 12px; border-radius: 8px; max-height: 150px; overflow-y: auto;}
    .update-actions { display: flex; gap: 12px; }
    .btn-update { flex: 1; background: #3b82f6; color: white; border: none; padding: 10px; border-radius: 8px; font-weight: 600; cursor: pointer; transition: 0.2s; }
    .btn-update:hover { background: #2563eb; }
    .btn-update:disabled { background: #94a3b8; cursor: not-allowed; }
    .btn-ignore { padding: 10px 16px; background: transparent; color: #64748b; border: 1px solid #cbd5e1; border-radius: 8px; cursor: pointer; transition: 0.2s; }
    .btn-ignore:hover { background: #f1f5f9; color: #334155; }
    .update-progress { display: none; margin-top: 15px; }
    .progress-bar { width: 100%; height: 8px; background: #e2e8f0; border-radius: 4px; overflow: hidden; }
    .progress-fill { width: 0%; height: 100%; background: #10b981; transition: width 0.3s ease; }
    .progress-text { font-size: 12px; color: #64748b; margin-top: 6px; text-align: center; }
</style>

<div class="update-modal-overlay" id="updateModal">
    <div class="update-modal">
        <div class="update-header">
            <i class="fas fa-cloud-download-alt update-icon"></i>
            <div>
                <h3 class="update-title">发现新版本！</h3>
                <div class="update-version" id="newVersionNumber">v...</div>
            </div>
        </div>
        <div class="update-desc" id="updateLog">正在获取更新日志...</div>
        
        <div class="update-progress" id="updateProgressBox">
            <div class="progress-bar"><div class="progress-fill" id="updateProgressFill"></div></div>
            <div class="progress-text" id="updateProgressText">正在下载更新包...</div>
        </div>

        <div class="update-actions" id="updateActionBtns">
            <button class="btn-ignore" onclick="closeUpdateModal()">暂不更新</button>
            <button class="btn-update" id="btnDoUpdate" onclick="startUpdate()">立即更新</button>
        </div>
    </div>
</div>
<script src="assets/js/header.js?v=<?= time() ?>"></script>