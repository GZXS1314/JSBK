<?php
// pages/music.php
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
// 1. 基础配置加载
require_once 'includes/config.php';
$pdo = getDB();
$redis = getRedis(); 

define('SITE_CONFIG_CACHE_KEY', CACHE_PREFIX . 'settings:all');

// 2. 全局配置函数
if (!function_exists('conf')) {
    $site_config = null; 

    function conf($key, $default = '') {
        global $site_config, $pdo, $redis;
        if ($site_config === null) {
            $site_config = []; 
            if ($redis) {
                $cachedConfig = $redis->get(SITE_CONFIG_CACHE_KEY);
                if ($cachedConfig) {
                    $site_config = json_decode($cachedConfig, true);
                }
            }
            if (empty($site_config)) {
                $stmt = $pdo->query("SELECT key_name, value FROM settings");
                $db_config = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
                if ($db_config) { $site_config = $db_config; }
                if ($redis) { $redis->set(SITE_CONFIG_CACHE_KEY, json_encode($site_config), 86400); }
            }
        }
        return isset($site_config[$key]) && $site_config[$key] !== '' ? htmlspecialchars($site_config[$key]) : $default;
    }
}

// 3. 状态定义
$is_home = false; $is_album = false; $is_music = true;
$is_user_login = isset($_SESSION['user_id']);
$current_user_avatar = $_SESSION['avatar'] ?? '';
$current_user_name = $_SESSION['nickname'] ?? '';
$enable_chatroom = conf('enable_chatroom') == '1';

// 配置读取
$api_url = conf('music_api_url', 'https://yy.jx1314.cc');
$playlist_id = conf('music_playlist_id', '884870906');
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>音乐馆 - <?= conf('site_name') ?></title>
    <link href="https://cdn.bootcdn.net/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;700&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="/pages/assets/css/music-player.css?v=<?= time() ?>">
</head>
<body>

<div class="mobile-menu-overlay" id="mobileOverlay"></div>

<div class="mobile-sidebar" id="mobileSidebar">
    <div class="m-header">
        <div class="m-user-info">
            <img src="<?= $is_user_login ? htmlspecialchars($current_user_avatar) : conf('author_avatar') ?>" class="m-avatar" alt="Avatar">
            <div>
                <div class="m-username"><?= $is_user_login ? htmlspecialchars($current_user_name) : conf('author_name') ?></div>
                <div class="m-bio"><?= $is_user_login ? '欢迎回来' : '游客访问' ?></div>
            </div>
        </div>
    </div>
    
    <div class="m-nav-list">
        <a href="/" class="m-nav-item"><i class="fa-solid fa-house"></i> 首页</a>
        <a href="/album" class="m-nav-item"><i class="fa-regular fa-images"></i> 视觉画廊</a>
        <a href="/music" class="m-nav-item active"><i class="fa-solid fa-music"></i> 音乐馆</a>
        <a href="/love" class="m-nav-item"><i class="fa-solid fa-heart"></i> Love</a>
        <a href="/friends" class="m-nav-item"><i class="fa-solid fa-link"></i> 友情链接</a>
        <?php if($enable_chatroom): ?>
            <a href="/chat" class="m-nav-item"><i class="fa-regular fa-comments"></i> 在线聊天室</a>
        <?php endif; ?>
    </div>
    
    <div class="m-footer">
        <?php if($is_user_login): ?>
            <a href="user/dashboard.php" class="m-btn"><i class="fa-solid fa-user-gear"></i> 个人中心</a>
            <a href="user/logout.php" class="m-btn"><i class="fa-solid fa-power-off"></i> 退出</a>
        <?php else: ?>
            <a href="javascript:;" onclick="openAuthModal('login'); toggleMenu();" class="m-btn"><i class="fa-solid fa-right-to-bracket"></i> 登录/注册</a>
        <?php endif; ?>
    </div>
</div>

<nav class="navbar-wrapper" id="mainNav">
    <div class="navbar-inner">
        <a href="/" class="logo"><?= conf('site_name', 'BLOG.') ?></a>
        <ul class="nav-links">
            <li><a href="/"><i class="fa-solid fa-house"></i> 首页</a></li>
            <li><a href="/album"><i class="fa-regular fa-images"></i> 相册</a></li>
            <a href="/music" class="active"><i class="fa-solid fa-music"></i> 音乐馆</a>
            <li><a href="/love"><i class="fa-solid fa-heart"></i> Love</a></li>
            <li><a href="/friends"><i class="fa-solid fa-link"></i> 友链</a></li>
        </ul>
        <div class="nav-right">
            <div class="search-box">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" placeholder="Search..." disabled title="音乐馆内暂不支持搜索">
            </div>
            <?php if($is_user_login): ?>
                <a href="user/dashboard.php" class="nav-user-btn"><img src="<?= htmlspecialchars($current_user_avatar) ?>" class="nav-avatar"></a>
            <?php else: ?>
                <a href="javascript:;" onclick="openAuthModal('login')" class="nav-login-link">登录 / 注册</a>
            <?php endif; ?>
            <div class="nav-menu-btn" id="menuBtn"><i class="fa-solid fa-bars-staggered"></i></div>
        </div>
    </div>
</nav>

<div class="bg-layer" id="bg-layer"></div>

<div class="loading-overlay" id="loading-layer">
    <div class="spinner"></div>
    <div id="loading-text">同步云端歌单...</div>
</div>

<div class="player-card">
    <div class="current-track-area">
        <div class="album-art-container">
            <img src="" alt="Album Art" class="album-art" id="album-art">
        </div>
        <div class="track-info">
            <div class="track-name" id="track-name">等待载入</div>
            <div class="track-artist" id="track-artist">歌手</div>
        </div>
        <div class="lyrics-mask">
            <ul class="lyrics-list" id="lyrics-list">
                <li class="lyric-line active">就绪</li>
            </ul>
        </div>
        <div class="controls">
            <button class="btn" id="prev-btn"><i class="fas fa-backward"></i></button>
            <button class="btn btn-play" id="play-btn"><i class="fas fa-play"></i></button>
            <button class="btn" id="next-btn"><i class="fas fa-forward"></i></button>
            <button class="btn m-playlist-btn" id="playlist-toggle-btn"><i class="fas fa-list-ul"></i></button>
        </div>
    </div>
    
    <div class="playlist-overlay" id="playlist-overlay"></div>
    
    <div class="playlist-area" id="playlist-area">
        <div class="playlist-header">
            <span>播放列表</span>
            <span id="p-count">0 首</span>
            <button class="close-playlist-btn" id="close-playlist-btn"><i class="fas fa-times"></i></button>
        </div>
        <div class="playlist-items" id="p-items"></div>
    </div>
</div>

<audio id="audio-player"></audio>

<?php require_once __DIR__ . '/../includes/auth_modal.php'; ?>

<script>
    const PLAYLIST_ID = '<?= $playlist_id ?>';
</script>

<script src="/pages/assets/js/music-player.js?v=<?= time() ?>"></script>
</body>
</html>