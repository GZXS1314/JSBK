<?php
// admin/users.php
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

// --- [优化] 辅助函数：清除单个用户的缓存 ---
function clearUserCache($userId) {
    global $redis;
    if (!$redis || !$userId) return;
    // 约定用户缓存键模式为 bkcs:user:{id}
    $redis->del('bkcs:user:' . $userId);
}

// --- [优化] 辅助函数：清除所有文章缓存 ---
// (因为用户删除会影响评论和点赞数)
function clearAllArticleCache() {
    global $redis;
    if (!$redis) return;
    
    // 使用 SCAN 避免阻塞，并用 pipeline 批量删除
    $iterator = null;
    do {
        // 同时匹配列表和详情页缓存
        $keys = $redis->scan($iterator, 'bkcs:list:*');
        $keys = array_merge($keys, $redis->scan($iterator, 'bkcs:article:*'));
        if (!empty($keys)) {
            $pipe = $redis->pipeline();
            foreach ($keys as $key) {
                $pipe->del($key);
            }
            $pipe->execute();
        }
    } while ($iterator > 0);
}


// --- 1. 逻辑处理部分 ---
if ((isset($_GET['action']) && $_GET['action'] == 'delete') || (isset($_POST['action']) && $_POST['action'] == 'batch_delete')) {
    $ids = [];
    if (isset($_GET['id'])) {
        $ids[] = intval($_GET['id']);
    } elseif (isset($_POST['ids']) && is_array($_POST['ids'])) {
        $ids = array_map('intval', $_POST['ids']);
    }
    
    if (!empty($ids)) {
        // 构建占位符
        $in  = str_repeat('?,', count($ids) - 1) . '?';
        
        // 级联删除：评论 -> 点赞 -> 用户
        $pdo->prepare("DELETE FROM comments WHERE user_id IN ($in)")->execute($ids);
        $pdo->prepare("DELETE FROM article_likes WHERE user_id IN ($in)")->execute($ids);
        $pdo->prepare("DELETE FROM users WHERE id IN ($in)")->execute($ids);
        
        // [优化] 清除被删除用户的缓存
        foreach ($ids as $userId) {
            clearUserCache($userId);
        }
        // [优化] 清除所有文章缓存，因为评论和点赞数已改变
        clearAllArticleCache();
    }
    header("Location: users.php"); exit;
}

if (isset($_GET['action']) && $_GET['action'] == 'toggle_ban' && isset($_GET['id'])) {
    $userId = intval($_GET['id']);
    if ($userId > 0) {
        $pdo->prepare("UPDATE users SET is_banned = NOT is_banned WHERE id = ?")->execute([$userId]);
        // [优化] 封禁/解封后，必须清除该用户的缓存以立即生效
        clearUserCache($userId);
    }
    header("Location: users.php"); exit;
}

// 获取用户列表
$users = $pdo->query("SELECT * FROM users ORDER BY created_at DESC")->fetchAll();

require 'header.php';
?>

<!-- UI 部分完全不变 -->
<!-- 引入独立的 CSS 文件 -->
<link rel="stylesheet" href="assets/css/users.css?v=<?= time() ?>">

<!-- 页面主操作区 -->
<div class="page-header">
    <h3 class="page-title">用户管理 (<?= count($users) ?>)</h3>
</div>

<!-- 用户列表卡片 -->
<div class="card no-padding">
    <form id="listForm" method="POST">
        <input type="hidden" name="action" value="batch_delete">
        
        <!-- 工具栏 -->
        <div class="toolbar">
            <label class="checkbox-label" title="全选/反选">
                <input type="checkbox" onchange="toggleAll(this.checked)" class="custom-checkbox">
            </label>
            <button type="button" class="btn btn-danger-ghost" onclick="batchDelete()">
                <i class="fas fa-trash-alt"></i> 批量删除
            </button>
            <div class="toolbar-info">
                <i class="fas fa-info-circle"></i> 删除用户会清空其所有关联数据
            </div>
        </div>

        <!-- 数据表格 -->
        <div class="table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th class="col-checkbox"></th>
                        <th class="col-user">用户</th>
                        <th>邮箱</th>
                        <th class="col-date">注册时间</th>
                        <th class="col-status">状态</th>
                        <th class="col-actions">操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($users)): ?>
                        <tr>
                            <td colspan="6" class="empty-state">暂无用户数据。</td>
                        </tr>
                    <?php else: foreach($users as $u): ?>
                        <tr>
                            <td class="col-checkbox">
                                <input type="checkbox" name="ids[]" value="<?= $u['id'] ?>" class="item-check custom-checkbox">
                            </td>
                            <td>
                                <div class="user-cell">
                                    <img src="<?= htmlspecialchars($u['avatar'] ?: 'https://ui-avatars.com/api/?name='.urlencode($u['nickname'] ?: $u['username']).'&background=random') ?>" class="user-avatar" alt="Avatar">
                                    <div class="user-info">
                                        <div class="user-name"><?= htmlspecialchars($u['nickname']) ?></div>
                                        <div class="user-id">@<?= htmlspecialchars($u['username']) ?></div>
                                    </div>
                                </div>
                            </td>
                            <td class="user-email"><?= htmlspecialchars($u['email']) ?></td>
                            <td class="user-date"><?= date('Y-m-d H:i', strtotime($u['created_at'])) ?></td>
                            <td class="col-status">
                                <?php if($u['is_banned']): ?>
                                    <span class="status-badge status-banned"><i class="fas fa-ban"></i> 封禁中</span>
                                <?php else: ?>
                                    <span class="status-badge status-normal"><i class="fas fa-check-circle"></i> 正常</span>
                                <?php endif; ?>
                            </td>
                            <td class="col-actions">
                                <div class="action-buttons">
                                    <a href="?action=toggle_ban&id=<?= $u['id'] ?>" class="btn btn-ghost" title="<?= $u['is_banned'] ? '解封用户' : '封禁用户' ?>">
                                        <i class="fas <?= $u['is_banned'] ? 'fa-unlock-alt' : 'fa-ban' ?>" style="color: <?= $u['is_banned'] ? '#16a34a' : '#f59e0b' ?>;"></i>
                                    </a>
                                    <a href="?action=delete&id=<?= $u['id'] ?>" class="btn btn-ghost btn-danger-ghost" 
                                       onclick="return confirm('⚠️ 警告：确定删除用户“<?= htmlspecialchars($u['nickname']) ?>”吗？\n这将同时删除该用户的所有评论和点赞！')" 
                                       title="删除">
                                        <i class="fas fa-trash-alt"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </form>
</div>

<!-- 引入独立的 JS 文件 -->
<script src="assets/js/users.js?v=<?= time() ?>"></script>

<?php require 'footer.php'; ?>
