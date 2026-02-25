<?php
/**
 * api/index.php - BKCS 核心业务接口 (安全增强版)
 */
/**
                _ _                    ____  _                               
               | (_) __ _ _ __   __ _  / ___|| |__  _   _  ___               
            _  | | |/ _` | '_ \ / _` | \___ \| '_ \| | | |/ _ \              
           | |_| | | (_| | | | | (_| |  ___) | | | | |_| | (_) |             
            \___/|_|\__,_|_| |_|\__, | |____/|_| |_|\__,_|\___/              
   ____  _____          _  __  |___/  _____   _   _  _          ____ ____  
  / ___| |__  /         | | \ \/ / / | |___ /  / | | || |        / ___/ ___|
 | |  _    / /       _  | |  \  /  | |   |_ \  | | | || |_      | |  | |  
 | |_| |  / /_   _  | |_| |  /  \  | |  ___) | | | |__   _|  _  | |__| |___ 
  \____| /____| (_)  \___/  /_/\_\ |_| |____/  |_|    |_|   (_)  \____\____|
                                                                             
                                追求极致的美学                               
**/
ob_start();
require_once __DIR__ . '/../includes/config.php';
if (ob_get_length()) ob_end_clean();

error_reporting(0); 
ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');

try {
    $pdo = getDB();
    $redis = function_exists('getRedis') ? getRedis() : null; 
} catch (Exception $e) {
    echo json_encode(['error' => '连接失败']);
    exit;
}

$action = $_GET['action'] ?? '';

// --- Redis 辅助工具 ---
function getCache($key) {
    global $redis;
    if (!$redis) return false;
    $data = $redis->get('bkcs:' . $key);
    return $data ? json_decode($data, true) : false;
}
function setCache($key, $data, $ttl = 600) {
    global $redis;
    if ($redis) $redis->setex('bkcs:' . $key, $ttl, json_encode($data));
}
function delCache($key) {
    global $redis;
    if ($redis) $redis->del('bkcs:' . $key);
}
function clearListCache() {
    global $redis;
    if (!$redis) return;
    $keys = $redis->keys('bkcs:list:*');
    if (!empty($keys)) { foreach ($keys as $k) $redis->del($k); }
}

// --- 业务逻辑 ---

// 1. 获取文章列表
if ($action == 'get_list') {
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $cat = $_GET['category'] ?? 'all';
    $key = $_GET['keyword'] ?? '';
    $cacheKey = "list:p{$page}_c{$cat}_k" . md5($key);

    if ($data = getCache($cacheKey)) { echo json_encode($data); exit; }

    $limit = 6; $offset = ($page - 1) * $limit;
    $where = "WHERE is_hidden = 0"; $params = [];
    if ($cat !== 'all') { $where .= " AND category = ?"; $params[] = $cat; }
    if ($key) { $where .= " AND (title LIKE ? OR summary LIKE ?)"; $params[] = "%$key%"; $params[] = "%$key%"; }

    $stmt_c = $pdo->prepare("SELECT COUNT(*) FROM articles $where");
    $stmt_c->execute($params);
    $total_pages = ceil($stmt_c->fetchColumn() / $limit);

    $stmt = $pdo->prepare("SELECT * FROM articles $where ORDER BY is_recommended DESC, created_at DESC LIMIT $offset, $limit");
    $stmt->execute($params);
    $list = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($list as &$a) {
        $a['date'] = date('m-d', strtotime($a['created_at']));
        $st = $pdo->prepare("SELECT COUNT(*) FROM comments WHERE article_id = ?");
        $st->execute([$a['id']]);
        $a['comments_count'] = $st->fetchColumn();
        $a['title'] = htmlspecialchars($a['title']);
    }

    $res = ['articles' => $list, 'total_pages' => $total_pages, 'current_page' => $page];
    setCache($cacheKey, $res);
    echo json_encode($res);
    exit;
}

