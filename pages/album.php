<?php
// pages/album.php
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
// 1. 引入公共头部
require_once 'includes/header.php';

// --- [新增] 腾讯云 COS 缩略图生成助手函数 ---
// 动态拼接腾讯云图片处理参数：指定宽度等比缩放、开启渐进式加载(interlace/1)、图片质量80%
function getCosThumb($url, $width = 600) {
    if (empty($url)) return $url;
    // 如果不是 http 开头，或者 URL 已经带有参数，则为了安全原样返回
    if (strpos($url, 'http') !== 0 || strpos($url, '?') !== false) {
        return $url;
    }
    return $url . '?imageMogr2/thumbnail/' . $width . 'x/interlace/1/q/80';
}

// --- [优化] 2. 使用缓存获取画廊数据 ---

// A. 获取 Hero 推荐大图 (先读缓存)
$heroes = Cache::get('photos_featured');
if ($heroes === false) {
    // 缓存未命中，查询数据库
    $stmt_hero = $pdo->query("
        SELECT p.id, p.album_id, p.title, p.device, p.image_url, a.name as album_name 
        FROM photos p 
        LEFT JOIN albums a ON p.album_id = a.id 
        WHERE p.is_featured = 1 AND p.is_hidden = 0
        ORDER BY p.id DESC
    ");
    $heroes = $stmt_hero->fetchAll();
    // 将结果写入缓存，有效期15分钟 (900秒)
    Cache::set('photos_featured', $heroes, 900);
}

// B. 获取相册分类 (先读缓存)
$albums = Cache::get('albums_list_with_count');
if ($albums === false) {
    // 缓存未命中，查询数据库
    $stmt_albums = $pdo->query("
        SELECT a.*, (SELECT COUNT(*) FROM photos WHERE album_id = a.id AND is_hidden = 0) as photo_count 
        FROM albums a 
        WHERE a.is_hidden = 0 
        ORDER BY a.sort_order ASC
    ");
    $albums = $stmt_albums->fetchAll();
    // 将结果写入缓存，有效期1小时 (3600秒)
    Cache::set('albums_list_with_count', $albums, 3600);
}

// C. 获取所有照片 (先读缓存) - 【警告】此部分数据量大时，应改为API分页加载
$photos = Cache::get('photos_all_visible');
if ($photos === false) {
    // 缓存未命中，查询数据库
    $stmt_photos = $pdo->query("
        SELECT p.id, p.album_id, p.title, p.device, p.image_url, p.is_featured, a.name as album_name 
        FROM photos p 
        LEFT JOIN albums a ON p.album_id = a.id 
        WHERE p.is_hidden = 0
        ORDER BY p.id DESC
    ");
    $photos = $stmt_photos->fetchAll();
    // 将结果写入缓存，有效期15分钟 (900秒)
    Cache::set('photos_all_visible', $photos, 900);
}
?>

<link rel="stylesheet" href="/pages/assets/css/album.css?v=<?php echo time(); ?>">

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
                <div class="stat-box"><span class="stat-num"><?= count($photos) ?></span><span class="stat-label">Photos</span></div>
                <div class="stat-box"><span class="stat-num"><?= count($albums) ?></span><span class="stat-label">Albums</span></div>
            </div>
        </div>

        <div class="hero-slider-wrapper">
            <?php if(count($heroes) > 0): ?>
                <?php foreach($heroes as $index => $hero): ?>
                    <div class="hero-slide <?= $index === 0 ? 'active' : '' ?>" data-index="<?= $index ?>" onclick="openLightbox('<?= h($hero['image_url']) ?>')">
                        <div class="overlay-gradient"></div>
                        <img src="<?= getCosThumb(h($hero['image_url']), 1200) ?>" class="featured-bg" alt="Featured">
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
                <?php $cover = !empty($photos[0]['image_url']) ? $photos[0]['image_url'] : 'https://placehold.co/400x300'; ?>
                <img src="<?= getCosThumb(h($cover), 400) ?>" class="folder-preview">
                <div class="folder-count"><?= count($photos) ?></div>
            </div>
            <?php foreach($albums as $album): ?>
            <div class="folder-card" onclick="filterGallery('album-<?= $album['id'] ?>', this)">
                <div class="folder-tab"><?= h($album['name']) ?></div>
                <img src="<?= getCosThumb(h($album['cover_image']), 400) ?>" class="folder-preview">
                <div class="folder-count"><?= $album['photo_count'] ?></div>
            </div>
            <?php endforeach; ?>
            <div style="width: 1px; flex-shrink: 0;"></div>
        </div>
        <button class="scroll-arrow right" onclick="scrollCategory('right')"><i class="fa-solid fa-chevron-right"></i></button>
    </div>

    <div class="gallery-masonry" id="galleryGrid">
        <?php foreach($photos as $photo): ?>
        <div class="art-card album-<?= $photo['album_id'] ?>" onclick="openLightbox('<?= h($photo['image_url']) ?>')">
            <?php if($photo['album_name']): ?><div class="art-badge"><?= h($photo['album_name']) ?></div><?php endif; ?>
            <img src="<?= getCosThumb(h($photo['image_url']), 600) ?>" class="art-img" loading="lazy" alt="<?= h($photo['title']) ?>">
            <div class="art-info-panel">
                <div class="art-text">
                    <h3><?= h($photo['title'] ?: 'Untitled') ?></h3>
                    <p><?= h($photo['device']) ?></p>
                </div>
                <div class="art-action"><i class="fa-solid fa-arrow-right"></i></div>
            </div>
        </div>
        <?php endforeach; ?>
        <?php if(empty($photos)): ?><div style="text-align:center; width:100%; grid-column:1/-1; padding:50px; color:#999;">暂无照片</div><?php endif; ?>
    </div>
    
    <div style="text-align:center; padding: 20px;">
        <button style="padding: 10px 25px; border-radius: 30px; border: 1px solid #000; background: transparent; font-weight: bold; cursor: pointer; transition: 0.3s; font-size: 12px;" onmouseover="this.style.background='#000'; this.style.color='#fff'" onmouseout="this.style.background='transparent'; this.style.color='#000'">LOAD MORE</button>
    </div>

</div>

<div class="lightbox" id="lightbox">
    <div class="lb-close" onclick="closeLightbox()">&times;</div>
    <img src="" class="lb-img" id="lbImage">
</div>
<script src="/pages/assets/js/album.js?v=<?php echo time(); ?>"></script>

</body>
</html>