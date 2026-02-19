<?php
// api/captcha.php
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
// 1. 引用全局配置
require_once __DIR__ . '/../includes/config.php'; 

// 2. 禁止缓存
header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
header("Cache-Control: no-cache, must-revalidate");
header("Pragma: no-cache");
header('Content-type: image/png');

// 3. 创建画布
$width = 120;
$height = 40;
$image = imagecreatetruecolor($width, $height);

// 4. 定义颜色
$bg_color = imagecolorallocate($image, 255, 255, 255); 
$text_color = imagecolorallocate($image, 0, 0, 0);       
$line_color = imagecolorallocate($image, 200, 200, 200); 
$pixel_color = imagecolorallocate($image, 100, 100, 100); 

// 5. 填充背景
imagefill($image, 0, 0, $bg_color);

// 6. 画干扰线
for ($i = 0; $i < 5; $i++) {
    imageline($image, rand(0, $width), rand(0, $height), rand(0, $width), rand(0, $height), $line_color);
}

// 7. 画干扰点
for ($i = 0; $i < 50; $i++) {
    imagesetpixel($image, rand(0, $width), rand(0, $height), $pixel_color);
}

// 8. 生成随机字符并绘制
$code = '';
$charset = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789'; 
$len = strlen($charset) - 1;

for ($i = 0; $i < 4; $i++) {
    $char = $charset[rand(0, $len)];
    $code .= $char;
    imagestring($image, 5, 20 + ($i * 20), 10, $char, $text_color);
}

// 9. 存入 Session
$_SESSION['captcha_code'] = strtolower($code);

// 🔥🔥🔥 核心修复：强制立即把 Session 数据写入磁盘/Redis 🔥🔥🔥
// 这一步至关重要，防止脚本结束过快导致数据丢失，或者被并发请求锁死
session_write_close(); 

// 10. 输出图片
imagepng($image);
imagedestroy($image);
?>
