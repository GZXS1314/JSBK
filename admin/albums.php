<?php
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
// admin/albums.php
// --- 0. 引入配置及通用函数 ---
require_once '../includes/config.php';
requireLogin();
$pdo = getDB();
$redis = getRedis();

// [辅助函数] 清除相册缓存
function clearAlbumCache() {
    global $redis;
    if (!$redis) return;
    $keys = $redis->keys('bkcs:albums*'); 
    if (!empty($keys)) {
        foreach ($keys as $k) {
            $redis->del($k);
        }
    }
}

// --- 1. AJAX 获取相册详情 ---
if (isset($_GET['action']) && $_GET['action'] == 'get_detail' && isset($_GET['id'])) {
    header('Content-Type: application/json');
    $id = intval($_GET['id']);
    $stmt = $pdo->prepare("SELECT * FROM albums WHERE id = ?");
    $stmt->execute([$id]);
    $album = $stmt->fetch(PDO::FETCH_ASSOC);
    echo json_encode(['success' => !!$album, 'data' => $album]);
    exit;
}

// --- 2. 处理 POST 请求 ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    
    // 2.1 批量操作
    if ($_POST['action'] == 'batch_ops') {
        $ids = $_POST['ids'] ?? [];
        $type = $_POST['batch_type']; 
        if (!empty($ids) && is_array($ids)) {
            // 确保ID都是整数，防止注入
            $ids = array_map('intval', $ids);
            $in = str_repeat('?,', count($ids) - 1) . '?';
            
            if ($type == 'hide' || $type == 'show') {
                $val = ($type == 'hide') ? 1 : 0;
                $sql = "UPDATE albums SET is_hidden = ? WHERE id IN ($in)";
                $pdo->prepare($sql)->execute(array_merge([$val], $ids));
            }
            clearAlbumCache();
        }
    }

    // 2.2 保存相册 (新增/编辑)
    if ($_POST['action'] == 'save_album') {
        $id = intval($_POST['id']);
        $name = trim($_POST['name']);
        $sort = intval($_POST['sort_order']);
        $is_hidden = isset($_POST['is_hidden']) ? 1 : 0;
        
        $cover_image = '';
        
        // 处理封面上传
        if (isset($_FILES['cover_file']) && $_FILES['cover_file']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['cover_file'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                // 检查是否开启了 COS
                $stmtCos = $pdo->prepare("SELECT value FROM settings WHERE key_name = 'cos_enabled'");
                $stmtCos->execute();
                $cosEnabled = $stmtCos->fetchColumn();
                
                $newName = 'album_' . date('YmdHis') . rand(1000,9999) . '.' . $ext;
                
                if ($cosEnabled == '1') {
                    require_once '../includes/cos_helper.php';
                    $cosPath = 'uploads/albums/' . $newName;
                    $uploadedUrl = uploadToCOS($file['tmp_name'], $cosPath);
                    if ($uploadedUrl) {
                        $cover_image = $uploadedUrl;
                    }
                } else {
                    $uploadDir = '../assets/uploads/';
                    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                    if (move_uploaded_file($file['tmp_name'], $uploadDir . $newName)) {
                        $cover_image = $uploadDir . $newName;
                    }
                }
            }
        }

        // 数据库操作
        if ($id > 0) { 
            // 编辑模式
            if (empty($cover_image)) $cover_image = $_POST['old_cover_image'];
            // [优化] 如果还是没有图，使用本地默认图，不再请求远程地址
            if (empty($cover_image)) $cover_image = 'assets/img/default.png';
            
            $stmt = $pdo->prepare("UPDATE albums SET name=?, sort_order=?, cover_image=?, is_hidden=? WHERE id=?");
            $stmt->execute([$name, $sort, $cover_image, $is_hidden, $id]);
        } else { 
            // 新增模式
            // [优化] 默认使用本地图片
            if (empty($cover_image)) $cover_image = 'assets/img/default.png';
            
            $stmt = $pdo->prepare("INSERT INTO albums (name, sort_order, cover_image, is_hidden) VALUES (?, ?, ?, ?)");
            $stmt->execute([$name, $sort, $cover_image, $is_hidden]);
        }

        clearAlbumCache();
    }

    header("Location: albums.php"); 
    exit;
}

