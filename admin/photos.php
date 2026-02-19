<?php
// admin/photos.php
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
    
    // A. 批量操作
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

    // B. 发布新照片
    if (isset($_POST['action']) && $_POST['action'] == 'upload_photo') {
        $album_id = intval($_POST['album_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $device = trim($_POST['device'] ?? '');
        $is_featured = isset($_POST['is_featured']) ? 1 : 0;
        
        // 读取 COS 设置
        $stmt_cos = $pdo->prepare("SELECT value FROM settings WHERE key_name = 'cos_enabled'");
        $stmt_cos->execute();
        $cosEnabled = $stmt_cos->fetchColumn(); 

        if ($album_id > 0 && isset($_FILES['image_file']) && $_FILES['image_file']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['image_file'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                $newName = 'photo_' . date('Ymd_His_') . uniqid() . '.' . $ext;
                $final_url = '';

                // COS 上传
                if ($cosEnabled == '1') {
                    require_once '../includes/cos_helper.php';
                    $cosPath = 'uploads/' . date('Ym') . '/' . $newName;
                    $cosUrl = uploadToCOS($file['tmp_name'], $cosPath);
                    if ($cosUrl) $final_url = $cosUrl;
                }

                // 本地回退
                if (empty($final_url)) {
                    $uploadDirRel = '../assets/uploads/'; // 物理路径
                    $uploadDirWeb = '/assets/uploads/';   // 网页路径
                    if (!is_dir($uploadDirRel)) @mkdir($uploadDirRel, 0755, true);
                    if (move_uploaded_file($file['tmp_name'], $uploadDirRel . $newName)) {
                        $final_url = $uploadDirWeb . $newName;
                    }
                }

                if (!empty($final_url)) {
                    $stmt = $pdo->prepare("INSERT INTO photos (album_id, title, device, image_url, is_featured) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$album_id, $title, $device, $final_url, $is_featured]);
                    clearPhotoAndAlbumCache();
                }
            }
        }
    }
    
    ob_end_clean();
    header("Location: photos.php"); 
    exit;
}

// --- 3. 处理单个删除 ---
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    if ($id > 0) {
        $pdo->prepare("DELETE FROM photos WHERE id = ?")->execute([$id]);
        clearPhotoAndAlbumCache();
    }
    ob_end_clean();
    header("Location: photos.php"); exit;
}

// --- 4. 数据读取 (核心优化：分页 + 筛选) ---

// 获取所有相册供筛选和上传使用
$albums = $pdo->query("SELECT * FROM albums ORDER BY sort_order ASC")->fetchAll();

// 构建查询条件
$where = "WHERE 1=1";
$params = [];

// [新增] 按相册筛选
$filter_album_id = isset($_GET['album_id']) ? intval($_GET['album_id']) : 0;
if ($filter_album_id > 0) {
    $where .= " AND p.album_id = ?";
    $params[] = $filter_album_id;
}

// 分页计算
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$pageSize = 24; // 每页24张，网格布局比较整齐
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
        LIMIT $offset, $pageSize";

$stmt = $pdo->prepare($sql);
// PDO bindParam在LIMIT中有时会有类型问题，这里直接拼在SQL里是安全的因为$offset和$pageSize是计算出来的整数
// 或者再次 execute params
// 为了简单起见，我们重新构造 execute 的参数，因为 LIMIT 最好直接用 bindValue
$stmt = $pdo->prepare("SELECT p.*, a.name as album_name 
                       FROM photos p 
                       LEFT JOIN albums a ON p.album_id = a.id 
                       $where 
                       ORDER BY p.id DESC 
                       LIMIT :offset, :limit");

// 绑定筛选参数
foreach ($params as $k => $v) {
    $stmt->bindValue($k + 1, $v); // PDO索引从1开始
}
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->bindValue(':limit', $pageSize, PDO::PARAM_INT);
$stmt->execute();
$photos = $stmt->fetchAll();

require 'header.php';
ob_end_flush();
?>

<link rel="stylesheet" href="assets/css/photos.css">
<!-- [优化] 使用国内 CDN -->
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
        <i class="fas fa-cloud-upload-alt"></i> 上传新照片
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
            
            <!-- [新增] 筛选功能 -->
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
                    <!-- [优化] 懒加载 + 本地错误图 -->
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
        
        <!-- [新增] 分页条 -->
        <?php if($totalPages > 1): ?>
        <div class="pagination-bar">
            <div style="font-size: 14px; color: #64748b;">
                页码: <?= $page ?> / <?= $totalPages ?>
            </div>
            <div class="pagination">
                <?php 
                // 简单的分页逻辑，保留当前筛选参数
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

<!-- 上传弹窗 (无变化，保持原样) -->
<div class="modal-overlay" id="uploadModal" onclick="closeModal(event)">
    <div class="modal-content">
        <form id="uploadForm" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="upload_photo">
            
            <div class="modal-header">
                <h3 class="modal-title">上传新照片</h3>
                <button type="button" class="btn btn-ghost" style="padding: 4px 8px;" data-close="true"><i class="fas fa-times"></i></button>
            </div>
            
            <div class="modal-body">
                <div class="form-group">
                    <label for="cover-upload" class="form-label">选择图片文件</label>
                    <label for="cover-upload" class="upload-area">
                        <input type="file" name="image_file" id="cover-upload" accept="image/*" onchange="previewImage(this)">
                        <div id="upload-prompt">
                            <div class="upload-icon"><i class="fas fa-cloud-upload-alt"></i></div>
                            <div class="upload-text">点击或拖拽文件到此处上传</div>
                        </div>
                        <div id="preview-container">
                            <img id="preview-img" src="">
                        </div>
                    </label>
                </div>
                
                <div class="form-row">
                    <div class="form-group" style="flex-grow:1;">
                        <label class="form-label">所属相册 <span style="color:red">*</span></label>
                        <select name="album_id" class="form-control" required>
                            <option value="">-- 请选择 --</option>
                            <?php foreach($albums as $a): ?>
                                <!-- 自动选中当前筛选的相册 -->
                                <option value="<?= $a['id'] ?>" <?= $filter_album_id == $a['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($a['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" style="flex-grow:1;">
                        <label class="form-label">照片标题 (选填)</label>
                        <input type="text" name="title" class="form-control" placeholder="山间清晨">
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
                    <span style="color:#f59e0b; font-weight:bold;"><i class="fas fa-star"></i> 设为精选</span>
                </label>
                <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i> 确认上传</button>
            </div>
        </form>
    </div>
</div>

<script src="assets/js/photos.js"></script>

<?php require 'footer.php'; ?>
