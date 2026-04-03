<?php
/**
 * admin/orders.php - 充值订单管理
 */
require_once '../includes/config.php';
requireLogin();
$pdo = getDB();

// 分页与搜索参数
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 15;
$offset = ($page - 1) * $limit;
$search = trim($_GET['search'] ?? '');

// 构建查询条件
$where = "1=1";
$params = [];
if ($search !== '') {
    $where .= " AND (o.order_no LIKE ? OR o.trade_no LIKE ? OR u.username LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// 获取总数
$stmt_count = $pdo->prepare("SELECT COUNT(*) FROM recharge_orders o LEFT JOIN users u ON o.user_id = u.id WHERE $where");
$stmt_count->execute($params);
$total_records = $stmt_count->fetchColumn();
$total_pages = ceil($total_records / $limit);

// 获取列表数据
$sql = "SELECT o.*, u.username, u.nickname, u.avatar 
        FROM recharge_orders o 
        LEFT JOIN users u ON o.user_id = u.id 
        WHERE $where 
        ORDER BY o.id DESC 
        LIMIT $offset, $limit";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();

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
    
    .status-badge { padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; }
    .status-0 { background: #fef3c7; color: #d97706; } /* 未支付 */
    .status-1 { background: #dcfce7; color: #059669; } /* 已支付 */
    
    .pay-type { display: inline-flex; align-items: center; gap: 5px; font-size: 13px; font-weight: 600; }
    
    .user-info { display: flex; align-items: center; gap: 10px; }
    .user-info img { width: 30px; height: 30px; border-radius: 50%; object-fit: cover; }
    
    .pagination { display: flex; gap: 5px; margin-top: 20px; justify-content: center; }
    .page-btn { padding: 8px 12px; border: 1px solid #e2e8f0; border-radius: 6px; color: #475569; text-decoration: none; background: #fff; font-size: 14px; }
    .page-btn.active { background: #0f172a; color: #fff; border-color: #0f172a; }
    .page-btn:hover:not(.active) { background: #f1f5f9; }
</style>

<div style="padding: 20px; max-width: 1200px; margin: 0 auto;">
    <div class="page-header">
        <h1 class="page-title"><i class="fas fa-file-invoice-dollar"></i> 充值订单管理</h1>
        <form method="GET" class="search-box">
            <input type="text" name="search" class="search-input" placeholder="搜索订单号 / 流水号 / 用户名" value="<?= htmlspecialchars($search) ?>">
            <button type="submit" class="search-btn"><i class="fas fa-search"></i> 搜索</button>
            <?php if($search): ?><a href="orders.php" class="page-btn">重置</a><?php endif; ?>
        </form>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th>订单号 / 流水号</th>
                <th>用户</th>
                <th>充值金额</th>
                <th>获得积分</th>
                <th>支付方式</th>
                <th>状态</th>
                <th>创建/支付时间</th>
            </tr>
        </thead>
        <tbody>
            <?php if(empty($orders)): ?>
                <tr><td colspan="7" style="text-align: center; padding: 30px; color: #94a3b8;">暂无订单数据</td></tr>
            <?php else: ?>
                <?php foreach($orders as $row): ?>
                    <tr>
                        <td>
                            <div style="font-weight: 600; color: #0f172a;"><?= htmlspecialchars($row['order_no']) ?></div>
                            <div style="font-size: 12px; color: #94a3b8; margin-top: 4px;"><?= htmlspecialchars($row['trade_no'] ?: '等待支付...') ?></div>
                        </td>
                        <td>
                            <div class="user-info">
                                <img src="<?= htmlspecialchars($row['avatar'] ?: 'https://ui-avatars.com/api/?name=User') ?>" alt="avatar">
                                <div>
                                    <div style="font-weight:600;"><?= htmlspecialchars($row['nickname'] ?: '未知用户') ?></div>
                                    <div style="font-size:12px; color:#94a3b8;">@<?= htmlspecialchars($row['username']) ?></div>
                                </div>
                            </div>
                        </td>
                        <td style="font-weight: bold; color: #ef4444;">￥<?= $row['amount'] ?></td>
                        <td style="font-weight: bold; color: #3b82f6;">+<?= $row['points'] ?></td>
                        <td>
                            <?php if(strpos($row['pay_type'], 'alipay') !== false): ?>
                                <span class="pay-type" style="color:#00a1d6;"><i class="fab fa-alipay"></i> 支付宝</span>
                            <?php elseif(strpos($row['pay_type'], 'wxpay') !== false): ?>
                                <span class="pay-type" style="color:#07c160;"><i class="fab fa-weixin"></i> 微信支付</span>
                            <?php else: ?>
                                <span class="pay-type"><i class="fas fa-wallet"></i> <?= htmlspecialchars($row['pay_type']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if($row['status'] == 1): ?>
                                <span class="status-badge status-1">已支付</span>
                            <?php else: ?>
                                <span class="status-badge status-0">未支付</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div style="font-size:13px; color:#475569;"><?= $row['created_at'] ?> (创)</div>
                            <?php if($row['paid_at']): ?>
                                <div style="font-size:12px; color:#059669; margin-top:4px;"><?= $row['paid_at'] ?> (付)</div>
                            <?php endif; ?>
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