<?php
// api/chatroom.php
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
require_once '../includes/config.php';

// 首先，获取数据库和Redis连接实例
$pdo = getDB();
$redis = getRedis(); // 如果Redis连接失败，这里会得到 null

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

// 使用您在 config.php 中定义的缓存前缀，非常好的习惯！
define('CHAT_MESSAGES_CACHE_KEY', CACHE_PREFIX . 'chatroom:latest_messages');

// 1. 获取消息列表 (已启用Redis缓存 + 自动降级)
if ($action == 'get_messages') {
    
    // --- [优化] 1. 检查 Redis 是否可用，并尝试获取缓存 ---
    // 这个 if 判断是关键，实现了平滑降级
    if ($redis) {
        $cached_data_json = $redis->get(CHAT_MESSAGES_CACHE_KEY);
        if ($cached_data_json) {
            // 缓存命中，直接返回缓存的数据
            echo $cached_data_json;
            exit;
        }
    }
    
    // --- [缓存未命中 或 Redis不可用] 2. 执行数据库查询 ---
    $stmt = $pdo->query("
        SELECT m.id, m.message, m.created_at, m.user_id, u.nickname, u.avatar 
        FROM chat_messages m 
        LEFT JOIN users u ON m.user_id = u.id 
        ORDER BY m.id DESC LIMIT 50
    ");
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $stmt_set = $pdo->query("SELECT value FROM settings WHERE key_name = 'chatroom_muted'");
    $res = $stmt_set->fetch();
    $is_muted = ($res && $res['value'] == '1');

    $response_data = [
        'success' => true, 
        'data' => array_reverse($messages),
        'is_muted' => $is_muted
    ];
    $response_json = json_encode($response_data);

    // --- [优化] 3. 如果 Redis 可用，则将查询结果写入缓存 ---
    if ($redis) {
        $redis->set(CHAT_MESSAGES_CACHE_KEY, $response_json, 5); // 缓存5秒
    }

    echo $response_json;
    exit;
}

// 2. 发送消息 (已集成Redis缓存失效 + 自动降级)
if ($action == 'send_message') {
    // ... [A, B, C, D 部分的验证逻辑完全不变] ...

    // A. 先检查登录
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'msg' => '请先登录']);
        exit;
    }
    
    // B. 防刷逻辑
    $last = $pdo->prepare("SELECT created_at FROM chat_messages WHERE user_id = ? ORDER BY id DESC LIMIT 1");
    $last->execute([$_SESSION['user_id']]);
    $lastTime = $last->fetchColumn();
    
    if ($lastTime && (time() - strtotime($lastTime) < 2)) {
        echo json_encode(['success' => false, 'msg' => '说话太快了，歇歇吧']);
        exit;
    }
    
    // C. 检查禁言状态
    $stmt_set = $pdo->query("SELECT value FROM settings WHERE key_name = 'chatroom_muted'");
    $res = $stmt_set->fetch();
    if ($res && $res['value'] == '1') {
        echo json_encode(['success' => false, 'msg' => '全员禁言中，暂时无法发言']);
        exit;
    }
    
    // D. 消息内容校验
    $msg = trim($_POST['message'] ?? '');
    if (empty($msg)) {
        echo json_encode(['success' => false, 'msg' => '消息不能为空']);
        exit;
    }
    
    // E. 入库
    $stmt = $pdo->prepare("INSERT INTO chat_messages (user_id, message) VALUES (?, ?)");
    if ($stmt->execute([$_SESSION['user_id'], htmlspecialchars($msg)])) {
        
        // --- [优化] 关键一步：如果 Redis 可用，则删除缓存 ---
        if ($redis) {
            $redis->del(CHAT_MESSAGES_CACHE_KEY);
        }
        
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'msg' => '发送失败']);
    }
    exit;
}

// 如果没有匹配的 action，返回错误
echo json_encode(['success' => false, 'msg' => '无效的操作']);
?>
