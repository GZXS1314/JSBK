<?php
/**
 * includes/cos_helper.php - 腾讯云 COS 轻量级上传工具
 */
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
function uploadToCOS($localFile, $targetPath) {
    $pdo = getDB();
    
    // 从数据库读取配置
    $stmt = $pdo->query("SELECT key_name, value FROM settings WHERE key_name LIKE 'cos_%'");
    $conf = [];
    while($r = $stmt->fetch()) $conf[$r['key_name']] = $r['value'];

    $secretId  = $conf['cos_secret_id'] ?? '';
    $secretKey = $conf['cos_secret_key'] ?? '';
    $bucket    = $conf['cos_bucket'] ?? '';
    $region    = $conf['cos_region'] ?? '';
    $domain    = $conf['cos_domain'] ?? '';

    if (!$secretId || !$secretKey || !$bucket || !$region) return false;

    // 整理域名
    $host = "{$bucket}.cos.{$region}.myqcloud.com";
    $url  = "https://{$host}/" . ltrim($targetPath, '/');
    
    // 生成签名 (简单版用于 PUT 上传)
    $httpMethod = "put";
    $httpUri = "/" . ltrim($targetPath, '/');
    $timestamp = time();
    $expiredTime = $timestamp + 3600;
    $keyTime = "{$timestamp};{$expiredTime}";
    
    $signKey = hash_hmac('sha1', $keyTime, $secretKey);
    $httpString = strtolower($httpMethod) . "\n" . $httpUri . "\n\n\n";
    $stringToSign = "sha1\n" . $keyTime . "\n" . sha1($httpString) . "\n";
    $signature = hash_hmac('sha1', $stringToSign, $signKey);
    
    $authorization = "q-sign-algorithm=sha1&q-ak={$secretId}&q-sign-time={$keyTime}&q-key-time={$keyTime}&q-header-list=&q-url-param-list=&q-signature={$signature}";

    // 使用 CURL 发送 PUT 请求
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_PUT, true);
    curl_setopt($ch, CURLOPT_INFILE, fopen($localFile, 'rb'));
    curl_setopt($ch, CURLOPT_INFILESIZE, filesize($localFile));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: {$authorization}"]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode == 200) {
        // 如果有自定义域名，返回自定义域名的链接
        if ($domain) {
            return rtrim($domain, '/') . "/" . ltrim($targetPath, '/');
        }
        return $url;
    }
    
    return false;
}