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
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
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
    
    // 2.1 下载更新包
    $zipData = @file_get_contents($downloadUrl);
    if ($zipData === false) {
        echo json_encode(['status' => 'error', 'message' => '下载更新包失败']);
        exit;
    }
    file_put_contents($tempZipFile, $zipData);

    // 2.2 解压并覆盖文件 (根目录为 ../)
    $targetDir = realpath(__DIR__ . '/../'); 
    
    $zip = new ZipArchive;
    if ($zip->open($tempZipFile) === TRUE) {
        // 解压到根目录
        $zip->extractTo($targetDir);
        $zip->close();
        @unlink($tempZipFile); 
        $sqlFile = $targetDir . '/update.sql';
        if (file_exists($sqlFile)) {
            try {
                $pdo = getDB(); // 调用 config.php 中的 PDO 实例获取函数
                $sqlContent = file_get_contents($sqlFile);
                
                if (!empty(trim($sqlContent))) {
                    // 执行 SQL 脚本
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
        echo json_encode(['status' => 'error', 'message' => '解压更新包失败']);
        exit;
    }

    $configFile = $targetDir . '/includes/config.php';
    if (is_writable($configFile)) {
        $configContent = file_get_contents($configFile);
        $configContent = preg_replace("/define\('APP_VERSION',\s*'.*?'\);/", "define('APP_VERSION', '{$newVersion}');", $configContent);
        file_put_contents($configFile, $configContent);
    } else {
        echo json_encode(['status' => 'error', 'message' => '文件与数据库更新成功，但 includes/config.php 权限不足无法修改版本号，请手动修改']);
        exit;
    }

    echo json_encode(['status' => 'success', 'message' => '更新完成']);
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Invalid action']);