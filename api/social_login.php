<?php
// === 必须放在文件的绝对第一行，任何 require 之前 ===
ini_set('session.cookie_path', '/');
ini_set('session.cookie_secure', '1'); // 强制声明为安全传输 (适配 EdgeOne)
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax'); // 核心：允许 OAuth 跨站回调携带凭证
// =================================================

// api/social_login.php
require_once '../includes/config.php';
require_once '../includes/redis_helper.php'; 

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// =======================================================

$pdo = getDB();

function getSocialConf($key) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT value FROM settings WHERE key_name = ?");
    $stmt->execute([$key]);
    return trim($stmt->fetchColumn() ?? '');
}

function curl_get($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $data = curl_exec($ch);
    curl_close($ch);
    return $data;
}

function getCurrentCallbackUrl() {
    $protocol = "https://";

    // 获取域名
    $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '';

    // 剥离默认端口
    if (($pos = strpos($host, ':')) !== false) {
        $port = substr($host, $pos + 1);
        if ($port === '80' || $port === '443') {
            $host = substr($host, 0, $pos);
        }
    }

    // 拼接路径
    $script_name = $_SERVER['SCRIPT_NAME'] ?? '';
    $path = (strpos($script_name, '/api/social_login.php') !== false) ? $script_name : '/api/social_login.php';

    return $protocol . $host . $path;
}


$login_mode = getSocialConf('social_login_mode');
if (empty($login_mode)) $login_mode = 'aggregated';

$action = $_GET['act'] ?? '';
$code = $_GET['code'] ?? '';
$type = trim($_GET['type'] ?? '');
$state = trim($_GET['state'] ?? '');

