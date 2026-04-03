<?php
/**
 * includes/cos_helper.php - 腾讯云 COS 轻量级上传工具 (安全防泄漏版)
 */

function uploadToCOS($localFile, $targetPath) {
    if (!file_exists($localFile)) return false;

    $pdo = getDB();
    
    // 性能优化提示：这里最好也用 Cache::get('cos_config') 包裹起来，避免每次上传都查库！
    $stmt = $pdo->query("SELECT key_name, value FROM settings WHERE key_name LIKE 'cos_%'");
    $conf = [];
    while($r = $stmt->fetch()) $conf[$r['key_name']] = $r['value'];

    $secretId  = $conf['cos_secret_id'] ?? '';
    $secretKey = $conf['cos_secret_key'] ?? '';
    $bucket    = $conf['cos_bucket'] ?? '';
    $region    = $conf['cos_region'] ?? '';
    $domain    = $conf['cos_domain'] ?? '';

    if (!$secretId || !$secretKey || !$bucket || !$region) return false;

    $host = "{$bucket}.cos.{$region}.myqcloud.com";
    $url  = "https://{$host}/" . ltrim($targetPath, '/');
    
    // 【修复1】动态获取文件的真实 MIME 类型
    $mimeType = mime_content_type($localFile) ?: 'application/octet-stream';
    
    // 签名逻辑保持不变...
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

    // 【修复2】规范化文件句柄
    $fileStream = fopen($localFile, 'rb');

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_PUT, true);
    curl_setopt($ch, CURLOPT_INFILE, $fileStream);
    curl_setopt($ch, CURLOPT_INFILESIZE, filesize($localFile));
    
    // 【修复3】加入 Content-Type，确保浏览器能正确渲染图片/视频
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: {$authorization}",
        "Content-Type: {$mimeType}"
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    // 【修复4】强制开启 SSL 校验，防止中间人窃取密钥！
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true); 
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    // 【修复5】释放资源，防止内存和句柄溢出
    if (is_resource($fileStream)) {
        fclose($fileStream);
    }

    if ($httpCode == 200) {
        if ($domain) {
            return rtrim($domain, '/') . "/" . ltrim($targetPath, '/');
        }
        return $url;
    }
    
    return false;
}
?>