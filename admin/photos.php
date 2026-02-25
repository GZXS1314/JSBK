<?php
// admin/photos.php
/**
 * Photo Gallery Manager
 * Supports Batch Upload & Management
 */
// 1. 开启输出缓冲区
ob_start();

require_once '../includes/config.php';
requireLogin();

$pdo = getDB();
$redis = getRedis();

// --- [辅助] 缓存清理函数 ---
function clearPhotoAndAlbumCache() {
    global $redis;
    if (!$redis || !is_object($redis)) return;
    try {
        $keys = array_merge(
            $redis->keys('bkcs:albums*'),
            $redis->keys('bkcs:photos*')
        );
        if (!empty($keys)) $redis->del($keys);
    } catch (Throwable $e) {
        error_log("Redis Cache Clear Failed: " . $e->getMessage());
    }
}

// --- 2. 处理 POST 请求 ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // A. 批量操作 (保持不变)
    if (isset($_POST['action']) && $_POST['action'] == 'batch_ops') {
        $ids = $_POST['ids'] ?? [];
        $type = $_POST['batch_type'] ?? '';

        if (!empty($ids) && is_array($ids) && !empty($type)) {
            $ids = array_map('intval', $ids); // 安全过滤
            $in = str_repeat('?,', count($ids) - 1) . '?';
            
            $sql = "";
            if ($type == 'delete') {
                $sql = "DELETE FROM photos WHERE id IN ($in)";
            } elseif ($type == 'hide') {
                $sql = "UPDATE photos SET is_hidden = 1 WHERE id IN ($in)";
            } elseif ($type == 'show') {
                $sql = "UPDATE photos SET is_hidden = 0 WHERE id IN ($in)";
            } elseif ($type == 'feature') {
                $sql = "UPDATE photos SET is_featured = 1 WHERE id IN ($in)";
            } elseif ($type == 'unfeature') {
                $sql = "UPDATE photos SET is_featured = 0 WHERE id IN ($in)";
            }

            if ($sql) {
                $stmt = $pdo->prepare($sql);
                $stmt->execute($ids);
                clearPhotoAndAlbumCache();
            }
        }
    }

    // B. [修改] 批量发布新照片
    if (isset($_POST['action']) && $_POST['action'] == 'upload_photo') {
        $album_id = intval($_POST['album_id'] ?? 0);
        $base_title = trim($_POST['title'] ?? ''); // 基础标题
        $device = trim($_POST['device'] ?? '');
        $is_featured = isset($_POST['is_featured']) ? 1 : 0;
        
        // 读取 COS 设置
        $stmt_cos = $pdo->prepare("SELECT value FROM settings WHERE key_name = 'cos_enabled'");
        $stmt_cos->execute();
        $cosEnabled = $stmt_cos->fetchColumn(); 

        // [核心修改] 检测 image_files 数组
        if ($album_id > 0 && isset($_FILES['image_files'])) {
            $files = $_FILES['image_files'];
            // 获取上传文件数量
            $file_count = count($files['name']);
            $success_count = 0;

            // 循环处理每一张图片
            for ($i = 0; $i < $file_count; $i++) {
                if ($files['error'][$i] === UPLOAD_ERR_OK) {
                    $tmp_name = $files['tmp_name'][$i];
                    $name = $files['name'][$i];
                    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));

                    if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                        // 生成唯一文件名，防止重名
                        $newName = 'photo_' . date('Ymd_His_') . uniqid() . '_' . $i . '.' . $ext;
                        $final_url = '';

                        // COS 上传
                        if ($cosEnabled == '1') {
                            require_once '../includes/cos_helper.php';
                            $cosPath = 'uploads/' . date('Ym') . '/' . $newName;
                            $cosUrl = uploadToCOS($tmp_name, $cosPath);
                            if ($cosUrl) $final_url = $cosUrl;
                        }

                        // 本地回退
                        if (empty($final_url)) {
                            $uploadDirRel = '../assets/uploads/'; // 物理路径
                            $uploadDirWeb = '/assets/uploads/';   // 网页路径
                            if (!is_dir($uploadDirRel)) @mkdir($uploadDirRel, 0755, true);
                            if (move_uploaded_file($tmp_name, $uploadDirRel . $newName)) {
                                $final_url = $uploadDirWeb . $newName;
                            }
                        }

                        if (!empty($final_url)) {
                            // 如果用户没有填标题，默认空；如果填了，所有图片共用一个标题
                            $this_title = $base_title;
                            
                            $stmt = $pdo->prepare("INSERT INTO photos (album_id, title, device, image_url, is_featured) VALUES (?, ?, ?, ?, ?)");
                            $stmt->execute([$album_id, $this_title, $device, $final_url, $is_featured]);
                            $success_count++;
                        }
                    }
                }
            }

            if ($success_count > 0) {
                clearPhotoAndAlbumCache();
            }
        }
    }
    
    ob_end_clean();
    header("Location: photos.php"); 
    exit;
}

