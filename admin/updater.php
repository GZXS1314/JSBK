<?php
// admin/updater.php
require_once '../includes/config.php';
requireLogin();

// --- 【核心修复 1】：解除时间与内存限制，防止下载解压时 500 崩溃 ---
@set_time_limit(0); 
@ini_set('memory_limit', '512M');
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
    
    // --- 【核心修复 2】：流式下载，直接写入硬盘，避开内存溢出 ---
    $fp = fopen($tempZipFile, 'w+');
    if ($fp === false) {
        echo json_encode(['status' => 'error', 'message' => '无法在系统临时目录创建文件，请检查权限']);
        exit;
    }

    $httpCode = 0;
    if (function_exists('curl_init')) {
        $ch = curl_init($downloadUrl);
        curl_setopt($ch, CURLOPT_TIMEOUT, 300); // 放宽到 5 分钟
        curl_setopt($ch, CURLOPT_FILE, $fp); // 关键：将下载流直接定向到文件
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
    }
    fclose($fp);

    // 检查下载是否真的成功
    if ($httpCode !== 200 || filesize($tempZipFile) === 0) {
        @unlink($tempZipFile);
        echo json_encode(['status' => 'error', 'message' => '下载更新包失败，HTTP状态码: ' . $httpCode]);
        exit;
    }

    // 2.2 解压并覆盖文件
    $targetDir = dirname(__DIR__); 
    
    $zip = new ZipArchive;
    if ($zip->open($tempZipFile) === TRUE) {
        
        if (!$zip->extractTo($targetDir)) {
            $zip->close();
            @unlink($tempZipFile);
            echo json_encode(['status' => 'error', 'message' => '解压失败，请检查网站根目录是否具有读写权限 (755/www)']);
            exit;
        }
        $zip->close();
        @unlink($tempZipFile); 

        // --- 【核心修复 3】：加入全局异常捕获 Throwable，防止 upgrade.php 抛错导致 500 ---
        $upgradeEngine = $targetDir . '/upgrade.php';
        if (file_exists($upgradeEngine)) {
            try {
                if (!defined('ROOT_PATH')) {
                    define('ROOT_PATH', $targetDir);
                }
                require_once $upgradeEngine;
            } catch (Throwable $e) {
                echo json_encode(['status' => 'error', 'message' => '文件已更新，但数据库升级失败: ' . $e->getMessage()]);
                exit;
            }
        }

    } else {
        @unlink($tempZipFile);
        echo json_encode(['status' => 'error', 'message' => '无法打开更新包，可能包已损坏']);
        exit;
    }

    // 2.3 写入新版本号
    $coreFile = $targetDir . '/includes/core.php';
    if (is_writable($coreFile)) {
        $coreContent = file_get_contents($coreFile);
        $coreContent = preg_replace("/define\('APP_VERSION',\s*'.*?'\);/", "define('APP_VERSION', '{$newVersion}');", $coreContent);
        file_put_contents($coreFile, $coreContent);
    } else {
        echo json_encode(['status' => 'error', 'message' => '文件更新成功，但 core.php 权限不足无法修改版本号']);
        exit;
    }

    echo json_encode(['status' => 'success', 'message' => '更新完成']);
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
?>