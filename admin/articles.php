<?php
// admin/articles.php
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
require_once '../includes/config.php';
requireLogin();
$pdo = getDB();
$redis = getRedis();

// --- 辅助函数：清除缓存 ---
function clearArticleCache($article_id = 0) {
    global $redis;
    if (!$redis) return;
    $keys = $redis->keys('bkcs:list:*');
    foreach ($keys as $k) $redis->del($k);
    if ($article_id > 0) $redis->del('bkcs:article:' . $article_id);
}

// --- 0. AJAX 获取文章详情 ---
if (isset($_GET['action']) && $_GET['action'] == 'get_detail' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    // 这里依然查询所有字段，包括 content，用于编辑
    $stmt = $pdo->prepare("SELECT * FROM articles WHERE id = ?");
    $stmt->execute([$id]);
    $art = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($art) {
        $stmt_tags = $pdo->prepare("SELECT tag_name FROM tags WHERE article_id = ?");
        $stmt_tags->execute([$id]);
        $art['tags'] = implode(', ', $stmt_tags->fetchAll(PDO::FETCH_COLUMN));
        echo json_encode(['success' => true, 'data' => $art]);
    } else { echo json_encode(['success' => false]); }
    exit;
}

// --- 1. 处理保存文章 (新增/编辑) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'save_article') {
    $id = intval($_POST['id']);
    $title = trim($_POST['title']);
    $content = $_POST['content']; 
    $summary = trim($_POST['summary']);
    $category = trim($_POST['category']);
    $tags_str = trim($_POST['tags']); 
    $is_recommended = isset($_POST['is_recommended']) ? 1 : 0;
    $is_hidden = isset($_POST['is_hidden']) ? 1 : 0;
    
    $cover_image = trim($_POST['cover_image']);

    // --- 处理封面上传 ---
    if (isset($_FILES['cover_file']) && $_FILES['cover_file']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['cover_file'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        
        if (in_array($ext, $allowed)) {
            $stmtCos = $pdo->prepare("SELECT value FROM settings WHERE key_name = 'cos_enabled'");
            $stmtCos->execute();
            $cosEnabled = $stmtCos->fetchColumn();
            
            $newName = date('Ymd_His_') . uniqid() . '_cover.' . $ext;
            
            if ($cosEnabled == '1') {
                require_once '../includes/cos_helper.php';
                $cosPath = 'uploads/' . date('Ym') . '/' . $newName;
                $uploadedUrl = uploadToCOS($file['tmp_name'], $cosPath);
                if ($uploadedUrl) $cover_image = $uploadedUrl;
            } else {
                // 本地上传逻辑修复
                $uploadDir = '../assets/uploads/'; // 物理存储路径
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                
                $target = $uploadDir . $newName;
                if (move_uploaded_file($file['tmp_name'], $target)) {
                    // 数据库存储路径：使用绝对路径 /assets/...
                    $cover_image = '/assets/uploads/' . $newName;
                }
            }
        }
    }

    if ($title && $content) {
        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE articles SET title=?, content=?, summary=?, category=?, cover_image=?, is_recommended=?, is_hidden=? WHERE id=?");
            $stmt->execute([$title, $content, $summary, $category, $cover_image, $is_recommended, $is_hidden, $id]);
            $pdo->prepare("DELETE FROM tags WHERE article_id=?")->execute([$id]);
            $article_id = $id;
        } else {
            $stmt = $pdo->prepare("INSERT INTO articles (title, content, summary, category, cover_image, is_recommended, is_hidden) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$title, $content, $summary, $category, $cover_image, $is_recommended, $is_hidden]);
            $article_id = $pdo->lastInsertId();
        }
        if ($tags_str) {
            $tags_arr = explode(',', str_replace('，', ',', $tags_str));
            foreach ($tags_arr as $t) {
                $t = trim($t);
                if ($t) $pdo->prepare("INSERT INTO tags (article_id, tag_name) VALUES (?, ?)")->execute([$article_id, $t]);
            }
        }
        clearArticleCache($article_id);
        header("Location: articles.php"); exit;
    }
}

