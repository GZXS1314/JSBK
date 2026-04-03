<?php
/**
 * admin/points_log.php - 积分收支明细日志
 */
require_once '../includes/config.php';
requireLogin();
$pdo = getDB();

// 分页与搜索参数
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;
$search = trim($_GET['search'] ?? '');

// 构建查询条件
$where = "1=1";
$params = [];
if ($search !== '') {
    $where .= " AND (l.action LIKE ? OR l.description LIKE ? OR u.username LIKE ? OR u.nickname LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// 获取总数
$stmt_count = $pdo->prepare("SELECT COUNT(*) FROM points_log l LEFT JOIN users u ON l.user_id = u.id WHERE $where");
$stmt_count->execute($params);
$total_records = $stmt_count->fetchColumn();
$total_pages = ceil($total_records / $limit);

// 获取列表数据
$sql = "SELECT l.*, u.username, u.nickname, u.avatar 
        FROM points_log l 
        LEFT JOIN users u ON l.user_id = u.id 
        WHERE $where 
        ORDER BY l.id DESC 
        LIMIT $offset, $limit";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

// 动作类型字典 (美化显示)
$action_dict = [
    'daily_login' => ['name' => '每日登录', 'color' => '#3b82f6', 'icon' => 'fa-calendar-check'],
    'register' => ['name' => '新用户注册', 'color' => '#8b5cf6', 'icon' => 'fa-user-plus'],
    'comment_article' => ['name' => '发表评论', 'color' => '#10b981', 'icon' => 'fa-comment-dots'],
    'like_article' => ['name' => '点赞文章', 'color' => '#ec4899', 'icon' => 'fa-heart'],
    'share_article' => ['name' => '分享文章', 'color' => '#06b6d4', 'icon' => 'fa-share-nodes'],
    'recharge' => ['name' => '余额充值', 'color' => '#f59e0b', 'icon' => 'fa-coins'],
    'pay_view' => ['name' => '付费阅读', 'color' => '#ef4444', 'icon' => 'fa-book-open-reader'],
    'pay_resource' => ['name' => '资源下载', 'color' => '#ef4444', 'icon' => 'fa-cloud-arrow-down']
];

require 'header.php';
?>

<style>
    .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
    .page-title { font-size: 20px; font-weight: bold; color: #1e293b; margin: 0; }
    .search-box { display: flex; gap: 10px; }
    .search-input { padding: 8px 15px; border: 1px solid #e2e8f0; border-radius: 8px; outline: none; width: 250px; }
    .search-btn { background: #0f172a; color: #fff; border: none; padding: 8px 15px; border-radius: 8px; cursor: pointer; }
    
    .data-table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
    .data-table th, .data-table td { padding: 15px; text-align: left; border-bottom: 1px solid #f1f5f9; font-size: 14px; }
    .data-table th { background: #f8fafc; font-weight: 600; color: #475569; }
    .data-table tr:hover { background: #f8fafc; }
    
    .user-info { display: flex; align-items: center; gap: 10px; }
    .user-info img { width: 30px; height: 30px; border-radius: 50%; object-fit: cover; }
    
    .change-positive { color: #10b981; font-weight: bold; font-size: 16px; }
    .change-negative { color: #ef4444; font-weight: bold; font-size: 16px; }
    
    .action-badge { display: inline-flex; align-items: center; gap: 6px; padding: 5px 12px; border-radius: 6px; font-size: 12px; font-weight: 600; background: #f8fafc; border: 1px solid #e2e8f0;}
    
    .pagination { display: flex; gap: 5px; margin-top: 20px; justify-content: center; }
    .page-btn { padding: 8px 12px; border: 1px solid #e2e8f0; border-radius: 6px; color: #475569; text-decoration: none; background: #fff; font-size: 14px; }
    .page-btn.active { background: #0f172a; color: #fff; border-color: #0f172a; }
    .page-btn:hover:not(.active) { background: #f1f5f9; }
</style>

<div style="padding: 20px; max-width: 1200px; margin: 0 auto;">
    <div class="page-header">
        <h1 class="page-title"><i class="fas fa-coins"></i> 积分收支明细</h1>
        <form method="GET" class="search-box">
            <input type="text" name="search" class="search-input" placeholder="搜索动作 / 描述 / 用户" value="<?= htmlspecialchars($search) ?>">
            <button type="submit" class="search-btn"><i class="fas fa-search"></i> 搜索</button>
            <?php if($search): ?><a href="points_log.php" class="page-btn">重置</a><?php endif; ?>
        </form>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th>流水 ID</th>
                <th>用户</th>
                <th>变动类型</th>
                <th>积分变动</th>
                <th>详细说明</th>
                <th>记录时间</th>
            </tr>
        </thead>
        <tbody>
            <?php if(empty($logs)): ?>
                <tr><td colspan="6" style="text-align: center; padding: 30px; color: #94a3b8;">暂无流水记录</td></tr>
            <?php else: ?>
                <?php foreach($logs as $row): ?>
                    <?php 
                        $action_info = $action_dict[$row['action']] ?? ['name' => $row['action'], 'color' => '#64748b', 'icon' => 'fa-hashtag'];
                    ?>
                    <tr>
                        <td style="color: #94a3b8; font-family: monospace;">#<?= $row['id'] ?></td>
                        <td>
                            <div class="user-info">
                                <img src="<?= htmlspecialchars($row['avatar'] ?: 'https://ui-avatars.com/api/?name=User') ?>" alt="avatar">
                                <div>
                                    <div style="font-weight:600;"><?= htmlspecialchars($row['nickname'] ?: '未知用户') ?></div>
                                    <div style="font-size:12px; color:#94a3b8;">@<?= htmlspecialchars($row['username']) ?></div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="action-badge" style="color: <?= $action_info['color'] ?>;">
                                <i class="fas <?= $action_info['icon'] ?>"></i> <?= $action_info['name'] ?>
                            </div>
                        </td>
                        <td>
                            <?php if($row['points_change'] > 0): ?>
                                <span class="change-positive">+<?= $row['points_change'] ?></span>
                            <?php else: ?>
                                <span class="change-negative"><?= $row['points_change'] ?></span>
                            <?php endif; ?>
                        </td>
                        <td style="color: #475569; font-size: 13px; max-width: 250px; line-height: 1.5;">
                            <?= htmlspecialchars($row['description']) ?>
                        </td>
                        <td style="color: #64748b; font-size: 13px;">
                            <?= $row['created_at'] ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <?php if($total_pages > 1): ?>
        <div class="pagination">
            <?php for($i = 1; $i <= $total_pages; $i++): ?>
                <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>" class="page-btn <?= $i == $page ? 'active' : '' ?>"><?= $i ?></a>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
</div>

<?php require 'footer.php'; ?>