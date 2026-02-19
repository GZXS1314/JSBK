<?php
// admin/love.php
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
ob_start(); // 开启缓冲，防止header报错

require_once '../includes/config.php';
requireLogin();
$pdo = getDB();
$redis = getRedis();

// --- 0. 辅助函数 ---

/**
 * [新增] 腾讯云 COS 缩略图生成助手
 * 用于后台列表预览，极大提升加载速度
 */
function getCosThumb($url, $width = 200) {
    if (empty($url)) return '';
    // 如果不是 http 开头，或者已经是带参数的链接，直接返回
    if (strpos($url, 'http') !== 0 || strpos($url, '?') !== false) {
        return $url;
    }
    // 默认生成宽200的缩略图，质量80，渐进式显示
    return $url . '?imageMogr2/thumbnail/' . $width . 'x/interlace/1/q/80';
}

/**
 * [修复] 清除情侣空间的“配置”缓存
 * 键名必须与前端 pages/love.php 中定义的一致
 */
function clearLoveSettingsCache() {
    global $redis;
    if (!$redis) return;
    $redis->del('bkcs:love:config'); 
}

/**
 * [修复] 清除情侣空间的“动态列表”缓存
 */
function clearLoveEventsCache() {
    global $redis;
    if (!$redis) return;
    $redis->del('bkcs:love:events');
}

// 读取 COS 设置
$stmt_cos = $pdo->prepare("SELECT value FROM settings WHERE key_name = 'cos_enabled'");
$stmt_cos->execute();
$cosEnabled = $stmt_cos->fetchColumn(); 

// --- 1. 处理 POST 保存 ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    
    // A. 保存配置
    if ($_POST['action'] == 'save_settings') {
        $fields = ['love_boy','love_girl','love_boy_avatar','love_girl_avatar','love_start_date','love_bg','love_letter_enabled','love_letter_content','love_letter_music'];
        foreach ($fields as $key) {
            $val = trim($_POST[$key] ?? '');
            if ($key === 'love_letter_enabled') $val = isset($_POST[$key]) ? '1' : '0';
            $pdo->prepare("INSERT INTO settings (key_name, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = ?")->execute([$key, $val, $val]);
        }
        clearLoveSettingsCache(); // 立即清除缓存
    }
    
    // B. 发布新动态
    if ($_POST['action'] == 'add_event') {
        $img_list = [];
        
        // 本地/COS 上传逻辑
        if (!empty($_FILES['local_images']['name'][0])) { 
            $upload_dir_rel = '../assets/uploads/';
            $upload_dir_web = '/assets/uploads/';
            
            if (!is_dir($upload_dir_rel)) @mkdir($upload_dir_rel, 0755, true);
            
            foreach($_FILES['local_images']['tmp_name'] as $key => $tmp_name) {
                if ($_FILES['local_images']['error'][$key] === UPLOAD_ERR_OK) {
                    $ext = strtolower(pathinfo($_FILES['local_images']['name'][$key], PATHINFO_EXTENSION));
                    if(in_array($ext, ['jpg','jpeg','png','gif','webp'])) {
                        $new_name = 'love_' . date('Ymd_His') . '_' . uniqid() . '.' . $ext;
                        $final_url = '';
                        
                        // COS 上传
                        if ($cosEnabled == '1') {
                            require_once '../includes/cos_helper.php';
                            $cosPath = 'uploads/' . date('Ym') . '/' . $new_name; 
                            $cosUrl = uploadToCOS($tmp_name, $cosPath);
                            if ($cosUrl) $final_url = $cosUrl;
                        }
                        
                        // 回退本地
                        if (empty($final_url)) {
                            if (move_uploaded_file($tmp_name, $upload_dir_rel . $new_name)) { 
                                $final_url = $upload_dir_web . $new_name; 
                            }
                        }
                        
                        if (!empty($final_url)) $img_list[] = $final_url;
                    }
                }
            }
        } elseif (!empty($_POST['net_images'])) { // 网络链接
            $urls = explode("\n", str_replace("\r", "", $_POST['net_images']));
            foreach($urls as $u) { 
                if($u = trim($u)) $img_list[] = $u; 
            }
        }
        
        $img_json = !empty($img_list) ? json_encode(array_slice($img_list, 0, 9)) : '';
        $pdo->prepare("INSERT INTO love_events (title, description, event_date, image_url) VALUES (?, ?, ?, ?)")->execute([trim($_POST['title']), trim($_POST['description']), $_POST['event_date'], $img_json]);
        
        clearLoveEventsCache(); // 立即清除列表缓存
    }
    
    ob_end_clean();
    header("Location: love.php"); 
    exit;
}

