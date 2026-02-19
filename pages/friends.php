<?php
// pages/friends.php
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
// --- 1. 引入配置 (智能判断路径) ---
$configPath = 'includes/config.php';
if (!file_exists($configPath)) {
    $configPath = '../includes/config.php'; 
}
if (file_exists($configPath)) {
    require_once $configPath;
} else {
    die("Error: 找不到配置文件 includes/config.php");
}

// --- 2. 获取数据库和 Redis 连接 ---
$pdo = getDB();
$redis = getRedis(); 

// --- 3. 定义缓存键 ---
define('FRIENDS_LIST_CACHE_KEY', CACHE_PREFIX . 'friends:approved_list');


// --- 4. API 处理逻辑 ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_GET['action']) && $_GET['action'] == 'apply') {
    if (ob_get_length()) ob_clean(); 
    header('Content-Type: application/json; charset=utf-8');
    
    if (session_status() == PHP_SESSION_NONE) session_start();

    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'msg' => '请先登录后再申请友链']); 
        exit;
    }
    
    $name = trim($_POST['site_name'] ?? '');
    $url = trim($_POST['site_url'] ?? '');
    $avatar = trim($_POST['site_avatar'] ?? '');
    $desc = trim($_POST['site_desc'] ?? '');
    
    if (!$name || !$url) {
        echo json_encode(['success' => false, 'msg' => '站点名称和链接不能为空']); 
        exit;
    }
    
    try {
        // 注意：这里插入的默认状态是 0（待审核）
        $stmt = $pdo->prepare("INSERT INTO friends (site_name, site_url, site_avatar, site_desc, status, created_at) VALUES (?, ?, ?, ?, 0, NOW())");
        $res = $stmt->execute([$name, $url, $avatar, $desc]);

        echo json_encode(['success' => $res, 'msg' => $res ? '申请成功，已进入审核队列' : '提交失败']);
    } catch (Exception $e) {
        error_log('Friend apply error: ' . $e->getMessage()); 
        echo json_encode(['success' => false, 'msg' => '系统繁忙，请稍后再试']);
    }
    exit; 
}


// --- 5. 加载页面头部 ---
require_once 'includes/header.php';

// --- [新增] 调试专属：通过 URL 参数强行清理缓存 ---
// 访问 /pages/friends.php?clear_cache=1 即可刷新 Redis
if (isset($_GET['clear_cache']) && $_GET['clear_cache'] == '1' && $redis) {
    $redis->del(FRIENDS_LIST_CACHE_KEY);
}

// --- 6. 获取友链数据 (严格遵守后台 Redis 开关) ---
$friends = null;

// [核心修复]：不仅要求 Redis 服务正常，还必须要求后台开关开启 (值为 '1')
$use_redis = ($redis && conf('redis_enabled') == '1');

// 1. 如果后台开启了 Redis，才尝试从 Redis 读取
if ($use_redis) {
    $cachedFriends = $redis->get(FRIENDS_LIST_CACHE_KEY);
    if ($cachedFriends !== false) {
        $decoded = json_decode($cachedFriends, true);
        if (is_array($decoded)) {
            $friends = $decoded;
        }
    }
}

// 2. 如果 Redis 没开、或者缓存没命中、或者拿到的不是数组，就老老实实查数据库
if (!is_array($friends)) {
    try {
        // 直接查数据库里 status = 1 的数据
        $friends = $pdo->query("SELECT * FROM friends WHERE status = 1 ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
        
        // 3. 如果后台开启了 Redis，才把新查到的数据写回缓存
        if ($use_redis) {
            $redis->set(FRIENDS_LIST_CACHE_KEY, json_encode($friends), 3600);
        }
    } catch (PDOException $e) {
        $friends = []; 
    }
}

// [优化 2] 如果 Redis 中没有有效数组，则从数据库查询
if (!is_array($friends)) {
    try {
        // 使用 PDO::FETCH_ASSOC 确保只获取关联数组，使 JSON 更干净
        $friends = $pdo->query("SELECT * FROM friends WHERE status = 1 ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
        
        // [优化 3] 查出数据后再存入 Redis
        if ($redis) {
            $redis->set(FRIENDS_LIST_CACHE_KEY, json_encode($friends), 3600);
        }
    } catch (PDOException $e) {
        // 防御性编程：万一数据库没建好，降级为空数组，防止页面崩溃
        $friends = []; 
    }
}


// --- 7. 数据处理与页面渲染 ---

// 动态分排
$total = count($friends);
if ($total > 0) {
    $perRow = ceil($total / 3);
    $rows = array_chunk($friends, max(1, $perRow));
} else {
    $rows = [];
}
?>

<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/pages/assets/css/friends.css?v=<?php echo time(); ?>">

<div class="friends-page-body">
    <div class="bg-decoration"><div class="blob blob-1"></div><div class="blob blob-2"></div><div class="blob blob-3"></div></div>
    <div class="title-area"><h1>Partners.</h1><p>每一位链接都是一段奇妙的相遇</p></div>
    <div class="friends-canvas">
        <div class="scroll-track-container">
            <?php if (empty($rows)): ?>
                <div style="text-align:center; color:#86868b; padding: 40px; width: 100%;">期待您的加入...</div>
            <?php else: ?>
                <?php foreach ($rows as $index => $rowFriends): 
                    $dirClass = ($index % 2 == 0) ? 'animate-l' : 'animate-r';
                    $displayList = array_merge($rowFriends, $rowFriends, $rowFriends, $rowFriends);
                ?>
                    <div class="track <?= $dirClass ?>">
                        <?php foreach($displayList as $f): ?>
                            <a href="<?= htmlspecialchars($f['site_url']) ?>" target="_blank" class="sq-card">
                                <img src="<?= htmlspecialchars($f['site_avatar'] ?: 'https://ui-avatars.com/api/?background=random&name='.$f['site_name']) ?>" class="sq-avatar">
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

<script src="/pages/assets/js/friends.js?v=<?php echo time(); ?>"></script>

<?php
// require_once 'includes/footer.php';
?>