<?php
// admin/updater.php
require_once '../includes/config.php';
requireLogin();

ini_set('display_errors', 0);
error_reporting(E_ALL & ~E_NOTICE);

header('Content-Type: application/json; charset=utf-8');

define('UPDATE_API_URL', 'https://yunxiaoquan-1259323713.cos.ap-chengdu.myqcloud.com/update/version.json'); 

$action = $_GET['action'] ?? '';

if ($action === 'check') {
    // 1. 获取远程版本信息
    $ch = curl_init(UPDATE_API_URL);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    curl_close($ch);

    if (!$response) {
        echo json_encode(['status' => 'error', 'message' => '无法连接到更新服务器']);
        exit;
    }

    $remoteData = json_decode($response, true);

    if ($remoteData && isset($remoteData['version'])) {
        $hasUpdate = version_compare($remoteData['version'], APP_VERSION, '>');
        echo json_encode([
            'status' => 'success',
            'has_update' => $hasUpdate,
            'current_version' => APP_VERSION,
            'info' => $remoteData 
        ]);
    } else {
        echo json_encode(['status' => 'error', 'message' => '更新数据格式无效']);
    }
    exit;

} elseif ($action === 'update' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $downloadUrl = $_POST['download_url'] ?? '';
    $newVersion = $_POST['version'] ?? '';

    if (empty($downloadUrl) || empty($newVersion)) {
        echo json_encode(['status' => 'error', 'message' => '参数缺失']);
        exit;
    }

    if (!class_exists('ZipArchive')) {
        echo json_encode(['status' => 'error', 'message' => '服务器缺少 PHP ZipArchive 扩展，无法解压']);
        exit;
    }

    $tempZipFile = sys_get_temp_dir() . '/update_' . time() . '.zip';
    
    // 2.1 增强版下载更新包 (优先使用 cURL)
    $zipData = false;
    if (function_exists('curl_init')) {
        $ch = curl_init($downloadUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60); // 允许下载 60 秒
        $zipData = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($httpCode !== 200) $zipData = false;
    } else {
        // Fallback
        $context = stream_context_create(['http' => ['timeout' => 60]]);
        $zipData = @file_get_contents($downloadUrl, false, $context);
    }

    if ($zipData === false || empty($zipData)) {
        echo json_encode(['status' => 'error', 'message' => '下载更新包失败，请检查服务器网络']);
        exit;
    }
    file_put_contents($tempZipFile, $zipData);

    // 2.2 解压并覆盖文件 (更安全地获取根目录)
    $targetDir = dirname(__DIR__); 
    
    $zip = new ZipArchive;
    if ($zip->open($tempZipFile) === TRUE) {
        
        // 核心修复：检查是否解压成功（防止无权限静默失败）
        if (!$zip->extractTo($targetDir)) {
            $zip->close();
            @unlink($tempZipFile);
            echo json_encode(['status' => 'error', 'message' => '解压失败，请检查网站根目录是否具有读写权限 (755/www)']);
            exit;
        }
        $zip->close();
        @unlink($tempZipFile); 

        $sqlFile = $targetDir . '/update.sql';
        if (file_exists($sqlFile)) {
            try {
                $pdo = getDB(); 
                $sqlContent = file_get_contents($sqlFile);
                
                if (!empty(trim($sqlContent))) {
                    $pdo->exec($sqlContent); 
                }
                @unlink($sqlFile); 
                
            } catch (Exception $e) {
                @unlink($sqlFile);
                echo json_encode(['status' => 'error', 'message' => '文件已更新，但数据库升级失败：' . $e->getMessage()]);
                exit;
            }
        }
    } else {
        @unlink($tempZipFile);
        echo json_encode(['status' => 'error', 'message' => '无法打开更新包，可能包已损坏']);
        exit;
    }

    $configFile = $targetDir . '/includes/config.php';
    if (is_writable($configFile)) {
        $configContent = file_get_contents($configFile);
        $configContent = preg_replace("/define\('APP_VERSION',\s*'.*?'\);/", "define('APP_VERSION', '{$newVersion}');", $configContent);
        file_put_contents($configFile, $configContent);
    } else {
        echo json_encode(['status' => 'error', 'message' => '文件更新成功，但 config.php 权限不足无法修改版本号']);
        exit;
    }

    echo json_encode(['status' => 'success', 'message' => '更新完成']);
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
