<?php
// pages/love.php
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
// 1. 基础配置加载与全局连接
require_once 'includes/config.php';
$pdo = getDB();
$redis = getRedis();

// [核心修复1] 引入公共头部（包含 nav 导航栏等），必须放在最前面
require_once 'includes/header.php';

// [关键] 定义与后台一致的缓存键名
$cache_key_config = 'bkcs:love:config';
$cache_key_events = 'bkcs:love:events';

// [核心修复2] 增加缓存开关检查，防止幽灵缓存 Bug
$use_redis = ($redis && conf('redis_enabled') == '1');

// --- 缩略图助手 ---
if (!function_exists('getCosThumb')) {
    function getCosThumb($url, $width = 600) {
        if (empty($url)) return $url;
        if (strpos($url, 'http') !== 0 || strpos($url, '?') !== false) return $url;
        return $url . '?imageMogr2/thumbnail/' . $width . 'x/interlace/1/q/80';
    }
}

// --- 获取配置 (带缓存开关校验) ---
$love_config = [];
if ($use_redis) {
    $cached = $redis->get($cache_key_config);
    if ($cached) $love_config = json_decode($cached, true);
}

if (empty($love_config)) {
    try {
        $stmt = $pdo->prepare("SELECT key_name, value FROM settings WHERE key_name LIKE 'love_%'");
        $stmt->execute();
        $love_config = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        if ($use_redis && !empty($love_config)) {
            $redis->set($cache_key_config, json_encode($love_config), 86400); 
        }
    } catch (PDOException $e) {
        $love_config = [];
    }
}

function get_conf_local($key, $arr, $default = '') {
    return isset($arr[$key]) && $arr[$key] !== '' ? $arr[$key] : $default;
}

// 解析配置
$boy = get_conf_local('love_boy', $love_config, '先生');
$girl = get_conf_local('love_girl', $love_config, '小姐');
$boy_av = get_conf_local('love_boy_avatar', $love_config, 'https://api.dicebear.com/7.x/avataaars/svg?seed=Felix');
$girl_av = get_conf_local('love_girl_avatar', $love_config, 'https://api.dicebear.com/7.x/avataaars/svg?seed=Aneka');
$start_date = get_conf_local('love_start_date', $love_config, date('Y-m-d'));
$bg_url = get_conf_local('love_bg', $love_config);
if (empty($bg_url)) $bg_url = 'https://images.unsplash.com/photo-1518621736915-f3b1c41bfd00?q=80&w=2500&auto=format&fit=crop';

$letter_enabled = get_conf_local('love_letter_enabled', $love_config) == '1';
$letter_content = get_conf_local('love_letter_content', $love_config, '写给亲爱的你...');
$letter_music = get_conf_local('love_letter_music', $love_config);

// --- 获取动态列表 (带缓存开关校验) ---
$events = null;
if ($use_redis) {
    $cachedEvents = $redis->get($cache_key_events);
    if ($cachedEvents) {
        $decoded = json_decode($cachedEvents, true);
        if (is_array($decoded)) $events = $decoded;
    }
}

if (!is_array($events)) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM love_events ORDER BY event_date DESC, id DESC");
        $stmt->execute();
        $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($use_redis) {
            $redis->set($cache_key_events, json_encode($events), 3600); 
        }
    } catch (PDOException $e) {
        $events = [];
    }
}

$is_user_login = isset($_SESSION['user_id']);
$user_avatar = $_SESSION['avatar'] ?? 'https://ui-avatars.com/api/?name=Guest';
?>

<link href="https://fonts.googleapis.com/css2?family=Lato:wght@300;400;700&family=Noto+Serif+SC:wght@400;700&family=Ma+Shan+Zheng&display=swap" rel="stylesheet">

<link rel="stylesheet" href="/pages/assets/css/love.css?v=<?php echo time(); ?>">

<style>
    body > .container, .container { 
        max-width: 100% !important; 
        width: 100% !important; 
        padding: 0 !important; 
        margin: 0 !important; 
    }
    .hero-section { 
        background-image: url('<?= getCosThumb(htmlspecialchars($bg_url), 1920) ?>') !important; 
    }
</style>

