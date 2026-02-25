<?php
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
if (!file_exists('includes/config.php')) {
    if (file_exists('install/index.php')) {
        header('Location: install/index.php');
        exit;
    } else {
        die('系统未安装，且找不到安装程序 (install/index.php)。请上传安装包。');
    }
}
// 加载安全响应头 (CSP, Anti-Clickjacking)
require_once 'includes/security_headers.php';

// 加载 WAF 防火墙 (拦截恶意注入)
require_once 'includes/waf.php';

// 检查 pages 目录是否存在
if (!is_dir(__DIR__ . '/pages')) {
    die("<h1>严重错误：找不到 pages 目录</h1><p>请确认你已经在根目录下创建了 'pages' 文件夹，并且把原来的 php 文件都移进去了。</p>");
}

// 检查配置文件是否存在
$config_file = 'includes/config.php';
if (!file_exists($config_file)) {
    die("<h1>严重错误：找不到配置文件</h1><p>系统试图加载 '$config_file' 失败。请检查 includes 目录位置。</p>");
}

// 引入全局配置
require_once $config_file;

// 获取当前请求的 URL 路径
$request_uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// 简单的路由分发
switch ($request_uri) {
    // 首页
    case '/':
    case '/index.php':
    case '/home':
        $file = 'pages/home.php';
        if (file_exists($file)) {
            require $file;
        } else {
            die("<h1>路由错误</h1><p>系统试图加载首页文件 '$file'，但它不存在。<br>请确认你是否将原来的 index.php 移动到了 pages 目录并重命名为 home.php？</p>");
        }
        break;

    // 画廊
    case '/album':
        $file = 'pages/album.php';
        if (file_exists($file)) require $file; else die("文件不存在: $file");
        break;

    // 聊天室
    case '/chat':
        $file = 'pages/chat.php';
        if (file_exists($file)) require $file; else die("文件不存在: $file");
        break;

    // 管理员登录
    case '/admin-login': 
        $file = 'pages/admin_login.php';
        if (file_exists($file)) require $file; else die("文件不存在: $file");
        break;

    // 退出
    case '/logout':
        $file = 'pages/logout.php';
        if (file_exists($file)) require $file; else die("文件不存在: $file");
        break;
        
    // 音乐    
    case '/music':
    $file = 'pages/music.php';
    if (file_exists($file)) require $file; else die("文件不存在: $file");
        break;
        
    // 情侣
    case '/love':
        $file = 'pages/love.php';
        if (file_exists($file)) require $file; else die("文件不存在: $file");
        break;
    
    // friends
    case '/friends':
    $file = 'pages/friends.php';
    if (file_exists($file)) require $file; else die("文件不存在: $file");
    break;
    
    // 404 页面
    default:
        http_response_code(404);
        echo '<div style="text-align:center;padding:100px;"><h1>404 Not Found</h1><p>你访问的页面路径是: ' . htmlspecialchars($request_uri) . '</p><a href="/">返回首页</a></div>';
        break;
}
?>