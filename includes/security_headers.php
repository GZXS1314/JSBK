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
// 1. 防止点击劫持 (推荐保留，作为 CSP frame-ancestors 的兼容后备)
header("X-Frame-Options: SAMEORIGIN");

// 2. 禁止浏览器猜测内容类型 (防止 MIME 嗅探攻击)
header("X-Content-Type-Options: nosniff");

// 3. 控制 Referrer 泄露 (保护用户隐私，提升安全性)
header("Referrer-Policy: strict-origin-when-cross-origin");

// 4. 强制 HTTPS (HSTS) - 极度推荐，防止降级攻击和中间人劫持
header("Strict-Transport-Security: max-age=31536000; includeSubDomains; preload");

// 5. 权限策略 (防止第三方脚本悄悄调用敏感硬件)
header("Permissions-Policy: geolocation=(), microphone=(), camera=()");

// 6. 内容安全策略 (CSP) - 
$csp = "default-src 'self'; ";

// 允许的脚本: 自身, Staticfile (国内极速公共库), 百度统计
$csp .= "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.staticfile.net https://hm.baidu.com; ";

// 允许的样式: 自身, 内联样式, Staticfile, Loli字体镜像 (替换Google Fonts)
$csp .= "style-src 'self' 'unsafe-inline' https://cdn.staticfile.net https://fonts.loli.net; ";

// 允许的图片: 自身, Base64图片, 所有 HTTPS/HTTP 图片 (兼容国内各大图床、你的 COS 和第三方媒体源)
$csp .= "img-src 'self' data: https: http:; ";

// 允许的字体: 自身, Base64, Staticfile, Loli字体真实文件直链镜像
$csp .= "font-src 'self' data: https:; ";

// 允许的 AJAX/API 请求: 自身, 以及所有 HTTPS 接口 (兼容跨域 API 获取)
$csp .= "connect-src 'self' https:; ";

// 允许媒体加载 (音频/视频): 自身, HTTPS/HTTP
$csp .= "media-src 'self' https: http:;";

header("Content-Security-Policy: $csp");
?>