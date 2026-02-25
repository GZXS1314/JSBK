<?php
// --- 0. 引入配置及通用函数 ---
require_once '../includes/config.php';
requireLogin();
$pdo = getDB();
$redis = getRedis(); // [新增] 获取 Redis 连接

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
// --- [新增] 辅助函数：清除友链缓存 ---
/**
 * 清除友情链接相关的缓存。
 * 通常友链页面会有一个主列表缓存。
 */
function clearFriendsCache() {
    global $redis;
    // 如果 Redis 未连接或未启用，则直接返回
    if (!$redis) {
        return;
    }

    // 我们约定一个前台友链列表的缓存键，例如 'bkcs:friends_list'，并删除它
    $redis->del('bkcs:friends_list');
}

// --- 1. 处理批量操作 ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'batch_ops') {
    $ids = $_POST['ids'] ?? [];
    $type = $_POST['batch_type'] ?? '';
    
    // 只有当有选中项时才执行
    if (!empty($ids) && is_array($ids)) {
        $in = str_repeat('?,', count($ids) - 1) . '?';
        
        if ($type == 'delete') {
            $pdo->prepare("DELETE FROM friends WHERE id IN ($in)")->execute($ids);
        } elseif ($type == 'approve') {
            $pdo->prepare("UPDATE friends SET status = 1 WHERE id IN ($in)")->execute($ids);
        }
        
        clearFriendsCache(); // [新增] 批量操作后清除缓存
    }
    header("Location: friends.php"); exit;
}

// --- 2. 处理单条保存 ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'save_friend') {
    $id = intval($_POST['id']);
    $name = trim($_POST['site_name']);
    $url = trim($_POST['site_url']);
    $avatar = trim($_POST['site_avatar']);
    $desc = trim($_POST['site_desc']);
    $status = intval($_POST['status']);
    
    if ($id > 0) {
        $pdo->prepare("UPDATE friends SET site_name=?, site_url=?, site_avatar=?, site_desc=?, status=? WHERE id=?")->execute([$name, $url, $avatar, $desc, $status, $id]);
    } else {
        $pdo->prepare("INSERT INTO friends (site_name, site_url, site_avatar, site_desc, status) VALUES (?,?,?,?,?)")->execute([$name, $url, $avatar, $desc, $status]);
    }
    
    clearFriendsCache(); // [新增] 保存友链后清除缓存
    
    header("Location: friends.php"); exit;
}

// --- 3. 处理快捷操作 ---
if (isset($_GET['action'])) {
    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    
    if ($id > 0) {
        if ($_GET['action'] == 'approve') {
            $pdo->prepare("UPDATE friends SET status = 1 WHERE id = ?")->execute([$id]);
        }
        if ($_GET['action'] == 'delete') {
            $pdo->prepare("DELETE FROM friends WHERE id = ?")->execute([$id]);
        }
        
        clearFriendsCache(); // [新增] 快捷操作后清除缓存
    }
    
    header("Location: friends.php"); exit;
}

// --- 4. 读取数据 ---
$friends = $pdo->query("SELECT * FROM friends ORDER BY status ASC, id DESC")->fetchAll();
require 'header.php';
?>

