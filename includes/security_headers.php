<?php
// includes/security_headers.php
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
// 1. 禁止被嵌入 iframe (防止点击劫持攻击)
header("X-Frame-Options: SAMEORIGIN");

// 2. 强制开启 XSS 过滤 (浏览器内置)
header("X-XSS-Protection: 1; mode=block");

// 3. 禁止浏览器猜测内容类型 (防止 MIME 嗅探攻击)
header("X-Content-Type-Options: nosniff");

// 4. 控制 Referrer 泄露 (只发送源站信息，保护用户隐私)
header("Referrer-Policy: strict-origin-when-cross-origin");

// 5. 内容安全策略 (CSP) - 白名单机制
// 修复点：添加了 cdnjs, google fonts, 和 API 的域名白名单

$csp = "default-src 'self'; ";

// 允许的脚本来源: 自身, BootCDN, 百度统计, Google分析
$csp .= "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.bootcdn.net https://hm.baidu.com https://www.googletagmanager.com; ";

// 允许的样式来源: 自身, 内联样式, BootCDN, CDNJS (新增), Google Fonts (新增)
$csp .= "style-src 'self' 'unsafe-inline' https://cdn.bootcdn.net https://cdnjs.cloudflare.com https://fonts.googleapis.com; ";

// 允许的图片来源: 自身, Base64图片, 所有 HTTPS 图片 (为了兼容网易云封面)
$csp .= "img-src 'self' data: https: http:; ";

// 允许的字体来源: 自身, BootCDN, Base64, Google Fonts (新增), CDNJS (新增)
$csp .= "font-src 'self' https://cdn.bootcdn.net data: https://fonts.gstatic.com https://cdnjs.cloudflare.com; ";

// 允许的 AJAX 连接请求: 自身, 以及所有 HTTPS 接口 (修复点：允许连接外部音乐API)
// 注意：为了方便你在后台随意更换音乐API，这里放宽为允许所有 https 连接
$csp .= "connect-src 'self' https:;"; 

// 允许媒体加载 (音频文件): 自身, 以及所有 HTTPS 音频
$csp .= "media-src 'self' https: http:;";

header("Content-Security-Policy: $csp");
?>