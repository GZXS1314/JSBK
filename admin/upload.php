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

// 开启错误显示以便调试（生产环境可注释掉）
ini_set('display_errors', 1);
error_reporting(E_ALL);

requireLogin();

header('Content-Type: application/json; charset=utf-8');

// --- 1. 读取设置 ---
$pdo = getDB();
$stmt = $pdo->prepare("SELECT value FROM settings WHERE key_name = 'cos_enabled'");
$stmt->execute();
$cosEnabled = $stmt->fetchColumn(); 

// --- 配置允许的后缀与 MIME ---
// 注意：finfo 检测的 mime 类型可能与浏览器发送的不完全一致，这里做宽松匹配
$allowedConfig = [
    'jpg'  => ['image/jpeg', 'image/pjpeg'],
    'jpeg' => ['image/jpeg', 'image/pjpeg'],
    'png'  => ['image/png', 'image/x-png'], 
    'gif'  => ['image/gif'],
    'webp' => ['image/webp'],
    'mp4'  => ['video/mp4', 'application/octet-stream'], // 部分环境 mp4 识别为 octet-stream
    'webm' => ['video/webm']
];

// --- 辅助函数：处理单个文件上传 ---
function processSingleUpload($fileData, $cosEnabled, $allowedConfig) {
    // 1. 基础错误检查
    if ($fileData['error'] !== UPLOAD_ERR_OK) {
        return ["errno" => 1, "message" => "上传错误 (Code: " . $fileData['error'] . ")"];
    }

    // 2. 后缀名检查
    $ext = strtolower(pathinfo($fileData['name'], PATHINFO_EXTENSION));
    if (!array_key_exists($ext, $allowedConfig)) {
        return ["errno" => 1, "message" => "不支持的文件后缀: " . $ext];
    }

    // 3. MIME 类型检查 (可选，为了兼容性可暂时放宽，这里保留逻辑但增加 try-catch)
    /*
    try {
        if (class_exists('finfo')) {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $realMime = $finfo->file($fileData['tmp_name']);
            // 如果检测到的类型不在允许列表中，且不是 octet-stream (二进制流有时是正常的)，则报错
            // 这里为了防止误杀，先注释掉强校验，或者你可以根据实际情况开启
            // if (!in_array($realMime, $allowedConfig[$ext])) { ... }
        }
    } catch (Exception $e) { }
    */

    // 4. 大小限制 (50MB)
    if ($fileData['size'] > 50 * 1024 * 1024) {
        return ["errno" => 1, "message" => "文件过大 (Max 50MB)"];
    }

    // 5. 生成文件名
    $newName = date('Ymd_His_') . uniqid() . '.' . $ext;

    // --- 分支 A: COS 上传 ---
    if ($cosEnabled == '1') {
        // 注意：这里需确保 cos_helper.php 路径正确且函数可用
        if (file_exists('../includes/cos_helper.php')) {
            require_once '../includes/cos_helper.php';
            $cosPath = 'uploads/' . date('Ym') . '/' . $newName;
            $cosUrl = uploadToCOS($fileData['tmp_name'], $cosPath);
            
            if ($cosUrl) {
                return [
                    "errno" => 0,
                    "data" => [
                        "url" => $cosUrl,
                        "alt" => $fileData['name'],
                        "href" => $cosUrl
                    ]
                ];
            }
        }
        return ["errno" => 1, "message" => "云存储配置错误或上传失败"];
    } 
    
    // --- 分支 B: 本地上传 ---
    else {
        // 物理路径：用于移动文件 (相对于 admin/upload.php，即 ../assets/uploads/)
        $uploadDir = '../assets/uploads/';
        
        // 确保目录存在
        if (!is_dir($uploadDir)) {
            if (!mkdir($uploadDir, 0755, true)) {
                return ["errno" => 1, "message" => "无法创建上传目录"];
            }
        }
        
        $target = $uploadDir . $newName;
        
        if (move_uploaded_file($fileData['tmp_name'], $target)) {
            // URL 路径：用于前端显示 (相对于网站根目录，假设 admin 和 assets 同级)
            // 这是一个常见坑点：物理路径是 ../assets，但 URL 路径通常是 /assets
            // 请根据你的实际目录结构调整这个 $webUrl
            $webUrl = "../assets/uploads/" . $newName; // 或者是 "/assets/uploads/" . $newName;

            return [
                "errno" => 0,
                "data" => [
                    "url" => $webUrl,
                    "alt" => $fileData['name'],
                    "href" => $webUrl
                ]
            ];
        } else {
            return ["errno" => 1, "message" => "文件移动失败"];
        }
    }
}

// --- 主逻辑：处理 WangEditor 可能的数组结构 ---

if (isset($_FILES['wangeditor-uploaded-image'])) {
    $rawFiles = $_FILES['wangeditor-uploaded-image'];
    
    // 判断是否是多文件上传结构 (name 是数组)
    if (is_array($rawFiles['name'])) {
        // WangEditor v5 默认一次请求只发一个文件，但以防万一处理第一个
        $fileData = [
            'name'     => $rawFiles['name'][0],
            'type'     => $rawFiles['type'][0],
            'tmp_name' => $rawFiles['tmp_name'][0],
            'error'    => $rawFiles['error'][0],
            'size'     => $rawFiles['size'][0]
        ];
        echo json_encode(processSingleUpload($fileData, $cosEnabled, $allowedConfig));
    } else {
        // 单文件结构
        echo json_encode(processSingleUpload($rawFiles, $cosEnabled, $allowedConfig));
    }
    exit;
}

echo json_encode(["errno" => 1, "message" => "未接收到有效文件"]);
?>