// --- 2. 处理 GET 删除 ---
if (isset($_GET['action']) && $_GET['action'] == 'delete_event' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    if ($id > 0) {
        $pdo->prepare("DELETE FROM love_events WHERE id = ?")->execute([$id]);
        clearLoveEventsCache(); // 立即清除列表缓存
    }
    ob_end_clean();
    header("Location: love.php"); 
    exit;
}

// --- 3. 读取数据 (后台总是直读数据库，确保最新) ---
$settings = $pdo->query("SELECT * FROM settings")->fetchAll(PDO::FETCH_KEY_PAIR);
$events = $pdo->query("SELECT * FROM love_events ORDER BY event_date DESC, id DESC")->fetchAll();

require 'header.php';
ob_end_flush();
?>

<!-- 引入样式 (加时间戳防缓存) -->
<link rel="stylesheet" href="assets/css/love.css?v=<?= time() ?>">

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <h3 style="font-size: 18px; font-weight: 600; color: var(--text-main);">
        <i class="fas fa-heart" style="color: var(--love-pink); margin-right: 8px;"></i>情侣空间动态
    </h3>
    <div>
        <button class="btn btn-ghost" onclick="openModal('settingsModal')"><i class="fas fa-sliders-h"></i> 甜蜜档案设置</button>
        <button class="btn btn-love" onclick="openModal('eventModal')" style="margin-left: 12px;"><i class="fas fa-paper-plane"></i> 发布新动态</button>
    </div>
</div>

<div class="card">
    <div class="table-wrapper">
        <table class="data-table">
            <thead><tr><th style="width:120px;">日期</th><th style="width:160px;">照片</th><th>内容</th><th style="width:80px; text-align:right;">操作</th></tr></thead>
            <tbody>
                <?php if(empty($events)): ?>
                    <tr><td colspan="4" style="text-align: center; padding: 40px; color: var(--text-tertiary);">暂无动态，快去发布第一条吧！</td></tr>
                <?php else: foreach($events as $e): ?>
                <tr>
                    <td style="font-size:13px; color:var(--text-secondary);"><?= $e['event_date'] ?></td>
                    <td>
                        <?php $imgs = json_decode($e['image_url'], true) ?: []; if(!empty($imgs)): ?>
                        <div class="img-stack">
                            <?php foreach(array_slice($imgs, 0, 3) as $img): ?>
                                <!-- 使用缩略图 -->
                                <img src="<?= getCosThumb(htmlspecialchars($img), 200) ?>" class="img-preview-sm" loading="lazy">
                            <?php endforeach; ?>
                            <?php if(count($imgs) > 3): ?><span style="margin-left:4px; font-size:12px; color:var(--text-tertiary)">+<?= count($imgs) - 3 ?></span><?php endif; ?>
                        </div>
                        <?php else: ?><span style="font-size:12px; color: var(--text-tertiary);">无图</span><?php endif; ?>
                    </td>
                    <td>
                        <div style="font-weight: 600; color: var(--text-main);"><?= htmlspecialchars($e['title']) ?></div>
                        <p style="font-size: 13px; color: var(--text-secondary); margin:4px 0 0; max-width: 400px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?= htmlspecialchars($e['description']) ?></p>
                    </td>
                    <td style="text-align: right;">
                        <a href="?action=delete_event&id=<?= $e['id'] ?>" class="btn btn-ghost" style="color: var(--danger);" onclick="return confirm('确认删除这条回忆吗?')" title="删除"><i class="fas fa-trash-alt"></i></a>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Settings Modal -->