// 2. 获取文章详情 (已加入 users 连表查询头像，解决头像获取不到的问题)
if ($action == 'get_article') {
    $id = intval($_GET['id']);
    $pdo->prepare("UPDATE articles SET views = views + 1 WHERE id = ?")->execute([$id]);
    
    // 清除这篇旧文章的详情缓存，确保连表查询生效
    delCache("article:$id");

    if (!($art = getCache("article:$id"))) {
        $stmt = $pdo->prepare("SELECT * FROM articles WHERE id = ?");
        $stmt->execute([$id]);
        $art = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($art) setCache("article:$id", $art, 3600);
    }

    if ($art) {
        // 核心修复：连表查询 comments 和 users，获取真实 avatar 头像
        $stmt = $pdo->prepare("
            SELECT c.username, c.content, c.created_at, u.avatar 
            FROM comments c 
            LEFT JOIN users u ON c.user_id = u.id 
            WHERE c.article_id = ? 
            ORDER BY c.id DESC
        ");
        $stmt->execute([$id]);
        $art['comments'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $art['is_liked'] = false;
        if (isset($_SESSION['user_id'])) {
            $stmt = $pdo->prepare("SELECT id FROM article_likes WHERE user_id=? AND article_id=?");
            $stmt->execute([$_SESSION['user_id'], $id]);
            if ($stmt->fetch()) $art['is_liked'] = true;
        }
        $st_rt = $pdo->prepare("SELECT views, likes FROM articles WHERE id = ?");
        $st_rt->execute([$id]);
        $rt = $st_rt->fetch();
        $art['views'] = $rt['views']; $art['likes'] = $rt['likes'];
        
        // --- [新增] 密码保护逻辑 ---
        $pwd = $_GET['pwd'] ?? '';
        if (!empty($art['password']) && $pwd !== $art['password']) {
            $art['require_password'] = true;
            // 擦除敏感数据，防止前端通过抓包查看
            $art['content'] = '';
            $art['media_data'] = '[]';
            $art['resource_data'] = '';
            $art['comments'] = [];
            $art['cover_image'] = ''; // 隐藏封面防止泄露
        } else {
            $art['require_password'] = false;
        }
        // 核心：不论加不加密，永远不要将真实密码传给前端
        unset($art['password']);

        echo json_encode($art);
    } else { echo json_encode(['error' => 'Not Found']); }
    exit;
}

// 3. 提交评论 (频率与封禁限制)
if ($action == 'comment') {
    if (!isset($_SESSION['user_id'])) { echo json_encode(['success'=>false, 'msg'=>'请先登录']); exit; }
    
    if (($_POST['csrf_token'] ?? '') !== ($_SESSION['csrf_token'] ?? '')) { 
        echo json_encode(['success'=>false, 'msg'=>'非法请求']); exit; 
    }

    $user_id = $_SESSION['user_id'];

    $stmt_user = $pdo->prepare("SELECT is_banned FROM users WHERE id = ?");
    $stmt_user->execute([$user_id]);
    if ($stmt_user->fetchColumn() == 1) { 
        echo json_encode(['success'=>false, 'msg'=>'您的账号已被封禁']); exit; 
    }

    $cool_down = 60;
    if (isset($_SESSION['last_comment_time']) && (time() - $_SESSION['last_comment_time'] < $cool_down)) {
        $remain = $cool_down - (time() - $_SESSION['last_comment_time']);
        echo json_encode(['success'=>false, 'msg'=>"发太快了，请休息 {$remain} 秒后再试"]); exit;
    }

    $article_id = intval($_POST['article_id']);
    $content = trim($_POST['content']);
    
    if (mb_strlen($content, 'UTF-8') < 2 || mb_strlen($content, 'UTF-8') > 500) { 
        echo json_encode(['success'=>false, 'msg'=>'内容长度需在 2-500 字之间']); exit; 
    }

    $stmt = $pdo->prepare("INSERT INTO comments (article_id, username, content, user_id) VALUES (?, ?, ?, ?)");
    if ($stmt->execute([$article_id, $_SESSION['nickname'], htmlspecialchars($content), $user_id])) {
        $_SESSION['last_comment_time'] = time();
        clearListCache(); 
        
        // 核心修复：发表评论后，必须清理这篇文章的详细缓存
        delCache("article:$article_id"); 

        echo json_encode(['success' => true]);
    } else { 
        echo json_encode(['success' => false, 'msg' => '数据库写入失败']); 
    }
    exit;
}

// 4. 点赞
if ($action == 'like') {
    $id = intval($_GET['id']);
    if (!isset($_SESSION['user_id'])) { echo json_encode(['success'=>false, 'msg'=>'请登录']); exit; }
    
    $uid = $_SESSION['user_id'];
    $check = $pdo->prepare("SELECT id FROM article_likes WHERE user_id=? AND article_id=?");
    $check->execute([$uid, $id]);
    
    if ($check->fetch()) {
        $pdo->prepare("DELETE FROM article_likes WHERE user_id=? AND article_id=?")->execute([$uid, $id]);
        $pdo->prepare("UPDATE articles SET likes = GREATEST(likes-1,0) WHERE id=?")->execute([$id]);
        $liked = false;
    } else {
        $pdo->prepare("INSERT INTO article_likes (user_id, article_id) VALUES (?,?)")->execute([$uid, $id]);
        $pdo->prepare("UPDATE articles SET likes = likes + 1 WHERE id=?")->execute([$id]);
        $liked = true;
    }
    delCache("article:$id"); clearListCache();
    $st = $pdo->prepare("SELECT likes FROM articles WHERE id=?"); $st->execute([$id]);
    echo json_encode(['success'=>true, 'new_likes'=>$st->fetchColumn(), 'liked'=>$liked]);
    exit;
}

// 5. 邮件逻辑
if ($action == 'send_email_code' || $action == 'send_reset_code') {
    require_once __DIR__ . '/../includes/email_helper.php';
    $email = trim($_POST['email'] ?? '');
    $captcha = trim($_POST['captcha'] ?? '');
    
    if (empty($_SESSION['captcha_code']) || strtolower($captcha) !== strtolower($_SESSION['captcha_code'])) {
        echo json_encode(['success'=>false, 'msg'=>'图形码错误']); exit;
    }

    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $exists = $stmt->fetch();

    if ($action == 'send_email_code' && $exists) { echo json_encode(['success'=>false, 'msg'=>'邮箱已注册']); exit; }
    if ($action == 'send_reset_code' && !$exists) { echo json_encode(['success'=>false, 'msg'=>'邮箱未注册']); exit; }

    $code = rand(100000, 999999);
    global $email_error_msg;
    if (sendEmailCode($email, $code)) {
        if ($action == 'send_email_code') { $_SESSION['email_verify_code'] = $code; $_SESSION['email_verify_addr'] = $email; } 
        else { $_SESSION['reset_email_code'] = $code; $_SESSION['reset_email_addr'] = $email; }
        unset($_SESSION['captcha_code']);
        echo json_encode(['success' => true, 'msg' => '验证码已发送']);
    } else {
        echo json_encode(['success' => false, 'msg' => '发信失败: ' . $email_error_msg]);
    }
    exit;
}
// ==========================================
//        6. 聊天室：获取消息
// ==========================================
if ($action == 'get_messages') {
    // 连表查询获取发言人的昵称和头像，按时间正序排列（最新的在最下）
    $stmt = $pdo->query("
        SELECT c.id, c.user_id, c.message, c.created_at, u.nickname, u.avatar 
        FROM chat_messages c 
        LEFT JOIN users u ON c.user_id = u.id 
        ORDER BY c.id ASC 
        LIMIT 100
    ");
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'data' => $messages]);
    exit;
}

// ==========================================
//        7. 聊天室：发送消息
// ==========================================
if ($action == 'send_message') {
    if (!isset($_SESSION['user_id'])) { 
        echo json_encode(['success' => false, 'msg' => '请先登录']); 
        exit; 
    }
    
    // CSRF 校验
    if (($_POST['csrf_token'] ?? '') !== ($_SESSION['csrf_token'] ?? '')) { 
        echo json_encode(['success' => false, 'msg' => '非法请求']); 
        exit; 
    }

    $user_id = $_SESSION['user_id'];
    $message = trim($_POST['message'] ?? '');

    if (empty($message)) { 
        echo json_encode(['success' => false, 'msg' => '消息不能为空']); 
        exit; 
    }
    
    if (mb_strlen($message, 'UTF-8') > 200) { 
        echo json_encode(['success' => false, 'msg' => '消息太长了，精简一点吧']); 
        exit; 
    }

    // 检查用户是否被封禁
    $stmt_user = $pdo->prepare("SELECT is_banned FROM users WHERE id = ?");
    $stmt_user->execute([$user_id]);
    if ($stmt_user->fetchColumn() == 1) { 
        echo json_encode(['success' => false, 'msg' => '您的账号已被封禁']); 
        exit; 
    }

    // 写入数据库
    $stmt = $pdo->prepare("INSERT INTO chat_messages (user_id, message) VALUES (?, ?)");
    if ($stmt->execute([$user_id, htmlspecialchars($message)])) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'msg' => '发送失败，请重试']);
    }
    exit;
}
echo json_encode(['error' => 'Invalid action']);
?>