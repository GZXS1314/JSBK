<?php
// 引入公共头部
require_once 'includes/header.php';

// --- [优化] 1. 使用缓存获取数据 ---
$categories_db = Cache::get('categories_list');
if ($categories_db === false) {
    $stmt_cat = $pdo->query("SELECT * FROM categories WHERE is_hidden = 0 ORDER BY sort_order ASC, id DESC");
    $categories_db = $stmt_cat->fetchAll();
    Cache::set('categories_list', $categories_db, 3600);
}

$slides = Cache::get('articles_recommended');
if ($slides === false) {
    $stmt_rec = $pdo->prepare("SELECT id, title, category, cover_image, views, created_at FROM articles WHERE is_recommended = 1 AND is_hidden = 0 ORDER BY created_at DESC LIMIT 5");
    $stmt_rec->execute();
    $slides = $stmt_rec->fetchAll();
    Cache::set('articles_recommended', $slides, 600);
}

$friend_links = json_decode($site_config['friend_links'] ?? '[]', true);
$hot_tags_str = conf('hot_tags');
$hot_tags = $hot_tags_str ? explode(',', $hot_tags_str) : [];
$enable_chatroom = conf('enable_chatroom') == '1';
$enable_friend_links = conf('enable_friend_links') == '1';
$enable_hot_tags = conf('enable_hot_tags') == '1';

$slogan_main = str_replace('&lt;br&gt;', '<br>', htmlspecialchars_decode(conf('home_slogan_main', '人生如棋<br>落子无悔'))); 
$slogan_sub  = conf('home_slogan_sub', 'Code With Passion');
$btn1_text = conf('home_btn1_text', '项目');
$btn1_link = conf('home_btn1_link', 'javascript:void(0)');
$btn2_text = conf('home_btn2_text', '技术');
$btn2_link = conf('home_btn2_link', 'javascript:void(0)');
$btn3_text = conf('home_btn3_text', '生活');
$btn3_link = conf('home_btn3_link', 'javascript:void(0)');
?>