// --- 2. 处理批量操作 ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'batch_ops') {
    $ids = $_POST['ids'] ?? [];
    $type = $_POST['batch_type'] ?? '';
    $target_cat = $_POST['target_category'] ?? '';

    if (!empty($ids) && is_array($ids)) {
        $in  = str_repeat('?,', count($ids) - 1) . '?';
        
        if ($type == 'delete') {
            $pdo->prepare("DELETE FROM tags WHERE article_id IN ($in)")->execute($ids);
            $pdo->prepare("DELETE FROM comments WHERE article_id IN ($in)")->execute($ids);
            $pdo->prepare("DELETE FROM article_likes WHERE article_id IN ($in)")->execute($ids);
            $pdo->prepare("DELETE FROM articles WHERE id IN ($in)")->execute($ids);
        }
        if ($type == 'move' && !empty($target_cat)) {
            $params = array_merge([$target_cat], $ids);
            $pdo->prepare("UPDATE articles SET category = ? WHERE id IN ($in)")->execute($params);
        }
        if ($type == 'hide') { $pdo->prepare("UPDATE articles SET is_hidden = 1 WHERE id IN ($in)")->execute($ids); }
        if ($type == 'publish') { $pdo->prepare("UPDATE articles SET is_hidden = 0 WHERE id IN ($in)")->execute($ids); }

        if ($redis) {
            $keys = $redis->keys('bkcs:list:*');
            foreach ($keys as $k) $redis->del($k);
            foreach ($ids as $aid) $redis->del('bkcs:article:' . $aid);
        }
    }
    header("Location: articles.php"); exit;
}

// --- 3. 单个操作 ---
if (isset($_GET['action'])) {
    $id = intval($_GET['id']);
    if ($_GET['action'] == 'delete') {
        $pdo->prepare("DELETE FROM tags WHERE article_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM comments WHERE article_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM article_likes WHERE article_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM articles WHERE id = ?")->execute([$id]);
        clearArticleCache($id);
    }
    if ($_GET['action'] == 'toggle_hide') {
        $pdo->prepare("UPDATE articles SET is_hidden = NOT is_hidden WHERE id = ?")->execute([$id]);
        clearArticleCache($id);
    }
    if ($_GET['action'] == 'toggle_recommend') {
        $pdo->prepare("UPDATE articles SET is_recommended = NOT is_recommended WHERE id = ?")->execute([$id]);
        clearArticleCache($id); 
    }
    if($_GET['action'] != 'get_detail') { header("Location: articles.php"); exit; }
}

// --- 优化：列表查询移除 content 字段，提高加载速度 ---
$articles = $pdo->query("SELECT id, title, summary, category, cover_image, is_recommended, is_hidden, views, likes, created_at FROM articles ORDER BY is_recommended DESC, created_at DESC")->fetchAll();
$categories = $pdo->query("SELECT * FROM categories ORDER BY sort_order ASC")->fetchAll();

require 'header.php';
?>

<link href="assets/css/wangeditor.css" rel="stylesheet">
<link href="assets/css/articles.css?v=<?php echo time(); ?>" rel="stylesheet">

<div class="page-header">
    <div class="page-title">文章管理</div>
    
    <div class="batch-toolbar">
        <button class="btn btn-ghost" onclick="toggleAllCards()" title="全选/反选">
            <i class="fa-solid fa-check-double"></i>
        </button>

        <div class="dropdown">
            <button class="btn btn-ghost">
                <i class="fa-solid fa-layer-group"></i> 
                <span style="display:none; @media(min-width:600px){display:inline; margin-left:4px;}">批量操作</span> 
                <i class="fa-solid fa-angle-down" style="font-size:10px; margin-left:5px;"></i>
            </button>
            <div class="dropdown-content">
                <div class="dropdown-item" onclick="submitBatch('hide')"><i class="fa-solid fa-eye-slash"></i> 设为隐藏</div>
                <div class="dropdown-item" onclick="submitBatch('publish')"><i class="fa-solid fa-check"></i> 设为公开</div>
                <div style="height:1px; background:rgba(0,0,0,0.05); margin:4px 0;"></div>
                <div class="dropdown-item" onclick="openMoveModal()"><i class="fa-solid fa-arrow-right-to-bracket"></i> 移动分类</div>
                <div style="height:1px; background:rgba(0,0,0,0.05); margin:4px 0;"></div>
                <div class="dropdown-item danger" onclick="submitBatch('delete')"><i class="fa-solid fa-trash"></i> 删除选中</div>
            </div>
        </div>
        <button class="btn btn-primary" onclick="openModal()"><i class="fa-solid fa-plus"></i> <span style="display:none; @media(min-width:600px){display:inline; margin-left:4px;}">写文章</span></button>
    </div>
