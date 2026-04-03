<?php
/**
 * 核心驱动引擎 (Single Entry Point) - 主题化重构版
 */

// =========================================================================
// 1. 系统核心安全常量：用于防止直接越权访问物理 PHP 文件
// =========================================================================
define('IN_BKCS', true);

// =========================================================================
// 2. 环境健康检查 (已改为检测 themes 目录)
// =========================================================================
if (!file_exists('includes/config.php')) {
    if (file_exists('install/index.php')) {
        header('Location: install/index.php');
        exit;
    } else {
        die('系统未安装，且找不到安装程序 (install/index.php)。请上传安装包。');
    }
}

if (!is_dir(__DIR__ . '/themes')) {
    die("<h1>严重错误：找不到 themes 主题目录</h1>");
}

// =========================================================================
// 3. 核心组件加载
// =========================================================================
require_once 'includes/config.php';
// 假设这里或者 config.php 里已经初始化了 $pdo 数据库连接
// 如果你的 PDO 实例化在 core.php，请确保在这里将其引入，例如： require_once 'includes/core.php';
require_once 'includes/security_headers.php';
require_once 'includes/waf.php';

/// =========================================================================
// 4. 动态主题引擎 (Theme Engine)
// =========================================================================
$active_theme = 'default'; // 默认保底主题

// 尝试从数据库动态获取当前启用的主题
try {
    $pdo = getDB(); // 【关键修改】调用 core.php 中的 getDB() 函数获取数据库实例
    if ($pdo) {
        $stmt = $pdo->prepare("SELECT value FROM settings WHERE key_name = 'active_theme' LIMIT 1");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result && !empty($result['value'])) {
            $active_theme = preg_replace('/[^a-zA-Z0-9_-]/', '', $result['value']); // 过滤非法字符，防止目录穿越
        }
    }
} catch (Exception $e) {
    // 数据库可能还没建这个字段，静默失败，继续使用 default 主题
}

// 检查获取到的主题目录是否存在，不存在则强制降级到 default
if (!is_dir(__DIR__ . '/themes/' . $active_theme)) {
    $active_theme = 'default';
}

// 定义主题全局常量 (划重点：前端写模板时极其有用！)
define('THEME_NAME', $active_theme);
define('THEME_PATH', __DIR__ . '/themes/' . $active_theme); // 物理路径，用于 PHP require
define('THEME_URL', '/themes/' . $active_theme);            // URL 路径，用于加载 css/js/图片
// =========================================================================
// 5. 现代化路由分发引擎 (Router)
// =========================================================================
$request_uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// 静态核心路由映射表 (动态指向当前主题目录)
$routes = [
    '/'            => THEME_PATH . '/home.php',
    '/index.php'   => THEME_PATH . '/home.php',
    '/home'        => THEME_PATH . '/home.php',
    '/album'       => THEME_PATH . '/album.php',
    '/chat'        => THEME_PATH . '/chat.php',
    '/music'       => THEME_PATH . '/music.php',
    '/love'        => THEME_PATH . '/love.php',
    '/about'       => THEME_PATH . '/about.php',
    '/friends'     => THEME_PATH . '/friends.php',
    
    // 注意：如果是系统级页面（不随主题改变），可以依然保留原路径或独立出去
    // 比如后台登录和退出可以放到 user 目录或 admin 目录
    '/admin-login' => THEME_PATH . '/admin_login.php', 
    '/logout'      => THEME_PATH . '/logout.php'       
];

// 执行分发
if (array_key_exists($request_uri, $routes)) {
    // 命中静态路由
    $file = $routes[$request_uri];
    if (file_exists($file)) {
        require $file;
    } else {
        die("<h1>主题错误</h1><p>当前主题 <b>{$active_theme}</b> 缺少核心页面文件 '" . basename($file) . "'。</p>");
    }
} else {
    // 动态路由嗅探：尝试在当前主题目录下找同名文件
    $dynamic_file = THEME_PATH . $request_uri . '.php';
    if (trim($request_uri, '/') !== '' && file_exists($dynamic_file)) {
        require $dynamic_file;
    } else {
        // 彻底找不到，优雅地抛出 404
        http_response_code(404);
        
        // 如果主题自带 404 页面，优先加载主题的 404
        if (file_exists(THEME_PATH . '/404.php')) {
            require THEME_PATH . '/404.php';
        } else {
            // 系统默认 404
            echo '<div style="text-align:center;padding:100px;font-family:sans-serif;">
                    <h1 style="font-size:80px;margin-bottom:10px;">404</h1>
                    <p style="color:#666;">你访问的页面路径不存在: ' . htmlspecialchars($request_uri) . '</p>
                    <a href="/" style="display:inline-block;margin-top:20px;padding:10px 20px;background:#000;color:#fff;text-decoration:none;border-radius:8px;">返回首页</a>
                  </div>';
        }
    }
}
?>