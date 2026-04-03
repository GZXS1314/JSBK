<?php
// themes/default/love.php
/**
 * default 主题 情侣空间视图
 **/

// 引入主题专属头部
require_once __DIR__ . '/header.php';

// --- 缩略图助手 ---
if (!function_exists('getCosThumb')) {
    function getCosThumb($url, $width = 600) {
        if (empty($url)) return $url;
        if (strpos($url, 'http') !== 0 || strpos($url, '?') !== false) return $url;
        return $url . '?imageMogr2/thumbnail/' . $width . 'x/interlace/1/q/80';
    }
}

// 兼容读取系统前缀配置 (让情侣空间也支持后台主题独立设置，虽然目前是在旧表里)
// 1. 获取配置 (使用极致优化的 Cache 类)
$love_config = Cache::get('love_config');
if ($love_config === false) {
    // 假设 $pdo 在 header.php 里已经全局化，直接使用
    $stmt = $pdo->query("SELECT key_name, value FROM settings WHERE key_name LIKE 'love_%'");
    $love_config = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    Cache::set('love_config', $love_config, 86400); 
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

// --- 2. 获取动态列表 (使用 Cache 类) ---
$events = Cache::get('love_events');
if ($events === false) {
    $stmt = $pdo->query("SELECT * FROM love_events ORDER BY event_date DESC, id DESC");
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
    Cache::set('love_events', $events, 3600);
}

// --- 3. 提前拉取真实的弹幕数据 ---
$wishes = Cache::get('love_wishes_list');
if ($wishes === false) {
    $stmt = $pdo->query("SELECT nickname, avatar, content FROM love_wishes ORDER BY id DESC LIMIT 50");
    $wishes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($wishes)) {
        // 如果数据库为空，给个初始假数据
        $wishes = [['nickname' => 'Sys', 'avatar' => 'https://ui-avatars.com/api/?name=S', 'content' => '愿时光温柔以待~']];
    }
    Cache::set('love_wishes_list', $wishes, 300); // 缓存 5 分钟
}

$is_user_login = isset($_SESSION['user_id']);
$user_avatar = $_SESSION['avatar'] ?? 'https://ui-avatars.com/api/?name=Guest';
?>

<link href="https://fonts.loli.net/css2?family=Lato:wght@300;400;700&family=Noto+Serif+SC:wght@400;700&family=Ma+Shan+Zheng&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= THEME_URL ?>/assets/css/love.css?v=<?= time() ?>">
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
                <input type="text" id="wishInput" class="wish-input-field" placeholder="<?= $is_user_login ? '写下祝福，发送弹幕...' : '请先登录后发送祝福...' ?>" <?= $is_user_login ? '' : 'disabled' ?>>
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
        isLetterEnabled: <?= json_encode($letter_enabled) ?>,
        csrfToken: "<?= $_SESSION['csrf_token'] ?? '' ?>",
        initialWishes: <?= json_encode($wishes, JSON_UNESCAPED_UNICODE) ?>
    };
</script>

<script src="<?= THEME_URL ?>/assets/js/love.js?v=<?= time() ?>"></script>

<?php
// 引入当前主题的全局尾部视图 
require_once __DIR__ . '/footer.php'; 
?>