<div class="love-page-wrapper">
    <section class="hero-section">
        <div class="hero-overlay"></div>
        <div class="danmaku-zone" id="danmakuLayer"></div>

        <div class="hero-content">
            <div class="couple-box">
                <div class="av-halo">
                    <img src="<?= htmlspecialchars($boy_av) ?>" alt="Boy">
                    <div class="av-name"><?= htmlspecialchars($boy) ?></div>
                </div>
                <div class="heart-center" id="openLetterBtn">
                    <i class="fa-solid fa-heart"></i>
                </div>
                <div class="av-halo">
                    <img src="<?= htmlspecialchars($girl_av) ?>" alt="Girl">
                    <div class="av-name"><?= htmlspecialchars($girl) ?></div>
                </div>
            </div>
            
            <div class="timer-area">
                <div class="timer-title">我们已经相爱</div>
                <div class="timer-digits">
                    <div class="t-unit"><span class="t-num" id="d-days">0</span><span class="t-label">DAYS</span></div>
                    <div class="t-unit"><span class="t-num" id="d-hours">0</span><span class="t-label">HOURS</span></div>
                    <div class="t-unit"><span class="t-num" id="d-mins">0</span><span class="t-label">MINS</span></div>
                    <div class="t-unit"><span class="t-num" id="d-secs">0</span><span class="t-label">SECS</span></div>
                </div>
            </div>

            <div class="input-bar-glass">
                <input type="text" id="wishInput" class="wish-input-field" placeholder="写下祝福，发送弹幕...">
                <button class="wish-send-btn" id="sendWishBtn"><i class="fa-solid fa-paper-plane"></i></button>
            </div>
        </div>

        <div class="scroll-hint" id="scrollHint">
            翻阅我们的故事 <i class="fa-solid fa-chevron-down" style="margin-left:5px"></i>
        </div>
    </section>

    <div class="letter-overlay" id="letterModal">
        <div class="envelope-container">
            <div class="envelope-body paper-texture"></div> 
            <div class="letter-preview"></div>
            <div class="envelope-pocket paper-texture"></div> 
            <div class="envelope-flap paper-texture"></div> 
            <div class="wax-seal-btn" id="openEnvelopeBtn">
                <i class="fa-solid fa-heart"></i>
            </div>
        </div>
        
        <div class="letter-paper-full">
            <div class="close-letter" id="closeLetterBtn"><i class="fa-solid fa-xmark"></i></div>
            <div class="letter-content"><?= nl2br(htmlspecialchars($letter_content)) ?></div>
        </div>
    </div>

    <div class="lightbox-overlay" id="lightbox">
        <img src="" class="lightbox-img" id="lightboxImg" alt="Lightbox">
    </div>

    <?php if($letter_music): ?>
    <audio id="loveBgm" loop><source src="<?= htmlspecialchars($letter_music) ?>" type="audio/mpeg"></audio>
    <?php endif; ?>

    <section class="section-content" id="contentSec">
        <div class="bg-blob blob-1"></div>
        <div class="bg-blob blob-2"></div>
        <div class="bg-blob blob-3"></div>

        <div class="timeline-wrap">
            <div class="timeline-line"></div>
            
            <?php if(!empty($events)): ?>
                <?php foreach($events as $e): 
                    $ts = strtotime($e['event_date']);
                    $day = date('d', $ts);
                    $ym = date('M.Y', $ts);
                    
                    $raw_img = $e['image_url'];
                    $imgs = [];

                    if (!empty($raw_img)) {
                        $decoded = json_decode($raw_img, true);
                        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                            $imgs = $decoded;
                        } 
                        elseif (strpos($raw_img, ',') !== false) {
                            $imgs = explode(',', $raw_img);
                        } 
                        else {
                            $imgs = [$raw_img];
                        }
                    }

                    $imgs = array_filter(array_map('trim', $imgs));
                    $count = count($imgs);
                    
                    $gridClass = 'grid-1';
                    if ($count == 2 || $count == 4) $gridClass = 'grid-2';
                    elseif ($count >= 3) $gridClass = 'grid-3';
                ?>
                <div class="tl-node">
                    <div class="tl-point"></div>
                    <div class="tl-card">
                        <div class="tl-header">
                            <div class="tl-date-badge">
                                <span class="date-day"><?= $day ?></span>
                                <span class="date-ym"><?= $ym ?></span>
                            </div>
                            <h3 class="tl-title"><?= htmlspecialchars($e['title']) ?></h3>
                        </div>
                        
                        <?php if($count > 0): ?>
                        <div class="img-grid-container <?= $gridClass ?>">
                            <?php foreach($imgs as $url): ?>
                                <?php if(!empty($url)): ?>
                                <img src="<?= getCosThumb(htmlspecialchars($url), 600) ?>" class="img-item" data-src="<?= htmlspecialchars($url) ?>" loading="lazy" alt="Moment">
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                        
                        <div class="tl-desc"><?= nl2br(htmlspecialchars($e['description'])) ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div style="text-align:center; position:relative; z-index:10; padding:100px 0;">
                    <div class="empty-timeline-placeholder">
                        <i class="fa-regular fa-folder-open"></i>
                        <span>暂无记录，期待我们的第一次点滴...</span>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </section>
</div>

<script>
    const loveConfig = {
        startDate: "<?= htmlspecialchars($start_date) ?>",
        isLogin: <?= json_encode($is_user_login) ?>,
        userAvatar: "<?= htmlspecialchars($user_avatar) ?>",
        isLetterEnabled: <?= json_encode($letter_enabled) ?>
    };
</script>
<script src="/pages/assets/js/love.js?v=<?php echo time(); ?>"></script>