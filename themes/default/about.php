<?php
// themes/default/about.php
/**
 * default 主题 - 关于本站 (超级引擎适配版)
 **/
// 1. 引入主题专属头部
require_once __DIR__ . '/header.php';

// ======== 1. 获取实时统计数据 ========
global $pdo; 
$stats_total = $pdo->query("SELECT SUM(views) FROM articles")->fetchColumn() ?: 0;
$stats_month = $pdo->query("SELECT COUNT(*) FROM comments")->fetchColumn() ?: 0;
$stats_today = $pdo->query("SELECT COUNT(*) FROM articles")->fetchColumn() ?: 0;

// ======== 2. 核心机制：带降级防御的配置获取器 ========
// THEME_NAME 常量在根目录 index.php 已定义
$theme_prefix = 'theme_' . THEME_NAME . '_';

if (!function_exists('t_conf')) {
    function t_conf($key, $default = '') {
        global $theme_prefix;
        // 逻辑：优先读取当前主题配置 -> 其次读取系统老配置 -> 最后使用默认值
        $theme_val = conf($theme_prefix . $key);
        if ($theme_val !== '' && $theme_val !== null) {
            return $theme_val;
        }
        return conf($key, $default);
    }
}

// ======== 3. 获取基础全局配置 (非主题特定) ========
$author_name = conf('author_name', '江硕');
$author_bio = conf('author_bio', '全栈开发者 / UI设计爱好者');
$author_avatar = conf('author_avatar', 'https://images.unsplash.com/photo-1535713875002-d1d0cf377fde?ixlib=rb-4.0.3&auto=format&fit=crop&w=200&q=80');

// ======== 4. 获取并解析主题独立配置 ========
// 头像标签
$avatar_tags_raw = t_conf('about_avatar_tags', '[]');
$avatar_tags = json_decode(htmlspecialchars_decode($avatar_tags_raw), true) ?: [];
$avatar_tags = array_pad($avatar_tags, 8, "未设置");

// 追剧动漫封面
$anime_covers_raw = t_conf('about_anime_covers', '[]');
$anime_covers = json_decode(htmlspecialchars_decode($anime_covers_raw), true) ?: [];

// 生涯模块数据
$career_events_raw = t_conf('about_career_events', '[]');
$career_events = json_decode(htmlspecialchars_decode($career_events_raw), true) ?: [];

// 生涯时间轴坐标
$career_axis_raw = t_conf('about_career_axis', '[]');
$career_axis = json_decode(htmlspecialchars_decode($career_axis_raw), true) ?: [];
?>

<link rel="stylesheet" href="<?= THEME_URL ?>/assets/css/about.css?v=<?= time() ?>">

<style>
    /* 解决内容与头部导航栏两边宽度不对齐的问题 */
    .about-page-container {
        max-width: 100% !important;
        padding-left: 0 !important;
        padding-right: 0 !important;
    }
</style>

<div class="apple-ambient-bg">
    <div class="blob blob-1"></div>
    <div class="blob blob-2"></div>
    <div class="blob blob-3"></div>
</div>

