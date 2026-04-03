<?php
// api/ai_generate.php
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

// 1. 强制关闭 PHP 输出缓冲 (解决卡顿的关键)
@ini_set('zlib.output_compression', 0);
@ini_set('implicit_flush', 1);
while (ob_get_level() > 0) {
    ob_end_clean();
}

// 2. 权限检查
if (empty($_SESSION['admin_logged_in'])) {
    header('HTTP/1.1 403 Forbidden');
    die("data: [ERROR] 未登录\n\n");
}

// 3. 设置流式响应头
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no'); // 针对 Nginx

$pdo = getDB();

// 4. 获取输入
$inputJSON = file_get_contents('php://input');
$data = json_decode($inputJSON, true);
$topic = trim($data['topic'] ?? '');

if (empty($topic)) {
    echo "data: [ERROR] 请输入主题\n\n";
    flush();
    exit;
}

// 5. 读取配置
$stmt = $pdo->prepare("SELECT key_name, value FROM settings WHERE key_name IN ('ai_api_url', 'ai_api_key', 'ai_model_name')");
$stmt->execute();
$conf = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

$apiUrl = $conf['ai_api_url'] ?? '';
$apiKey = $conf['ai_api_key'] ?? '';
$model  = $conf['ai_model_name'] ?? 'gpt-3.5-turbo';

if (empty($apiUrl) || empty($apiKey)) {
    echo "data: [ERROR] 请先在后台设置中配置 AI API Key\n\n";
    flush();
    exit;
}

// 6. 构造提示词 (Prompt) - 核心修改点
$separator = "===PART_SPLIT_MARKER===";

$prompt = "你是一位在该领域有深厚造诣的专家博主。请以“{$topic}”为主题创作一篇深度文章。

请严格按照以下**三个步骤**输出，步骤之间必须使用分隔符“{$separator}”隔开：

第一步：创作一个吸引人的文章标题（纯文本，不要包含'标题：'字样）。
{$separator}
第二步：写一段约 100-150 字的精彩导读摘要（纯文本）。
{$separator}
第三步：写文章正文（HTML格式，包含 <h2>, <p>, <ul> 等标签）。

**正文要求**：
1. 字数至少 1500 字以上。
2. 深度解析，拒绝泛泛而谈。
3. 结构清晰，包含引言、小标题和结语。

请开始创作，严格遵守格式：标题 -> 分隔符 -> 摘要 -> 分隔符 -> 正文。";

// 7. CURL 设置
$payload = [
    'model' => $model,
    'messages' => [
        ['role' => 'system', 'content' => '你是一个专业的博客文章写作助手。'],
        ['role' => 'user', 'content' => $prompt]
    ],
    'stream' => true,
    'temperature' => 0.7
];

$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $apiKey
]);

// 处理流式数据
curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($ch, $data) {
    $len = strlen($data);
    if ($len === 0) return 0;

    $lines = explode("\n", $data);
    
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line) || strpos($line, 'data: ') !== 0) continue;
        
        $content = substr($line, 6);
        if ($content === '[DONE]') continue;
        
        $json = json_decode($content, true);
        
        if (is_array($json) && isset($json['choices'][0]['delta']['content'])) {
            $text = $json['choices'][0]['delta']['content'];
            echo $text;
            flush(); // 立即发送给前端
        }
    }
    return $len;
});

curl_setopt($ch, CURLOPT_TIMEOUT, 120);
$result = curl_exec($ch);

if (curl_errno($ch)) {
    echo "data: [ERROR] CURL错误: " . curl_error($ch) . "\n\n";
    flush();
}

curl_close($ch);
?>
