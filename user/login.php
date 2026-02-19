<?php
/**
 * user/login.php - 用户认证中心 (安全加固版)
 * /**
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
 */
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/config.php';
$pdo = getDB();

function jsonOut($success, $msg, $redirect = '') {
    header('Content-Type: application/json');
    echo json_encode(['success' => $success, 'msg' => $msg, 'redirect' => $redirect]);
    exit;
}

$action = $_POST['action'] ?? '';

// --- 1. 登录 ---
if ($action == 'login') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $captcha = trim($_POST['captcha']);
    
    // [安全修复] 1. 验证码校验逻辑优化
    // 无论验证码输入对错，验证一次后必须作废，防止暴力破解密码
    if (empty($_SESSION['captcha_code']) || strtolower($captcha) !== strtolower($_SESSION['captcha_code'])) {
        unset($_SESSION['captcha_code']); // 输错销毁
        jsonOut(false, "图形验证码错误");
    }
    unset($_SESSION['captcha_code']); // 输对也销毁 (One-time use)

    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
    $stmt->execute([$username, $username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        if ($user['is_banned']) jsonOut(false, "账号已被封禁，请联系管理员");
        
        // [安全修复] 2. 防止 Session 固定攻击
        // 登录成功后，强制生成新的 Session ID
        session_regenerate_id(true);

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['nickname'] = $user['nickname'];
        $_SESSION['avatar'] = $user['avatar'];
        
        jsonOut(true, "登录成功", "index.php");
    } else {
        // 模糊报错，不告诉黑客是用户名错还是密码错
        jsonOut(false, "账号或密码错误");
    }
}

// --- 2. 注册 (自动登录版) ---
if ($action == 'register') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $email = trim($_POST['email']);
    $email_code = trim($_POST['email_code']);

    // 验证邮件验证码
    if (empty($_SESSION['email_verify_code']) || $email_code != $_SESSION['email_verify_code'] || $email != $_SESSION['email_verify_addr']) {
        jsonOut(false, "邮件验证码错误或失效");
    }

    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$username]);
    if ($stmt->fetch()) jsonOut(false, "用户名已存在");

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $avatar = 'https://api.dicebear.com/7.x/avataaars/svg?seed=' . urlencode($username);
    
    $stmt = $pdo->prepare("INSERT INTO users (username, password, nickname, avatar, email) VALUES (?, ?, ?, ?, ?)");
    if ($stmt->execute([$username, $hash, $username, $avatar, $email])) {
        
        // [安全修复] 3. 注册成功自动登录，同样需要重置 Session ID
        session_regenerate_id(true);

        $new_id = $pdo->lastInsertId();
        $_SESSION['user_id'] = $new_id;
        $_SESSION['username'] = $username;
        $_SESSION['nickname'] = $username;
        $_SESSION['avatar'] = $avatar;
        
        // 清理验证码 Session，防止复用
        unset($_SESSION['email_verify_code']);
        unset($_SESSION['email_verify_addr']);
        
        jsonOut(true, "欢迎加入！注册并登录成功", "index.php");
    } else {
        jsonOut(false, "注册失败，请稍后重试");
    }
}

// --- 3. 找回密码 (重置即登录版) ---
if ($action == 'reset_password') {
    $email = trim($_POST['email']);
    $new_pwd = $_POST['new_password'];
    $code = trim($_POST['email_code']);

    // 验证邮件验证码
    if (empty($_SESSION['reset_email_code']) || $code != $_SESSION['reset_email_code'] || $email != $_SESSION['reset_email_addr']) {
        jsonOut(false, "验证码错误或已过期");
    }

    $hash = password_hash($new_pwd, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE email = ?");
    if ($stmt->execute([$hash, $email])) {
        // [安全修复] 4. 重置成功后获取用户信息并自动登录
        $u_stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $u_stmt->execute([$email]);
        $user = $u_stmt->fetch();

        // 同样重置 Session ID
        session_regenerate_id(true);

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['nickname'] = $user['nickname'];
        $_SESSION['avatar'] = $user['avatar'];
        
        // 清理验证码
        unset($_SESSION['reset_email_code']);
        unset($_SESSION['reset_email_addr']);
        
        jsonOut(true, "新密码设置成功，已为您自动登录", "index.php");
    } else {
        jsonOut(false, "重置失败");
    }
}
?>
