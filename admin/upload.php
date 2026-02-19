<?php
// admin/upload.php
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
require_once '../includes/config.php';
requireLogin();

header('Content-Type: application/json; charset=utf-8');

// --- 1. 读取设置 ---
$pdo = getDB();
$stmt = $pdo->prepare("SELECT value FROM settings WHERE key_name = 'cos_enabled'");
$stmt->execute();
$cosEnabled = $stmt->fetchColumn(); 

// --- 配置允许的后缀与 MIME ---
$allowedConfig = [
    'jpg'  => ['image/jpeg', 'image/pjpeg'],
    'jpeg' => ['image/jpeg', 'image/pjpeg'],
    'png'  => ['image/png'], 
    'gif'  => ['image/gif'],
    'webp' => ['image/webp'],
    'mp4'  => ['video/mp4'],
    'webm' => ['video/webm']
];

if (isset($_FILES['wangeditor-uploaded-image'])) {
    $file = $_FILES['wangeditor-uploaded-image'];
    
    // 基础错误检查
    if ($file['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(["errno" => 1, "message" => "上传错误 (Code: " . $file['error'] . ")"]);
        exit;
    }

    // 后缀名检查
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!array_key_exists($ext, $allowedConfig)) {
        echo json_encode(["errno" => 1, "message" => "不支持的文件后缀: " . $ext]);
        exit;
    }

    // MIME 类型检查 (防止脚本伪装成图片)
    try {
        if (class_exists('finfo')) {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $realMime = $finfo->file($file['tmp_name']);
            if (!in_array($realMime, $allowedConfig[$ext])) {
                echo json_encode(["errno" => 1, "message" => "文件内容不安全，禁止上传"]);
                exit;
            }
        }
    } catch (Exception $e) {
        // 如果服务器环境不支持 finfo，虽然降低了安全性但允许通过，避免直接崩溃
        error_log("Finfo check failed: " . $e->getMessage());
    }

    // 大小限制 (50MB)
    if ($file['size'] > 50 * 1024 * 1024) {
        echo json_encode(["errno" => 1, "message" => "文件过大 (Max 50MB)"]);
        exit;
    }

    // 生成文件名
    $newName = date('Ymd_His_') . uniqid() . '.' . $ext;

    // --- 分支 A: COS 上传 ---
    if ($cosEnabled == '1') {
        require_once '../includes/cos_helper.php';
        $cosPath = 'uploads/' . date('Ym') . '/' . $newName;
        $cosUrl = uploadToCOS($file['tmp_name'], $cosPath);
        
        if ($cosUrl) {
            echo json_encode([
                "errno" => 0,
                "data" => [
                    "url" => $cosUrl,
                    "alt" => $file['name'],
                    "href" => $cosUrl
                ]
            ]);
            exit;
        } else {
            echo json_encode(["errno" => 1, "message" => "云存储上传失败"]);
            exit;
        }
    } 
    
    // --- 分支 B: 本地上传 (关键修改) ---
    else {
        // 物理路径：用于移动文件 (相对于当前 php 文件)
        $uploadDir = '../assets/uploads/';
        
        // 如果目录不存在，尝试创建
        if (!is_dir($uploadDir)) {
            if (!mkdir($uploadDir, 0755, true)) {
                echo json_encode(["errno" => 1, "message" => "无法创建上传目录，请检查 assets 目录权限"]);
                exit;
            }
        }
        
        $target = $uploadDir . $newName;
        
        if (move_uploaded_file($file['tmp_name'], $target)) {
            // URL 路径：用于前端显示 (相对于网站根目录)
            // 修改点：从 "../assets/..." 改为 "/assets/..."
            $webUrl = "/assets/uploads/" . $newName;

            echo json_encode([
                "errno" => 0,
                "data" => [
                    "url" => $webUrl,
                    "alt" => $file['name'],
                    "href" => $webUrl
                ]
            ]);
            exit;
        } else {
            echo json_encode(["errno" => 1, "message" => "文件移动失败，请检查目录写入权限"]);
            exit;
        }
    }
}

echo json_encode(["errno" => 1, "message" => "未接收到有效文件"]);
?>