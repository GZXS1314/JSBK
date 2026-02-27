<?php
/**
 * JS Blog System Installer
 * 追求极致的美学
 **/
error_reporting(E_ALL);
ini_set('display_errors', 0);

// --- 定义常量 ---
define('ROOT_PATH', dirname(__DIR__));
define('CONFIG_FILE', ROOT_PATH . '/includes/config.php');
define('SQL_FILE', __DIR__ . '/install.sql');
define('LOCK_FILE', __DIR__ . '/install.lock');

// --- 检查是否已安装 ---
if (file_exists(LOCK_FILE)) {
    die('<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>系统已安装</title><style>body{font-family:-apple-system,BlinkMacSystemFont,sans-serif;background:#f5f5f7;display:flex;justify-content:center;align-items:center;height:100vh;margin:0}.card{background:rgba(255,255,255,0.8);backdrop-filter:blur(20px);padding:40px;border-radius:20px;box-shadow:0 10px 40px rgba(0,0,0,0.05);text-align:center}h1{margin:0 0 20px;font-size:24px}p{color:#666;margin-bottom:30px}.btn{background:#000;color:#fff;text-decoration:none;padding:12px 30px;border-radius:30px;transition:0.3s}.btn:hover{transform:scale(1.05);box-shadow:0 5px 15px rgba(0,0,0,0.2)}</style></head><body><div class="card"><h1>系统已安装</h1><p>检测到 install.lock 文件。如需重装，请先删除该文件。</p><a href="../" class="btn">返回首页</a></div></body></html>');
}

