<?php
// api/chatroom.php
require_once '../includes/config.php';

// 1. 设置 JSON Header，确保前端能正确解析
header('Content-Type: application/json; charset=utf-8');

// 2. 获取数据库和Redis连接
$pdo = getDB();
$redis = getRedis(); // 假设 getRedis() 失败返回 null/false

$action = $_GET['action'] ?? '';
// 定义缓存键名
$cacheKey = CACHE_PREFIX . 'chatroom:latest_messages';

// --- A. 获取消息列表 ---
if ($action == 'get_messages') {
    
    // [缓存层] 尝试从 Redis 读取
    if ($redis) {
        try {
            $cachedData = $redis->get($cacheKey);
            if ($cachedData) {
                echo $cachedData;
                exit;
            }
        } catch (Exception $e) {
            // Redis 出错则忽略，降级查询数据库
        }
    }

    // [数据库层] 缓存未命中，查询数据库
    try {
        // 获取最新的50条
        $stmt = $pdo->query("
            SELECT m.id, m.message, m.created_at, m.user_id, u.nickname, u.avatar 
            FROM chat_messages m 
            LEFT JOIN users u ON m.user_id = u.id 
            ORDER BY m.id DESC LIMIT 50
        ");
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // 检查禁言状态
        $stmt_set = $pdo->query("SELECT value FROM settings WHERE key_name = 'chatroom_muted'");
        $res = $stmt_set->fetch();
        $is_muted = ($res && $res['value'] == '1');

        $result = [
            'success' => true,
            'data' => array_reverse($messages), // 反转数组：旧消息在上，新消息在下
            'is_muted' => $is_muted
        ];

        $jsonOutput = json_encode($result);

        // [写入缓存] 缓存5秒，减轻数据库压力
        if ($redis) {
            $redis->set($cacheKey, $jsonOutput, 5);
        }

        echo $jsonOutput;

    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'msg' => 'Database Error']);
    }
    exit;
}

// --- B. 发送消息 ---
if ($action == 'send_message') {
    // 1. 权限检查
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'msg' => '请先登录']);
        exit;
    }

    $userId = $_SESSION['user_id'];
    $message = trim($_POST['message'] ?? '');

    // 2. 内容校验
    if (empty($message)) {
        echo json_encode(['success' => false, 'msg' => '内容不能为空']);
        exit;
    }

    // 3. 简单防刷（2秒间隔）
    $stmtLast = $pdo->prepare("SELECT created_at FROM chat_messages WHERE user_id = ? ORDER BY id DESC LIMIT 1");
    $stmtLast->execute([$userId]);
    $lastTime = $stmtLast->fetchColumn();

    if ($lastTime && (time() - strtotime($lastTime) < 2)) {
        echo json_encode(['success' => false, 'msg' => '发送太快了，歇一歇']);
        exit;
    }

    // 4. 禁言检查
    $stmtSet = $pdo->query("SELECT value FROM settings WHERE key_name = 'chatroom_muted'");
    $res = $stmtSet->fetch();
    if ($res && $res['value'] == '1') {
        echo json_encode(['success' => false, 'msg' => '全员禁言中']);
        exit;
    }

    // 5. 入库
    try {
        $stmt = $pdo->prepare("INSERT INTO chat_messages (user_id, message, created_at) VALUES (?, ?, NOW())");
        if ($stmt->execute([$userId, htmlspecialchars($message)])) {
            // [清除缓存] 发送成功后立即清除缓存，让所有人立刻看到新消息
            if ($redis) {
                $redis->del($cacheKey);
            }
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'msg' => '发送失败']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'msg' => 'System Error']);
    }
    exit;
}

// 默认返回
echo json_encode(['success' => false, 'msg' => 'Invalid Action']);
?>