<!-- 引入自定义 CSS -->
<link rel="stylesheet" href="assets/css/friends.css">
<!-- FontAwesome (如 header.php 已包含可移除) -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<div class="container-fluid">
    <!-- 头部 -->
    <div class="page-header">
        <h1 class="page-title">
            <i class="fas fa-link text-primary"></i> 友情链接
            <span class="badge-count"><?= count($friends) ?></span>
        </h1>
        <button class="btn btn-primary" onclick="openModal('create')">
            <i class="fas fa-plus"></i> <span class="d-none d-sm-inline">添加友链</span>
        </button>
    </div>

    <!-- 主卡片 -->
    <div class="card">
        <form id="batchForm" method="POST">
            <input type="hidden" name="action" value="batch_ops">
            <input type="hidden" name="batch_type" id="batchType">
            
            <!-- 批量操作栏 -->
            <div class="batch-toolbar">
                <label style="display:flex; align-items:center; gap:6px; cursor:pointer; font-size:14px; margin-right:auto;">
                    <input type="checkbox" onchange="toggleAll(this.checked)" style="width:16px; height:16px; accent-color: var(--primary);">
                    <span style="white-space:nowrap;">全选</span>
                </label>
                
                <button type="button" class="btn btn-outline" onclick="submitBatch('approve')">
                    <i class="fas fa-check"></i> 批量通过
                </button>
                <button type="button" class="btn btn-outline" style="color:var(--danger); border-color: #fee2e2; background:#fff;" onclick="submitBatch('delete')">
                    <i class="fas fa-trash"></i> 批量删除
                </button>
            </div>

            <!-- 数据表格 -->
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th width="40"></th>
                            <th>站点信息</th>
                            <th class="col-desc">描述</th>
                            <th width="100">状态</th>
                            <th width="120" style="text-align:right">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($friends)): ?>
                            <tr><td colspan="5" style="text-align:center; padding: 40px; color: #94a3b8;">暂无数据，点击右上角添加</td></tr>
                        <?php else: foreach($friends as $f): ?>
                        <tr>
                            <td>
                                <input type="checkbox" name="ids[]" value="<?= $f['id'] ?>" class="item-check" style="width:16px; height:16px; accent-color: var(--primary);">
                            </td>
                            <td>
                                <div class="site-info">
                                    <img src="<?= htmlspecialchars($f['site_avatar']) ?>" class="site-avatar" onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($f['site_name']) ?>&background=random&color=fff'">
                                    <div class="site-text">
                                        <div class="site-name"><?= htmlspecialchars($f['site_name']) ?></div>
                                        <a href="<?= htmlspecialchars($f['site_url']) ?>" target="_blank" class="site-url">
                                            <?= htmlspecialchars($f['site_url']) ?> <i class="fas fa-external-link-alt" style="font-size:10px;"></i>
                                        </a>
                                    </div>
                                </div>
                            </td>
                            <td class="col-desc" style="color: var(--text-sub); font-size: 13px; max-width: 250px;">
                                <?= htmlspecialchars(mb_strimwidth($f['site_desc'], 0, 50, '...')) ?: '<span style="color:#cbd5e1">-</span>' ?>
                            </td>
                            <td>
                                <?php if($f['status'] == 0): ?>
                                    <span class="status-badge status-0"><i class="fas fa-clock"></i> 待审核</span>
                                <?php elseif($f['status'] == 1): ?>
                                    <span class="status-badge status-1"><i class="fas fa-check-circle"></i> 已通过</span>
                                <?php else: ?>
                                    <span class="status-badge status-2"><i class="fas fa-ban"></i> 已拒绝</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: right;">
                                <?php if($f['status'] == 0): ?>
                                    <a href="?action=approve&id=<?= $f['id'] ?>" class="btn btn-ghost btn-ghost-success" title="通过">
                                        <i class="fas fa-check"></i>
                                    </a>
                                <?php endif; ?>
                                <!-- 注意：这里使用了 htmlspecialchars 处理 JSON 数据，防止引号截断 -->
                                <button type="button" class="btn btn-ghost" onclick='openModal("edit", <?= json_encode($f) ?>)' title="编辑">
                                    <i class="fas fa-pen"></i>
                                </button>
                                <a href="?action=delete&id=<?= $f['id'] ?>" class="btn btn-ghost btn-ghost-danger" onclick="return confirm('确定删除该友链吗？')" title="删除">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </form>
    </div>
</div>

<!-- 弹窗组件 -->
<div class="modal-overlay" id="friendModal" onclick="if(event.target === this) closeModal()">
    <div class="modal-content">
        <form method="POST">
            <input type="hidden" name="action" value="save_friend">
            <input type="hidden" name="id" id="f_id" value="0">
            
            <div class="modal-header">
                <span id="modalTitle">添加友链</span>
                <button type="button" onclick="closeModal()" style="background:none; border:none; cursor:pointer; font-size:18px; color:var(--text-sub);"><i class="fas fa-times"></i></button>
            </div>
            
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">站点名称 <span style="color:red">*</span></label>
                        <input type="text" name="site_name" id="f_name" class="form-control" placeholder="如: Google" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">状态</label>
                        <select name="status" id="f_status" class="form-control">
                            <option value="0">🕒 待审核</option>
                            <option value="1">✅ 已通过</option>
                            <option value="2">🚫 已拒绝</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">站点链接 <span style="color:red">*</span></label>
                    <input type="url" name="site_url" id="f_url" class="form-control" placeholder="https://" required>
                </div>

                <div class="form-group">
                    <label class="form-label">头像地址</label>
                    <div style="display: flex; gap: 10px;">
                        <input type="text" name="site_avatar" id="f_avatar" class="form-control" placeholder="https://" oninput="updatePreview(this.value)">
                        <img id="avatarPreview" src="https://placehold.co/40x40?text=Img" style="width: 40px; height: 40px; border-radius: 6px; object-fit: cover; border: 1px solid #eee;">
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">站点描述</label>
                    <textarea name="site_desc" id="f_desc" class="form-control" rows="3" placeholder="一句话介绍..."></textarea>
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
<script src="assets/js/friends.js"></script>

<?php require 'footer.php'; ?>
