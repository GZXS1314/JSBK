<?php
// admin/categories.php
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
$redis = getRedis(); 

function clearRelatedCache() {
    global $redis;
    if (!$redis) {
        return;
    }
    $listKeys = $redis->keys('bkcs:list:*');
    if (!empty($listKeys) && is_array($listKeys)) {
        $pipe = $redis->multi(Redis::PIPELINE);
        
        foreach ($listKeys as $key) {
            $pipe->del($key);
        }
        
        $pipe->exec();
    }
    
    $redis->del('bkcs:categories_list');
}


// --- 1. 处理新增分类 ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'add') {
    // 简单的服务端校验
    $name = trim($_POST['name'] ?? '');
    $sort = intval($_POST['sort_order'] ?? 0);

    if (!empty($name)) {
        $stmt = $pdo->prepare("INSERT INTO categories (name, sort_order) VALUES (?, ?)");
        $stmt->execute([$name, $sort]);
        
        clearRelatedCache(); // [新增] 新增分类后清除缓存
    }
    
    header("Location: categories.php"); 
    exit;
}

// --- 2. 处理 GET 请求 (删除/切换状态) ---
if (isset($_GET['action'])) {
    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    
    // 删除操作
    if($_GET['action'] == 'delete' && $id > 0) {
        $pdo->prepare("DELETE FROM categories WHERE id=?")->execute([$id]);
        clearRelatedCache(); // [新增] 删除分类后清除缓存
    }
    
    // 切换隐藏/显示状态操作
    if($_GET['action'] == 'toggle_hide' && $id > 0) {
        // 使用 NOT is_hidden 翻转布尔值
        $pdo->prepare("UPDATE categories SET is_hidden = NOT is_hidden WHERE id=?")->execute([$id]);
        clearRelatedCache(); // [新增] 更新分类状态后清除缓存
    }
    
    header("Location: categories.php"); 
    exit;
}

// --- 3. 获取数据用于页面渲染 ---
$list = $pdo->query("SELECT * FROM categories ORDER BY sort_order ASC, id DESC")->fetchAll();

require 'header.php';
?>

<link rel="stylesheet" href="assets/css/categories.css">

<div class="card">
    <h3 style="font-size: 16px; font-weight: 600; margin-bottom: 20px; padding-bottom: 16px; border-bottom: 1px solid #eef2ff; display:flex; align-items:center; gap:10px; color: var(--text-main);">
        <i class="fas fa-plus-circle" style="color: var(--primary);"></i>
        添加新分类
    </h3>
    
    <form method="POST" class="add-form-grid">
        <input type="hidden" name="action" value="add">
        <div class="form-group">
            <label for="category-name" class="form-label">分类名称</label>
            <input type="text" id="category-name" name="name" class="form-control" placeholder="例如：技术教程" required>
        </div>
        <div class="form-group">
            <label for="category-sort" class="form-label">排序值 (越小越靠前)</label>
            <input type="number" id="category-sort" name="sort_order" class="form-control" placeholder="0" value="0">
        </div>
        <div class="form-group">
            <button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> 添加</button>
        </div>
    </form>
</div>

<div class="card" style="padding: 0; overflow: hidden;">
    <div class="table-wrapper">
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 80px;">排序</th>
                    <th>分类名称</th>
                    <th style="width: 120px;">状态</th>
                    <th style="width: 120px; text-align: right;">操作</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($list)): ?>
                    <tr><td colspan="4" style="text-align:center; padding: 40px; color: var(--text-tertiary);">暂无分类数据，请在上方添加一个新分类。</td></tr>
                <?php else: ?>
                    <?php foreach($list as $item): ?>
                    <tr>
                        <td><span class="badge badge-mono"><?= $item['sort_order'] ?></span></td>
                        <td><div style="font-weight: 600; color: var(--text-main);"><?= htmlspecialchars($item['name']) ?></div></td>
                        <td>
                            <?php if($item['is_hidden']): ?>
                                <span class="badge badge-secondary"><i class="fas fa-eye-slash"></i> 隐藏</span>
                            <?php else: ?>
                                <span class="badge badge-success"><i class="fas fa-check-circle"></i> 显示</span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align: right;">
                            <div class="action-buttons">
                                <a href="?action=toggle_hide&id=<?= $item['id'] ?>" class="btn btn-ghost" title="<?= $item['is_hidden'] ? '点击显示' : '点击隐藏' ?>">
                                    <i class="fas <?= $item['is_hidden'] ? 'fa-eye' : 'fa-eye-slash' ?>"></i>
                                </a>
                                <a href="?action=delete&id=<?= $item['id'] ?>" class="btn btn-ghost danger btn-delete" data-name="<?= htmlspecialchars($item['name']) ?>" title="删除">
                                    <i class="fas fa-trash-alt"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script src="assets/js/categories.js"></script>

<?php require 'footer.php'; ?>