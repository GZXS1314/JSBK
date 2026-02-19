<?php
// includes/header.php
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
// 1. 防止重复加载配置
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/redis_helper.php';
$pdo = getDB();

//加载网站配置 (修改为优先读缓存)
$site_config = Cache::get('site_settings');
if (!$site_config) {
    $stmt_set = $pdo->query("SELECT * FROM settings");
    $site_config = [];
    while ($row = $stmt_set->fetch()) {
        $site_config[$row['key_name']] = $row['value'];
    }
    Cache::set('site_settings', $site_config, 86400);
}

if (!function_exists('conf')) {
    function conf($key, $default = '') {
        global $site_config;
        return isset($site_config[$key]) && $site_config[$key] !== '' ? htmlspecialchars($site_config[$key]) : $default;
    }
}

// 2. 生成 CSRF 令牌
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// 3. 读取外观配置
$bg_type = conf('site_bg_type', 'color');
$bg_val  = conf('site_bg_value', '#f5f5f7');
$bg_grad_start = conf('site_bg_gradient_start', '#a18cd1');
$bg_grad_end   = conf('site_bg_gradient_end', '#fbc2eb');
$card_opacity  = conf('site_bg_overlay_opacity', '0.85');

$final_bg_css = "";
if ($bg_type == 'color') {
    $final_bg_css = "background-color: {$bg_val};";
} elseif ($bg_type == 'gradient') {
    $final_bg_css = "background: linear-gradient(135deg, {$bg_grad_start} 0%, {$bg_grad_end} 100%); background-attachment: fixed;";
} elseif ($bg_type == 'image') {
    $noise = "url(\"data:image/svg+xml,%3Csvg viewBox='0 0 200 200' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noiseFilter'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.65' numOctaves='3' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noiseFilter)' opacity='0.04'/%3E%3C/svg%3E\")";
    $final_bg_css = "background: {$noise}, url('{$bg_val}') no-repeat center center fixed; background-size: cover;";
} else {
    $final_bg_css = "background-color: #f5f5f7;";
}
$final_card_bg = "rgba(255, 255, 255, {$card_opacity})";

// 4. 用户信息
$is_user_login = isset($_SESSION['user_id']);
$current_user_id = $is_user_login ? $_SESSION['user_id'] : 0;
$current_user_avatar = $is_user_login ? $_SESSION['avatar'] : '';
$current_user_name = $is_user_login ? $_SESSION['nickname'] : '';

