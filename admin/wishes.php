<?php
// admin/wishes.php
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
// --- 0. 引入配置及通用函数 ---
require_once '../includes/config.php';
requireLogin();
$pdo = getDB();
$redis = getRedis(); // [优化] 获取 Redis 连接

// --- [优化] 辅助函数：清除情侣空间相关的缓存 ---
/**
 * 清除所有与情侣空间相关的缓存（包括祝福列表）。
 * 因为祝福是情侣空间页的一部分，修改祝福需要刷新整个页面的缓存。
 */
function clearLoveModuleCache() {
    global $redis;
    if (!$redis) return;

    // 使用 pipeline 提高效率
    $pipe = $redis->pipeline();
    
    // 约定并清除可能存在的祝福列表缓存
    $pipe->del('bkcs:love_wishes_list'); 
    
    // 同时，为保险起见，也清除情侣空间的其他主要缓存
    $pipe->del('bkcs:love_settings');
    $pipe->del('bkcs:love_events_list');
    
    // 执行所有删除命令
    $pipe->execute();
}


// --- 1. 处理批量操作 ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'batch_ops') {
    $ids = $_POST['ids'] ?? [];
    $type = $_POST['batch_type'] ?? '';

    if (!empty($ids) && is_array($ids)) {
        $in  = str_repeat('?,', count($ids) - 1) . '?';
        
        if ($type == 'delete') {
            $pdo->prepare("DELETE FROM love_wishes WHERE id IN ($in)")->execute($ids);
        } elseif ($type == 'copy') {
            $stmt = $pdo->prepare("SELECT * FROM love_wishes WHERE id = ?");
            $insStmt = $pdo->prepare("INSERT INTO love_wishes (user_id, nickname, avatar, content) VALUES (?, ?, ?, ?)");
            foreach ($ids as $id) {
                $stmt->execute([$id]);
                if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $insStmt->execute([$row['user_id'], $row['nickname'], $row['avatar'], $row['content']]); 
                }
            }
        }
        
        clearLoveModuleCache(); // [优化] 任何批量操作后都清除相关缓存
    }
    header("Location: wishes.php"); exit;
}

// --- 2. 处理单条编辑保存 ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'edit_wish') {
    $id = intval($_POST['id']);
    $content = trim($_POST['content']);
    
    if ($id > 0) {
        $stmt = $pdo->prepare("UPDATE love_wishes SET content = ? WHERE id = ?");
        $stmt->execute([$content, $id]);
        clearLoveModuleCache(); // [优化] 编辑祝福后清除缓存
    }
    header("Location: wishes.php"); exit;
}

// --- 3. 读取数据 ---
$wishes = $pdo->query("SELECT * FROM love_wishes ORDER BY created_at DESC")->fetchAll();

require 'header.php';
?>

<!-- UI 部分完全不变 -->
<!-- 引入独立的 CSS 文件 -->
<link rel="stylesheet" href="assets/css/wishes.css?v=<?= time() ?>">

<!-- 页面主操作区 -->
<div class="page-header">
    <h3 class="page-title">祝福留言管理 (<?= count($wishes) ?>)</h3>
</div>

<!-- 祝福列表卡片 -->
<div class="card no-padding">
    <form id="batchForm" method="POST">
        <input type="hidden" name="action" value="batch_ops">
        <input type="hidden" name="batch_type" id="batchType">

        <!-- 工具栏 -->
        <div class="toolbar">
            <label class="checkbox-label" title="全选/反选">
                <input type="checkbox" onchange="toggleAll(this.checked)" class="custom-checkbox">
            </label>
            
            <div class="dropdown">
                <button type="button" class="btn btn-ghost dropdown-toggle">
                    批量操作 <i class="fas fa-angle-down" style="font-size: 10px; margin-left: 6px;"></i>
                </button>
                <div class="dropdown-menu">
                    <button type="button" class="dropdown-item" onclick="submitBatch('copy')">
                        <i class="far fa-copy"></i> 复制选中
                    </button>
                    <div class="dropdown-divider"></div>
                    <button type="button" class="dropdown-item danger" onclick="submitBatch('delete')">
                        <i class="fas fa-trash-alt"></i> 删除选中
                    </button>
                </div>
            </div>
        </div>

        <!-- 数据表格 -->
        <div class="table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th class="col-checkbox"></th>
                        <th class="col-user">用户</th>
                        <th>祝福内容</th>
                        <th class="col-time">时间</th>
                        <th class="col-actions">操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($wishes)): ?>
                        <tr>
                            <td colspan="5" class="empty-state">暂无祝福留言。</td>
                        </tr>
                    <?php else: foreach($wishes as $w): ?>
                        <tr>
                            <td class="col-checkbox">
                                <input type="checkbox" name="ids[]" value="<?= $w['id'] ?>" class="item-check custom-checkbox">
                            </td>
                            <td>
                                <div class="user-cell">
                                    <img src="<?= htmlspecialchars($w['avatar']) ?>" class="user-avatar" alt="Avatar"
                                         onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($w['nickname']) ?>&background=random'">
                                    <span class="user-name"><?= htmlspecialchars($w['nickname']) ?></span>
                                </div>
                            </td>
                            <td>
                                <p class="wish-content" title="<?= htmlspecialchars($w['content']) ?>">
                                    <?= htmlspecialchars($w['content']) ?>
                                </p>
                            </td>
                            <td class="col-time">
                                <span class="wish-time"><?= date('Y-m-d H:i:s', strtotime($w['created_at'])) ?></span>
                            </td>
                            <td class="col-actions">
                                <button type="button" class="btn btn-ghost btn-icon" 
                                        onclick="openEditModal(<?= $w['id'] ?>, '<?= htmlspecialchars(addslashes($w['content'])) ?>')" 
                                        title="编辑">
                                    <i class="fas fa-pen"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </form>
</div>

<!-- 编辑弹窗 Modal -->
<div class="modal-overlay" id="editModal" onclick="closeEditModal(event)">
    <div class="modal-content">
        <form id="editForm" method="POST">
            <input type="hidden" name="action" value="edit_wish">
            <input type="hidden" name="id" id="editId">
            
            <div class="modal-header">
                <h3 class="modal-title">编辑祝福留言</h3>
                <button type="button" class="close-btn" onclick="closeEditModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div class="modal-body">
                <div class="form-group">
                    <label for="editContent" class="form-label">留言内容</label>
                    <textarea id="editContent" name="content" class="form-control" rows="5" placeholder="输入祝福内容..." required></textarea>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" onclick="closeEditModal()">取消</button>
                <button type="submit" class="btn btn-primary">保存修改</button>
            </div>
        </form>
    </div>
</div>

<!-- 引入独立的 JS 文件 -->
<script src="assets/js/wishes.js?v=<?= time() ?>"></script>

<?php require 'footer.php'; ?>