// === 诊断助手：帮助其他部署者获取并填写准确的回调地址 ===
if (empty($action) && empty($code) && empty($state)) {
    $exact_url = getCurrentCallbackUrl();
    die("<div style='font-family:sans-serif; padding: 40px; max-width:800px; margin:0 auto;'>
            <h2 style='color:#333; border-bottom:2px solid #eee; padding-bottom:10px;'>🔗 社交登录配置诊断助手</h2>
            <p style='font-size:16px;'>请将下方红色高亮的地址，<strong>原封不动（一字不差）</strong>复制并填写到各大开放平台的回调/重定向地址白名单中：</p>
            <div style='background:#f4f4f5; padding: 15px 20px; border-radius: 8px; color:#e11d48; font-weight:bold; font-size:18px; margin-bottom:20px; word-break: break-all;'>
                {$exact_url}
            </div>
            <ul style='line-height:2.2; color:#555; font-size:15px; background:#f9fafb; padding:20px 40px; border-radius:8px;'>
                <li><b>QQ互联：</b>将上述完整地址填入应用的【网站回调域】中。</li>
                <li><b>抖音：</b>将上述完整地址填入应用的【Web授权回调页】中。</li>
                <li><b>微信 PC扫码：</b>在开放平台【授权回调域】中仅填写域名：<b style='color:#000;'>" . parse_url($exact_url, PHP_URL_HOST) . "</b></li>
                <li><b>微信公众号：</b>在公众平台【网页授权域名】中仅填写域名：<b style='color:#000;'>" . parse_url($exact_url, PHP_URL_HOST) . "</b></li>
            </ul>
            <p style='color:#f59e0b; font-size:14px; margin-top:20px;'>⚠️ 注意：如果上面显示的地址是以 <code>http://</code> 开头，而你的网站实际启用了 SSL (https)，说明你使用了 CDN 或反向代理导致后端协议识别错误，请检查你的 CDN (如 EdgeOne) 是否正确传递了 <code>X-Forwarded-Proto</code> 头信息。</p>
         </div>");
}

if (!empty($state) && in_array($state, ['qq', 'wx', 'mp_wx', 'douyin'])) {
    $type = $state; 
}

if (!empty($code)) {
    $action = 'callback';
    if (empty($type) && strpos($_GET['act'] ?? '', '?type=') !== false) {
        $parts = explode('?type=', $_GET['act']);
        $type = $parts[1] ?? '';
    }
}

$allowed_types = ['qq', 'wx', 'mp_wx', 'douyin', 'alipay', 'sina', 'baidu', 'huawei', 'xiaomi', 'bilibili', 'dingtalk'];
if (!empty($type) && !in_array($type, $allowed_types)) {
    die("不支持的登录方式: " . htmlspecialchars($type));
}

// 获取中控代理配置 (全局读取，供授权和回调阶段使用)
$proxy_on = getSocialConf('wx_proxy_enabled') == '1';
$proxy_url = rtrim(getSocialConf('wx_proxy_api_url'), '/');
// 决定微信网关根地址
$wx_open_base = ($proxy_on && !empty($proxy_url)) ? $proxy_url : 'https://open.weixin.qq.com';
$wx_api_base  = ($proxy_on && !empty($proxy_url)) ? $proxy_url : 'https://api.weixin.qq.com';

// ==========================================
// 阶段一：发起授权
// ==========================================
if ($action === 'login') {
    
    // 获取精准的当前地址作为基础回调
    $callback_url = getCurrentCallbackUrl();
    
    // 🌟 核心修复：把生成的地址存入 Session，保证 QQ 回调换取 Token 时用的一模一样，无视多重代理造成的协议变化
    $_SESSION['oauth_redirect_uri'] = $callback_url;

    if ($login_mode === 'aggregated') {
        $apiUrl = rtrim(preg_replace('/\/connect\.php.*/i', '', getSocialConf('social_login_url') ?: 'https://u.cccyun.cc'), '/');
        $appId = getSocialConf('social_appid');
        $appKey = getSocialConf('social_appkey');
        if (empty($appId) || empty($appKey)) die('未配置聚合登录信息');

        $params = ['act' => 'login', 'appid' => $appId, 'appkey' => $appKey, 'type' => $type, 'redirect_uri' => $callback_url];
        $res = json_decode(curl_get($apiUrl . "/connect.php?" . http_build_query($params)), true);
        if ($res && $res['code'] == 0 && !empty($res['url'])) {
            header("Location: " . $res['url']); exit;
        } else {
            die("获取聚合授权失败：" . ($res['msg'] ?? ''));
        }
    } 
    else if ($login_mode === 'official') {
        $clean_redirect_uri = urlencode($callback_url);
        
        if ($type === 'douyin') {
            $client_key = getSocialConf('official_dy_clientkey');
            if(empty($client_key)) die("未配置抖音 Client Key");
            $url = "https://open.douyin.com/platform/oauth/connect/?client_key={$client_key}&response_type=code&scope=user_info&redirect_uri={$clean_redirect_uri}&state=douyin";
            header("Location: " . $url); exit;
        }
        else if ($type === 'qq') {
            $client_id = getSocialConf('official_qq_appid');
            if(empty($client_id)) die("未配置 QQ App ID");
            $clean_redirect_uri = urlencode($callback_url);
            $url = "https://graph.qq.com/oauth2.0/authorize?response_type=code&client_id={$client_id}&redirect_uri={$clean_redirect_uri}&state=qq";
            header("Location: " . $url); exit;
        }
        else if ($type === 'wx') {
            // 微信开放平台 (PC扫码) - 引入动态代理
            $appid = getSocialConf('official_wx_appid');
            if(empty($appid)) die("未配置微信开放平台 AppID");
            $url = "{$wx_open_base}/connect/qrconnect?appid={$appid}&redirect_uri={$clean_redirect_uri}&response_type=code&scope=snsapi_login&state=wx#wechat_redirect";
            header("Location: " . $url); exit;
        }
        else if ($type === 'mp_wx') {
            // 微信公众号 (微信内静默/授权登录) - 引入动态代理
            $appid = getSocialConf('official_mp_wx_appid');
            if(empty($appid)) die("未配置微信公众号 AppID");
            $url = "{$wx_open_base}/connect/oauth2/authorize?appid={$appid}&redirect_uri={$clean_redirect_uri}&response_type=code&scope=snsapi_userinfo&state=mp_wx#wechat_redirect";
            header("Location: " . $url); exit;
        }
    }
} 
// ==========================================
// 阶段二：解析回调
// ==========================================
elseif ($action === 'callback') {
    if (empty($code)) die('回调参数缺失');

    $social_uid = ''; $nickname = ''; $avatar = '';
    
    // 🌟 核心修复：优先使用 Session 中暂存的地址，防止 EO/FRP 反向代理导致二次生成的地址发生变化
    if (!empty($_SESSION['oauth_redirect_uri'])) {
        $callback_url = $_SESSION['oauth_redirect_uri'];
        unset($_SESSION['oauth_redirect_uri']); // 用完即焚，保持干净
    } else {
        // 降级处理：如果 Session 丢失，则尝试重新生成
        $callback_url = getCurrentCallbackUrl();
    }

    if ($login_mode === 'aggregated') {
        $apiUrl = rtrim(preg_replace('/\/connect\.php.*/i', '', getSocialConf('social_login_url') ?: 'https://u.cccyun.cc'), '/');
        $params = ['act' => 'callback', 'appid' => getSocialConf('social_appid'), 'appkey' => getSocialConf('social_appkey'), 'type' => $type, 'code' => $code];
        $res = json_decode(curl_get($apiUrl . "/connect.php?" . http_build_query($params)), true);
        if ($res && isset($res['code']) && $res['code'] == 0) {
            $social_uid = $res['social_uid'];
            $nickname = $res['nickname'] ?? ('用户_' . rand(1000, 9999));
            $avatar = $res['faceimg'] ?? '';
        } else {
            die("聚合授权失败：" . ($res['msg'] ?? ''));
        }
    } 
    else if ($login_mode === 'official') {
        $clean_redirect_uri = urlencode($callback_url);

        if ($type === 'douyin') {
            $client_key = getSocialConf('official_dy_clientkey');
            $client_secret = getSocialConf('official_dy_clientsecret');
            $token_url = "https://open.douyin.com/oauth/access_token/?client_key={$client_key}&client_secret={$client_secret}&code={$code}&grant_type=authorization_code";
            $token_res = json_decode(curl_get($token_url), true);
            if (isset($token_res['data']['error_code']) && $token_res['data']['error_code'] == 0) {
                $access_token = $token_res['data']['access_token'];
                $open_id = $token_res['data']['open_id'];
                $info_url = "https://open.douyin.com/oauth/userinfo/?access_token={$access_token}&open_id={$open_id}";
                $info_res = json_decode(curl_get($info_url), true);
                if (isset($info_res['data']['error_code']) && $info_res['data']['error_code'] == 0) {
                    $social_uid = $open_id;
                    $nickname = $info_res['data']['nickname'] ?? ('抖音用户_' . rand(1000, 9999));
                    $avatar = $info_res['data']['avatar'] ?? '';
                } else die("抖音获取用户信息失败");
            } else die("抖音获取Token失败");
        }
        elseif ($type === 'qq') {
            $client_id = getSocialConf('official_qq_appid');
            $client_secret = getSocialConf('official_qq_appkey');
            // QQ Token 换取阶段严密校验 redirect_uri，这里使用的是上面锁定的 $callback_url
            $token_url = "https://graph.qq.com/oauth2.0/token?grant_type=authorization_code&client_id={$client_id}&client_secret={$client_secret}&code={$code}&redirect_uri={$clean_redirect_uri}&fmt=json";
            $token_res = json_decode(curl_get($token_url), true);
            if (isset($token_res['access_token'])) {
                $access_token = $token_res['access_token'];
                $me_url = "https://graph.qq.com/oauth2.0/me?access_token={$access_token}&fmt=json";
                $me_res = json_decode(curl_get($me_url), true);
                if (isset($me_res['openid'])) {
                    $open_id = $me_res['openid'];
                    $social_uid = $me_res['unionid'] ?? $open_id;
                    $info_url = "https://graph.qq.com/user/get_user_info?access_token={$access_token}&oauth_consumer_key={$client_id}&openid={$open_id}";
                    $info_res = json_decode(curl_get($info_url), true);
                    $nickname = $info_res['nickname'] ?? ('QQ用户_' . rand(1000, 9999));
                    $avatar = $info_res['figureurl_qq_2'] ?? $info_res['figureurl_qq_1'] ?? '';
                } else die("QQ获取OpenID失败");
            } else die("QQ获取Token失败，响应: " . json_encode($token_res));
        }
        elseif ($type === 'wx' || $type === 'mp_wx') {
            // 1. 分别获取两套不同的凭证
            $wx_pc_appid  = getSocialConf('official_wx_appid');
            $wx_pc_secret = getSocialConf('official_wx_appsecret');
            
            $wx_mp_appid  = getSocialConf('official_mp_wx_appid');
            $wx_mp_secret = getSocialConf('official_mp_wx_appsecret');

            // 2. 核心判断：只有公众号登录(mp_wx)且开启了代理时，才使用代理地址
            $proxy_on = getSocialConf('wx_proxy_enabled') == '1';
            $proxy_url = rtrim(getSocialConf('wx_proxy_api_url'), '/');

            if ($type === 'mp_wx' && $proxy_on && !empty($proxy_url)) {
                // 公众号模式：走中控代理
                $appid = $wx_mp_appid;
                $secret = $wx_mp_secret;
                $token_base = $proxy_url;
            } else {
                // PC扫码模式(wx) 或 未开代理：强制走官方原版地址
                $appid = ($type === 'wx') ? $wx_pc_appid : $wx_mp_appid;
                $secret = ($type === 'wx') ? $wx_pc_secret : $wx_mp_secret;
                $token_base = "https://api.weixin.qq.com";
            }

            // 3. 发起请求
            $token_url = "{$token_base}/sns/oauth2/access_token?appid={$appid}&secret={$secret}&code={$code}&grant_type=authorization_code";
            $token_res = json_decode(curl_get($token_url), true);

            if (isset($token_res['access_token'])) {
                $access_token = $token_res['access_token'];
                $open_id = $token_res['openid'];
                $social_uid = $token_res['unionid'] ?? $open_id; 
                
                // 获取用户信息也遵循同样的 base 地址
                $info_url = "{$token_base}/sns/userinfo?access_token={$access_token}&openid={$open_id}&lang=zh_CN";
                $info_res = json_decode(curl_get($info_url), true);
                $nickname = $info_res['nickname'] ?? ('微信用户_' . rand(1000, 9999));
                $avatar = $info_res['headimgurl'] ?? '';
            } else {
                // 如果失败，输出具体的错误信息便于排查
                die("微信获取Token失败: " . ($token_res['errmsg'] ?? json_encode($token_res)));
            }
        }
    }

    if (empty($avatar)) $avatar = 'https://api.dicebear.com/7.x/avataaars/svg?seed=' . urlencode($nickname);

    // ==========================================
    // 阶段三：账号落地
    // ==========================================
    if (!empty($social_uid)) {
        $db_field_type = ($type === 'mp_wx') ? 'wx' : $type;
        $uid_field = $db_field_type . '_uid'; 

        $stmt = $pdo->prepare("SELECT * FROM users WHERE {$uid_field} = ? LIMIT 1");
        $stmt->execute([$social_uid]);
        $user = $stmt->fetch();

        if ($user) {
            if ($user['is_banned']) die('<h3>账号已被封禁。</h3>');
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['nickname'] = $user['nickname'];
            $_SESSION['avatar'] = $user['avatar'];
            
            $stmt_conf = $pdo->query("SELECT value FROM settings WHERE key_name = 'points_login'");
            $val = $stmt_conf ? $stmt_conf->fetchColumn() : false;
            $points_login = ($val !== false && $val !== '') ? intval($val) : 0;
            if ($points_login > 0) {
                $stmt_check = $pdo->prepare("SELECT id FROM points_log WHERE user_id = ? AND action = 'daily_login' AND DATE(created_at) = CURDATE()");
                $stmt_check->execute([$user['id']]);
                if (!$stmt_check->fetch()) {
                    $pdo->prepare("UPDATE users SET points = points + ? WHERE id = ?")->execute([$points_login, $user['id']]);
                    $pdo->prepare("INSERT INTO points_log (user_id, action, points_change, description) VALUES (?, 'daily_login', ?, '每日首次登录奖励')")->execute([$user['id'], $points_login]);
                }
            }
        } else {
            $username = $db_field_type . '_' . substr(md5($social_uid), 0, 8);
            $email = $username . '@social.local';
            $password = password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT);

            $insertStmt = $pdo->prepare("INSERT INTO users (username, email, nickname, password, avatar, {$uid_field}, points, level) VALUES (?, ?, ?, ?, ?, ?, 0, 1)");
            $insertStmt->execute([$username, $email, $nickname, $password, $avatar, $social_uid]);
            $newUserId = $pdo->lastInsertId();
            
            $_SESSION['user_id'] = $newUserId;
            $_SESSION['username'] = $username;
            $_SESSION['nickname'] = $nickname;
            $_SESSION['avatar'] = $avatar;
            
            $stmt_conf = $pdo->query("SELECT value FROM settings WHERE key_name = 'points_register'");
            $val = $stmt_conf ? $stmt_conf->fetchColumn() : false;
            $points_register = ($val !== false && $val !== '') ? intval($val) : 0;
            if ($points_register > 0) {
                $pdo->prepare("UPDATE users SET points = points + ? WHERE id = ?")->execute([$points_register, $newUserId]);
                $pdo->prepare("INSERT INTO points_log (user_id, action, points_change, description) VALUES (?, 'register', ?, '新用户注册奖励')")->execute([$newUserId, $points_register]);
            }
        }

        // 关键：强制将 Session 数据刷入存储
        session_write_close();
        
        // ========== 绝杀方案：动态软跳转，防止多主题/多目录下 404 ==========
        echo '<!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>登录成功，正在跳转...</title>
            <style>
                body { background: #f7f7f9; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; font-family: sans-serif; }
                .loader { text-align: center; color: #666; }
                .spinner { width: 40px; height: 40px; border: 4px solid #e5e7eb; border-top-color: #000; border-radius: 50%; animation: spin 1s linear infinite; margin: 0 auto 15px auto; }
                @keyframes spin { to { transform: rotate(360deg); } }
            </style>
        </head>
        <body>
            <div class="loader">
                <div class="spinner"></div>
                <p>授权成功，正在为您进入系统...</p>
            </div>
            
            <script>
                setTimeout(function() {
                    // 动态计算并跳转回网站首页，由前台首页路由自动处理登录状态
                    let homeUrl = window.location.origin || (window.location.protocol + "//" + window.location.host);
                    window.location.replace(homeUrl + "/");
                }, 500);
            </script>
        </body>
        </html>';
        exit;
        // ===================================================================
    }
} else {
    die('未知操作');
}
