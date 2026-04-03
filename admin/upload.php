<?php
// admin/upload.php
/**
 * 极致安全 - 严苛的文件上传防御中心
 */
require_once '../includes/config.php';

// 生产环境务必关闭错误显示，防止绝对路径泄露
ini_set('display_errors', 0);
error_reporting(0);

requireLogin();
header('Content-Type: application/json; charset=utf-8');

$pdo = getDB();
$stmt = $pdo->prepare("SELECT value FROM settings WHERE key_name = 'cos_enabled'");
$stmt->execute();
$cosEnabled = $stmt->fetchColumn(); 

$allowedConfig = [
    'jpg'  => ['image/jpeg', 'image/pjpeg'],
    'jpeg' => ['image/jpeg', 'image/pjpeg'],
    'png'  => ['image/png', 'image/x-png'], 
    'gif'  => ['image/gif'],
    'webp' => ['image/webp'],
    'mp4'  => ['video/mp4', 'application/octet-stream'], 
    'webm' => ['video/webm']
];

function processSingleUpload($fileData, $cosEnabled, $allowedConfig) {
    if ($fileData['error'] !== UPLOAD_ERR_OK) {
        return ["errno" => 1, "message" => "上传错误 (Code: " . $fileData['error'] . ")"];
    }

    $ext = strtolower(pathinfo($fileData['name'], PATHINFO_EXTENSION));
    if (!array_key_exists($ext, $allowedConfig)) {
        return ["errno" => 1, "message" => "安全拦截：不支持的文件后缀"];
    }

    // 【修复1】强制开启严格的 MIME 检测，彻底粉碎“假后缀”木马
    if (class_exists('finfo')) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $realMime = $finfo->file($fileData['tmp_name']);
        
        if (!in_array($realMime, $allowedConfig[$ext])) {
            // 记录极其可疑的攻击行为
            error_log("非法文件上传尝试: 真实MIME {$realMime}, 伪装后缀 {$ext}, 用户IP " . $_SERVER['REMOTE_ADDR']);
            return ["errno" => 1, "message" => "安全拦截：文件内容与后缀不符"];
        }
    }

    if ($fileData['size'] > 50 * 1024 * 1024) {
        return ["errno" => 1, "message" => "文件过大 (Max 50MB)"];
    }

    // 【修复2】净化原始文件名，防范 XSS 攻击
    $safeOriginalName = htmlspecialchars($fileData['name'], ENT_QUOTES, 'UTF-8');
    $newName = date('Ymd_His_') . bin2hex(random_bytes(8)) . '.' . $ext; // 使用 random_bytes 替代 uniqid 更安全

    if ($cosEnabled == '1' && file_exists('../includes/cos_helper.php')) {
        require_once '../includes/cos_helper.php';
        $cosPath = 'uploads/' . date('Ym') . '/' . $newName;
        $cosUrl = uploadToCOS($fileData['tmp_name'], $cosPath);
        
        if ($cosUrl) {
            return [
                "errno" => 0,
                "data" => [
                    "url" => $cosUrl,
                    "alt" => $safeOriginalName,
                    "href" => $cosUrl
                ]
            ];
        }
        return ["errno" => 1, "message" => "云存储上传失败"];
    } else {
        $uploadDir = '../assets/uploads/';
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
            return ["errno" => 1, "message" => "服务器目录权限错误"];
        }
        
        $target = $uploadDir . $newName;
        if (move_uploaded_file($fileData['tmp_name'], $target)) {
            // 【修复3】返回绝对路径，无论前端哪个页面都能正确加载
            $webUrl = "/assets/uploads/" . $newName; 

            return [
                "errno" => 0,
                "data" => [
                    "url" => $webUrl,
                    "alt" => $safeOriginalName,
                    "href" => $webUrl
                ]
            ];
        }
        return ["errno" => 1, "message" => "文件移动失败"];
    }
}

// ... 下方 WangEditor 的单/多文件处理逻辑保持不变 ...
if (isset($_FILES['wangeditor-uploaded-image'])) {
    $rawFiles = $_FILES['wangeditor-uploaded-image'];
    
    if (is_array($rawFiles['name'])) {
        $fileData = [
            'name'     => $rawFiles['name'][0],
            'type'     => $rawFiles['type'][0],
            'tmp_name' => $rawFiles['tmp_name'][0],
            'error'    => $rawFiles['error'][0],
            'size'     => $rawFiles['size'][0]
        ];
        echo json_encode(processSingleUpload($fileData, $cosEnabled, $allowedConfig));
    } else {
        echo json_encode(processSingleUpload($rawFiles, $cosEnabled, $allowedConfig));
    }
    exit;
}
echo json_encode(["errno" => 1, "message" => "未接收到有效文件"]);
?>