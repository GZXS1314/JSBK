<?php
// proxy.php - 针对 BKCS 提速版后端优化 (全静态内存+Redis双缓存版，已解除域名限制)
error_reporting(0); 

$config_path = __DIR__ . '/../includes/config.php';
if (!file_exists($config_path)) {
    header('Content-Type: application/json; charset=utf-8');
    die(json_encode(['code' => 500, 'msg' => '配置文件缺失']));
}
require_once $config_path;
require_once __DIR__ . '/../includes/core.php'; 
require_once __DIR__ . '/../includes/redis_helper.php';
// ==========================================
// 1. 跨域与请求头配置 (适配任意环境部署，告别403)
// ==========================================
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

// 处理浏览器的 OPTIONS 预检请求
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ==========================================
// 2. 获取 API 基础地址 (复用 site_settings 缓存，阻断无效 DB 连接)
// ==========================================
$site_config = Cache::get('site_settings');
if (!$site_config) {
    $pdo = getDB(); // 只有缓存穿透时才唤醒数据库
    $stmt = $pdo->query("SELECT * FROM settings");
    $site_config = [];
    while ($row = $stmt->fetch()) {
        $site_config[$row['key_name']] = $row['value'];
    }
    Cache::set('site_settings', $site_config, 86400);
}
$api_base = rtrim($site_config['music_api_url'] ?? 'https://yy.jx1314.cc', '/');
$redis = getRedis();

// ==========================================
// 3. 路径解析与特定接口拦截
// ==========================================
$path = isset($_GET['path']) ? (string)$_GET['path'] : '';
$path = '/' . ltrim($path, '/');
$url_info = parse_url($path);
parse_str($url_info['query'] ?? '', $query_params);

$is_lyric_intercept = false;
// 拦截歌词请求，直接走网易云官方接口加速
if (strpos($path, '/lyric') === 0) {
    $lyricId = $query_params['id'] ?? '0'; 
    if($lyricId === '0'){
        $inputJSON = file_get_contents('php://input');
        $inputData = json_decode($inputJSON, true) ?: [];
        $lyricId = $inputData['id'] ?? '0';
    }
    $url = "https://music.163.com/api/song/lyric?id={$lyricId}&lv=1&kv=1&tv=-1";
    $is_lyric_intercept = true;
} else {
    $url = $api_base . $path;
}

// ==========================================
// 4. CC 防御与频率限制 (依赖 Redis)
// ==========================================
if ($redis) {
    $clientIP = $_SERVER['REMOTE_ADDR'] ?: 'unknown';
    $rateLimitKey = CACHE_PREFIX . 'rate_limit:proxy:' . $clientIP;
    if ($redis->incr($rateLimitKey) > 150) { 
        header('HTTP/1.1 429 Too Many Requests');
        die(json_encode(['code' => 429, 'msg' => '操作太快了，歇会儿再试']));
    }
    if ($redis->ttl($rateLimitKey) == -1) $redis->expire($rateLimitKey, 60);
}

// ==========================================
// 5. 本地 L1 缓存击穿防护
// ==========================================
$cacheKey = CACHE_PREFIX . 'api_proxy:' . md5($url . file_get_contents('php://input'));
if ($redis && ($cachedData = $redis->get($cacheKey))) {
    header('Content-Type: application/json; charset=utf-8');
    header('X-Cache: HIT-Redis');
    echo is_array($cachedData) ? json_encode($cachedData, JSON_UNESCAPED_UNICODE) : $cachedData;
    exit;
}

// ==========================================
// 6. 执行上游代理请求
// ==========================================
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_ENCODING, ""); // 开启 Gzip 压缩传输提速
curl_setopt($ch, CURLOPT_TIMEOUT, 90);         
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);  

// 伪装请求头
$headers = [
    'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/145.0.0.0 Safari/537.36',
    'Referer: https://music.163.com/',
    'X-Forwarded-For: ' . ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'),
    'Accept: application/json, text/plain, */*'
];

// 如果不是拦截的 GET 请求，且前端发起的是 POST，则透传 POST 数据
if (!$is_lyric_intercept && $_SERVER['REQUEST_METHOD'] === 'POST') {
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, file_get_contents('php://input'));
    $headers[] = 'Content-Type: application/json';
}

curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

// ==========================================
// 7. 响应处理与缓存写入
// ==========================================
header('Content-Type: application/json; charset=utf-8');

if ($response === false) {
    echo json_encode(['code' => 500, 'msg' => '上游服务响应超时', 'detail' => $error]);
} else {
    echo $response;
    // 仅在请求成功且返回有效数据时写入缓存
    if ($redis && $httpCode == 200 && strlen($response) > 100) {
        // 歌单缓存 2 小时，其他请求缓存 1 小时
        $ttl = (strpos($path, '/playlist') !== false) ? 7200 : 3600;
        $redis->setex($cacheKey, $ttl, $response);
    }
}