// 5. 获取当前脚本名称和路由
$request_uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$is_home = in_array($request_uri, ['/', '/index.php', '/home']);
$is_album = ($request_uri == '/album');
$is_music = ($request_uri == '/music');
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    
    <title><?= conf('site_name', '极简 · 个人博客') ?></title>
    <meta name="keywords" content="<?= conf('site_keywords', '个人博客,技术分享,生活记录') ?>">
    <meta name="description" content="<?= conf('site_description', '欢迎来到我的个人博客，这里记录了我的技术学习与生活点滴。') ?>">
    
    <?php if($bd = conf('baidu_verify')): ?><meta name="baidu-site-verification" content="<?= $bd ?>" /><?php endif; ?>
    <?php if($gg = conf('google_verify')): ?><meta name="google-site-verification" content="<?= $gg ?>" /><?php endif; ?>
    
    <link href="https://cdn.bootcdn.net/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <?php if ($is_home): ?>
    <link rel="stylesheet" href="../pages/assets/css/home.css?v=<?= time() ?>">
    <?php endif; ?>

    <style>
        /* --- 全局变量与基础样式 --- */
        :root {
            --card-bg: <?= $final_card_bg ?>; 
            --bg-color: #f5f5f7;
            --card-border: 1px solid rgba(255, 255, 255, 0.4);
            --glass-blur: blur(20px);
            --text-main: #1a1a1a;
            --text-sub: #666;
            --shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.05);
            --radius: 16px;
            --content-width: 1280px; 
            --transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }
        * { margin: 0; padding: 0; box-sizing: border-box; outline: none; -webkit-tap-highlight-color: transparent; }
        body { font-family: "PingFang SC", "Microsoft YaHei", -apple-system, BlinkMacSystemFont, Roboto, sans-serif; <?= $final_bg_css ?> color: var(--text-main); overflow-x: hidden; padding-top: 90px; min-height: 100vh; transition: background 0.5s ease; }
        a { text-decoration: none; color: inherit; }
        ul { list-style: none; }
        img { max-width: 100%; height: auto; display: block; }
        html { scrollbar-width: none; -ms-overflow-style: none; }
        .glass-card { background: var(--card-bg) !important; backdrop-filter: var(--glass-blur); -webkit-backdrop-filter: var(--glass-blur); border: var(--card-border); border-radius: var(--radius); box-shadow: var(--shadow); transition: var(--transition); }
        .container { max-width: var(--content-width); margin: 0 auto; padding: 0 20px; width: 100%; }

        /* --- 导航栏 --- */
        .navbar-wrapper { position: fixed; top: 0; left: 0; right: 0; z-index: 1000; height: 70px; background: var(--card-bg); backdrop-filter: blur(20px); border-bottom: 1px solid rgba(0,0,0,0.05); }
        .navbar-inner { height: 100%; display: flex; justify-content: space-between; align-items: center; max-width: var(--content-width) !important; margin: 0 auto; padding: 0 20px; width: 100%; }
        .logo { font-size: 24px; font-weight: 800; letter-spacing: -1px; min-width: 100px; z-index: 1001; }
        .nav-links { display: flex; gap: 30px; position: absolute; left: 50%; transform: translateX(-50%); }
        .nav-links li a { font-size: 15px; font-weight: 600; color: var(--text-sub); position: relative; padding: 5px 0; transition: color 0.3s; display: flex; align-items: center; gap: 8px; }
        .nav-links li a i { font-size: 14px; transition: transform 0.3s ease; opacity: 0.8; }
        .nav-links li a:hover, .nav-links li a.active { color: var(--text-main); }
        .nav-links li a:hover i, .nav-links li a.active i { transform: translateY(-1px); opacity: 1; }
        .nav-links li a::after { content: ''; position: absolute; bottom: -2px; left: 50%; width: 0; height: 2px; background: #000; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); transform: translateX(-50%); border-radius: 2px; }
        .nav-links li a:hover::after, .nav-links li a.active::after { width: 100%; }
        .nav-right { display: flex; align-items: center; gap: 15px; z-index: 1001; }
        .search-box { position: relative; }
        .search-box input { background: rgba(0,0,0,0.05); border: none; padding: 8px 15px 8px 35px; border-radius: 20px; width: 180px; transition: var(--transition); font-size: 13px; height: 36px; }
        .search-box i { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); font-size: 13px; color: #999; pointer-events: none; }
        .search-box input:focus { width: 240px; background: #fff; box-shadow: 0 0 0 2px rgba(0,0,0,0.1); }
        .nav-menu-btn { cursor: pointer; font-size: 20px; color: var(--text-main); display: none; padding: 5px; margin-left: 5px; }
        .nav-user-btn { display: flex; align-items: center; gap: 8px; font-size: 14px; font-weight: bold; cursor: pointer; }
        .nav-avatar { width: 32px; height: 32px; border-radius: 50%; border: 1px solid #ddd; object-fit: cover; }
        .nav-login-link { font-size: 14px; font-weight: 600; color: #333; padding: 6px 15px; border: 1px solid #ddd; border-radius: 20px; transition: 0.3s; }
        .nav-login-link:hover { background: #000; color: #fff; border-color: #000; }

        /* --- 移动端侧滑菜单 --- */
        .mobile-menu-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.2); z-index: 1999; opacity: 0; visibility: hidden; transition: 0.3s; backdrop-filter: blur(4px); }
        .mobile-menu-overlay.active { opacity: 1; visibility: visible; }
        .mobile-sidebar { position: fixed; top: 0; right: -300px; width: 300px; height: 100%; background: rgba(255,255,255,0.95); z-index: 2000; transition: 0.4s cubic-bezier(0.16, 1, 0.3, 1); box-shadow: -10px 0 40px rgba(0,0,0,0.1); display: flex; flex-direction: column; backdrop-filter: blur(20px); }
        .mobile-sidebar.active { right: 0; }
        .m-header { padding: 60px 30px 30px; border-bottom: 1px solid rgba(0,0,0,0.05); }
        .m-user-info { display: flex; align-items: center; gap: 15px; }
        .m-avatar { width: 60px; height: 60px; border-radius: 50%; object-fit: cover; border: 2px solid #fff; box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
        .m-username { font-size: 18px; font-weight: 800; color: #1a1a1a; margin-bottom: 4px; }
        .m-bio { font-size: 12px; color: #999; }
        .m-nav-list { flex: 1; padding: 20px; overflow-y: auto; display: flex; flex-direction: column; gap: 8px; }
        .m-nav-item { display: flex; align-items: center; gap: 15px; padding: 12px 15px; border-radius: 12px; font-size: 15px; font-weight: 600; color: #444; transition: all 0.2s; }
        .m-nav-item i { width: 24px; text-align: center; font-size: 16px; color: #888; transition: 0.2s; }
        .m-nav-item:hover, .m-nav-item.active { background: #f0f2f5; color: #000; }
        .m-nav-item:hover i, .m-nav-item.active i { color: #000; transform: scale(1.1); }
        .m-footer { padding: 20px 30px 40px; border-top: 1px solid rgba(0,0,0,0.05); display: flex; gap: 10px; }
        .m-btn { flex: 1; padding: 10px; border-radius: 10px; font-size: 13px; font-weight: 600; text-align: center; border: 1px solid #eee; color: #666; transition: 0.2s; display: flex; align-items: center; justify-content: center; gap: 6px; }
        .m-btn:hover { background: #000; color: #fff; border-color: #000; }
        
        /* --- 全局弹窗样式 --- */
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 99999 !important; backdrop-filter: blur(5px); display: none; align-items: center; justify-content: center; }
        .modal-overlay.active { opacity: 1; visibility: visible; display: flex; }
        .modal-card { background: #fff; width: 800px; max-width: 90%; max-height: 90vh; border-radius: 16px; overflow: hidden; display: flex; flex-direction: column; position: relative; transform: scale(0.9); transition: 0.3s; box-shadow: 0 20px 40px rgba(0,0,0,0.2); }
        .modal-overlay.active .modal-card { transform: scale(1); }
        .modal-header-bar { flex-shrink: 0; height: 50px; background: rgba(255, 255, 255, 0.95); border-bottom: 1px solid #eee; display: flex; align-items: center; justify-content: space-between; padding: 0 15px; z-index: 10; backdrop-filter: blur(10px); }
        .modal-header-title { flex:1; font-weight:bold; font-size:14px; color:#999; overflow:hidden; white-space:nowrap; text-overflow:ellipsis; padding-right:20px; }
        .close-modal-btn { width: 32px; height: 32px; border-radius: 50%; background: #f1f3f5; color: #333; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: 0.2s; font-size: 18px; }
        .close-modal-btn:hover { background: #e9ecef; transform: rotate(90deg); }
        .modal-scroll-area { flex: 1; overflow-y: auto; padding: 30px; overscroll-behavior: contain; }
        .modal-title { font-size: 28px; font-weight: bold; margin-bottom: 20px; line-height: 1.3; }
        .action-bar { display: flex; gap: 20px; margin-top: 30px; border-top: 1px solid #eee; padding-top: 20px;}
        .action-btn { cursor: pointer; display: flex; align-items: center; gap: 5px; color: #666; transition:0.3s; font-size: 14px; }
        .action-btn:hover { color: #000; } .action-btn.liked { color: #ff4757; }
        .comment-section { margin-top: 30px; background: #f9f9f9; padding: 25px; border-radius: 12px; }
        .comment-input { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px; margin-bottom: 10px; font-size: 14px; } .comment-input:focus { border-color: #000; }
        .comment-list { margin-top: 20px; max-height: 300px; overflow-y: auto; }
        .comment-item { border-bottom: 1px solid #eee; padding: 12px 0; font-size: 13px; }

        /* --- 全局 Footer --- */
        .minimal-footer { background: #fff; border-top: 1px solid rgba(0,0,0,0.05); margin-top: 40px; padding: 20px 0; font-size: 12px; color: #999; }
        .footer-inner { max-width: var(--content-width) !important; margin: 0 auto; padding: 0 20px; display: flex; justify-content: space-between; align-items: center; }
        .f-left { display: flex; align-items: center; gap: 8px; }
        .f-logo { font-weight: 800; color: #000; font-size: 14px; letter-spacing: -0.5px; }
        .f-divider { color: #ddd; }
        .f-right { display: flex; align-items: center; gap: 15px; }
        .f-icp { color: #bbb; transition: 0.3s; }
        .f-icp:hover { color: #666; }
        .f-admin-btn { color: #eee; transition: 0.3s; }
        .f-admin-btn:hover { color: #000; }

        /* --- 响应式 --- */
        @media (max-width: 1024px) {
            .container, .navbar-inner { padding: 0 15px; width: 100%; max-width: 100% !important; }
            .nav-links, .search-box { display: none; }
            .nav-menu-btn { display: block; }
        }
        @media (max-width: 768px) {
            .modal-scroll-area { padding: 20px; }
            .footer-inner { flex-direction: column; gap: 10px; padding-bottom: 20px; }
        }
    </style>
    
    <?php if(conf('enable_loading_anim') == '1'): ?>
    <style>
        #global-loader { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: #fff; z-index: 999999; display: flex; justify-content: center; align-items: center; transition: opacity 0.6s ease, visibility 0.6s ease; }
        .loader-avatar-box { position: relative; width: 100px; height: 100px; border-radius: 50%; padding: 5px; box-shadow: 0 0 20px rgba(0,0,0,0.05); animation: loader-pulse 2s infinite ease-in-out; }
        .loader-avatar { width: 100%; height: 100%; border-radius: 50%; object-fit: cover; }
        @keyframes loader-pulse { 0% { transform: scale(0.95); opacity: 0.8; } 50% { transform: scale(1.05); opacity: 1; } 100% { transform: scale(0.95); opacity: 0.8; } }
        .loader-hidden { opacity: 0; visibility: hidden; }
    </style>
    <?php endif; ?>
    <?php 
    // 直接读取 raw 数据，避免 htmlspecialchars 转义
    $custom_css = $site_config['custom_css'] ?? '';
    if (!empty($custom_css)): 
    ?>
    <style>
        <?= $custom_css ?>
    </style>
    <?php endif; ?>
</head>
<body>
<?php if(conf('enable_loading_anim') == '1'): ?>
<div id="global-loader"><div class="loader-avatar-box"><img src="<?= conf('author_avatar') ?>" class="loader-avatar"></div></div>
<?php endif; ?>

<div class="mobile-menu-overlay" id="mobileOverlay"></div>
<div class="mobile-sidebar" id="mobileSidebar">
    <div class="m-header">
        <div class="m-user-info">
            <img src="<?= $is_user_login ? $current_user_avatar : conf('author_avatar') ?>" class="m-avatar" alt="Avatar">
            <div>
                <div class="m-username"><?= $is_user_login ? $current_user_name : conf('author_name') ?></div>
                <div class="m-bio"><?= $is_user_login ? '欢迎回来' : '游客访问' ?></div>
            </div>
        </div>
    </div>
    <div class="m-nav-list">
        <a href="/" class="m-nav-item <?= $is_home ? 'active' : '' ?>"><i class="fa-solid fa-house"></i> 首页</a>
        <a href="/album" class="m-nav-item <?= $is_album ? 'active' : '' ?>"><i class="fa-regular fa-images"></i> 视觉画廊</a>
        <a href="/music" class="m-nav-item <?= $is_music ? 'active' : '' ?>"><i class="fa-solid fa-music"></i> 音乐馆</a>
        <a href="/love" class="m-nav-item <?= $request_uri == '/love' ? 'active' : '' ?>"><i class="fa-solid fa-heart"></i> Love</a>
        <a href="/friends" class="m-nav-item <?= $request_uri == '/friends' ? 'active' : '' ?>"><i class="fa-solid fa-link"></i> 友情链接</a>
        <?php if(conf('enable_chatroom') == '1'): ?>
        <a href="/chat" class="m-nav-item"><i class="fa-regular fa-comments"></i> 在线聊天室</a>
        <?php endif; ?>
    </div>
    <div class="m-footer">
        <?php if($is_user_login): ?>
        <a href="/user/dashboard.php" class="m-btn"><i class="fa-solid fa-user-gear"></i> 个人中心</a>
        <a href="/user/logout.php" class="m-btn"><i class="fa-solid fa-power-off"></i> 退出</a>
        <?php else: ?>
        <a href="javascript:;" onclick="openAuthModal('login'); toggleMenu();" class="m-btn"><i class="fa-solid fa-right-to-bracket"></i> 登录/注册</a>
        <?php endif; ?>
    </div>
</div>

<nav class="navbar-wrapper">
    <div class="navbar-inner">
        <div class="logo"><?= conf('site_name', 'BLOG.') ?></div>
        <ul class="nav-links">
            <li><a href="/" class="<?= $is_home ? 'active' : '' ?>"><i class="fa-solid fa-house"></i> 首页</a></li>
            <li><a href="/album" class="<?= $is_album ? 'active' : '' ?>"><i class="fa-regular fa-images"></i> 相册</a></li>
            <li><a href="/music" class="<?= $is_music ? 'active' : '' ?>"><i class="fa-solid fa-music"></i> 音乐馆</a></li>
            <li><a href="/love" class="<?= $request_uri == '/love' ? 'active' : '' ?>"><i class="fa-solid fa-heart"></i> Love</a></li>
            <li><a href="/friends" class="<?= $request_uri == '/friends' ? 'active' : '' ?>"><i class="fa-solid fa-link"></i> 友链</a></li>
        </ul>
        <div class="nav-right">
            <div class="search-box">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" id="searchInput" placeholder="Search..." <?= !$is_home ? 'disabled title="仅在首页可用"' : '' ?>>
            </div>
            <?php if($is_user_login): ?>
            <a href="/user/dashboard.php" class="nav-user-btn" style="margin-left: 15px;"><img src="<?= $current_user_avatar ?>" class="nav-avatar"></a>
            <?php else: ?>
            <a href="javascript:;" onclick="openAuthModal('login')" class="nav-login-link">登录 / 注册</a>
            <?php endif; ?>
            <div class="nav-menu-btn" id="menuBtn"><i class="fa-solid fa-bars-staggered"></i></div>
        </div>
    </div>
</nav>

<?php require_once __DIR__ . '/auth_modal.php'; ?>

<script>
    // 全局脚本
    document.addEventListener('DOMContentLoaded', () => {
        const menuBtn = document.getElementById('menuBtn');
        const mobileOverlay = document.getElementById('mobileOverlay');
        const mobileSidebar = document.getElementById('mobileSidebar');
        window.toggleMenu = () => {
            mobileOverlay.classList.toggle('active');
            mobileSidebar.classList.toggle('active');
        };
        if(menuBtn) menuBtn.addEventListener('click', window.toggleMenu);
        if(mobileOverlay) mobileOverlay.addEventListener('click', window.toggleMenu);

        <?php if(conf('enable_loading_anim') == '1'): ?>
        window.addEventListener('load', function() {
            const loader = document.getElementById('global-loader');
            if (loader) {
                setTimeout(() => {
                    loader.classList.add('loader-hidden');
                    setTimeout(() => { loader.style.display = 'none'; }, 600);
                }, 500);
            }
        });
        <?php endif; ?>
    });
</script>

<div class="container" style="min-height: 80vh;">
