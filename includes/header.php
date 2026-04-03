<?php
// includes/header.php
/**
 * [主题重构版] 核心数据准备层 (剥离了所有的 HTML/UI 代码)
 **/

// 1. 防止重复加载配置
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/redis_helper.php';

// 【核心修复】：必须在全局声明 $pdo，否则后续页面会崩溃白屏！
$pdo = getDB();

// 优先读取双层缓存中的配置
$site_config = Cache::get('site_settings');
if (!$site_config) {
    $stmt_set = $pdo->query("SELECT * FROM settings");
    $site_config = [];
    while ($row = $stmt_set->fetch()) {
        $site_config[$row['key_name']] = $row['value'];
    }
    Cache::set('site_settings', $site_config, 86400);
}

// 剥离底层的 htmlspecialchars，提升性能
if (!function_exists('conf')) {
    function conf($key, $default = '') {
        global $site_config;
        return isset($site_config[$key]) && $site_config[$key] !== '' ? $site_config[$key] : $default;
    }
}

// 2. 生成 CSRF 令牌
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// 3. 读取外观配置 (准备 CSS 变量，供前端主题调用)
$bg_type = htmlspecialchars(conf('site_bg_type', 'color'));
$bg_val  = htmlspecialchars(conf('site_bg_value', '#f5f5f7'));
$bg_grad_start = htmlspecialchars(conf('site_bg_gradient_start', '#a18cd1'));
$bg_grad_end   = htmlspecialchars(conf('site_bg_gradient_end', '#fbc2eb'));
$card_opacity  = htmlspecialchars(conf('site_bg_overlay_opacity', '0.85'));

$final_bg_css = "";
if ($bg_type == 'color') {
    $final_bg_css = "background-color: {$bg_val};";
} elseif ($bg_type == 'gradient') {
    $final_bg_css = "background: linear-gradient(135deg, {$bg_grad_start} 0%, {$bg_grad_end} 100%); background-attachment: fixed;";
} elseif ($bg_type == 'image') {
    $noise = "url(\"data:image/svg+xml,%3Csvg viewBox='0 0 200 200' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noiseFilter'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.65' numOctaves='3' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noiseFilter)' opacity='0.04'/%3E%3C/svg%3E\")";
    $final_bg_css = "background: {$noise}, url('{$bg_val}') no-repeat center center fixed; background-size: cover;";
} else {
    $final_bg_css = "background-color: #f5f5f7;";
}
$final_card_bg = "rgba(255, 255, 255, {$card_opacity})";

// 4. 用户信息
$is_user_login = isset($_SESSION['user_id']);
$current_user_id = $is_user_login ? $_SESSION['user_id'] : 0;
$current_user_avatar = $is_user_login ? $_SESSION['avatar'] : '';
$current_user_name = $is_user_login ? $_SESSION['nickname'] : '';

// 5. 获取当前脚本名称和路由 (供前端判断菜单 active 状态)
$request_uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$is_home = in_array($request_uri, ['/', '/index.php', '/home']);
$is_album = ($request_uri == '/album');
$is_music = ($request_uri == '/music');
$is_about = ($request_uri == '/about');

// 注意：这里不再有任何 HTML 代码了。它默默地把所有变量准备好，等待被包含。
?>