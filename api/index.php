<?php
/**
 * api/index.php - BKCS 现代化核心业务接口 (终极完整版)
 */
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
    die(json_encode(['success' => false, 'msg' => '数据库连接失败']));
}

$action = $_GET['action'] ?? ($_POST['action'] ?? '');

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

function checkRateLimit($action_name, $limit_seconds = 3) {
    if (!empty($_SESSION['admin_logged_in'])) return true; 
    $session_key = 'last_req_' . $action_name;
    $now = time();
    if (isset($_SESSION[$session_key]) && ($now - $_SESSION[$session_key] < $limit_seconds)) {
        $remain = $limit_seconds - ($now - $_SESSION[$session_key]);
        echo json_encode(['success' => false, 'msg' => "操作太快了，请 {$remain} 秒后再试"]);
        exit;
    }
    $_SESSION[$session_key] = $now;
    return true;
}

switch ($action) {
    
    // --- 账号登录 ---
    case 'login':
        checkRateLimit('login', 3);
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $captcha = trim($_POST['captcha'] ?? '');

        if (empty($_SESSION['captcha_code']) || strtolower($captcha) !== strtolower($_SESSION['captcha_code'])) {
            echo json_encode(['success' => false, 'msg' => '图形验证码错误']);
            break;
        }

        $stmt = $pdo->prepare("SELECT * FROM users WHERE (username = ? OR email = ?) LIMIT 1");
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            if ($user['is_banned']) {
                echo json_encode(['success' => false, 'msg' => '账号已被封禁']);
                break;
            }
            // 记录 Session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['nickname'] = $user['nickname'];
            $_SESSION['avatar'] = $user['avatar'];
            unset($_SESSION['captcha_code']); // 销毁验证码
            
            echo json_encode(['success' => true, 'msg' => '登录成功']);
        } else {
            echo json_encode(['success' => false, 'msg' => '账号或密码错误']);
        }
        break;

    // --- 账号注册 ---
    case 'register':
        checkRateLimit('register', 5);
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $email_code = trim($_POST['email_code'] ?? '');
        
        // 校验邮箱验证码
        if (empty($_SESSION['email_verify_code']) || $email_code != $_SESSION['email_verify_code'] || $email !== $_SESSION['email_verify_addr']) {
            echo json_encode(['success' => false, 'msg' => '邮箱验证码错误或已失效']);
            break;
        }

        // 查重
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'msg' => '用户名或邮箱已被注册']);
            break;
        }

        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $nickname = '用户_' . rand(1000, 9999);
        $avatar = 'https://api.dicebear.com/7.x/avataaars/svg?seed=' . urlencode($username);

        $stmt = $pdo->prepare("INSERT INTO users (username, email, nickname, password, avatar, points, level) VALUES (?, ?, ?, ?, ?, 0, 1)");
        if ($stmt->execute([$username, $email, $nickname, $hashed_password, $avatar])) {
            // 注册成功直接登录
            $newUserId = $pdo->lastInsertId();
            $_SESSION['user_id'] = $newUserId;
            $_SESSION['username'] = $username;
            $_SESSION['nickname'] = $nickname;
            $_SESSION['avatar'] = $avatar;
            unset($_SESSION['email_verify_code'], $_SESSION['email_verify_addr']);
            
            echo json_encode(['success' => true, 'msg' => '注册成功']);
        } else {
            echo json_encode(['success' => false, 'msg' => '注册失败，请稍后重试']);
        }
        break;

    // --- 重置密码 ---
    case 'reset_password':
        checkRateLimit('reset_pwd', 5);
        $email = trim($_POST['email'] ?? '');
        $new_password = $_POST['new_password'] ?? '';
        $email_code = trim($_POST['email_code'] ?? '');

        if (empty($_SESSION['reset_email_code']) || $email_code != $_SESSION['reset_email_code'] || $email !== $_SESSION['reset_email_addr']) {
            echo json_encode(['success' => false, 'msg' => '邮箱验证码错误或已失效']);
            break;
        }

        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        if (!$user) {
            echo json_encode(['success' => false, 'msg' => '该邮箱未绑定任何账号']);
            break;
        }

        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE users SET password = ? WHERE email = ?")->execute([$hashed_password, $email]);
        
        // 重置成功后直接登录
        $_SESSION['user_id'] = $user['id'];
        unset($_SESSION['reset_email_code'], $_SESSION['reset_email_addr']);
        
        echo json_encode(['success' => true, 'msg' => '密码重置成功']);
        break;
    
    
    // --- 获取文章列表 ---
    case 'get_list':
        $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
        $cat = $_GET['category'] ?? 'all';
        $key = $_GET['keyword'] ?? '';
        $cacheKey = "list:p{$page}_c{$cat}_k" . md5($key);

        if ($data = getCache($cacheKey)) { echo json_encode($data); exit; }

        $limit = 6; $offset = ($page - 1) * $limit;
        $where = "WHERE a.is_hidden = 0"; $params = [];
        if ($cat !== 'all') { $where .= " AND a.category = ?"; $params[] = $cat; }
        if ($key) { $where .= " AND (a.title LIKE ? OR a.summary LIKE ?)"; $params[] = "%$key%"; $params[] = "%$key%"; }

        $stmt_c = $pdo->prepare("SELECT COUNT(*) FROM articles a $where");
        $stmt_c->execute($params);
        $total_pages = ceil($stmt_c->fetchColumn() / $limit);

        $sql = "SELECT a.*, COUNT(c.id) as comments_count 
                FROM articles a 
                LEFT JOIN comments c ON a.id = c.article_id 
                $where 
                GROUP BY a.id 
                ORDER BY a.is_recommended DESC, a.created_at DESC 
                LIMIT $offset, $limit";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $list = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($list as &$a) {
            $a['date'] = date('m-d', strtotime($a['created_at']));
            $a['title'] = htmlspecialchars($a['title']);
            unset($a['password']); 
        }

        $res = ['articles' => $list, 'total_pages' => $total_pages, 'current_page' => $page];
        setCache($cacheKey, $res);
        echo json_encode($res);
        break;

    // --- 获取文章详情 (包含付费/密码拦截 & 余额查询) ---
    case 'get_article':
        $id = intval($_GET['id']);
        if (!isset($_SESSION['viewed_art_'.$id])) {
            $pdo->prepare("UPDATE articles SET views = views + 1 WHERE id = ?")->execute([$id]);
            $_SESSION['viewed_art_'.$id] = true;
            delCache("article:$id"); 
        }

        if (!($art = getCache("article:$id"))) {
            $stmt = $pdo->prepare("SELECT * FROM articles WHERE id = ?");
            $stmt->execute([$id]);
            $art = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($art) setCache("article:$id", $art, 3600);
        }

        if ($art) {
            // ★核心修复：读取用户的真实余额，前端才不会显示 0
            $art['user_points'] = 0;
            if (isset($_SESSION['user_id'])) {
                $st_pts = $pdo->prepare("SELECT points FROM users WHERE id = ?");
                $st_pts->execute([$_SESSION['user_id']]);
                $art['user_points'] = intval($st_pts->fetchColumn());
            }

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
            
            $pwd = $_GET['pwd'] ?? '';
            // 1. 拦截密码
            if (!empty($art['password']) && $pwd !== $art['password']) {
                $art['require_password'] = true;
                $art['require_view_points'] = false;
                $art['content'] = ''; $art['media_data'] = '[]'; $art['resource_data'] = ''; $art['comments'] = []; $art['cover_image'] = ''; 
            } else {
                $art['require_password'] = false;
                
                // 2. 拦截阅读积分
                if (!empty($art['view_points']) && $art['view_points'] > 0) {
                    $paid = false;
                    if (isset($_SESSION['user_id'])) {
                        $st_paid = $pdo->prepare("SELECT id FROM points_log WHERE user_id = ? AND action = 'pay_view' AND description = ?");
                        $st_paid->execute([$_SESSION['user_id'], "unlock_article_{$id}"]);
                        if ($st_paid->fetch()) $paid = true;
                    }
                    if (!$paid) {
                        $art['require_view_points'] = true;
                        $art['content'] = ''; $art['media_data'] = '[]'; $art['resource_data'] = ''; $art['comments'] = []; $art['cover_image'] = '';
                    } else {
                        $art['require_view_points'] = false;
                    }
                } else {
                    $art['require_view_points'] = false;
                }
            }
            unset($art['password']);

            // 3. 处理下载资源积分
            if (!empty($art['resource_data'])) {
                $resData = json_decode($art['resource_data'], true);
                if ($resData) {
                    $points_req = isset($resData['points']) ? intval($resData['points']) : 0;
                    if ($points_req > 0) {
                        $resData['need_pay'] = true;
                        $paid_res = false;
                        if (isset($_SESSION['user_id'])) {
                            $st_pr = $pdo->prepare("SELECT id FROM points_log WHERE user_id=? AND action='pay_resource' AND description=?");
                            $st_pr->execute([$_SESSION['user_id'], "unlock_resource_{$id}"]);
                            if ($st_pr->fetch()) $paid_res = true;
                        }
                        if ($paid_res) {
                            $resData['need_pay'] = false; // 已购买，显示链接
                        } else {
                            $resData['link'] = ''; // 未购买，隐藏链接
                        }
                        $art['resource_data'] = json_encode($resData);
                    } else {
                        $resData['need_pay'] = false;
                        $art['resource_data'] = json_encode($resData);
                    }
                }
            }

            echo json_encode($art);
        } else { 
            echo json_encode(['error' => 'Not Found']); 
        }
        break;

    // --- 支付解锁文章 ---
    case 'pay_view':
        if (!isset($_SESSION['user_id'])) { echo json_encode(['success'=>false, 'msg'=>'未登录']); break; }
        $id = intval($_POST['id'] ?? 0);
        $uid = $_SESSION['user_id'];
        
        $st = $pdo->prepare("SELECT view_points FROM articles WHERE id = ?");
        $st->execute([$id]);
        $vp = intval($st->fetchColumn());
        
        if ($vp <= 0) { echo json_encode(['success'=>true]); break; }
        
        $st = $pdo->prepare("SELECT id FROM points_log WHERE user_id=? AND action='pay_view' AND description=?");
        $st->execute([$uid, "unlock_article_{$id}"]);
        if ($st->fetch()) { echo json_encode(['success'=>true]); break; }
        
        $st = $pdo->prepare("SELECT points FROM users WHERE id=?");
        $st->execute([$uid]);
        $up = intval($st->fetchColumn());
        
        if ($up < $vp) { echo json_encode(['success'=>false, 'msg'=>'余额积分不足！']); break; }
        
        $pdo->prepare("UPDATE users SET points = points - ? WHERE id=?")->execute([$vp, $uid]);
        $pdo->prepare("INSERT INTO points_log (user_id, action, points_change, description) VALUES (?, 'pay_view', ?, ?)")->execute([$uid, -$vp, "unlock_article_{$id}"]);
        
        echo json_encode(['success'=>true]);
        break;

    // --- 支付解锁资源 ---
    case 'pay_resource':
        if (!isset($_SESSION['user_id'])) { echo json_encode(['success'=>false, 'msg'=>'未登录']); break; }
        $id = intval($_POST['id'] ?? 0);
        $uid = $_SESSION['user_id'];
        
        $st = $pdo->prepare("SELECT resource_data FROM articles WHERE id = ?");
        $st->execute([$id]);
        $resDataStr = $st->fetchColumn();
        $resData = json_decode($resDataStr, true);
        if (!$resData || empty($resData['link'])) { echo json_encode(['success'=>false, 'msg'=>'资源不存在']); break; }
        
        $points = isset($resData['points']) ? intval($resData['points']) : 0;
        
        if ($points > 0) {
            $st = $pdo->prepare("SELECT id FROM points_log WHERE user_id=? AND action='pay_resource' AND description=?");
            $st->execute([$uid, "unlock_resource_{$id}"]);
            if (!$st->fetch()) {
                $st = $pdo->prepare("SELECT points FROM users WHERE id=?");
                $st->execute([$uid]);
                $up = intval($st->fetchColumn());
                
                if ($up < $points) { echo json_encode(['success'=>false, 'msg'=>'余额积分不足！']); break; }
                
                $pdo->prepare("UPDATE users SET points = points - ? WHERE id=?")->execute([$points, $uid]);
                $pdo->prepare("INSERT INTO points_log (user_id, action, points_change, description) VALUES (?, 'pay_resource', ?, ?)")->execute([$uid, -$points, "unlock_resource_{$id}"]);
            }
        }
        echo json_encode(['success'=>true, 'link'=>$resData['link']]);
        break;

    // --- 评论 ---
    case 'comment':
        checkRateLimit('comment', 30); 
        if (!isset($_SESSION['user_id'])) { echo json_encode(['success'=>false, 'msg'=>'请先登录']); break; }
        if (($_POST['csrf_token'] ?? '') !== ($_SESSION['csrf_token'] ?? '')) { echo json_encode(['success'=>false, 'msg'=>'非法请求']); break; }

        $user_id = $_SESSION['user_id'];
        $stmt_user = $pdo->prepare("SELECT is_banned FROM users WHERE id = ?");
        $stmt_user->execute([$user_id]);
        if ($stmt_user->fetchColumn() == 1) { echo json_encode(['success'=>false, 'msg'=>'账号被封禁']); break; }

        $article_id = intval($_POST['article_id']);
        $content = trim($_POST['content']);
        
        if (mb_strlen($content, 'UTF-8') < 2 || mb_strlen($content, 'UTF-8') > 500) { 
            echo json_encode(['success'=>false, 'msg'=>'内容长度需在 2-500 字之间']); break; 
        }

        $stmt = $pdo->prepare("INSERT INTO comments (article_id, username, content, user_id) VALUES (?, ?, ?, ?)");
        if ($stmt->execute([$article_id, $_SESSION['nickname'], htmlspecialchars($content), $user_id])) {
            clearListCache(); delCache("article:$article_id"); 

            $points_awarded = 0;
            $stmt_conf = $pdo->query("SELECT value FROM settings WHERE key_name = 'points_comment'");
            $val = $stmt_conf ? $stmt_conf->fetchColumn() : false;
            $points_comment = ($val !== false && $val !== '') ? intval($val) : 0;
            
            if ($points_comment > 0) {
                $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM points_log WHERE user_id = ? AND action = 'comment_article' AND DATE(created_at) = CURDATE()");
                $stmt_check->execute([$user_id]);
                if ($stmt_check->fetchColumn() < 5) {
                    $pdo->prepare("UPDATE users SET points = points + ? WHERE id = ?")->execute([$points_comment, $user_id]);
                    $pdo->prepare("INSERT INTO points_log (user_id, action, points_change, description) VALUES (?, 'comment_article', ?, ?)")->execute([$user_id, $points_comment, "评论文章 ID:{$article_id}"]);
                    $points_awarded = $points_comment;
                }
            }
            echo json_encode(['success' => true, 'points_awarded' => $points_awarded]);
        } else { echo json_encode(['success' => false, 'msg' => '数据库写入失败']); }
        break;

    // --- 点赞 ---
    case 'like':
        checkRateLimit('like', 2); 
        $id = intval($_GET['id']);
        if (!isset($_SESSION['user_id'])) { echo json_encode(['success'=>false, 'msg'=>'请登录']); break; }
        
        $uid = $_SESSION['user_id'];
        $check = $pdo->prepare("SELECT id FROM article_likes WHERE user_id=? AND article_id=?");
        $check->execute([$uid, $id]);
        
        $points_awarded = 0;
        if ($check->fetch()) {
            $pdo->prepare("DELETE FROM article_likes WHERE user_id=? AND article_id=?")->execute([$uid, $id]);
            $pdo->prepare("UPDATE articles SET likes = GREATEST(likes-1,0) WHERE id=?")->execute([$id]);
            $liked = false;
        } else {
            $pdo->prepare("INSERT INTO article_likes (user_id, article_id) VALUES (?,?)")->execute([$uid, $id]);
            $pdo->prepare("UPDATE articles SET likes = likes + 1 WHERE id=?")->execute([$id]);
            $liked = true;

            $stmt_conf = $pdo->query("SELECT value FROM settings WHERE key_name = 'points_like'");
            $val = $stmt_conf ? $stmt_conf->fetchColumn() : false;
            $points_like = ($val !== false && $val !== '') ? intval($val) : 0;
            
            if ($points_like > 0) {
                $stmt_check = $pdo->prepare("SELECT id FROM points_log WHERE user_id = ? AND action = 'like_article' AND description = ?");
                $stmt_check->execute([$uid, "点赞文章 ID:{$id}"]);
                if (!$stmt_check->fetch()) {
                    $pdo->prepare("UPDATE users SET points = points + ? WHERE id = ?")->execute([$points_like, $uid]);
                    $pdo->prepare("INSERT INTO points_log (user_id, action, points_change, description) VALUES (?, 'like_article', ?, ?)")->execute([$uid, $points_like, "点赞文章 ID:{$id}"]);
                    $points_awarded = $points_like;
                }
            }
        }
        delCache("article:$id"); clearListCache();
        $st = $pdo->prepare("SELECT likes FROM articles WHERE id=?"); $st->execute([$id]);
        echo json_encode(['success'=>true, 'new_likes'=>$st->fetchColumn(), 'liked'=>$liked, 'points_awarded'=>$points_awarded]);
        break;

    // --- 发信 ---
    case 'send_email_code':
    case 'send_reset_code':
        checkRateLimit('email', 60); 
        require_once __DIR__ . '/../includes/email_helper.php';
        $email = trim($_POST['email'] ?? '');
        $captcha = trim($_POST['captcha'] ?? '');
        
        if (empty($_SESSION['captcha_code']) || strtolower($captcha) !== strtolower($_SESSION['captcha_code'])) {
            echo json_encode(['success'=>false, 'msg'=>'图形码错误']); break;
        }

        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $exists = $stmt->fetch();

        if ($action == 'send_email_code' && $exists) { echo json_encode(['success'=>false, 'msg'=>'邮箱已注册']); break; }
        if ($action == 'send_reset_code' && !$exists) { echo json_encode(['success'=>false, 'msg'=>'邮箱未注册']); break; }

        $code = rand(100000, 999999);
        global $email_error_msg;
        if (sendEmailCode($email, $code)) {
            if ($action == 'send_email_code') { 
                $_SESSION['email_verify_code'] = $code; $_SESSION['email_verify_addr'] = $email; 
            } else { 
                $_SESSION['reset_email_code'] = $code; $_SESSION['reset_email_addr'] = $email; 
            }
            unset($_SESSION['captcha_code']);
            echo json_encode(['success' => true, 'msg' => '验证码已发送']);
        } else {
            echo json_encode(['success' => false, 'msg' => '发信失败']);
        }
        break;

    // --- 聊天室读 ---
    case 'get_messages':
        $stmt = $pdo->query("SELECT c.id, c.user_id, c.message, c.created_at, u.nickname, u.avatar FROM chat_messages c LEFT JOIN users u ON c.user_id = u.id ORDER BY c.id ASC LIMIT 100");
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        break;

    // --- 聊天室写 ---
    case 'send_message':
        checkRateLimit('chat', 5); 
        if (!isset($_SESSION['user_id'])) { echo json_encode(['success' => false, 'msg' => '请先登录']); break; }
        $message = trim($_POST['message'] ?? '');
        if (empty($message)) { echo json_encode(['success' => false, 'msg' => '消息不能为空']); break; }
        $stmt = $pdo->prepare("INSERT INTO chat_messages (user_id, message) VALUES (?, ?)");
        if ($stmt->execute([$_SESSION['user_id'], htmlspecialchars($message)])) echo json_encode(['success' => true]);
        break;

    // --- 分享 ---
    case 'share':
        checkRateLimit('share', 10);
        if (!isset($_SESSION['user_id'])) { echo json_encode(['success'=>false, 'msg'=>'未登录']); break; }
        $user_id = $_SESSION['user_id'];
        $article_id = intval($_POST['article_id']);
        $points_awarded = 0;
        
        $stmt_conf = $pdo->query("SELECT value FROM settings WHERE key_name = 'points_share'");
        $val = $stmt_conf ? $stmt_conf->fetchColumn() : false;
        $points_share = ($val !== false && $val !== '') ? intval($val) : 0;
        
        if ($points_share > 0) {
            $stmt_check_daily = $pdo->prepare("SELECT COUNT(*) FROM points_log WHERE user_id = ? AND action = 'share_article' AND DATE(created_at) = CURDATE()");
            $stmt_check_daily->execute([$user_id]);
            
            $stmt_check_art = $pdo->prepare("SELECT id FROM points_log WHERE user_id = ? AND action = 'share_article' AND description = ? AND DATE(created_at) = CURDATE()");
            $stmt_check_art->execute([$user_id, "分享文章 ID:{$article_id}"]);
            
            if ($stmt_check_daily->fetchColumn() < 3 && !$stmt_check_art->fetch()) {
                $pdo->prepare("UPDATE users SET points = points + ? WHERE id = ?")->execute([$points_share, $user_id]);
                $pdo->prepare("INSERT INTO points_log (user_id, action, points_change, description) VALUES (?, 'share_article', ?, ?)")->execute([$user_id, $points_share, "分享文章 ID:{$article_id}"]);
                $points_awarded = $points_share;
            }
        }
        echo json_encode(['success' => true, 'points_awarded' => $points_awarded]);
        break;

    default:
        echo json_encode(['error' => 'Invalid action']);
        break;
}
?>