<div class="modal-overlay" id="settingsModal" onclick="closeModal(event)">
    <div class="modal-content">
        <form method="POST">
            <input type="hidden" name="action" value="save_settings">
            <div class="modal-header">
                <h3 class="modal-title" style="color: var(--love-pink);"><i class="fas fa-user-friends"></i> 甜蜜档案设置</h3>
                <button type="button" class="btn btn-ghost" style="padding:4px 8px" onclick="closeModal()"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body">
                <div class="grid-2-col" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div class="form-group"><label class="form-label">男生昵称</label><input type="text" name="love_boy" class="form-control" value="<?= htmlspecialchars($settings['love_boy'] ?? '') ?>"></div>
                    <div class="form-group"><label class="form-label">女生昵称</label><input type="text" name="love_girl" class="form-control" value="<?= htmlspecialchars($settings['love_girl'] ?? '') ?>"></div>
                    <div class="form-group"><label class="form-label">男生头像URL</label><input type="text" name="love_boy_avatar" class="form-control" value="<?= htmlspecialchars($settings['love_boy_avatar'] ?? '') ?>"></div>
                    <div class="form-group"><label class="form-label">女生头像URL</label><input type="text" name="love_girl_avatar" class="form-control" value="<?= htmlspecialchars($settings['love_girl_avatar'] ?? '') ?>"></div>
                    <div class="form-group"><label class="form-label">相恋起始日期</label><input type="date" name="love_start_date" class="form-control" value="<?= htmlspecialchars($settings['love_start_date'] ?? '') ?>"></div>
                    <div class="form-group"><label class="form-label">背景大图URL</label><input type="text" name="love_bg" class="form-control" value="<?= htmlspecialchars($settings['love_bg'] ?? '') ?>"></div>
                </div>
                
                <div style="margin-top: 24px; padding-top: 24px; border-top: 1px solid #eef2ff;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <h4 style="font-size: 15px; font-weight: 600; color: #be185d; margin: 0;"><i class="fas fa-envelope-open-text"></i> 时光情书</h4>
                        <label class="form-label" style="display:flex; align-items:center; gap:8px; cursor:pointer; margin:0;">启用 <label class="switch"><input type="checkbox" name="love_letter_enabled" value="1" <?= ($settings['love_letter_enabled'] ?? '') == '1' ? 'checked' : '' ?>><span class="slider"></span></label></label>
                    </div>
                    <div class="grid-2-col" style="display: grid; grid-template-columns: 1fr 2fr; gap: 20px;">
                        <div class="form-group"><label class="form-label">背景音乐 URL</label><input type="text" name="love_letter_music" class="form-control" value="<?= htmlspecialchars($settings['love_letter_music'] ?? '') ?>"></div>
                        <div class="form-group"><label class="form-label">情书内容 (支持 HTML)</label><textarea name="love_letter_content" class="form-control" rows="1"><?= htmlspecialchars($settings['love_letter_content'] ?? '') ?></textarea></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" onclick="closeModal()">取消</button>
                <button type="submit" class="btn btn-love"><i class="fas fa-save"></i> 保存设置</button>
            </div>
        </form>
    </div>
</div>

<!-- Event Modal -->
<div class="modal-overlay" id="eventModal" onclick="closeModal(event)">
    <div class="modal-content">
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="add_event">
            <div class="modal-header">
                <h3 class="modal-title"><i class="fas fa-paper-plane" style="color:var(--primary)"></i> 发布新动态</h3>
                <button type="button" class="btn btn-ghost" style="padding:4px 8px" onclick="closeModal()"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body">
                <div class="form-group"><label class="form-label">日期</label><input type="date" name="event_date" class="form-control" required value="<?= date('Y-m-d') ?>"></div>
                <div class="form-group"><label class="form-label">标题</label><input type="text" name="title" class="form-control" placeholder="今天发生了什么美好？" required></div>
                <div class="form-group"><label class="form-label">详细描述</label><textarea name="description" class="form-control" rows="3" placeholder="写下这一刻的心情..."></textarea></div>
                
                <div class="form-group">
                    <label class="form-label">图片上传 (最多9张)</label>
                    <div class="upload-tabs">
                        <div class="upload-tab active" onclick="switchUploadTab('local', this)">本地上传</div>
                        <div class="upload-tab" onclick="switchUploadTab('net', this)">网络链接</div>
                    </div>
                    <div id="pane-local" class="upload-pane active"><input type="file" name="local_images[]" class="form-control" multiple accept="image/*"></div>
                    <div id="pane-net" class="upload-pane"><textarea name="net_images" class="form-control" rows="3" placeholder="每行一个图片链接..."></textarea></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" onclick="closeModal()">取消</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-check"></i> 发布</button>
            </div>
        </form>
    </div>
</div>

<script src="assets/js/love.js?v=<?php echo time(); ?>"></script>

<?php require 'footer.php'; ?>