// --- 处理 AJAX 请求 ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';

    // --- 环境检测 ---
    if ($action === 'check_env') {
        $results = [];
        
        // PHP版本
        $phpVersion = PHP_VERSION;
        $results[] = [
            'name' => 'PHP Version >= 7.4',
            'status' => version_compare($phpVersion, '7.4.0', '>='),
            'msg' => '当前: ' . $phpVersion
        ];

        // 扩展检测
        $exts = ['pdo_mysql', 'curl', 'gd', 'mbstring', 'json', 'redis'];
        foreach ($exts as $ext) {
            $isOptional = ($ext === 'redis');
            $loaded = extension_loaded($ext);
            $results[] = [
                'name' => ucfirst($ext) . ' 扩展',
                'status' => $loaded || $isOptional,
                'msg' => $loaded ? '已开启' : ($isOptional ? '未开启 (可选)' : '未开启'),
                'is_optional' => $isOptional
            ];
        }

        // 目录权限
        $dirs = [
            '/includes/' => '配置文件目录',
            '/assets/uploads/' => '上传目录'
        ];
        foreach ($dirs as $path => $desc) {
            $fullPath = ROOT_PATH . $path;
            if (!file_exists($fullPath)) {
                @mkdir($fullPath, 0755, true);
            }
            $writable = is_writable($fullPath);
            $results[] = [
                'name' => $desc . " ($path)",
                'status' => $writable,
                'msg' => $writable ? '可写' : '不可写 (需 755/777 权限)'
            ];
        }

        $canInstall = true;
        foreach ($results as $r) {
            if (!$r['status']) $canInstall = false;
        }

        echo json_encode(['results' => $results, 'can_install' => $canInstall]);
        exit;
    }

    // --- 数据库连通性及空库检测 ---
    if ($action === 'check_db') {
        try {
            $dbHost = trim($_POST['db_host']);
            $dbName = trim($_POST['db_name']);
            $dbUser = trim($_POST['db_user']);
            $dbPass = trim($_POST['db_pass']);

            $dsn = "mysql:host=$dbHost;charset=utf8mb4";
            $pdo = new PDO($dsn, $dbUser, $dbPass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]);

            // 检查数据库是否存在
            $stmt = $pdo->query("SHOW DATABASES LIKE '$dbName'");
            if ($stmt->rowCount() == 0) {
                // 库不存在，必定为空，可直接安装
                echo json_encode(['success' => true, 'is_empty' => true]);
                exit;
            }

            // 检查表是否存在
            $pdo->exec("USE `$dbName`");
            $stmt = $pdo->query("SHOW TABLES");
            if ($stmt->rowCount() == 0) {
                echo json_encode(['success' => true, 'is_empty' => true]);
                exit;
            }

            // 非空
            echo json_encode(['success' => true, 'is_empty' => false]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => '数据库连接失败: ' . $e->getMessage()]);
        }
        exit;
    }

    // --- 执行安装 ---
    if ($action === 'install') {
        try {
            $mode = $_POST['install_mode'] ?? 'full'; // full, increment, skip
            
            $dbHost = trim($_POST['db_host']);
            $dbName = trim($_POST['db_name']);
            $dbUser = trim($_POST['db_user']);
            $dbPass = trim($_POST['db_pass']);
            $siteName = trim($_POST['site_name']);
            $adminUser = trim($_POST['admin_user']);
            $adminPass = trim($_POST['admin_pass']);
            
            $redisHost = trim($_POST['redis_host']) ?: '127.0.0.1';
            $redisPort = (int)(trim($_POST['redis_port']) ?: 6379);
            $redisPass = trim($_POST['redis_pass']);

            if (!$dbHost || !$dbName || !$dbUser || !$adminUser || !$adminPass) {
                throw new Exception("请填写所有必填项");
            }

            $dsn = "mysql:host=$dbHost;charset=utf8mb4";
            $pdo = new PDO($dsn, $dbUser, $dbPass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

            $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbName` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
            $pdo->exec("USE `$dbName`");

            // 根据模式决定是否导入SQL
            if ($mode !== 'skip') {
                if (!file_exists(SQL_FILE)) {
                    throw new Exception("找不到 install.sql 文件");
                }

                $sqlFile = fopen(SQL_FILE, 'r');
                if (!$sqlFile) throw new Exception("无法读取 install.sql");

                $queryBuffer = '';
                while (($line = fgets($sqlFile)) !== false) {
                    $trimLine = trim($line);
                    
                    if (empty($trimLine) || strpos($trimLine, '--') === 0 || strpos($trimLine, '/*') === 0 || strpos($trimLine, '#') === 0) {
                        continue;
                    }

                    $queryBuffer .= $line;
                    
                    if (substr(rtrim($queryBuffer), -1) === ';') {
                        // 如果是增量覆盖，剥离 DROP 语句，并将 CREATE TABLE 转为 IF NOT EXISTS
                        if ($mode === 'increment') {
                            if (stripos($queryBuffer, 'DROP TABLE') !== false) {
                                $queryBuffer = ''; 
                                continue;
                            }
                            $queryBuffer = preg_replace('/CREATE TABLE\s+`/', 'CREATE TABLE IF NOT EXISTS `', $queryBuffer);
                        }

                        try {
                            $pdo->exec($queryBuffer);
                        } catch (PDOException $e) {
                            // 增量模式下忽略主键冲突和表已存在等错误
                            if ($mode !== 'increment') {
                                error_log("SQL Error: " . $e->getMessage());
                            }
                        }
                        $queryBuffer = '';
                    }
                }
                fclose($sqlFile);
            }

            // 更新管理员账号 (分模式处理防覆盖出错)
            $hashPass = password_hash($adminPass, PASSWORD_DEFAULT);
            if ($mode === 'full') {
                $pdo->exec("TRUNCATE TABLE `admin`"); 
                $stmt = $pdo->prepare("INSERT INTO `admin` (`username`, `password`) VALUES (?, ?)");
                $stmt->execute([$adminUser, $hashPass]);
            } else {
                // 增量/跳过模式：更新现有管理员，或插入新管理员
                try {
                    $check = $pdo->query("SELECT id FROM `admin` LIMIT 1");
                    if ($check && $check->rowCount() > 0) {
                        $stmt = $pdo->prepare("UPDATE `admin` SET `username` = ?, `password` = ?");
                        $stmt->execute([$adminUser, $hashPass]);
                    } else {
                        $stmt = $pdo->prepare("INSERT INTO `admin` (`username`, `password`) VALUES (?, ?)");
                        $stmt->execute([$adminUser, $hashPass]);
                    }
                } catch (Exception $e) { }
            }

            // 更新站点名称设置
            try {
                $stmt = $pdo->prepare("UPDATE `settings` SET `value` = ? WHERE `key_name` = 'site_name'");
                $stmt->execute([$siteName]);
            } catch (Exception $e) { }

            // 生成 config.php 
            $configTpl = <<<'PHP'
<?php
// includes/config.php
error_reporting(E_ALL); 
ini_set('display_errors', 0);

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '', 
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
    session_start();
}

// --- 0. 系统版本配置 ---
define('APP_VERSION', '1.0.0');

// --- 1. 数据库配置 ---
define('DB_HOST', '__DB_HOST__');
define('DB_NAME', '__DB_NAME__');
define('DB_USER', '__DB_USER__');
define('DB_PASS', '__DB_PASS__');
define('DB_CHARSET', 'utf8mb4');

// --- 1.5 Redis 配置 ---
define('REDIS_HOST', '__REDIS_HOST__');
define('REDIS_PORT', __REDIS_PORT__);
define('REDIS_PASS', '__REDIS_PASS__'); 
define('REDIS_DB', 0);
define('CACHE_PREFIX', 'bkcs:');

// --- 获取 Redis 连接 ---
function getRedis() {
    static $redis = null;
    static $is_checked = false;

    if ($redis === null && !$is_checked) {
        $is_checked = true; 

        if (!class_exists('Redis')) {
            return null;
        }

        try {
            $pdo = getDB();
            $stmt = $pdo->query("SELECT value FROM settings WHERE key_name = 'redis_enabled'");
            $res = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$res || $res['value'] !== '1') {
                return null;
            }
        } catch (Exception $e) {
            return null; 
        }

        try {
            $redis_conn = new Redis();
            $redis_conn->connect(REDIS_HOST, REDIS_PORT);
            if (REDIS_PASS) {
                $redis_conn->auth(REDIS_PASS);
            }
            $redis_conn->select(REDIS_DB);
            $redis = $redis_conn;
        } catch (Exception $e) {
            return null;
        }
    }
    return $redis;
}

// --- 3. 获取数据库连接 ---
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
            die(json_encode(['error' => '数据库连接失败'])); 
        }
    }
    return $pdo;
}

// --- 4. 辅助函数 ---
function h($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

// --- 5. 权限检查函数 ---
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
    $pdo = getDB();
    try {
        $stmt = $pdo->prepare("SELECT is_banned FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        if (!$user || $user['is_banned'] == 1) {
            session_destroy();
            if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'msg' => '账号已被封禁']);
                exit;
            }
            echo "<script>alert('您的账号已被封禁或不存在');window.location.href='login.php';</script>";
            exit;
        }
    } catch (Exception $e) {}
}

// --- 6. 全局状态自动检查 ---
if (isset($_SESSION['user_id'])) {
    try {
        $pdo_check = getDB();
        $stmt_check = $pdo_check->prepare("SELECT is_banned FROM users WHERE id = ?");
        $stmt_check->execute([$_SESSION['user_id']]);
        $u_check = $stmt_check->fetch();

        if (!$u_check || (isset($u_check['is_banned']) && $u_check['is_banned'] == 1)) {
            $_SESSION = [];
            if (ini_get("session.use_cookies")) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000,
                    $params["path"], $params["domain"],
                    $params["secure"], $params["httponly"]
                );
            }
            session_destroy();
        }
    } catch (Exception $e) {}
}
?>
PHP;
            
            $configContent = str_replace(
                ['__DB_HOST__', '__DB_NAME__', '__DB_USER__', '__DB_PASS__', '__REDIS_HOST__', '__REDIS_PORT__', '__REDIS_PASS__'],
                [$dbHost, $dbName, $dbUser, $dbPass, $redisHost, $redisPort, $redisPass],
                $configTpl
            );

            if (!file_put_contents(CONFIG_FILE, $configContent)) {
                throw new Exception("写入 config.php 失败，请检查 includes 目录权限");
            }

            file_put_contents(LOCK_FILE, date('Y-m-d H:i:s'));
            echo json_encode(['success' => true]);

        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>系统安装向导</title>
    <style>
        :root {
            --bg-color: #f5f5f7;
            --card-bg: rgba(255, 255, 255, 0.75);
            --text-main: #1d1d1f;
            --text-sub: #86868b;
            --accent: #000000;
            --border: rgba(0, 0, 0, 0.05);
            --error: #ff3b30;
            --success: #34c759;
            --warning: #ff9500;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background-color: var(--bg-color);
            margin: 0;
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            color: var(--text-main);
            overflow: hidden;
        }

        body::before {
            content: '';
            position: absolute;
            width: 120vw;
            height: 120vh;
            background: radial-gradient(circle at 50% 50%, #ffffff 0%, #e8e8ed 100%);
            z-index: -1;
        }

        .installer-container {
            width: 100%;
            max-width: 500px;
            perspective: 1000px;
            padding: 20px;
        }

        .step-card {
            background: var(--card-bg);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            padding: 40px;
            border-radius: 24px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.05);
            border: 1px solid rgba(255,255,255,0.4);
            transition: all 0.5s cubic-bezier(0.16, 1, 0.3, 1);
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) scale(0.95);
            width: 85%;
            max-width: 420px;
            opacity: 0;
            pointer-events: none;
            display: flex;
            flex-direction: column;
        }

        .step-card.active {
            opacity: 1;
            transform: translate(-50%, -50%) scale(1);
            pointer-events: all;
            z-index: 10;
        }

        .header { text-align: center; margin-bottom: 30px; }
        .header h1 { font-size: 24px; font-weight: 600; margin: 0; letter-spacing: -0.5px; }
        .header p { color: var(--text-sub); font-size: 14px; margin-top: 8px; }

        .form-group { margin-bottom: 20px; }
        label { display: block; font-size: 13px; font-weight: 500; margin-bottom: 8px; color: var(--text-sub); }
        input[type="text"], input[type="password"] {
            width: 100%; padding: 12px 16px; background: rgba(255,255,255,0.5);
            border: 1px solid var(--border); border-radius: 12px; font-size: 15px;
            color: var(--text-main); transition: 0.2s; box-sizing: border-box; outline: none;
        }
        input:focus { background: #fff; border-color: rgba(0,0,0,0.2); box-shadow: 0 0 0 4px rgba(0,0,0,0.03); }

        .btn {
            background: var(--accent); color: #fff; border: none; padding: 14px;
            width: 100%; border-radius: 14px; font-size: 15px; font-weight: 500;
            cursor: pointer; transition: all 0.2s; margin-top: 10px;
            display: flex; justify-content: center; align-items: center;
        }
        .btn:hover { transform: scale(1.02); box-shadow: 0 10px 20px rgba(0,0,0,0.1); }
        .btn:active { transform: scale(0.98); }
        .btn:disabled { background: #ccc; cursor: not-allowed; transform: none; box-shadow: none; }
        
        .btn-outline {
            background: transparent; color: var(--accent); border: 1px solid var(--accent);
        }

        .license-box {
            height: 200px; overflow-y: auto; background: rgba(255,255,255,0.4);
            border: 1px solid var(--border); border-radius: 12px; padding: 15px;
            font-size: 13px; color: #555; line-height: 1.6; margin-bottom: 20px;
        }
        .license-box::-webkit-scrollbar { width: 6px; }
        .license-box::-webkit-scrollbar-thumb { background: #ccc; border-radius: 3px; }

        .checkbox-wrapper {
            display: flex; align-items: center; margin-bottom: 20px; font-size: 14px; cursor: pointer;
        }
        .checkbox-wrapper input { margin-right: 10px; accent-color: var(--accent); width: 18px; height: 18px; }

        .check-list { margin-bottom: 20px; }
        .check-item {
            display: flex; justify-content: space-between; padding: 12px 0;
            border-bottom: 1px solid var(--border); font-size: 14px;
        }
        .check-item:last-child { border-bottom: none; }
        .status-ok { color: var(--success); font-weight: 500; }
        .status-fail { color: var(--error); font-weight: 500; }
        .status-warn { color: var(--warning); font-weight: 500; }

        .spinner {
            width: 18px; height: 18px; border: 2px solid rgba(255,255,255,0.3);
            border-radius: 50%; border-top-color: #fff; animation: spin 0.8s linear infinite;
            margin-right: 8px; display: none;
        }
        @keyframes spin { to { transform: rotate(360deg); } }

        .group-title {
            font-size: 12px; text-transform: uppercase; letter-spacing: 1px;
            color: var(--text-sub); margin: 20px 0 10px; border-bottom: 1px solid var(--border);
            padding-bottom: 5px;
        }

        /* 模态弹窗 */
        .modal-overlay {
            position: fixed; top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.4); backdrop-filter: blur(5px); z-index: 100;
            display: flex; justify-content: center; align-items: center;
            opacity: 0; pointer-events: none; transition: opacity 0.3s;
        }
        .modal-overlay.show { opacity: 1; pointer-events: all; }
        .modal-card {
            background: #fff; padding: 40px; border-radius: 24px; text-align: center;
            width: 90%; max-width: 360px; transform: scale(0.9);
            transition: transform 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
        .modal-overlay.show .modal-card { transform: scale(1); }
        
        .icon-circle {
            width: 60px; height: 60px; border-radius: 50%; display: flex;
            align-items: center; justify-content: center; margin: 0 auto 20px;
            color: #fff; font-size: 30px;
        }
        .icon-success { background: var(--success); }
        .icon-warning { background: var(--warning); }

        .account-info { background: #f5f5f7; padding: 15px; border-radius: 12px; margin: 20px 0; text-align: left; font-size: 14px; }
        .info-row { display: flex; justify-content: space-between; margin-bottom: 5px; }
        .info-row:last-child { margin-bottom: 0; }
        .info-label { color: var(--text-sub); }
        .info-val { font-weight: 600; }
        
        .btn-group-vertical { display: flex; flex-direction: column; gap: 10px; margin-top: 20px; }

    </style>
</head>
<body>

<div class="installer-container">
    
    <div class="step-card active" id="step1">
        <div class="header">
            <h1>欢迎使用</h1>
            <p>请阅读并同意以下协议以继续</p>
        </div>
        <div class="license-box">
           <center> <p><strong>JS Blog 系统使用许可协议</strong></p></center>
            <p>1. 本程序仅供个人学习、研究或非商业用途使用。</p>
            <p>2. 禁止利用本程序进行任何违法违规活动（包括但不限于发布色情、暴力、反动内容）。</p>
            <p>3. 作者不对使用本程序产生的任何数据丢失、法律风险承担责任。</p>
            <p>4. 您可以自由修改代码，但必须保留原作者的版权声明。</p>
            <p>5. 安装即代表您完全接受本协议的所有条款。</p>
            <br>
            <center><p>Copyright © 2026 江硕 JS. All rights reserved.</p></center>
        </div>
        <label class="checkbox-wrapper">
            <input type="checkbox" id="agreeCheck">
            <span>我已阅读并同意上述协议</span>
        </label>
        <button class="btn" id="btnStep1" disabled>下一步</button>
    </div>

    <div class="step-card" id="step2">
        <div class="header">
            <h1>环境检测</h1>
            <p>正在检查服务器环境配置...</p>
        </div>
        <div class="check-list" id="envList">
            <div style="text-align:center; padding: 20px;"><div class="spinner" style="display:inline-block;border-color: #999; border-top-color:#000;"></div> 检测中...</div>
        </div>
        <button class="btn" id="btnStep2" disabled>下一步</button>
    </div>

    <div class="step-card" id="step3">
        <div class="header">
            <h1>系统配置</h1>
            <p>填写数据库及管理员信息</p>
        </div>
        <form id="installForm">
            <div class="group-title">数据库连接</div>
            <div class="form-group">
                <input type="text" name="db_host" placeholder="数据库地址 (默认 localhost)" value="localhost">
            </div>
            <div class="form-group">
                <input type="text" name="db_name" placeholder="数据库名 (例如 bkcs)" >
            </div>
            <div class="form-group">
                <input type="text" name="db_user" placeholder="数据库账号">
            </div>
            <div class="form-group">
                <input type="password" name="db_pass" placeholder="数据库密码">
            </div>

            <div class="group-title">网站 & 管理员</div>
            <div class="form-group">
                <input type="text" name="site_name" placeholder="网站名称" value="我的个人博客">
            </div>
            <div class="form-group">
                <input type="text" name="admin_user" placeholder="管理员账号" value="admin">
            </div>
            <div class="form-group">
                <input type="password" name="admin_pass" placeholder="管理员密码 (请牢记)">
            </div>
            
            <div class="group-title">高级选项 (Redis 可选)</div>
            <div class="form-group">
                <input type="text" name="redis_host" placeholder="Redis 地址 (默认 127.0.0.1)">
            </div>
            <div class="form-group">
                <input type="password" name="redis_pass" placeholder="Redis 密码 (无密码留空)">
            </div>
        </form>
        <button class="btn" id="btnInstall">
            <span class="spinner"></span> <span id="btnText">立即安装</span>
        </button>
    </div>

</div>

<div class="modal-overlay" id="successModal">
    <div class="modal-card">
        <div class="icon-circle icon-success">✔</div>
        <h2>安装成功！</h2>
        <p style="color:#666; margin-bottom:20px;">系统已顺利部署完成</p>
        
        <div class="account-info">
            <div class="info-row">
                <span class="info-label">后台账号</span>
                <span class="info-val" id="resAdminUser">--</span>
            </div>
            <div class="info-row">
                <span class="info-label">后台密码</span>
                <span class="info-val" id="resAdminPass">******</span>
            </div>
        </div>

        <p style="font-size:12px; color:#ff3b30; margin-bottom: 20px;">出于安全考虑，请务必删除 install 目录</p>
        <a href="../admin" class="btn">进入网站后台</a>
    </div>
</div>

<div class="modal-overlay" id="dbExistModal">
    <div class="modal-card">
        <div class="icon-circle icon-warning">!</div>
        <h2 style="font-size: 20px;">发现已存在的数据表</h2>
        <p style="color:#666; font-size: 13px; line-height: 1.5;">检测到目标数据库 <strong>非空</strong>，继续操作可能会影响现有数据，请选择安装策略：</p>
        
        <div class="btn-group-vertical">
            <button class="btn" onclick="executeInstall('full')" style="background: var(--error);">全面覆盖 (清空原数据)</button>
            <button class="btn btn-outline" onclick="executeInstall('increment')">增量覆盖 (保留数据并补齐结构)</button>
            <button class="btn btn-outline" onclick="executeInstall('skip')" style="color:#555; border-color:#ccc;">不覆盖 (仅更新配置和管理员)</button>
            <button class="btn btn-outline" onclick="closeDbModal()" style="border:none; color:var(--text-sub); margin-top:5px;">取消</button>
        </div>
    </div>
</div>

<script>
    // 步骤切换控制
    function showStep(stepId) {
        document.querySelectorAll('.step-card').forEach(el => {
            el.classList.remove('active');
            el.style.opacity = '0';
            el.style.pointerEvents = 'none';
        });
        
        const target = document.getElementById(stepId);
        target.classList.add('active');
        target.style.opacity = '1';
        target.style.pointerEvents = 'all';
        
        if(stepId === 'step2') { runEnvCheck(); }
    }

    // --- Step 1 ---
    const agreeCheck = document.getElementById('agreeCheck');
    const btnStep1 = document.getElementById('btnStep1');
    agreeCheck.addEventListener('change', (e) => { btnStep1.disabled = !e.target.checked; });
    btnStep1.addEventListener('click', () => showStep('step2'));

    // --- Step 2 ---
    async function runEnvCheck() {
        const listEl = document.getElementById('envList');
        const btnStep2 = document.getElementById('btnStep2');
        
        try {
            const formData = new FormData();
            formData.append('action', 'check_env');
            const res = await fetch('', { method: 'POST', body: formData }).then(r => r.json());
            
            let html = '';
            res.results.forEach(item => {
                const cls = item.status ? 'status-ok' : (item.is_optional ? 'status-warn' : 'status-fail');
                html += `<div class="check-item"><span>${item.name}</span><span class="${cls}">${item.msg}</span></div>`;
            });
            listEl.innerHTML = html;
            btnStep2.disabled = !res.can_install;
        } catch (e) {
            listEl.innerHTML = '<div class="status-fail" style="text-align:center">检测接口请求失败</div>';
        }
    }
    document.getElementById('btnStep2').addEventListener('click', () => showStep('step3'));

    // --- Step 3: 前置检测与最终执行 ---
    const btnInstall = document.getElementById('btnInstall');
    
    // 按钮复位函数
    function resetInstallBtn() {
        btnInstall.disabled = false;
        btnInstall.querySelector('.spinner').style.display = 'none';
        document.getElementById('btnText').innerText = '立即安装';
    }

    // 关闭覆盖选项弹窗
    function closeDbModal() {
        document.getElementById('dbExistModal').classList.remove('show');
        resetInstallBtn();
    }

    // 真正的安装执行流程
    async function executeInstall(mode) {
        document.getElementById('dbExistModal').classList.remove('show');
        
        btnInstall.disabled = true;
        btnInstall.querySelector('.spinner').style.display = 'inline-block';
        document.getElementById('btnText').innerText = '安装部署中...';

        const form = document.getElementById('installForm');
        const formData = new FormData(form);
        formData.append('action', 'install');
        formData.append('install_mode', mode); // 附带覆盖模式指令

        try {
            const res = await fetch('', { method: 'POST', body: formData }).then(r => r.json());
            if (res.success) {
                document.getElementById('resAdminUser').innerText = formData.get('admin_user');
                document.getElementById('resAdminPass').innerText = formData.get('admin_pass');
                document.getElementById('successModal').classList.add('show');
            } else {
                alert('安装失败: ' + (res.message || '未知错误'));
                resetInstallBtn();
            }
        } catch (e) {
            alert('请求发生错误，请检查控制台或网络连接');
            resetInstallBtn();
        }
    }

    // 点击立即安装按钮 -> 先走 check_db 逻辑
    btnInstall.addEventListener('click', async (e) => {
        e.preventDefault(); 
        
        const form = document.getElementById('installForm');
        const formData = new FormData(form);
        
        if (!formData.get('db_name') || !formData.get('db_user') || !formData.get('admin_user') || !formData.get('admin_pass')) {
            alert("请填写完整的必填项！"); return;
        }

        btnInstall.disabled = true;
        btnInstall.querySelector('.spinner').style.display = 'inline-block';
        document.getElementById('btnText').innerText = '正在检测连通性...';

        formData.append('action', 'check_db');
        try {
            const checkRes = await fetch('', { method: 'POST', body: formData }).then(r => r.json());
            
            if (!checkRes.success) {
                alert(checkRes.message);
                resetInstallBtn();
                return;
            }

            // 如果库是空的 -> 直接以 full 模式无感安装
            if (checkRes.is_empty) {
                executeInstall('full');
            } else {
                // 如果库非空 -> 弹出警告让用户决策
                document.getElementById('dbExistModal').classList.add('show');
            }
            
        } catch(e) {
            alert('数据库检测失败，请检查填写是否正确或网络是否通畅');
            resetInstallBtn();
        }
    });
</script>

</body>
</html>