<div class="feature-section glass-card">
    <div class="chess-left">
        <div class="chess-bg-layer">
            <div class="icon-scroll row-1">
                <div class="icon-item"><i class="fa-brands fa-react"></i></div>
                <div class="icon-item"><i class="fa-brands fa-vuejs"></i></div>
                <div class="icon-item"><i class="fa-brands fa-angular"></i></div>
                <div class="icon-item"><i class="fa-brands fa-js"></i></div>
                <div class="icon-item"><i class="fa-brands fa-html5"></i></div>
                <div class="icon-item"><i class="fa-brands fa-css3-alt"></i></div>
                <div class="icon-item"><i class="fa-brands fa-sass"></i></div>
                <div class="icon-item"><i class="fa-brands fa-bootstrap"></i></div>
                <div class="icon-item"><i class="fa-brands fa-figma"></i></div>
                <div class="icon-item"><i class="fa-brands fa-wordpress"></i></div>
                <div class="icon-item"><i class="fa-brands fa-react"></i></div>
                <div class="icon-item"><i class="fa-brands fa-vuejs"></i></div>
                <div class="icon-item"><i class="fa-brands fa-angular"></i></div>
                <div class="icon-item"><i class="fa-brands fa-js"></i></div>
                <div class="icon-item"><i class="fa-brands fa-html5"></i></div>
                <div class="icon-item"><i class="fa-brands fa-react"></i></div>
                <div class="icon-item"><i class="fa-brands fa-vuejs"></i></div>
                <div class="icon-item"><i class="fa-brands fa-angular"></i></div>
                <div class="icon-item"><i class="fa-brands fa-js"></i></div>
                <div class="icon-item"><i class="fa-brands fa-html5"></i></div>
                <div class="icon-item"><i class="fa-brands fa-css3-alt"></i></div>
                <div class="icon-item"><i class="fa-brands fa-sass"></i></div>
                <div class="icon-item"><i class="fa-brands fa-bootstrap"></i></div>
                <div class="icon-item"><i class="fa-brands fa-figma"></i></div>
                <div class="icon-item"><i class="fa-brands fa-wordpress"></i></div>
                <div class="icon-item"><i class="fa-brands fa-react"></i></div>
                <div class="icon-item"><i class="fa-brands fa-vuejs"></i></div>
                <div class="icon-item"><i class="fa-brands fa-angular"></i></div>
                <div class="icon-item"><i class="fa-brands fa-js"></i></div>
                <div class="icon-item"><i class="fa-brands fa-html5"></i></div>
                <div class="icon-item"><i class="fa-brands fa-react"></i></div>
                <div class="icon-item"><i class="fa-brands fa-vuejs"></i></div>
                <div class="icon-item"><i class="fa-brands fa-angular"></i></div>
                <div class="icon-item"><i class="fa-brands fa-js"></i></div>
                <div class="icon-item"><i class="fa-brands fa-html5"></i></div>
                <div class="icon-item"><i class="fa-brands fa-css3-alt"></i></div>
                <div class="icon-item"><i class="fa-brands fa-sass"></i></div>
                <div class="icon-item"><i class="fa-brands fa-bootstrap"></i></div>
                <div class="icon-item"><i class="fa-brands fa-figma"></i></div>
                <div class="icon-item"><i class="fa-brands fa-wordpress"></i></div>
                <div class="icon-item"><i class="fa-brands fa-react"></i></div>
                <div class="icon-item"><i class="fa-brands fa-vuejs"></i></div>
                <div class="icon-item"><i class="fa-brands fa-angular"></i></div>
                <div class="icon-item"><i class="fa-brands fa-js"></i></div>
                <div class="icon-item"><i class="fa-brands fa-html5"></i></div>
            </div>
            
            <div class="icon-scroll row-2">
                <div class="icon-item"><i class="fa-brands fa-php"></i></div>
                <div class="icon-item"><i class="fa-brands fa-node"></i></div>
                <div class="icon-item"><i class="fa-brands fa-python"></i></div>
                <div class="icon-item"><i class="fa-brands fa-java"></i></div>
                <div class="icon-item"><i class="fa-brands fa-docker"></i></div>
                <div class="icon-item"><i class="fa-brands fa-git-alt"></i></div>
                <div class="icon-item"><i class="fa-brands fa-linux"></i></div>
                <div class="icon-item"><i class="fa-brands fa-aws"></i></div>
                <div class="icon-item"><i class="fa-solid fa-database"></i></div>
                <div class="icon-item"><i class="fa-solid fa-terminal"></i></div>
                <div class="icon-item"><i class="fa-brands fa-php"></i></div>
                <div class="icon-item"><i class="fa-brands fa-node"></i></div>
                <div class="icon-item"><i class="fa-brands fa-python"></i></div>
                <div class="icon-item"><i class="fa-brands fa-java"></i></div>
                <div class="icon-item"><i class="fa-brands fa-docker"></i></div>
                <div class="icon-item"><i class="fa-brands fa-php"></i></div>
                <div class="icon-item"><i class="fa-brands fa-node"></i></div>
                <div class="icon-item"><i class="fa-brands fa-python"></i></div>
                <div class="icon-item"><i class="fa-brands fa-java"></i></div>
                <div class="icon-item"><i class="fa-brands fa-docker"></i></div>
                <div class="icon-item"><i class="fa-brands fa-git-alt"></i></div>
                <div class="icon-item"><i class="fa-brands fa-linux"></i></div>
                <div class="icon-item"><i class="fa-brands fa-aws"></i></div>
                <div class="icon-item"><i class="fa-solid fa-database"></i></div>
                <div class="icon-item"><i class="fa-solid fa-terminal"></i></div>
                <div class="icon-item"><i class="fa-brands fa-php"></i></div>
                <div class="icon-item"><i class="fa-brands fa-node"></i></div>
                <div class="icon-item"><i class="fa-brands fa-python"></i></div>
                <div class="icon-item"><i class="fa-brands fa-java"></i></div>
                <div class="icon-item"><i class="fa-brands fa-docker"></i></div>
                <div class="icon-item"><i class="fa-brands fa-php"></i></div>
                <div class="icon-item"><i class="fa-brands fa-node"></i></div>
                <div class="icon-item"><i class="fa-brands fa-python"></i></div>
                <div class="icon-item"><i class="fa-brands fa-java"></i></div>
                <div class="icon-item"><i class="fa-brands fa-docker"></i></div>
                <div class="icon-item"><i class="fa-brands fa-git-alt"></i></div>
                <div class="icon-item"><i class="fa-brands fa-linux"></i></div>
                <div class="icon-item"><i class="fa-brands fa-aws"></i></div>
                <div class="icon-item"><i class="fa-solid fa-database"></i></div>
                <div class="icon-item"><i class="fa-solid fa-terminal"></i></div>
                <div class="icon-item"><i class="fa-brands fa-php"></i></div>
                <div class="icon-item"><i class="fa-brands fa-node"></i></div>
                <div class="icon-item"><i class="fa-brands fa-python"></i></div>
                <div class="icon-item"><i class="fa-brands fa-java"></i></div>
                <div class="icon-item"><i class="fa-brands fa-docker"></i></div>
                <div class="icon-item"><i class="fa-brands fa-php"></i></div>
                <div class="icon-item"><i class="fa-brands fa-node"></i></div>
                <div class="icon-item"><i class="fa-brands fa-python"></i></div>
                <div class="icon-item"><i class="fa-brands fa-java"></i></div>
                <div class="icon-item"><i class="fa-brands fa-docker"></i></div>
                <div class="icon-item"><i class="fa-brands fa-git-alt"></i></div>
                <div class="icon-item"><i class="fa-brands fa-linux"></i></div>
                <div class="icon-item"><i class="fa-brands fa-aws"></i></div>
                <div class="icon-item"><i class="fa-solid fa-database"></i></div>
                <div class="icon-item"><i class="fa-solid fa-terminal"></i></div>
                <div class="icon-item"><i class="fa-brands fa-php"></i></div>
                <div class="icon-item"><i class="fa-brands fa-node"></i></div>
                <div class="icon-item"><i class="fa-brands fa-python"></i></div>
                <div class="icon-item"><i class="fa-brands fa-java"></i></div>
                <div class="icon-item"><i class="fa-brands fa-docker"></i></div>
            </div>
        </div>

        <div class="chess-text-content">
            <div class="chess-title"><?= $slogan_main ?></div>
            <div class="chess-subtitle"><?= htmlspecialchars($slogan_sub) ?></div>
            <div class="chess-tags">
                <?php if($btn1_text): ?><div class="tag-item tag-project" onclick="window.location.href='<?= htmlspecialchars($btn1_link) ?>'"><?= htmlspecialchars($btn1_text) ?></div><?php endif; ?>
                <?php if($btn2_text): ?><div class="tag-item tag-university" onclick="window.location.href='<?= htmlspecialchars($btn2_link) ?>'"><?= htmlspecialchars($btn2_text) ?></div><?php endif; ?>
                <?php if($btn3_text): ?><div class="tag-item tag-life" onclick="window.location.href='<?= htmlspecialchars($btn3_link) ?>'"><?= htmlspecialchars($btn3_text) ?></div><?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="feature-right" id="sliderContainer">
        <?php if(empty($slides)): ?>
            <div style="width:100%; height:100%; display:flex; flex-direction:column; align-items:center; justify-content:center; color:#999; text-align:center; padding:20px;"><i class="fa-regular fa-image" style="font-size:48px; margin-bottom:15px;"></i><p>暂无推荐文章<br><span style="font-size:12px;">请在后台设置推荐</span></p></div>
        <?php else: ?>
            <button class="slider-btn prev-btn"><i class="fa-solid fa-chevron-left"></i></button>
            <div class="slider-track" id="sliderTrack">
                <?php foreach($slides as $slide): ?>
                <a href="javascript:void(0)" onclick="openArticle(<?= $slide['id'] ?>)" class="slider-item">
                    
                    <div class="slide-blur-bg" style="background-image: url('<?= htmlspecialchars(!empty($slide['cover_image']) ? $slide['cover_image'] : 'https://placehold.co/600x800?text=No+Image') ?>');"></div>
                    
                    <div class="slide-inner">
                        <div class="slide-cover-wrap">
                            <img src="<?= htmlspecialchars(!empty($slide['cover_image']) ? $slide['cover_image'] : 'https://placehold.co/600x800?text=No+Image') ?>" alt="<?= htmlspecialchars($slide['title']) ?>" loading="lazy">
                        </div>
                        
                        <div class="slide-content">
                            <span class="slide-tag"><?= htmlspecialchars($slide['category']) ?></span>
                            <h3 class="slide-title"><?= htmlspecialchars($slide['title']) ?></h3>
                            <div class="slide-meta">
                                <span><i class="fa-regular fa-eye"></i> <?= $slide['views'] ?> 浏览</span>
                                <span><i class="fa-regular fa-clock"></i> <?= date('Y-m-d', strtotime($slide['created_at'])) ?></span>
                            </div>
                            <div class="slide-read-btn">立即阅读 <i class="fa-solid fa-arrow-right"></i></div>
                        </div>
                    </div>

                </a>
                <?php endforeach; ?>
            </div>
            <button class="slider-btn next-btn"><i class="fa-solid fa-chevron-right"></i></button>
            <div class="slider-dots" id="sliderDots"><?php for($i=0; $i<count($slides); $i++): ?><div class="dot <?= $i===0?'active':'' ?>" onclick="goToSlide(<?= $i ?>)"></div><?php endfor; ?></div>
        <?php endif; ?>
    </div>
