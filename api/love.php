<?php
// api/love.php
/**
 * 追求极致的美学 (真实弹幕接口)
 **/
require_once '../includes/config.php';
$pdo = getDB();
header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

// 1. 获取所有祝福弹幕 (现在改由 love.php 直接 SSR 输出，此接口保留以备 AJAX 异步刷新)
if ($action == 'get_wishes') {
    $stmt = $pdo->query("SELECT nickname, avatar, content, image_url FROM love_wishes ORDER BY id DESC LIMIT 50");
    echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
    exit;
}

// 2. 发送真实祝福
if ($action == 'send_wish') {
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'msg' => '请先登录后送祝福']);
        exit;
    }

    $content = trim($_POST['content'] ?? '');
    
    // 安全过滤与防刷
    if (mb_strlen($content) > 50 || mb_strlen($content) < 1) {
        echo json_encode(['success' => false, 'msg' => '祝福语请控制在1-50字以内']);
        exit;
    }
    if (isset($_SESSION['last_wish_time']) && time() - $_SESSION['last_wish_time'] < 10) {
        echo json_encode(['success' => false, 'msg' => '发送太快啦，歇一会儿']);
        exit;
    }

    $stmt = $pdo->prepare("INSERT INTO love_wishes (user_id, nickname, avatar, content) VALUES (?, ?, ?, ?)");
    $res = $stmt->execute([
        $_SESSION['user_id'],
        $_SESSION['nickname'],
        $_SESSION['avatar'],
        htmlspecialchars($content) // XSS 防护
    ]);

    if ($res) {
        $_SESSION['last_wish_time'] = time();
        // 【关键】有新祝福入库，立刻清空弹幕缓存！
        require_once '../includes/redis_helper.php';
        Cache::del('love_wishes_list');
        echo json_encode(['success' => true, 'msg' => '祝福发送成功！']);
    } else {
        echo json_encode(['success' => false, 'msg' => '系统错误']);
    }
    exit;
}
?>