// --- 3. 处理删除 ---
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $pdo->prepare("DELETE FROM albums WHERE id = ?")->execute([$id]);
    clearAlbumCache();
    header("Location: albums.php"); 
    exit;
}

// --- 4. 页面渲染 (核心优化：分页逻辑) ---
// 获取当前页码
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$pageSize = 15; // 每页显示15条，减少单次加载压力
$offset = ($page - 1) * $pageSize;

// 1. 查询总记录数
$countStmt = $pdo->query("SELECT COUNT(*) FROM albums");
$totalRows = $countStmt->fetchColumn();
$totalPages = ceil($totalRows / $pageSize);

// 2. 分页查询数据 (使用 LIMIT)
$sql = "SELECT * FROM albums ORDER BY sort_order ASC, id DESC LIMIT :offset, :limit";
$stmt = $pdo->prepare($sql);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->bindValue(':limit', $pageSize, PDO::PARAM_INT);
$stmt->execute();
$albums = $stmt->fetchAll();

require 'header.php';
?>

<!-- 引入自定义 CSS -->
<link rel="stylesheet" href="assets/css/albums.css">
<!-- [优化] 替换 FontAwesome 为国内 BootCDN，解决加载卡顿 -->
<link href="https://cdn.bootcdn.net/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">

<!-- 简单的分页样式 -->
<style>
    .pagination { display: flex; align-items: center; justify-content: flex-end; gap: 8px; margin-top: 20px; }
    .page-link { padding: 6px 12px; border: 1px solid #ddd; border-radius: 4px; text-decoration: none; color: #333; background: #fff; }
    .page-link:hover { background: #f1f5f9; }
    .page-link.active { background: var(--primary); color: #fff; border-color: var(--primary); }
    .page-info { color: #64748b; font-size: 14px; margin-right: 10px; }
</style>

<div class="container-fluid">
    <!-- 顶部 -->
    <div class="page-header">
        <h1 class="page-title"><i class="fas fa-images text-primary"></i> 相册管理</h1>
        <button class="btn btn-primary" onclick="openModal('create')">
            <i class="fas fa-plus"></i> <span class="d-none d-sm-inline">新建相册</span>
        </button>
    </div>

    <!-- 主卡片 -->
    <div class="card">
        <form id="batchForm" method="POST">
            <input type="hidden" name="action" value="batch_ops">
            <input type="hidden" name="batch_type" id="batchType">
            
            <!-- 工具栏 -->
            <div class="batch-toolbar">
                <label style="display:flex; align-items:center; gap:6px; cursor:pointer; font-size:14px; margin-right:auto;">
                    <input type="checkbox" onchange="toggleAll(this.checked)" style="width:16px; height:16px; accent-color: var(--primary);">
                    <span style="white-space:nowrap;">全选</span>
                </label>
                
                <button type="button" class="btn btn-outline" onclick="submitBatch('hide')">
                    <i class="fas fa-eye-slash"></i> 隐藏
                </button>
                <button type="button" class="btn btn-outline" onclick="submitBatch('show')">
                    <i class="fas fa-eye"></i> 显示
                </button>
            </div>

            <!-- 数据表格 -->
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th width="40"></th>
                            <th width="60" class="col-id">ID</th>
                            <th>相册信息</th>
                            <th width="80" class="whitespace-nowrap">排序</th>
                            <th width="80" class="whitespace-nowrap">状态</th>
                            <th width="120" style="text-align:right">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($albums)): ?>
                            <tr><td colspan="6" style="text-align:center; padding: 40px; color: #94a3b8;">
                                <?php echo ($totalRows > 0) ? '本页没有数据' : '暂无数据，请点击右上角新建'; ?>
                            </td></tr>
                        <?php else: foreach($albums as $a): ?>
                        <tr>
                            <td><input type="checkbox" name="ids[]" value="<?= $a['id'] ?>" class="item-check" style="width:16px; height:16px; accent-color: var(--primary);"></td>
                            <td class="col-id"><?= $a['id'] ?></td>
                            <td>
                                <div style="display:flex; align-items:center; gap:12px;">
                                    <img src="<?= htmlspecialchars($a['cover_image']) ?>" 
                                         loading="lazy"
                                         class="cover-img" 
                                         onerror="this.src='assets/img/error.png'">
                                    <div style="font-weight: 500;"><?= htmlspecialchars($a['name']) ?></div>
                                </div>
                            </td>
                            <td><?= $a['sort_order'] ?></td>
                            <td class="whitespace-nowrap">
                                <?php if($a['is_hidden']): ?>
                                    <span class="status-badge status-hidden">隐藏</span>
                                <?php else: ?>
                                    <span class="status-badge status-active">显示</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: right;" class="whitespace-nowrap">
                                <button type="button" class="btn btn-outline btn-sm" onclick="openModal('edit', <?= $a['id'] ?>)">
                                    <i class="fas fa-pen"></i>
                                </button>
                                <a href="?action=delete&id=<?= $a['id'] ?>" class="btn btn-danger-light btn-sm" onclick="return confirm('确定要删除该相册吗？此操作不可恢复。')">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>

            <!--分页导航栏 -->
            <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <span class="page-info">共 <?= $totalRows ?> 条，<?= $page ?>/<?= $totalPages ?> 页</span>
                
                <?php if ($page > 1): ?>
                    <a href="?page=1" class="page-link">&laquo; 首页</a>
                    <a href="?page=<?= $page - 1 ?>" class="page-link">上一页</a>
                <?php endif; ?>

                <!-- 显示当前页 -->
                <a href="javascript:;" class="page-link active"><?= $page ?></a>

                <?php if ($page < $totalPages): ?>
                    <a href="?page=<?= $page + 1 ?>" class="page-link">下一页</a>
                    <a href="?page=<?= $totalPages ?>" class="page-link">尾页 &raquo;</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
        </form>
    </div>
</div>

<!-- 弹窗组件 -->
<div class="modal-overlay" id="albumModal" onclick="if(event.target === this) closeModal()">
    <div class="modal-content">
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="save_album">
            <input type="hidden" name="id" id="albumId" value="0">
            <input type="hidden" name="old_cover_image" id="oldCover" value="">
            
            <div class="modal-header">
                <span class="modal-title" id="modalTitle">新建相册</span>
                <button type="button" onclick="closeModal()" style="background:none; border:none; font-size:18px; cursor:pointer; color:#64748b;"><i class="fas fa-times"></i></button>
            </div>
            
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">相册名称 <span style="color:red">*</span></label>
                    <input type="text" name="name" id="albumName" class="form-control" placeholder="请输入相册名称" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">封面图片</label>
                    <div class="upload-box" onclick="document.getElementById('coverFile').click()">
                        <input type="file" name="cover_file" id="coverFile" accept="image/*" style="display:none" onchange="previewImage(this)">
                        
                        <div class="upload-placeholder" id="uploadPlaceholder">
                            <i class="fas fa-cloud-upload-alt" style="font-size: 24px; color: var(--primary);"></i>
                            <span>点击上传封面图</span>
                        </div>
                        <img id="imgPreview" class="upload-preview" src="">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">排序值 (越小越靠前)</label>
                    <input type="number" name="sort_order" id="albumSort" class="form-control" value="0">
                </div>

                <div class="form-group">
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                        <input type="checkbox" name="is_hidden" id="albumHidden" value="1" style="width: 18px; height: 18px; accent-color: var(--primary);">
                        <span>设为隐藏状态</span>
                    </label>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal()">取消</button>
                <button type="submit" class="btn btn-primary" id="submitBtn">保存</button>
            </div>
        </form>
    </div>
</div>

<!-- 引入自定义 JS -->
<script src="assets/js/albums.js"></script>

<?php require 'footer.php'; ?>
