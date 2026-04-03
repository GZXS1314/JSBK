<?php
// pages/friends.php
/**              _ _                      ____  _                             
                | (_) __ _ _ __   __ _   / ___|| |__  _   _  ___              
             _  | | |/ _` | '_ \ / _` | \___ \| '_ \| | | |/ _ \             
            | |_| | | (_| | | | | (_| |  ___) | | | | |_| | (_) |            
             \___/|_|\__,_|_| |_|\__, | |____/|_| |_|\__,_|\___/             
    ____  _____          _  __  |___/  _____   _  _  _          ____ ____ 
  / ___| |__  /         | | \ \/ / / | |___ /  / | | || |        / ___/ ___|
 | |  _    / /       _  | |  \  /  | |   |_ \  | | | || |_      | |  | |   
 | |_| |  / /_   _  | |_| |  /  \  | |  ___) | | | |__   _|  _  | |__| |___ 
  \____| /____| (_)  \___/  /_/\_\ |_| |____/  |_|    |_|   (_)  \____\____|
                                                                            
                                追求极致的美学                               
**/

// 1. 统一引入全局头部 (包含数据库、缓存、Session的极速初始化)
require_once __DIR__ . '/header.php';

// 2. API 处理逻辑：申请友链
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_GET['action']) && $_GET['action'] == 'apply') {
    ob_clean(); // 清除前面的任何意外输出
    header('Content-Type: application/json; charset=utf-8');
    
    if (empty($_SESSION['user_id'])) {
        die(json_encode(['success' => false, 'msg' => '请先登录后再申请友链']));
    }
    
    $name = trim($_POST['site_name'] ?? '');
    $url = trim($_POST['site_url'] ?? '');
    $avatar = trim($_POST['site_avatar'] ?? '');
    $desc = trim($_POST['site_desc'] ?? '');
    
    if (!$name || !$url) {
        die(json_encode(['success' => false, 'msg' => '站点名称和链接不能为空']));
    }
    
    try {
        global $pdo;
        $stmt = $pdo->prepare("INSERT INTO friends (site_name, site_url, site_avatar, site_desc, status, created_at) VALUES (?, ?, ?, ?, 0, NOW())");
        $res = $stmt->execute([$name, $url, $avatar, $desc]);
        die(json_encode(['success' => $res, 'msg' => $res ? '申请成功，已进入审核队列' : '提交失败']));
    } catch (Exception $e) {
        error_log('Friend apply error: ' . $e->getMessage()); 
        die(json_encode(['success' => false, 'msg' => '系统繁忙，请稍后再试']));
    }
}

// --- [调试专属] 强行清理缓存 ---
if (isset($_GET['clear_cache']) && $_GET['clear_cache'] == '1') {
    Cache::del('friends_approved_list');
}

// --- 3. 获取友链数据 (使用极速双层 Cache 类) ---
$friends = Cache::get('friends_approved_list');

if ($friends === false) {
    try {
        global $pdo;
        $friends = $pdo->query("SELECT * FROM friends WHERE status = 1 ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
        Cache::set('friends_approved_list', $friends, 3600); // 缓存 1 小时
    } catch (PDOException $e) {
        $friends = []; 
    }
}

// 动态分排 (分成 3 行走马灯)
$total = count($friends);
$rows = [];
if ($total > 0) {
    $perRow = ceil($total / 3);
    $rows = array_chunk($friends, max(1, $perRow));
}
?>

<link href="https://fonts.loli.net/css2?family=Outfit:wght@300;400;600;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= THEME_URL ?>/assets/css/friends.css?v=<?= time() ?>">
<div class="friends-page-body">
    <div class="bg-decoration">
        <div class="blob blob-1"></div>
        <div class="blob blob-2"></div>
        <div class="blob blob-3"></div>
    </div>
    
    <div class="title-area">
        <h1>Partners.</h1>
        <p>每一位链接都是一段奇妙的相遇</p>
    </div>
    
    <div class="friends-canvas">
        <div class="scroll-track-container">
            <?php if (empty($rows)): ?>
                <div style="text-align:center; color:#86868b; padding: 40px; width: 100%;">期待您的加入...</div>
            <?php else: ?>
                <?php foreach ($rows as $index => $rowFriends): 
                    $dirClass = ($index % 2 == 0) ? 'animate-l' : 'animate-r';
                    // 复制数组以确保无缝滚动 (如果数据太少，多复制几次)
                    $displayList = array_merge($rowFriends, $rowFriends, $rowFriends, $rowFriends);
                ?>
                    <div class="track <?= $dirClass ?>">
                        <?php foreach($displayList as $f): ?>
                            <a href="<?= htmlspecialchars($f['site_url']) ?>" target="_blank" class="sq-card">
                                <img src="<?= htmlspecialchars($f['site_avatar'] ?: 'https://ui-avatars.com/api/?background=random&name='.$f['site_name']) ?>" class="sq-avatar" loading="lazy">
                                <div class="sq-name"><?= htmlspecialchars($f['site_name']) ?></div>
                                <div class="sq-desc"><?= htmlspecialchars($f['site_desc'] ?: '这家伙很神秘，什么都没留下') ?></div>
                                <div class="sq-time"><?= isset($f['created_at']) ? date('M Y', strtotime($f['created_at'])) : 'UNK' ?></div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <div class="action-bar"><button class="btn-apply" id="applyBtn">申请交换友链</button></div>
    </div>
</div>

<div class="modal" id="friendModal">
    <div class="modal-box">
        <h2 style="margin:0 0 25px 0; text-align:center;">申请加入</h2>
        <form id="applyForm">
            <input type="text" name="site_name" class="f-input" placeholder="站点名称 *" required>
            <input type="url" name="site_url" class="f-input" placeholder="站点链接 (https://...) *" required>
            <input type="url" name="site_avatar" class="f-input" placeholder="头像/图标链接">
            <input type="text" name="site_desc" class="f-input" placeholder="站点简介">
            <button type="submit" class="btn-apply" id="subBtn" style="width:100%; margin-top:10px; border-radius: 18px;">立即提交申请</button>
        </form>
    </div>
</div>
<script src="' . THEME_URL . '/assets/js/friends.js?v=' . time() . '"></script>