// --- 3. 处理单个删除 (保持不变) ---
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    if ($id > 0) {
        $pdo->prepare("DELETE FROM photos WHERE id = ?")->execute([$id]);
        clearPhotoAndAlbumCache();
    }
    ob_end_clean();
    header("Location: photos.php"); exit;
}

// --- 4. 数据读取 (保持不变) ---

// 获取所有相册供筛选和上传使用
$albums = $pdo->query("SELECT * FROM albums ORDER BY sort_order ASC")->fetchAll();

// 构建查询条件
$where = "WHERE 1=1";
$params = [];

// 按相册筛选
$filter_album_id = isset($_GET['album_id']) ? intval($_GET['album_id']) : 0;
if ($filter_album_id > 0) {
    $where .= " AND p.album_id = ?";
    $params[] = $filter_album_id;
}

// 分页计算
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$pageSize = 24; 
$offset = ($page - 1) * $pageSize;

// 1. 查询总数
$countSql = "SELECT COUNT(*) FROM photos p $where";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalRows = $countStmt->fetchColumn();
$totalPages = ceil($totalRows / $pageSize);

// 2. 查询当前页数据
$sql = "SELECT p.*, a.name as album_name 
        FROM photos p 
        LEFT JOIN albums a ON p.album_id = a.id 
        $where 
        ORDER BY p.id DESC 
        LIMIT :offset, :limit";

$stmt = $pdo->prepare($sql);
foreach ($params as $k => $v) {
    $stmt->bindValue($k + 1, $v); 
}
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->bindValue(':limit', $pageSize, PDO::PARAM_INT);
$stmt->execute();
$photos = $stmt->fetchAll();

require 'header.php';
ob_end_flush();
?>

<link rel="stylesheet" href="assets/css/photos.css">
<link href="https://cdn.bootcdn.net/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">

