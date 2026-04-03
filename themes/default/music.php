<?php
// pages/music.php
/**              _ _                      ____  _                             
                | (_) __ _ _ __   __ _   / ___|| |__  _   _  ___              
             _  | | |/ _` | '_ \ / _` | \___ \| '_ \| | | |/ _ \             
            | |_| | | (_| | | | | (_| |  ___) | | | | |_| | (_) |            
             \___/|_|\__,_|_| |_|\__, | |____/|_| |_|\__,_|\___/             
    ____  _____          _  __  |___/  _____   _  _  _          ____ ____ 
  / ___| |__  /         | | \ \/ / / | |___ /  / | | || |        / ___/ ___|
 | |  _    / /       _  | |  \  /  | |   |_ \  | | | || |_      | |  | |   
 | |_| |  / /_   _  | |_| |  /  \  | |  ___) | | | |__   _|  _  | |__| |___ 
  \____| /____| (_)  \___/  /_/\_\ |_| |____/  |_|    |_|   (_)  \____\____|
                                                                            
                                追求极致的美学                               
**/
// 1. 直接引入全局 Header（内含数据库连接、配置加载、顶部导航、侧边栏和登录弹窗）
// 注意：按你的代码注释推测目录可能是 includes，如果是 includer 请自行修改为 /../includer/header.php
require_once __DIR__ . '/header.php';

// 2. 读取音乐 API 配置 (conf 函数在 header.php 中已定义)
$api_url = conf('music_api_url', 'https://yy.jx1314.cc');
$playlist_id = conf('music_playlist_id', '884870906');
?>

</div>

<link href="https://fonts.loli.net/css2?family=Outfit:wght@300;400;500;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= THEME_URL ?>/assets/css/music-player.css?v=<?= time() ?>">
<style>
    /* 重置 body 布局，覆盖 header.php 里的 padding 和默认背景 */
    body {
        height: 100vh !important;
        height: 100dvh !important;
        overflow: hidden !important;
        display: flex !important;
        justify-content: center !important;
        align-items: center !important;
        background: #121212 !important; 
        color: #fff !important;
        padding-top: 0 !important;
        background-image: none !important;
    }

    /* 👇 新增：隐藏 header.php 结尾那个占位的空 container，防止它把播放器挤到右边 */
    body > .container {
        display: none !important;
    }

    /* 强制重写顶部导航栏为深色透明质感 */
    .navbar-wrapper {
        background: rgba(0, 0, 0, 0.3) !important;
        border-bottom: 1px solid rgba(255, 255, 255, 0.05) !important;
    }
    .logo, .nav-menu-btn { color: #fff !important; }
    .nav-links li a { color: rgba(255, 255, 255, 0.6) !important; }
    .nav-links li a:hover, .nav-links li a.active { color: #fff !important; }
    .nav-links li a::after { background: #fff !important; }
    
    /* 强制深色搜索框 */
    .search-box input {
        background: rgba(255, 255, 255, 0.1) !important;
        border: 1px solid rgba(255, 255, 255, 0.1) !important;
        color: #fff !important;
    }
    .search-box i { color: rgba(255, 255, 255, 0.5) !important; }
    
    /* 强制深色登录/注册按钮 */
    .nav-login-link {
        color: #fff !important;
        border-color: rgba(255, 255, 255, 0.3) !important;
        background: rgba(255, 255, 255, 0.05) !important;
    }
    .nav-login-link:hover {
        background: #fff !important;
        color: #000 !important;
    }

    /* 强制重写移动端侧滑菜单为深色模式 */
    .mobile-sidebar { background: rgba(26, 26, 26, 0.95) !important; color: #fff !important; }
    .m-username { color: #fff !important; }
    .m-nav-item { color: rgba(255, 255, 255, 0.7) !important; }
    .m-nav-item:hover, .m-nav-item.active { background: rgba(255, 255, 255, 0.1) !important; color: #fff !important; }
    .m-header, .m-footer { border-color: rgba(255, 255, 255, 0.1) !important; }
    .m-btn { border-color: rgba(255, 255, 255, 0.2) !important; color: #ccc !important; }
    .m-btn:hover { background: #fff !important; color: #000 !important; border-color: #fff !important; }
</style>

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

<script>
    const PLAYLIST_ID = '<?= htmlspecialchars($playlist_id) ?>';
</script>

<script src="<?= THEME_URL ?>/assets/js/music-player.js?v=<?= time() ?>"></script>
</body>
</html>