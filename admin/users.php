<?php
// admin/users.php
/**
                _ _                    ____  _                              
               | (_) __ _ _ __   __ _  / ___|| |__  _   _  ___              
            _  | | |/ _` | '_ \ / _` | \___ \| '_ \| | | |/ _ \             
           | |_| | | (_| | | | | (_| |  ___) | | | | |_| | (_) |            
            \___/|_|\__,_|_| |_|\__, | |____/|_| |_|\__,_|\___/             
   ____  _____          _  __  |___/  _____  _  _  _          ____ ____ 
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
$redis = getRedis();

// --- 辅助函数：清除单个用户的缓存 ---
function clearUserCache($userId) {
    global $redis;
    if (!$redis || !$userId) return;
    $redis->del('bkcs:user:' . $userId);
}

// --- 辅助函数：清除所有文章缓存 ---
function clearAllArticleCache() {
    global $redis;
    if (!$redis) return;
    $iterator = null;
    do {
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

// --- 积分变动逻辑 ---
if (isset($_POST['action']) && $_POST['action'] == 'update_points') {
    $userId = intval($_POST['user_id']);
    $pointsChange = intval($_POST['points_change']);
    $description = trim($_POST['description']) ?: '后台管理员调整';

    if ($userId > 0 && $pointsChange != 0) {
        $pdo->prepare("UPDATE users SET points = points + ? WHERE id = ?")->execute([$pointsChange, $userId]);
        $actionType = $pointsChange > 0 ? 'admin_add' : 'admin_deduct';
        $pdo->prepare("INSERT INTO points_log (user_id, action, points_change, description) VALUES (?, ?, ?, ?)")
            ->execute([$userId, $actionType, $pointsChange, $description]);
        clearUserCache($userId);
    }
    // 回到带有原筛选参数的页面
    $referer = $_SERVER['HTTP_REFERER'] ?? 'users.php';
    header("Location: $referer"); exit;
}

// --- 删除/封禁逻辑 ---
if ((isset($_GET['action']) && $_GET['action'] == 'delete') || (isset($_POST['action']) && $_POST['action'] == 'batch_delete')) {
    $ids = [];
    if (isset($_GET['id'])) {
        $ids[] = intval($_GET['id']);
    } elseif (isset($_POST['ids']) && is_array($_POST['ids'])) {
        $ids = array_map('intval', $_POST['ids']);
    }
    
    if (!empty($ids)) {
        $in  = str_repeat('?,', count($ids) - 1) . '?';
        $pdo->prepare("DELETE FROM comments WHERE user_id IN ($in)")->execute($ids);
        $pdo->prepare("DELETE FROM article_likes WHERE user_id IN ($in)")->execute($ids);
        $pdo->prepare("DELETE FROM users WHERE id IN ($in)")->execute($ids);
        foreach ($ids as $userId) clearUserCache($userId);
        clearAllArticleCache();
    }
    $referer = $_SERVER['HTTP_REFERER'] ?? 'users.php';
    header("Location: $referer"); exit;
}

if (isset($_GET['action']) && $_GET['action'] == 'toggle_ban' && isset($_GET['id'])) {
    $userId = intval($_GET['id']);
    if ($userId > 0) {
        $pdo->prepare("UPDATE users SET is_banned = NOT is_banned WHERE id = ?")->execute([$userId]);
        clearUserCache($userId);
    }
    $referer = $_SERVER['HTTP_REFERER'] ?? 'users.php';
    header("Location: $referer"); exit;
}

// --- 2. 获取用户等级配置并建立映射 ---
$user_levels_json = $pdo->query("SELECT value FROM settings WHERE key_name = 'user_levels_config'")->fetchColumn();
$user_levels = json_decode($user_levels_json ?: '[]', true);
if (empty($user_levels)) {
    $user_levels = [
        ['level' => 1, 'points' => 0, 'name' => '青铜会员'],
        ['level' => 2, 'points' => 100, 'name' => '白银会员'],
        ['level' => 3, 'points' => 500, 'name' => '黄金会员'],
        ['level' => 4, 'points' => 1500, 'name' => '钻石会员'],
        ['level' => 5, 'points' => 5000, 'name' => '星耀会员'],
    ];
}
usort($user_levels, function($a, $b) { return $a['points'] <=> $b['points']; });

// 根据积分获取等级信息的辅助函数
function getUserLevelInfo($points, $levels) {
    $current = $levels[0];
    foreach ($levels as $lvl) {
        if ($points >= $lvl['points']) {
            $current = $lvl;
        } else {
            break;
        }
    }
    return $current;
}

// --- 3. 处理筛选参数并构建 SQL ---
$where = ["1=1"];
$params = [];

$min_points = isset($_GET['min_points']) && $_GET['min_points'] !== '' ? intval($_GET['min_points']) : null;
$max_points = isset($_GET['max_points']) && $_GET['max_points'] !== '' ? intval($_GET['max_points']) : null;
$filter_level = isset($_GET['level']) && $_GET['level'] !== '' ? intval($_GET['level']) : null;

// 积分范围筛选
if ($min_points !== null) {
    $where[] = "points >= ?";
    $params[] = $min_points;
}
if ($max_points !== null) {
    $where[] = "points <= ?";
    $params[] = $max_points;
}

// 等级筛选（将其转换为积分区间的 WHERE 条件）
if ($filter_level !== null) {
    $lvl_min_points = 0;
    $lvl_max_points = null;
    foreach ($user_levels as $idx => $lvl) {
        if ($lvl['level'] == $filter_level) {
            $lvl_min_points = $lvl['points'];
            if (isset($user_levels[$idx + 1])) {
                $lvl_max_points = $user_levels[$idx + 1]['points'] - 1;
            }
            break;
        }
    }
    $where[] = "points >= ?";
    $params[] = $lvl_min_points;
    if ($lvl_max_points !== null) {
        $where[] = "points <= ?";
        $params[] = $lvl_max_points;
    }
}

$where_sql = implode(' AND ', $where);
$stmt = $pdo->prepare("SELECT * FROM users WHERE $where_sql ORDER BY created_at DESC");
$stmt->execute($params);
$users = $stmt->fetchAll();

require 'header.php';
?>

<link rel="stylesheet" href="assets/css/users.css?v=<?= time() ?>">

<div class="page-header">
    <h3 class="page-title">用户管理 (<?= count($users) ?>)</h3>
</div>

<div class="filter-card">
    <form method="GET" action="users.php" class="filter-form">
        <div class="filter-group">
            <label>等级筛选</label>
            <select name="level" class="form-control">
                <option value="">全部等级</option>
                <?php foreach($user_levels as $lvl): ?>
                    <option value="<?= $lvl['level'] ?>" <?= ($filter_level === $lvl['level']) ? 'selected' : '' ?>>
                        Lv.<?= $lvl['level'] ?> <?= htmlspecialchars($lvl['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-group">
            <label>积分区间</label>
            <div class="range-inputs">
                <input type="number" name="min_points" class="form-control" placeholder="最低分" value="<?= $min_points !== null ? $min_points : '' ?>">
                <span>-</span>
                <input type="number" name="max_points" class="form-control" placeholder="最高分" value="<?= $max_points !== null ? $max_points : '' ?>">
            </div>
        </div>
        <div class="filter-actions">
            <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> 筛选</button>
            <a href="users.php" class="btn btn-ghost"><i class="fas fa-redo"></i> 重置</a>
        </div>
    </form>
</div>

<div class="card no-padding">
    <form id="listForm" method="POST">
        <input type="hidden" name="action" value="batch_delete">
        
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

        <div class="table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th class="col-checkbox"></th>
                        <th class="col-user">用户</th>
                        <th>邮箱</th>
                        <th class="col-level">身份等级</th>
                        <th class="col-points">积分</th>
                        <th class="col-date">注册时间</th>
                        <th class="col-status">状态</th>
                        <th class="col-actions">操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($users)): ?>
                        <tr>
                            <td colspan="8" class="empty-state">暂无符合条件的用户。</td>
                        </tr>
                    <?php else: foreach($users as $u): 
                        // 动态计算该用户的等级信息
                        $lvlInfo = getUserLevelInfo(intval($u['points']), $user_levels);
                    ?>
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
                            
                            <td class="col-level">
                                <span class="level-badge">
                                    Lv.<?= $lvlInfo['level'] ?> <?= htmlspecialchars($lvlInfo['name']) ?>
                                </span>
                            </td>

                            <td class="col-points">
                                <span class="points-value">
                                    <i class="fas fa-coins" style="color: #f59e0b;"></i> <?= intval($u['points']) ?>
                                </span>
                            </td>
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
                                    <button type="button" class="btn btn-ghost" onclick="openPointsModal(<?= $u['id'] ?>, '<?= htmlspecialchars($u['nickname']) ?>', <?= $u['points'] ?>)" title="调整积分">
                                        <i class="fas fa-hand-holding-usd" style="color: #3b82f6;"></i>
                                    </button>
                                    <a href="?action=toggle_ban&id=<?= $u['id'] ?>&<?= http_build_query($_GET) ?>" class="btn btn-ghost" title="<?= $u['is_banned'] ? '解封用户' : '封禁用户' ?>">
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

<div id="pointsModal" class="modal-overlay" style="display: none;">
    <div class="points-modal-content">
        <h4>💎 调整用户积分</h4>
        <p class="modal-subtitle">正在为用户 <strong id="pointsModalUserName" style="color: var(--primary);"></strong> 操作</p>
        
        <form method="POST" action="users.php">
            <input type="hidden" name="action" value="update_points">
            <input type="hidden" name="user_id" id="pointsModalUserId" value="">
            
            <div class="form-group">
                <label>当前积分</label>
                <input type="text" id="pointsModalCurrent" disabled class="form-control" style="background-color: #f8fafc; cursor: not-allowed;">
            </div>
            <div class="form-group">
                <label>变动数量 <span class="text-muted">(支持负数，如 -10 表示扣除)</span></label>
                <input type="number" name="points_change" required class="form-control" placeholder="输入变动数值，例如: 50 或 -20">
            </div>
            <div class="form-group">
                <label>变动说明 <span class="text-muted">(可选，将记录在积分日志中)</span></label>
                <input type="text" name="description" class="form-control" placeholder="例如：参与活动奖励">
            </div>
            
            <div class="modal-actions">
                <button type="button" class="btn btn-ghost" onclick="closePointsModal()">取消</button>
                <button type="submit" class="btn btn-primary">确认保存</button>
            </div>
        </form>
    </div>
</div>

<script src="assets/js/users.js?v=<?= time() ?>"></script>

<?php require 'footer.php'; ?>