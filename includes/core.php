<?php
// includes/core.php
require_once __DIR__ . '/redis_helper.php';

// --- 1. 环境变量与报错控制 ---
define('APP_ENV', 'production'); // 'development' 或 'production'
define('APP_VERSION', '1.1.8'); 

if (APP_ENV === 'development') {
    error_reporting(E_ALL); 
    ini_set('display_errors', 1);
} else {
    error_reporting(0); 
    ini_set('display_errors', 0);
}

// --- 2. Session 强化 ---
ini_set('session.save_handler', 'files'); 
ini_set('session.save_path', sys_get_temp_dir());
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '', 
        'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
    session_start();
}

// --- 3. 获取 Redis 连接 (单例) ---
function getRedis() {
    static $redis = null;
    if (!REDIS_ENABLED || !class_exists('Redis')) return null;

    if ($redis === null) {
        try {
            $redis_conn = new Redis();
            $redis_conn->pconnect(REDIS_HOST, REDIS_PORT);
            if (REDIS_PASS) $redis_conn->auth(REDIS_PASS);
            $redis_conn->select(REDIS_DB);
            $redis = $redis_conn;
        } catch (Exception $e) {
            error_log("Redis Connection Error: " . $e->getMessage());
            return null; // 挂了不影响主站
        }
    }
    return $redis;
}

// --- 4. 获取数据库连接 (单例) ---
function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (\PDOException $e) {
            error_log("Database Connection Error: " . $e->getMessage());
            die("系统数据库维护中，请稍后再试。"); 
        }
    }
    return $pdo;
}

// --- 5. 辅助函数 ---
function h($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

// --- 6. 权限检查函数 ---
function requireLogin() {
    if (empty($_SESSION['admin_logged_in'])) {
        header('Location: ../admin-login'); 
        exit;
    }
}

function requireUserLogin() {
    if (empty($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }
}

// --- 7. 全局状态自动检查 (封禁拦截) ---
if (isset($_SESSION['user_id'])) {
    $userId = $_SESSION['user_id'];
    $isBanned = false;
    $redis = getRedis();

    if ($redis) {
        $banCacheKey = CACHE_PREFIX . 'user_banned:' . $userId;
        $cachedStatus = $redis->get($banCacheKey);
        if ($cachedStatus !== false) {
            $isBanned = ($cachedStatus === '1');
        } else {
            $pdo_check = getDB();
            $stmt_check = $pdo_check->prepare("SELECT is_banned FROM users WHERE id = ?");
            $stmt_check->execute([$userId]);
            $u_check = $stmt_check->fetch();
            $isBanned = ($u_check && $u_check['is_banned'] == 1);
            $redis->setex($banCacheKey, 300, $isBanned ? '1' : '0');
        }
    } else {
        if (empty($_SESSION['last_ban_check_time']) || time() - $_SESSION['last_ban_check_time'] > 60) {
            $pdo_check = getDB();
            $stmt_check = $pdo_check->prepare("SELECT is_banned FROM users WHERE id = ?");
            $stmt_check->execute([$userId]);
            $u_check = $stmt_check->fetch();
            $isBanned = ($u_check && $u_check['is_banned'] == 1);
            $_SESSION['last_ban_check_time'] = time();
            $_SESSION['is_banned_cached'] = $isBanned;
        } else {
            $isBanned = $_SESSION['is_banned_cached'] ?? false;
        }
    }

    // 执行封禁剔除逻辑
    if ($isBanned) {
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();
        
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'msg' => '账号已被封禁']);
            exit;
        }
        echo "<script>alert('您的账号已被封禁或不存在');window.location.href='login.php';</script>";
        exit;
    }
}

// ==========================================
// 主题专属配置读取函数 (前台模板调用)
// ==========================================
function getThemeOption($key, $default = '') {
    // 静态缓存：一次页面加载无论调多少次，只查一次数据库
    static $theme_options = null; 

    if ($theme_options === null) {
        $pdo = getDB();
        
        // 1. 获取当前启用的主题
        $stmt = $pdo->query("SELECT value FROM settings WHERE key_name = 'active_theme'");
        $active_theme = $stmt->fetchColumn() ?: 'default';

        // 2. 获取该主题的 JSON 数据
        $stmt = $pdo->prepare("SELECT value FROM settings WHERE key_name = ?");
        $stmt->execute(["theme_options_" . $active_theme]);
        $json_str = $stmt->fetchColumn();

        $theme_options = $json_str ? json_decode($json_str, true) : [];
        if (!is_array($theme_options)) {
            $theme_options = [];
        }
    }

    // 3. 如果没取到，尝试去老 settings 表里找个兜底 (确保平滑过渡)
    if (!isset($theme_options[$key]) || $theme_options[$key] === '') {
        global $settings; // 假设 config.php 已经把全局 settings 存入了全局变量
        if (isset($settings[$key]) && $settings[$key] !== '') {
            return htmlspecialchars($settings[$key]);
        }
        return htmlspecialchars($default);
    }

    return htmlspecialchars($theme_options[$key]);
}
?>