<style>
    /* 简单的分页样式 */
    .pagination-bar { display: flex; justify-content: space-between; align-items: center; margin-top: 20px; padding-top: 20px; border-top: 1px solid #eee; }
    .pagination { display: flex; gap: 5px; }
    .page-link { padding: 6px 12px; border: 1px solid #ddd; border-radius: 4px; text-decoration: none; color: #333; background: #fff; font-size: 14px; }
    .page-link:hover { background: #f8fafc; }
    .page-link.active { background: var(--primary); color: #fff; border-color: var(--primary); }
    
    /* 筛选下拉框样式 */
    .filter-select { padding: 6px 10px; border: 1px solid #cbd5e1; border-radius: 6px; outline: none; font-size: 14px; margin-right: 10px; }
</style>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <h3 style="font-size: 18px; font-weight: 600; color: var(--text-main); display: flex; align-items: center;">
        <i class="fas fa-camera-retro" style="color: var(--primary); margin-right: 8px;"></i>照片库 
        <span style="font-size: 14px; color: var(--text-tertiary); font-weight: 400; margin-left: 8px;">(共 <?= $totalRows ?> 张)</span>
    </h3>
    <button class="btn btn-primary" onclick="openModal()">
        <i class="fas fa-cloud-upload-alt"></i> 批量上传照片
    </button>
</div>

<div class="card">
    <form id="batchForm" method="POST">
        <input type="hidden" name="action" value="batch_ops">
        <input type="hidden" name="batch_type" id="batchType">

        <div class="toolbar" style="flex-wrap: wrap; gap: 10px;">
            <!-- 全选 -->
            <label class="btn btn-ghost" style="padding: 8px 10px;" title="全选/反选">
                <input type="checkbox" onchange="toggleAll(this.checked)" style="width:16px; height:16px; accent-color: var(--primary);">
                <span style="font-size:13px; margin-left:6px;">全选</span>
            </label>
            
            <!-- 批量操作 -->
            <div class="dropdown">
                <button type="button" class="btn btn-ghost">批量操作 <i class="fas fa-angle-down" style="font-size: 10px; margin-left: 6px;"></i></button>
                <div class="dropdown-content">
                    <div class="dropdown-item" onclick="submitBatch('show')"><i class="fas fa-eye"></i> 设为显示</div>
                    <div class="dropdown-item" onclick="submitBatch('hide')"><i class="fas fa-eye-slash"></i> 设为隐藏</div>
                    <div style="height:1px; background:#f1f5f9; margin:4px 0;"></div>
                    <div class="dropdown-item" onclick="submitBatch('feature')"><i class="fas fa-star" style="color:#f59e0b"></i> 设为精选</div>
                    <div class="dropdown-item" onclick="submitBatch('unfeature')"><i class="far fa-star"></i> 取消精选</div>
                </div>
            </div>
            
            <!-- 筛选功能 -->
            <div style="margin-left: auto; display: flex; align-items: center;">
                <select class="filter-select" onchange="window.location.href='?album_id='+this.value">
                    <option value="0">全部相册</option>
                    <?php foreach($albums as $a): ?>
                        <option value="<?= $a['id'] ?>" <?= $filter_album_id == $a['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($a['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <button type="button" class="btn btn-ghost" onclick="submitBatch('delete')" style="color: var(--danger);">
                <i class="fas fa-trash-alt"></i> 批量删除
            </button>
        </div>

        <!-- 照片网格 -->
        <div class="photo-grid">
            <?php if(empty($photos)): ?>
                <div style="grid-column: 1 / -1; text-align: center; padding: 40px; color: #94a3b8;">
                    暂无照片数据
                </div>
            <?php else: foreach($photos as $p): ?>
            <div class="photo-card" onclick="toggleSelect(this)">
                <input type="checkbox" name="ids[]" value="<?= $p['id'] ?>" class="pc-checkbox" onclick="event.stopPropagation(); toggleSelect(this.closest('.photo-card'))">
                <div class="pc-img-box">
                    <img src="<?= htmlspecialchars($p['image_url']) ?>" 
                         loading="lazy" 
                         onerror="this.src='assets/img/error.png'">
                    
                    <?php if($p['is_featured']): ?>
                        <span class="badge-hero"><i class="fas fa-star"></i> 精选</span>
                    <?php elseif(isset($p['is_hidden']) && $p['is_hidden']): ?>
                        <span class="badge-hidden"><i class="fas fa-eye-slash"></i> 隐藏</span>
                    <?php endif; ?>
                </div>
                <div class="pc-info">
                    <div class="pc-title" title="<?= htmlspecialchars($p['title']) ?>"><?= htmlspecialchars($p['title'] ?: '无标题') ?></div>
                    <div class="pc-album"><i class="far fa-folder" style="font-size:10px; margin-right:4px;"></i><?= htmlspecialchars($p['album_name'] ?: '未分类') ?></div>
                </div>
            </div>
            <?php endforeach; endif; ?>
        </div>
        
        <!-- 分页条 -->
        <?php if($totalPages > 1): ?>
        <div class="pagination-bar">
            <div style="font-size: 14px; color: #64748b;">
                页码: <?= $page ?> / <?= $totalPages ?>
            </div>
            <div class="pagination">
                <?php 
                $qs = $filter_album_id > 0 ? "&album_id=$filter_album_id" : "";
                ?>
                <?php if($page > 1): ?>
                    <a href="?page=1<?= $qs ?>" class="page-link">首页</a>
                    <a href="?page=<?= $page - 1 ?><?= $qs ?>" class="page-link">上一页</a>
                <?php endif; ?>
                
                <a href="javascript:;" class="page-link active"><?= $page ?></a>
                
                <?php if($page < $totalPages): ?>
                    <a href="?page=<?= $page + 1 ?><?= $qs ?>" class="page-link">下一页</a>
                    <a href="?page=<?= $totalPages ?><?= $qs ?>" class="page-link">尾页</a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

    </form>
</div>

<!-- 上传弹窗 [重点修改区域] -->
<div class="modal-overlay" id="uploadModal" onclick="closeModal(event)">
    <div class="modal-content">
        <form id="uploadForm" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="upload_photo">
            
            <div class="modal-header">
                <h3 class="modal-title">批量上传照片</h3>
                <button type="button" class="btn btn-ghost" style="padding: 4px 8px;" data-close="true"><i class="fas fa-times"></i></button>
            </div>
            
            <div class="modal-body">
                <div class="form-group">
                    <label for="cover-upload" class="form-label">选择图片文件 (可多选)</label>
                    <label for="cover-upload" class="upload-area">
                        <!-- [修改] name变更为数组，添加 multiple 属性，事件改为 previewImages -->
                        <input type="file" name="image_files[]" id="cover-upload" accept="image/*" multiple onchange="previewImages(this)">
                        
                        <!-- 默认提示 -->
                        <div id="upload-prompt">
                            <div class="upload-icon"><i class="fas fa-cloud-upload-alt"></i></div>
                            <div class="upload-text">点击或拖拽文件到此处 (支持多选)</div>
                        </div>
                        
                        <!-- [修改] 新的预览网格容器 -->
                        <div id="preview-container" style="display:none;">
                            <div class="preview-grid" id="preview-grid">
                                <!-- JS 将在这里动态插入 img 标签 -->
                            </div>
                            <div style="margin-top:8px; font-size:12px; color:#64748b; text-align:center;">
                                已选中 <span id="selected-count" style="color:var(--primary); font-weight:bold;">0</span> 张照片准备上传
                            </div>
                        </div>
                    </label>
                </div>
                
                <!-- [样式修正] form-row 在移动端会强制 flex-direction: row -->
                <div class="form-row">
                    <div class="form-group" style="flex-grow:1;">
                        <label class="form-label">所属相册 <span style="color:red">*</span></label>
                        <select name="album_id" class="form-control" required>
                            <option value="">-- 请选择 --</option>
                            <?php foreach($albums as $a): ?>
                                <option value="<?= $a['id'] ?>" <?= $filter_album_id == $a['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($a['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" style="flex-grow:1;">
                        <label class="form-label">统一标题 (选填)</label>
                        <input type="text" name="title" class="form-control" placeholder="应用于所有照片">
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">拍摄设备 (选填)</label>
                    <input type="text" name="device" class="form-control" placeholder="Sony A7R3">
                </div>
            </div>
            
            <div class="modal-footer">
                <label style="display:flex; align-items:center; gap:8px; font-size:13px; cursor:pointer;">
                    <input type="checkbox" name="is_featured" value="1" style="width:16px; height:16px; accent-color: #f59e0b;"> 
                    <span style="color:#f59e0b; font-weight:bold;"><i class="fas fa-star"></i> 全部设为精选</span>
                </label>
                <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i> 开始上传</button>
            </div>
        </form>
    </div>
</div>

<script src="assets/js/photos.js"></script>

<?php require 'footer.php'; ?>