</div>

<form id="batchForm" method="POST">
    <input type="hidden" name="action" value="batch_ops">
    <input type="hidden" name="batch_type" id="batchType">
    <input type="hidden" name="target_category" id="targetCategory">

    <div class="article-grid">
        <?php foreach($articles as $art): ?>
        <div class="art-card" onclick="toggleSelect(this)">
            <div class="check-overlay">
                <input type="checkbox" name="ids[]" value="<?= $art['id'] ?>" onclick="event.stopPropagation(); toggleSelect(this.closest('.art-card'))">
                <i class="fa-solid fa-check check-icon"></i>
            </div>

            <div class="art-cover">
                <?php if($art['is_recommended']): ?><div class="rec-badge"><i class="fa-solid fa-star"></i> 推荐</div><?php endif; ?>
                <?php if(!empty($art['cover_image'])): ?>
                    <!-- 优化：图片懒加载和异步解码 -->
                    <img src="<?= htmlspecialchars($art['cover_image']) ?>" 
                         alt="Cover" 
                         loading="lazy" 
                         decoding="async">
                <?php else: ?>
                    <div class="art-cover-placeholder"><i class="fa-regular fa-image"></i></div>
                <?php endif; ?>
            </div>

            <div class="art-body">
                <div class="art-meta">
                    <span class="cat-tag"><?= htmlspecialchars($art['category']) ?></span>
                    <?php if($art['is_hidden']): ?><span class="status-hide" title="隐藏"><i class="fa-solid fa-eye-slash"></i></span><?php else: ?><span class="status-pub" title="公开"><i class="fa-solid fa-check"></i></span><?php endif; ?>
                </div>
                <div class="art-title" title="<?= htmlspecialchars($art['title']) ?>"><?= htmlspecialchars($art['title']) ?></div>
                <div class="art-footer">
                    <div style="display:flex; flex-direction:column; gap:2px;">
                        <div style="display:flex; gap:6px;">
                            <span><i class="fa-regular fa-eye"></i> <?= $art['views'] ?></span>
                            <span><i class="fa-regular fa-heart"></i> <?= $art['likes'] ?></span>
                        </div>
                        <div style="font-size:10px; opacity:0.6;"><?= date('Y-m-d', strtotime($art['created_at'])) ?></div>
                    </div>
                    <div class="art-btns">
                        <span class="icon-btn" onclick="event.stopPropagation(); editArticle(<?= $art['id'] ?>)" title="编辑"><i class="fa-solid fa-pen"></i></span>
                        <a href="?action=toggle_recommend&id=<?= $art['id'] ?>" class="icon-btn" onclick="event.stopPropagation()" title="推荐" style="color:<?= $art['is_recommended']?'#f59e0b':'' ?>"><i class="<?= $art['is_recommended']?'fa-solid':'fa-regular' ?> fa-star"></i></a>
                        <a href="?action=delete&id=<?= $art['id'] ?>" class="icon-btn del" onclick="event.stopPropagation(); return confirm('确定删除？')" title="删除"><i class="fa-solid fa-trash"></i></a>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</form>