</div>

<div class="main-grid">
    <div class="content-left">
        <div class="category-bar glass-card">
            <div class="cat-item active" onclick="filterCategory('all', this)">全部</div>
            <?php foreach($categories_db as $cat): ?><div class="cat-item" onclick="filterCategory('<?= htmlspecialchars($cat['name']) ?>', this)"><?= htmlspecialchars($cat['name']) ?></div><?php endforeach; ?>
        </div>
        
        <div class="article-list" id="articleContainer"></div>
        
        <div class="pagination-container" id="pagination"></div>
    </div>

    <div class="sidebar">
        <div class="profile-card glass-card">
            <div class="avatar"><img src="<?= conf('author_avatar', 'https://images.unsplash.com/photo-1535713875002-d1d0cf377fde?ixlib=rb-4.0.3&auto=format&fit=crop&w=200&q=80') ?>" alt="Avatar"></div>
            <div class="author-name"><?= conf('author_name', 'Alex Designer') ?></div>
            <div class="author-bio"><?= conf('author_bio', '全栈开发者 / 极简主义信徒 / 摄影爱好者') ?></div>
            <div class="social-links">
                <?php if($url = conf('social_github')): ?><a href="<?= $url ?>" target="_blank" class="social-btn"><i class="fa-brands fa-github"></i></a><?php endif; ?>
                <?php if($url = conf('social_twitter')): ?><a href="<?= $url ?>" target="_blank" class="social-btn"><i class="fa-brands fa-twitter"></i></a><?php endif; ?>
                <?php if($url = conf('social_email')): ?><a href="mailto:<?= $url ?>" class="social-btn"><i class="fa-solid fa-envelope"></i></a><?php endif; ?>
            </div>
        </div>

        <div class="wechat-flip-container">
            <div class="wechat-card">
                <div class="wc-front"><h4><i class="fa-brands fa-weixin"></i> 关注微信公众号</h4><p>快人一步获取最新文章</p></div>
                <div class="wc-back"><div class="wc-back-content"><div class="wc-text"><span>扫一扫</span><br><span>关注我</span></div><div class="wc-qr"><img src="<?= conf('wechat_qrcode', 'https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=WelcomeToMyBlog') ?>" alt="QR Code"></div></div></div>
            </div>
        </div>

        <?php if($enable_chatroom): ?>
        <div class="glass-card chatroom-card">
            <div class="chat-header"><i class="fa-regular fa-comments"></i> 摸鱼聊天室</div>
            <div class="chat-messages" id="chatMessages"><div style="text-align:center; color:#999; font-size:12px; margin-top:50px;">加载消息中...</div></div>
            <div class="chat-input-area">
                <div class="emoji-picker" id="pcEmojiPicker"></div>
                <!-- 关键修改：添加 onclick="togglePcEmoji(event)" -->
                <button class="emoji-btn" onclick="togglePcEmoji(event)"><i class="fa-regular fa-face-smile"></i></button>
                <input type="text" class="chat-input" id="chatInput" placeholder="<?= $is_user_login ? '说点什么...' : '请先登录' ?>" <?= $is_user_login ? '' : 'disabled' ?>>
                <button class="chat-send" onclick="sendChat()"><i class="fa-solid fa-paper-plane"></i></button>
            </div>
        </div>
        <?php endif; ?>

        <?php if($enable_friend_links && !empty($friend_links)): ?>
        <div class="glass-card friend-links">
            <div class="widget-title">友情链接</div>
            <div class="friend-grid"><?php foreach($friend_links as $link): ?><a href="<?= htmlspecialchars($link['url']) ?>" target="_blank" class="friend-item"><?= htmlspecialchars($link['name']) ?></a><?php endforeach; ?></div>
        </div>
        <?php endif; ?>

        <?php if($enable_hot_tags && !empty($hot_tags)): ?>
        <div class="glass-card tags-card">
            <div class="widget-title">热门标签</div>
            <div class="tag-cloud"><?php foreach($hot_tags as $tag): ?><a href="javascript:void(0)" onclick="searchArticles('<?= trim($tag) ?>')" class="tag"><?= trim($tag) ?></a><?php endforeach; ?></div>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="modal-overlay" id="articleModal">
    <div class="modal-card">
        <div class="modal-header-bar" onclick="closeModal()">
            <i class="fa-solid fa-xmark close-modal-btn"></i>
        </div>
        <div class="modal-scroll-area" id="modalBody"></div>
    </div>
</div>

<?php 
echo "<script>
    window.siteData = {
        isUserLogin: " . ($is_user_login ? 'true' : 'false') . ",
        currentUserId: " . (int)$current_user_id . ",
        currentUserName: '" . addslashes($current_user_name) . "',
        currentUserAvatar: '" . addslashes($_SESSION['user_avatar'] ?? 'https://api.dicebear.com/7.x/avataaars/svg?seed=' . urlencode($current_user_name)) . "',
        csrfToken: '" . ($_SESSION['csrf_token'] ?? '') . "',
        enableChatroom: " . ($enable_chatroom ? 'true' : 'false') . ",
        authorAvatar: '" . addslashes(conf('author_avatar', 'https://images.unsplash.com/photo-1535713875002-d1d0cf377fde?ixlib=rb-4.0.3&auto=format&fit=crop&w=200&q=80')) . "'
    };
</script>";

echo '<script src="../pages/assets/js/home.js?v=' . time() . '"></script>';
require_once 'includes/footer.php'; 
?>
