<?php
// admin/articles.php
/**
                _ _                    ____  _                               
               | (_) __ _ _ __   __ _  / ___|| |__  _   _  ___               
            _  | | |/ _` | '_ \ / _` | \___ \| '_ \| | | |/ _ \              
           | |_| | | (_| | | | | (_| |  ___) | | | | |_| | (_) |             
            \___/|_|\__,_|_| |_|\__, | |____/|_| |_|\__,_|\___/              
   ____  _____          _  __  |___/  _____   _   _  _          ____ ____  
  / ___| |__  /         | | \ \/ / / | |___ /  / | | || |        / ___/ ___|
 | |  _    / /       _  | |  \  /  | |   |_ \  | | | || |_      | |  | |  
 | |_| |  / /_   _  | |_| |  /  \  | |  ___) | | | |__   _|  _  | |__| |___ 
  \____| /____| (_)  \___/  /_/\_\ |_| |____/  |_|    |_|   (_)  \____\____|
                                                                             
                                追求极致的美学                               
**/
require_once '../includes/config.php';
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
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

// --- 辅助函数：处理文件上传 ---
function handleMediaUpload($tmp_name, $original_name) {
    global $pdo;
    $stmtCos = $pdo->prepare("SELECT value FROM settings WHERE key_name = 'cos_enabled'");
    $stmtCos->execute();
    $cosEnabled = $stmtCos->fetchColumn();
    
    $ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
    // [修改] 增加常用资源格式支持
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'mp4', 'webm', 'zip', 'rar', '7z', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'md'];
    if (!in_array($ext, $allowed)) return '';

    $newName = date('Ymd_His_') . uniqid() . '.' . $ext;
    
    if ($cosEnabled == '1') {
        require_once '../includes/cos_helper.php';
        $cosPath = 'uploads/' . date('Ym') . '/' . $newName;
        return uploadToCOS($tmp_name, $cosPath);
    } else {
        $uploadDir = '../assets/uploads/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        $target = $uploadDir . $newName;
        if (move_uploaded_file($tmp_name, $target) || rename($tmp_name, $target)) {
            return '/assets/uploads/' . $newName;
        }
    }
    return '';
}

// --- 0.5 AJAX 处理 Base64 图片上传 (用于生成笔记卡片) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'upload_base64') {
    $base64 = $_POST['image'] ?? '';
    if (preg_match('/^(data:\s*image\/(\w+);base64,)/', $base64, $result)) {
        $type = $result[2];
        $data = base64_decode(str_replace($result[1], '', $base64));
        $tmp_file = sys_get_temp_dir() . '/' . uniqid('note_') . '.' . $type;
        file_put_contents($tmp_file, $data);
        
        $url = handleMediaUpload($tmp_file, 'note_' . time() . '.' . $type);
        if (file_exists($tmp_file)) unlink($tmp_file);
        
        if ($url) {
            echo json_encode(['success' => true, 'url' => $url]);
        } else {
            echo json_encode(['success' => false, 'msg' => '文件保存或上传COS失败']);
        }
    } else {
        echo json_encode(['success' => false, 'msg' => '无效的图片数据']);
    }
    exit;
}