<div class="modal-overlay" id="moveModal">
    <div class="move-box">
        <h3 style="margin-bottom: 24px; color: var(--text-main); font-size: 18px;">移动到分类</h3>
        <select id="moveSelect" class="form-control" style="margin-bottom: 24px;">
            <?php foreach($categories as $cat): ?>
                <option value="<?= htmlspecialchars($cat['name']) ?>"><?= htmlspecialchars($cat['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <div style="display: flex; gap: 12px; justify-content: center;">
            <button class="btn btn-ghost" onclick="document.getElementById('moveModal').classList.remove('active')">取消</button>
            <button class="btn btn-primary" onclick="confirmMove()">确定移动</button>
        </div>
    </div>
</div>

<div class="modal-overlay" id="postModal">
    <form method="POST" class="modal-box" id="postForm" enctype="multipart/form-data">
        <input type="hidden" name="action" value="save_article">
        <input type="hidden" name="id" id="artId" value="0">
        
        <div class="modal-header">
            <input type="text" name="title" id="artTitle" class="modal-title-input" placeholder="输入文章标题..." required autocomplete="off">
            
            <div class="btn-group" style="display: flex; gap: 10px; flex-shrink: 0; white-space: nowrap;">
                <button type="button" class="btn btn-ghost" onclick="openAiModal()" style="color: #7c3aed; border-color: #7c3aed; background: rgba(124, 58, 237, 0.05);">
                    <i class="fa-solid fa-wand-magic-sparkles"></i> AI写作
                </button>
                <button type="button" class="btn btn-ghost" onclick="closeModal()">取消</button>
                <button type="submit" class="btn btn-primary">发布</button>
            </div>
        </div>
        
        <div class="modal-body">
            <div class="editor-section">
                <div id="editor-toolbar"></div>
                <div id="editor-container"></div>
                <textarea name="content" id="content-textarea" style="display:none"></textarea>
            </div>
            <div class="settings-section">
                <div class="setting-group">
                    <label>状态设置</label>
                    <div class="switch-row"><span>设为推荐</span><label class="toggle-switch"><input type="checkbox" name="is_recommended" id="artRec" value="1"><span class="slider"></span></label></div>
                    <div class="switch-row" style="margin-top:8px;"><span>隐藏/草稿</span><label class="toggle-switch"><input type="checkbox" name="is_hidden" id="artHide" value="1"><span class="slider"></span></label></div>
                </div>
                <div class="setting-group"><label>分类</label><select name="category" id="artCategory" class="form-control"><?php foreach($categories as $cat): ?><option value="<?= htmlspecialchars($cat['name']) ?>"><?= htmlspecialchars($cat['name']) ?></option><?php endforeach; ?></select></div>
                
                <div class="setting-group">
                    <label>封面图片</label>
                    <div id="cover-preview-box">
                        <img id="cover-preview-img" src="">
                    </div>
                    <div style="display:flex; flex-direction:column; gap:8px;">
                        <input type="file" name="cover_file" id="coverFile" class="form-control" accept="image/*" onchange="previewCover(this)" style="padding: 8px;">
                        <input type="text" name="cover_image" id="artCover" class="form-control" placeholder="或输入图片 URL..." oninput="previewUrl(this.value)">
                    </div>
                </div>

                <div class="setting-group"><label>标签</label><input type="text" name="tags" id="artTags" class="form-control" placeholder="PHP, Life"></div>
                <div class="setting-group"><label>摘要</label><textarea name="summary" id="artSummary" class="form-control" rows="3" style="resize:none;"></textarea></div>
            </div>
        </div>
    </form>
</div>

<div class="modal-overlay" id="aiModal">
    <div class="move-box" style="width: 450px; text-align: left;">
        <h3 style="margin-bottom: 16px; font-size: 18px; color: var(--text-main); display: flex; align-items: center; gap: 8px;"><i class="fa-solid fa-robot" style="color: #7c3aed;"></i> AI 智能创作</h3>
        <div class="form-group" style="margin-bottom: 24px;">
            <label style="display:block; margin-bottom:8px; font-weight:600; font-size:13px; color: var(--text-secondary);">请输入文章主题：</label>
            <textarea id="aiTopic" class="form-control" rows="4" placeholder="例如：写一篇关于 PHP 8.2 新特性的详细介绍，包含代码示例..." style="resize:none;"></textarea>
            <div style="font-size: 12px; color: var(--text-tertiary); margin-top: 8px;">* 预计生成时间 10-60 秒，支持流式输出</div>
        </div>
        <div style="display: flex; gap: 10px; justify-content: flex-end;">
            <button class="btn btn-ghost" onclick="closeAiModal()">取消</button>
            <button class="btn btn-primary" id="btnStartAi" onclick="startAiGenerate()" style="background: linear-gradient(135deg, #6366f1, #8b5cf6); border:none;">
                <i class="fa-solid fa-bolt"></i> 开始生成
            </button>
        </div>
    </div>
</div>

<script src="assets/js/wangeditor.js"></script>
<script src="assets/js/articles.js"></script>

<script>
    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.querySelector('.sidebar-overlay');
        if(sidebar && overlay) {
            sidebar.classList.toggle('active');
            overlay.classList.toggle('active');
        } else {
            console.error('Sidebar elements not found!');
        }
    }
</script>

<?php if(file_exists('footer.php')) require 'footer.php'; ?>
</body>
</html>
