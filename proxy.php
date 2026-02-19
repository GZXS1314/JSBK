<?php
// pages/proxy.php
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
// 1. 获取配置中的 API 地址
require_once 'includes/config.php'; // 确保路径正确，指向你的配置文件
$pdo = getDB();
$stmt = $pdo->query("SELECT value FROM settings WHERE key_name = 'music_api_url'");
$api_base = $stmt->fetchColumn() ?: 'https://yy.jx1314.cc';
$api_base = rtrim($api_base, '/');

// 2. 获取请求路径 (例如 /playlist)
$path = isset($_GET['path']) ? $_GET['path'] : '';
$url = $api_base . $path;

// 3. 接收前端传来的 JSON 数据
$inputJSON = file_get_contents('php://input');

// 4. 初始化 cURL
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, $inputJSON);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

// 5. 伪造请求头，模拟正常浏览器访问，防止被 API 屏蔽
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
    'Referer: https://music.163.com/',
    'Origin: https://music.163.com'
]);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // 忽略 SSL 证书验证
curl_setopt($ch, CURLOPT_TIMEOUT, 10); // 设置超时时间

// 6. 执行请求并输出结果
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if (curl_errno($ch)) {
    http_response_code(500);
    echo json_encode(['code' => 500, 'msg' => 'Proxy Error: ' . curl_error($ch)]);
} else {
    // 设置响应头为 JSON
    header('Content-Type: application/json');
    echo $response;
}

curl_close($ch);
?>