<div class="about-page-container">
    
    <div class="about-header-section">
        <div class="avatar-interactive-zone">
            <div class="about-avatar-wrapper">
                <img src="<?= htmlspecialchars($author_avatar) ?>" alt="Avatar">
                <div class="status-dot"></div>
                
                <div class="f-tag tag-l1"><i class="fa-solid fa-code"></i> <?= htmlspecialchars($avatar_tags[0]) ?></div>
                <div class="f-tag tag-l2"><i class="fa-solid fa-server"></i> <?= htmlspecialchars($avatar_tags[1]) ?></div>
                <div class="f-tag tag-l3"><i class="fa-solid fa-shield-halved"></i> <?= htmlspecialchars($avatar_tags[2]) ?></div>
                <div class="f-tag tag-l4"><i class="fa-solid fa-bug"></i> <?= htmlspecialchars($avatar_tags[3]) ?></div>

                <div class="f-tag tag-r1"><?= htmlspecialchars($avatar_tags[4]) ?> <i class="fa-solid fa-magnifying-glass"></i></div>
                <div class="f-tag tag-r2"><?= htmlspecialchars($avatar_tags[5]) ?> <i class="fa-solid fa-share-nodes"></i></div>
                <div class="f-tag tag-r3"><?= htmlspecialchars($avatar_tags[6]) ?> <i class="fa-solid fa-feather"></i></div>
                <div class="f-tag tag-r4"><?= htmlspecialchars($avatar_tags[7]) ?> <i class="fa-solid fa-book"></i></div>
            </div>
        </div>
        <h1 class="about-page-title">关于本站</h1>
    </div>

    <div class="bento-grid">
        
        <div class="bento-card bento-intro col-span-2">
            <div class="intro-badge">你好，很高兴认识你 👋</div>
            <h2>我叫 <?= htmlspecialchars($author_name) ?></h2>
            <p><?= htmlspecialchars($author_bio) ?></p>
        </div>

        <div class="bento-card bento-motto col-span-2">
            <span class="card-sm-title"><?= htmlspecialchars(t_conf('about_motto_tag', '追求')) ?></span>
            <h2><?= htmlspecialchars_decode(t_conf('about_motto_title', '源于<br>热爱而去创造')) ?></h2>
            <div class="motto-tag">代码与设计</div>
        </div>

        <div class="bento-card bento-skills col-span-2">
            <div class="skills-header">
                <span class="card-sm-title">技能栈</span>
                <h3>开启创造力</h3>
            </div>
            <div class="skills-scroll-area">
                <div class="bento-icon-scroll row-1">
                    <div class="bento-icon-item" style="background: #ff7675;"><i class="fa-brands fa-php"></i></div>
                    <div class="bento-icon-item" style="background: #74b9ff;"><i class="fa-brands fa-vuejs"></i></div>
                    <div class="bento-icon-item" style="background: #55efc4; color:#000;"><i class="fa-brands fa-js"></i></div>
                    <div class="bento-icon-item" style="background: #a29bfe;"><i class="fa-solid fa-database"></i></div>
                    <div class="bento-icon-item" style="background: #fdcb6e; color:#000;"><i class="fa-brands fa-html5"></i></div>
                    <div class="bento-icon-item" style="background: #6c5ce7;"><i class="fa-brands fa-css3-alt"></i></div>
                    <div class="bento-icon-item" style="background: #e17055;"><i class="fa-brands fa-figma"></i></div>
                    <div class="bento-icon-item" style="background: #ff7675;"><i class="fa-brands fa-php"></i></div>
                    <div class="bento-icon-item" style="background: #74b9ff;"><i class="fa-brands fa-vuejs"></i></div>
                </div>
                <div class="bento-icon-scroll row-2">
                    <div class="bento-icon-item" style="background: #e84393;"><i class="fa-solid fa-server"></i></div>
                    <div class="bento-icon-item" style="background: #d63031;"><i class="fa-brands fa-git-alt"></i></div>
                    <div class="bento-icon-item" style="background: #f1c40f; color:#000;"><i class="fa-brands fa-linux"></i></div>
                    <div class="bento-icon-item" style="background: #00cec9;"><i class="fa-solid fa-shield-halved"></i></div>
                    <div class="bento-icon-item" style="background: #2d3436;"><i class="fa-solid fa-terminal"></i></div>
                    <div class="bento-icon-item" style="background: #0984e3;"><i class="fa-brands fa-bootstrap"></i></div>
                </div>
            </div>
            <div class="skills-footer">
                <span>前端开发</span><span class="dot"></span>
                <span>后端架构</span><span class="dot"></span>
                <span>UI设计</span><span class="dot"></span>
                <span>服务器运维</span>
            </div>
        </div>

        <div class="bento-card bento-experience col-span-2">
            <span class="card-sm-title">生涯</span>
            <h3>无限进步</h3>
            
            <div class="career-legend">
                <div class="legend-item"><span class="lg-dot bg-blue"></span> 相关专业经历</div>
                <div class="legend-item"><span class="lg-dot bg-red"></span> UI / 全栈 / 研发经历</div>
            </div>

            <div class="career-chart-area">
                <div class="career-chart-scroll">
                    <div class="career-timeline">
                        <?php if(!empty($career_events)): ?>
                            <?php foreach($career_events as $event): ?>
                            <div class="t-bar <?= htmlspecialchars($event['color']) ?>" style="left: <?= htmlspecialchars($event['left']) ?>%; width: <?= htmlspecialchars($event['width']) ?>%; top: <?= htmlspecialchars($event['top']) ?>px;">
                                <div class="t-label <?= htmlspecialchars($event['pos']) ?>"><i class="fa-solid <?= htmlspecialchars($event['icon']) ?>"></i> <?= htmlspecialchars($event['title']) ?></div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <div class="career-axis">
                        <div class="axis-line"></div>
                        <?php if(!empty($career_axis)): ?>
                            <?php foreach($career_axis as $point): ?>
                            <span class="t-year" style="left: <?= htmlspecialchars($point['left']) ?>%;"><?= htmlspecialchars($point['text']) ?></span>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="bento-card bento-mbti col-span-1">
            <span class="card-sm-title">性格</span>
            <h3><?= htmlspecialchars(t_conf('about_mbti_name', '调停者')) ?><br>
                <?= htmlspecialchars(t_conf('about_mbti_type', 'INFP-T')) ?>
            </h3>
            <i class="fa-solid <?= htmlspecialchars(t_conf('about_mbti_icon', 'fa-leaf')) ?> mbti-icon"></i>
        </div>

        <div class="bento-card bento-photo col-span-1" style="background-image: url('<?= htmlspecialchars(t_conf('about_photo_bg')) ?>');">
        </div>

        <div class="bento-card bento-tag col-span-1">
            <span class="card-sm-title">信仰</span>
            <h3><?= htmlspecialchars_decode(t_conf('about_belief_title', '披荆斩棘之路，<br>劈风斩浪。')) ?></h3>
        </div>
        
        <div class="bento-card bento-tag col-span-1">
            <span class="card-sm-title">特长</span>
            <h3><?= htmlspecialchars_decode(t_conf('about_specialty_title', '高质感 UI<br>全栈开发<br>折腾能力 大师级')) ?></h3>
        </div>

        <div class="bento-card bento-hobby col-span-2" style="background-image: url('<?= htmlspecialchars(t_conf('about_game_bg')) ?>');">
            <div class="hobby-overlay">
                <span class="card-sm-title" style="color: rgba(255,255,255,0.8);">游戏热爱</span>
                <h3 style="color: #ffffff;"><?= htmlspecialchars(t_conf('about_game_title', '单机与主机游戏')) ?></h3>
            </div>
        </div>

        <div class="bento-card bento-hobby col-span-2" style="background-image: url('<?= htmlspecialchars(t_conf('about_tech_bg')) ?>');">
            <div class="hobby-overlay">
                <span class="card-sm-title" style="color: rgba(255,255,255,0.8);">数码科技</span>
                <h3 style="color: #ffffff;"><?= htmlspecialchars(t_conf('about_tech_title', '极客外设控')) ?></h3>
            </div>
        </div>

        <div class="bento-card bento-anime col-span-2">
            <div class="anime-grid">
                <?php if(!empty($anime_covers)): ?>
                    <?php foreach($anime_covers as $cover): ?>
                        <div class="anime-item" style="background-image: url('<?= htmlspecialchars($cover) ?>');"></div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <div class="hobby-overlay pointer-pass">
                <span class="card-sm-title" style="color: rgba(255,255,255,0.8);">近期追番 / 剧集</span>
                <h3 style="color: #ffffff;">动漫与热播剧</h3>
            </div>
        </div>

        <div class="bento-card bento-music col-span-2" style="background-image: url('<?= htmlspecialchars(t_conf('about_music_bg')) ?>');">
            <div class="music-overlay">
                <div class="music-top-text">
                    <span class="card-sm-title" style="color: rgba(255,255,255,0.8);">音乐偏好</span>
                    <h3 class="music-title"><?= htmlspecialchars(t_conf('about_music_title', '华语流行')) ?></h3>
                </div>
                <div class="music-bottom-bar">
                    <span class="music-desc">跟 <?= htmlspecialchars($author_name) ?> 一起欣赏更多音乐</span>
                    <a href="/music" class="music-btn">更多推荐 <i class="fa-solid fa-arrow-right"></i></a>
                </div>
            </div>
        </div>

        <div class="bento-card bento-stats col-span-1">
            <span class="card-sm-title">建站数据</span>
            <h3>访问统计</h3>
            <div class="stats-list">
                <div class="stat-box">
                    <span class="stat-num"><?= number_format($stats_today) ?></span>
                    <span class="stat-label">文章总数</span>
                </div>
                <div class="stat-box">
                    <span class="stat-num"><?= number_format($stats_month) ?></span>
                    <span class="stat-label">互动评论</span>
                </div>
                <div class="stat-box">
                    <span class="stat-num"><?= number_format($stats_total) ?></span>
                    <span class="stat-label">总阅读量</span>
                </div>
            </div>
        </div>

        <div class="bento-card bento-location col-span-3">
            <div class="loc-map-bg"></div>
            <div class="loc-content">
                <div class="loc-current">
                    <i class="fa-solid fa-location-dot"></i> 我现在住在 <strong><?= htmlspecialchars(t_conf('about_location_city', '中国')) ?></strong>
                </div>
                <div class="loc-details">
                    <div class="loc-item">
                        <i class="fa-solid fa-cake-candles"></i>
                        <span><?= htmlspecialchars(t_conf('about_loc_birth', '199X 出生')) ?></span>
                    </div>
                    <div class="loc-item">
                        <i class="fa-solid fa-graduation-cap"></i>
                        <span><?= htmlspecialchars(t_conf('about_loc_major', '计算机科学')) ?></span>
                    </div>
                    <div class="loc-item">
                        <i class="fa-solid fa-laptop-code"></i>
                        <span><?= htmlspecialchars(t_conf('about_loc_job', '全栈开发')) ?></span>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <div class="bento-card about-footer-block">
        <span class="card-sm-title">心路历程</span>
        <h2>为什么建站？</h2>
        <div class="story-content">
            <?= htmlspecialchars_decode(t_conf('about_journey_content', '<p>建立这个站点的初衷，是希望有一个属于自己的<strong>数字后花园</strong>。</p>')) ?>
        </div>
    </div>
</div>

<?php 
// 引入当前主题的全局尾部视图
require_once __DIR__ . '/footer.php'; 
?>