// --- 0. AJAX 获取文章详情 ---
if (isset($_GET['action']) && $_GET['action'] == 'get_detail' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
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
    
    // [新增] 处理文章密码
    $password = trim($_POST['password'] ?? '');
    
    // [新增] 处理资源数据
    $res_name = trim($_POST['resource_name'] ?? '');
    $res_link = trim($_POST['resource_link'] ?? '');
    
    // 如果上传了文件，覆盖链接
    if (isset($_FILES['resource_file']) && $_FILES['resource_file']['error'] === UPLOAD_ERR_OK) {
        $uploadedRes = handleMediaUpload($_FILES['resource_file']['tmp_name'], $_FILES['resource_file']['name']);
        if ($uploadedRes) {
            $res_link = $uploadedRes;
        }
    }
    
    $resource_data = '';
    if ($res_name || $res_link) {
        $resource_data = json_encode([
            'name' => $res_name,
            'link' => $res_link
        ], JSON_UNESCAPED_UNICODE);
    }
    
    $media_type = $_POST['media_type'] ?? 'images';
    $media_data = '';
    $cover_image = '';

    if ($media_type === 'images') {
        $urls = $_POST['image_urls'] ?? [];
        $files = $_FILES['image_files'] ?? null;
        $image_list = [];
        
        if (is_array($urls)) {
            for ($i = 0; $i < count($urls); $i++) {
                if (!empty($files['name'][$i]) && $files['error'][$i] === UPLOAD_ERR_OK) {
                    $uploadedUrl = handleMediaUpload($files['tmp_name'][$i], $files['name'][$i]);
                    if ($uploadedUrl) $image_list[] = $uploadedUrl;
                } elseif (!empty($urls[$i])) {
                    $image_list[] = $urls[$i];
                }
            }
        }
        $media_data = json_encode($image_list, JSON_UNESCAPED_UNICODE);
        if (count($image_list) > 0) $cover_image = $image_list[0];

    } else if ($media_type === 'video') {
        $v_cover_url = $_POST['video_cover_url'] ?? '';
        if (isset($_FILES['video_cover_file']) && $_FILES['video_cover_file']['error'] === UPLOAD_ERR_OK) {
            $v_cover_url = handleMediaUpload($_FILES['video_cover_file']['tmp_name'], $_FILES['video_cover_file']['name']) ?: $v_cover_url;
        }
        
        $v_url = $_POST['video_url'] ?? '';
        if (isset($_FILES['video_file']) && $_FILES['video_file']['error'] === UPLOAD_ERR_OK) {
            $v_url = handleMediaUpload($_FILES['video_file']['tmp_name'], $_FILES['video_file']['name']) ?: $v_url;
        }
        
        $media_data = json_encode(['video' => $v_url, 'cover' => $v_cover_url], JSON_UNESCAPED_UNICODE);
        $cover_image = $v_cover_url;
    }

    if ($title && $content) {
        if ($id > 0) {
            // [修改] 增加 password 更新
            $stmt = $pdo->prepare("UPDATE articles SET title=?, content=?, summary=?, category=?, cover_image=?, media_type=?, media_data=?, is_recommended=?, is_hidden=?, resource_data=?, password=? WHERE id=?");
            $stmt->execute([$title, $content, $summary, $category, $cover_image, $media_type, $media_data, $is_recommended, $is_hidden, $resource_data, $password, $id]);
            $pdo->prepare("DELETE FROM tags WHERE article_id=?")->execute([$id]);
            $article_id = $id;
        } else {
            // [修改] 增加 password 插入
            $stmt = $pdo->prepare("INSERT INTO articles (title, content, summary, category, cover_image, media_type, media_data, is_recommended, is_hidden, resource_data, password) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$title, $content, $summary, $category, $cover_image, $media_type, $media_data, $is_recommended, $is_hidden, $resource_data, $password]);
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
                    <img src="<?= htmlspecialchars($art['cover_image']) ?>" alt="Cover" loading="lazy" decoding="async">
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
                <button type="button" class="btn btn-ghost" onclick="openNoteModal()" style="color: #ec4899; border-color: #ec4899; background: rgba(236, 72, 153, 0.05);">
                    <i class="fa-solid fa-clone"></i> 笔记卡片
                </button>
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
                
                <div class="setting-group"><label>分类</label><select name="category" id="artCategory" class="form-control"><?php foreach($categories as $cat): ?><option value="<?= htmlspecialchars($cat['name']) ?>"><?= htmlspecialchars($cat['name']) ?></option><?php endforeach; ?></select></div>
                
                <div class="setting-group" style="background: #f0fdf4; border: 1px dashed #86efac; padding: 12px; border-radius: 8px;">
                    <label style="color: #16a34a;"><i class="fa-solid fa-download"></i> 附件/资源下载</label>
                    
                    <input type="text" name="resource_name" id="resName" class="form-control" placeholder="资源名称 (例如: 源码.zip)" style="margin-bottom: 8px;">
                    
                    <div style="font-size: 12px; margin-bottom: 4px; color: #666;">资源链接或上传文件:</div>
                    
                    <div style="display: flex; gap: 8px; margin-bottom: 8px;">
                        <label style="font-size: 12px; cursor: pointer; display:flex; align-items:center; gap:4px;"><input type="radio" name="res_type" value="link" checked onclick="toggleResInput('link')"> 外链</label>
                        <label style="font-size: 12px; cursor: pointer; display:flex; align-items:center; gap:4px;"><input type="radio" name="res_type" value="file" onclick="toggleResInput('file')"> 上传</label>
                    </div>

                    <input type="text" name="resource_link" id="resLink" class="form-control" placeholder="https://..." style="display: block;">
                    <div id="resFileBox" style="display: none;">
                        <input type="file" name="resource_file" id="resFile" class="input-file" onchange="updateFileName(this)">
                        <label for="resFile" class="btn-upload">
                            <i class="fa-solid fa-cloud-arrow-up"></i>
                            <span id="resFileName">点击选择文件...</span>
                        </label>
                    </div>

                    
                    <div style="font-size: 11px; color: #999; margin-top: 4px;">* 若上传文件，将自动覆盖外链输入框</div>
                </div>

                <div class="setting-group" style="background: #fffbeb; border: 1px dashed #fcd34d; padding: 12px; border-radius: 8px;">
                    <label style="color: #d97706;"><i class="fa-solid fa-lock"></i> 访问密码 (加密文章)</label>
                    <input type="text" name="password" id="artPassword" class="form-control" placeholder="留空则公开文章" style="margin-bottom: 4px;">
                    <div style="font-size: 11px; color: #92400e;">* 设置后前端必须输入此密码才能查看</div>
                </div>

                <div class="setting-group">
                    <label>状态设置</label>
                    <div class="switch-row"><span>设为推荐</span><label class="toggle-switch"><input type="checkbox" name="is_recommended" id="artRec" value="1"><span class="slider"></span></label></div>
                    <div class="switch-row" style="margin-top:8px;"><span>隐藏/草稿</span><label class="toggle-switch"><input type="checkbox" name="is_hidden" id="artHide" value="1"><span class="slider"></span></label></div>
                </div>

                <div class="setting-group">
                    <label>展示模式</label>
                    <select name="media_type" id="artMediaType" class="form-control" onchange="toggleMediaMode()">
                        <option value="images">图文展示 (单图/多图)</option>
                        <option value="video">视频展示</option>
                    </select>
                </div>

                <div id="mode-images" class="setting-group">
                    <label>图片列表 (第一张作为封面)</label>
                    <div id="image-list-container"></div>
                    <button type="button" class="btn btn-ghost" onclick="addImageInput()" style="width:100%; justify-content:center; margin-top:8px;">
                        <i class="fa-solid fa-plus"></i> 添加图片
                    </button>
                </div>

                <div id="mode-video" class="setting-group" style="display:none;">
                    <label style="color:#ef4444;"><i class="fa-solid fa-image"></i> 视频封面图</label>
                    <input type="file" name="video_cover_file" class="form-control" accept="image/*" style="padding: 8px; margin-bottom:6px;">
                    <input type="text" name="video_cover_url" id="vCoverUrl" class="form-control" placeholder="或输入封面 URL...">
                    
                    <label style="color:#3b82f6; margin-top:16px;"><i class="fa-solid fa-video"></i> 视频文件</label>
                    <input type="file" name="video_file" class="form-control" accept="video/*" style="padding: 8px; margin-bottom:6px;">
                    <input type="text" name="video_url" id="vUrl" class="form-control" placeholder="或输入视频 URL (mp4/webm)">
                </div>

                <div class="setting-group"><label>标签</label><input type="text" name="tags" id="artTags" class="form-control" placeholder="PHP, Life"></div>
                <div class="setting-group"><label>摘要</label><textarea name="summary" id="artSummary" class="form-control" rows="3" style="resize:none;"></textarea></div>
            </div>
        </div>
    </form>
</div>

<div class="modal-overlay" id="noteModal" style="z-index: 2050;">
    <div class="modal-box" style="width: 80%; max-width: 900px; height: 75vh;">
        <div class="modal-header">
            <h3 style="margin: 0; font-size: 18px; color: var(--text-main); display: flex; align-items: center; gap: 8px;">
                <i class="fa-solid fa-clone" style="color: #ec4899;"></i> 笔记卡片创作
            </h3>
            <div class="btn-group" style="display: flex; gap: 10px;">
                <button type="button" class="btn btn-ghost" onclick="closeNoteModal()">取消</button>
                <button type="button" class="btn btn-primary" id="btnGenerateNote" onclick="generateAndInsertNote()" style="background: linear-gradient(135deg, #ec4899, #f43f5e); border:none;">
                    <i class="fa-solid fa-check"></i> 生成并插入图片列表
                </button>
            </div>
        </div>
        <div class="modal-body note-modal-body">
            <div class="note-preview-area">
                <canvas id="noteCanvas" width="1080" height="1440"></canvas>
            </div>
            <div class="note-sidebar">
                <div class="setting-group">
                    <label>文本内容</label>
                    <textarea id="noteTextInput" class="form-control" rows="6" placeholder="输入你要分享的话..." oninput="drawNoteCard()" style="resize:vertical; font-size:14px;"></textarea>
                    <div style="font-size: 12px; color: #94a3b8; margin-top: 4px;">* 换行将自动体现在卡片上，注意字数不要过多。</div>
                </div>
                
                <div class="setting-group">
                    <label>文字颜色</label>
                    <div style="display: flex; gap: 10px; align-items: center;">
                        <input type="color" id="noteTextColor" value="#333333" onchange="drawNoteCard()" style="width: 40px; height: 40px; padding: 0; border: 1px solid #e2e8f0; border-radius: 8px; cursor: pointer;">
                        <span style="font-size: 13px; color: #64748b;" id="noteTextColorVal">#333333</span>
                    </div>
                </div>

                <div class="setting-group">
                    <label style="display: flex; justify-content: space-between;">
                        文字大小 <span id="noteTextSizeVal" style="color: #ec4899;">56px</span>
                    </label>
                    <input type="range" id="noteTextSize" min="24" max="120" value="56" step="2" oninput="updateFontSizeLabel(); drawNoteCard()" style="width: 100%; cursor: pointer;">
                </div>

                <div class="setting-group">
                    <label>卡片背景模板</label>
                    
                    <input type="file" id="customBgUpload" accept="image/*" style="display:none;" onchange="uploadCustomNoteBg(this)">
                    <label for="customBgUpload" class="btn btn-ghost" style="width: 100%; justify-content: center; margin-bottom: 12px; border: 1px dashed #cbd5e1; background: #f8fafc;">
                        <i class="fa-solid fa-image"></i> 上传自定义背景图
                    </label>
                    
                    <div style="margin-bottom: 12px;">
                        <label style="display: flex; justify-content: space-between; text-transform: none; color: #64748b;">
                            <span><i class="fa-solid fa-droplet"></i> 背景模糊</span>
                            <span id="noteBgBlurVal" style="color: #ec4899; font-weight: bold;">0px</span>
                        </label>
                        <input type="range" id="noteBgBlur" min="0" max="60" value="0" step="2" oninput="updateBgBlurLabel(); drawNoteCard()" style="width: 100%; cursor: pointer;">
                    </div>

                    <div class="note-bg-options" id="noteBgOptions"></div>
                </div>
            </div>
        </div>
    </div>
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
<script src="assets/js/articles.js?v=<?php echo time(); ?>"></script>

<script>
    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.querySelector('.sidebar-overlay');
        if(sidebar && overlay) {
            sidebar.classList.toggle('active');
            overlay.classList.toggle('active');
        }
    }
    
    // [新增] 切换资源输入模式
    function toggleResInput(type) {
        if(type === 'link') {
            document.getElementById('resLink').style.display = 'block';
            document.getElementById('resFileBox').style.display = 'none'; // 改用 Box
        } else {
            document.getElementById('resLink').style.display = 'none';
            document.getElementById('resFileBox').style.display = 'block'; // 改用 Box
        }
    }

    // [新增] 显示选中的文件名
    function updateFileName(input) {
        const span = document.getElementById('resFileName');
        if (input.files && input.files.length > 0) {
            span.innerText = input.files[0].name;
            span.style.color = '#333';
        } else {
            span.innerText = '点击选择文件...';
            span.style.color = '#666';
        }
    }
</script>

<?php if(file_exists('footer.php')) require 'footer.php'; ?>
</body>
</html>