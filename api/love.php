<?php
// api/love.php
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
$pdo = getDB();
header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

// 1. 获取所有祝福弹幕
if ($action == 'get_wishes') {
    $stmt = $pdo->query("SELECT nickname, avatar, content, image_url FROM love_wishes ORDER BY id DESC LIMIT 100");
    echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
    exit;
}

// 2. 发送祝福 (需要登录)
if ($action == 'send_wish') {
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'msg' => '请先登录后送祝福']);
        exit;
    }

    $content = trim($_POST['content'] ?? '');
    $image_url = trim($_POST['image_url'] ?? '');
    
    // 安全过滤
    if (mb_strlen($content) > 50 || mb_strlen($content) < 1) {
        echo json_encode(['success' => false, 'msg' => '祝福语请控制在1-50字以内']);
        exit;
    }
    
    // 简单的频率限制 (防止刷屏)
    if (isset($_SESSION['last_wish_time']) && time() - $_SESSION['last_wish_time'] < 10) {
        echo json_encode(['success' => false, 'msg' => '发送太快啦，歇一会儿']);
        exit;
    }

    $stmt = $pdo->prepare("INSERT INTO love_wishes (user_id, nickname, avatar, content, image_url) VALUES (?, ?, ?, ?, ?)");
    $res = $stmt->execute([
        $_SESSION['user_id'],
        $_SESSION['nickname'],
        $_SESSION['avatar'],
        htmlspecialchars($content), // XSS 防护
        htmlspecialchars($image_url)
    ]);

    if ($res) {
        $_SESSION['last_wish_time'] = time();
        echo json_encode(['success' => true, 'msg' => '祝福发送成功！']);
    } else {
        echo json_encode(['success' => false, 'msg' => '系统错误']);
    }
    exit;
}
?>