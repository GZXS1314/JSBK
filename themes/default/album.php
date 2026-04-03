<?php
// themes/default/album.php
/**
 * default 主题相册视图文件
 **/

// 引入当前主题的全局头部视图
require_once __DIR__ . '/header.php';

// ---------------------------------------------------------
// 补充辅助函数
// ---------------------------------------------------------
if (!function_exists('h')) {
    function h($str) {
        return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
    }
}

function isVideo($url) {
    if (empty($url)) return false;
    $ext = strtolower(pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
    return in_array($ext, ['mp4', 'webm', 'mov']);
}

function getCosThumb($url, $width = 600) {
    if (empty($url)) return $url;
    if (isVideo($url)) return $url;
    if (strpos($url, 'http') !== 0 || strpos($url, '?') !== false) return $url;
    return $url . '?imageMogr2/thumbnail/' . $width . 'x/interlace/1/q/80';
}

// ---------------------------------------------------------
// 获取数据 (使用之前配置好的 $pdo 和 Cache)
// ---------------------------------------------------------
// A. 获取 Hero 推荐大图
$heroes = Cache::get('photos_featured');
if ($heroes === false) {
    $stmt_hero = $pdo->query("SELECT p.id, p.album_id, p.title, p.device, p.image_url, p.video_cover, a.name as album_name FROM photos p LEFT JOIN albums a ON p.album_id = a.id WHERE p.is_featured = 1 AND p.is_hidden = 0 ORDER BY p.id DESC");
    $heroes = $stmt_hero->fetchAll(PDO::FETCH_ASSOC);
    Cache::set('photos_featured', $heroes, 900);
}

// B. 获取相册分类
$albums = Cache::get('albums_list_with_count');
if ($albums === false) {
    $stmt_albums = $pdo->query("SELECT a.*, (SELECT COUNT(*) FROM photos WHERE album_id = a.id AND is_hidden = 0) as photo_count FROM albums a WHERE a.is_hidden = 0 ORDER BY a.sort_order ASC");
    $albums = $stmt_albums->fetchAll(PDO::FETCH_ASSOC);
    Cache::set('albums_list_with_count', $albums, 3600);
}

// C. 获取所有照片
$photos = Cache::get('photos_all_visible');
if ($photos === false) {
    $stmt_photos = $pdo->query("SELECT p.id, p.album_id, p.title, p.device, p.image_url, p.video_cover, p.is_featured, a.name as album_name FROM photos p LEFT JOIN albums a ON p.album_id = a.id WHERE p.is_hidden = 0 ORDER BY p.id DESC");
    $photos = $stmt_photos->fetchAll(PDO::FETCH_ASSOC);
    Cache::set('photos_all_visible', $photos, 900);
}

// 【核心优化】：在 PHP 端提前处理好缩略图和视频标记，减轻 JS 负担
$processed_photos = [];
foreach ($photos as $p) {
    $p['is_video'] = isVideo($p['image_url']);
    $p['thumb_url'] = getCosThumb($p['image_url'], 600);
    // 过滤可能导致 XSS 的字符
    $p['title'] = htmlspecialchars($p['title'] ?: 'Untitled');
    $p['device'] = htmlspecialchars($p['device'] ?: '');
    $p['album_name'] = htmlspecialchars($p['album_name'] ?: '');
    $p['video_cover'] = htmlspecialchars($p['video_cover'] ?? '');
    $p['image_url'] = htmlspecialchars($p['image_url']);
    $processed_photos[] = $p;
}

// 获取主题动态前缀配置
$theme_prefix = 'theme_' . THEME_NAME . '_';
?>

<link rel="stylesheet" href="<?= THEME_URL ?>/assets/css/album.css?v=<?= time() ?>">

<div class="page-wrapper">
    
    <div class="hero-grid">
        <div class="glass profile-card-gallery">
            <div class="glow-blob"></div>
            <div class="glow-blob two"></div>
            <div class="avatar-wrap"><img src="<?= conf('author_avatar', 'https://placehold.co/200') ?>" class="avatar-img"></div>
            <h2 class="profile-name"><?= conf('author_name', 'Photographer') ?></h2>
            <p class="profile-bio"><?= conf('author_bio', 'Capturing light and shadow.') ?></p>

            <?php if(conf('social_jump_name') && conf('social_jump_url')): ?>
            <a href="<?= conf('social_jump_url') ?>" target="_blank" class="social-link-btn">
                <i class="<?= conf('social_jump_icon', 'fa-solid fa-link') ?>"></i> 
                <span><?= conf('social_jump_name') ?></span>
            </a>
            <?php endif; ?>

            <div class="social-row">
                <?php if(conf('social_github')): ?><a href="<?= conf('social_github') ?>" target="_blank" class="social-btn github" data-tooltip="Github"><i class="fa-brands fa-github"></i></a><?php endif; ?>
                <?php if(conf('social_twitter')): ?><a href="<?= conf('social_twitter') ?>" target="_blank" class="social-btn twitter" data-tooltip="Twitter / X"><i class="fa-brands fa-twitter"></i></a><?php endif; ?>
                <?php if(conf('social_email')): ?><a href="mailto:<?= conf('social_email') ?>" class="social-btn email" data-tooltip="Email"><i class="fa-solid fa-envelope"></i></a><?php endif; ?>
                <?php if(conf('wechat_qrcode')): ?><a href="javascript:;" onclick="openLightbox('<?= conf('wechat_qrcode') ?>')" class="social-btn wechat" data-tooltip="微信"><i class="fa-brands fa-weixin"></i></a><?php endif; ?>
            </div>

            <div class="stats-grid">
                <div class="stat-box"><span class="stat-num" id="totalPhotoCount"><?= count($photos) ?></span><span class="stat-label">Photos</span></div>
                <div class="stat-box"><span class="stat-num"><?= count($albums) ?></span><span class="stat-label">Albums</span></div>
            </div>
        </div>

        <div class="hero-slider-wrapper">
            <?php if(count($heroes) > 0): ?>
                <?php foreach($heroes as $index => $hero): ?>
                    <?php $is_vid = isVideo($hero['image_url']); ?>
                    <div class="hero-slide <?= $index === 0 ? 'active' : '' ?>" data-index="<?= $index ?>" onclick="openLightbox('<?= h($hero['image_url']) ?>', <?= $is_vid ? 'true' : 'false' ?>, '<?= h($hero['video_cover'] ?? '') ?>')">
                        <div class="overlay-gradient"></div>
                        
                        <?php if($is_vid): ?>
                            <video src="<?= h($hero['image_url']) ?>" <?= !empty($hero['video_cover']) ? 'poster="'.h($hero['video_cover']).'"' : '' ?> class="featured-bg" loop muted playsinline onmouseover="this.play()" onmouseout="this.pause(); this.currentTime = 0;"></video>
                        <?php else: ?>
                            <img src="<?= getCosThumb(h($hero['image_url']), 1200) ?>" class="featured-bg" alt="Featured">
                        <?php endif; ?>
                        
                        <div class="featured-content">
                            <span class="featured-tag">FEATURED</span>
                            <h1 class="featured-title"><?= h($hero['title'] ?: 'Untitled') ?></h1>
                            <p class="featured-desc"><?= h($hero['device'] ?: 'Shot on Camera') ?> <?= $hero['album_name'] ? '· '.h($hero['album_name']) : '' ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <?php if(count($heroes) > 1): ?>
                    <div class="slider-btn prev-btn" onclick="prevSlide(event)"><i class="fa-solid fa-chevron-left"></i></div>
                    <div class="slider-btn next-btn" onclick="nextSlide(event)"><i class="fa-solid fa-chevron-right"></i></div>
                    <div class="slider-dots">
                        <?php foreach($heroes as $index => $hero): ?><div class="slider-dot <?= $index === 0 ? 'active' : '' ?>" onclick="goToSlide(<?= $index ?>, event)"></div><?php endforeach; ?>
                    </div>
                <?php endif; ?>

            <?php else: ?>
                <div class="hero-slide active" style="background:#ddd; display:flex; align-items:center; justify-content:center; color:#999; pointer-events:none;"><div style="text-align:center;"><i class="fa-solid fa-image" style="font-size:48px; margin-bottom:10px;"></i><p>暂无 Featured 推荐图</p></div></div>
            <?php endif; ?>
        </div>
    </div>

    <div class="category-wrapper">
        <button class="scroll-arrow left" onclick="scrollCategory('left')"><i class="fa-solid fa-chevron-left"></i></button>
        <div class="category-scroll-container" id="categoryScroll">
            <div class="folder-card active" onclick="filterGallery('all', this)">
                <div class="folder-tab">All</div>
                <?php 
                    $cover = !empty($photos[0]['image_url']) ? $photos[0]['image_url'] : 'https://placehold.co/400x300'; 
                    $is_vid_cover = isVideo($cover);
                    $all_poster = !empty($photos[0]['video_cover']) ? $photos[0]['video_cover'] : '';
                ?>
                <?php if($is_vid_cover): ?>
                    <video src="<?= h($cover) ?>" <?= !empty($all_poster) ? 'poster="'.h($all_poster).'"' : '' ?> class="folder-preview" loop muted playsinline onmouseover="this.play()" onmouseout="this.pause(); this.currentTime = 0;"></video>
                <?php else: ?>
                    <img src="<?= getCosThumb(h($cover), 400) ?>" class="folder-preview">
                <?php endif; ?>
                <div class="folder-count"><?= count($photos) ?></div>
            </div>
            
            <?php foreach($albums as $album): ?>
            <div class="folder-card" onclick="filterGallery('album-<?= $album['id'] ?>', this)">
                <div class="folder-tab"><?= h($album['name']) ?></div>
                <?php $is_vid_album = isVideo($album['cover_image']); ?>
                <?php if($is_vid_album): ?>
                    <video src="<?= h($album['cover_image']) ?>" class="folder-preview" loop muted playsinline onmouseover="this.play()" onmouseout="this.pause(); this.currentTime = 0;"></video>
                <?php else: ?>
                    <img src="<?= getCosThumb(h($album['cover_image']), 400) ?>" class="folder-preview">
                <?php endif; ?>
                <div class="folder-count"><?= $album['photo_count'] ?></div>
            </div>
            <?php endforeach; ?>
            <div style="width: 1px; flex-shrink: 0;"></div>
        </div>
        <button class="scroll-arrow right" onclick="scrollCategory('right')"><i class="fa-solid fa-chevron-right"></i></button>
    </div>

    <div class="gallery-masonry" id="galleryGrid"></div>
    
    <div style="text-align:center; padding: 20px;">
        <button id="loadMoreBtn" onclick="loadMorePhotos()" style="padding: 12px 30px; border-radius: 30px; border: 2px solid #111; background: transparent; font-weight: 800; cursor: pointer; transition: 0.3s; font-size: 13px; letter-spacing: 1px; display: none;">LOAD MORE</button>
    </div>

</div>

<div class="lightbox" id="lightbox">
    <div class="lb-close" onclick="closeLightbox()">&times;</div>
    <img src="" class="lb-img" id="lbImage" style="display:none;">
    <video src="" class="lb-video" id="lbVideo" controls autoplay style="display:none;"></video>
</div>

<script>
    window.galleryAllData = <?= json_encode($processed_photos, JSON_UNESCAPED_UNICODE) ?>;
</script>

<script src="<?= THEME_URL ?>/assets/js/album.js?v=<?php echo time(); ?>"></script>

<?php 
// 引入当前主题的全局尾部视图 (它包含 </body> </html>)
require_once __DIR__ . '/footer.php'; 
?>