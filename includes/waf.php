<?php
/**
 * 全局应用防火墙 (Simple PHP WAF)
 * 功能：拦截 SQL 注入、XSS、路径遍历、恶意 User-Agent
 * /**
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
 */

class WAF {
    private static $log_file = __DIR__ . '/../security_log.php';  // 攻击日志路径

    // 1. 启动防御
    public static function run() {
        self::check_user_agent(); // 检查爬虫
        self::check_data($_GET, 'GET'); // 检查 URL 参数
        self::check_data($_POST, 'POST'); // 检查表单提交
        self::check_data($_COOKIE, 'COOKIE'); // 检查 Cookie
    }

    // 2. 恶意关键词黑名单 (正则)
    private static function get_patterns() {
        return [
            // SQL 注入
            '/select\s+.*from/i',
            '/union\s+select/i',
            '/insert\s+into/i',
            '/update\s+.*set/i',
            '/delete\s+from/i',
            '/drop\s+table/i',
            '/information_schema/i',
            '/--/i',  // 注释符
            
            // XSS 跨站脚本
            '/<script/i',
            '/javascript:/i',
            '/on(click|load|error|mouse)\s*=/i',
            '/\<iframe/i',
            '/\<object/i',
            
            // 路径遍历 / 系统命令
            '/\.\.\//', // ../
            '/etc\/passwd/i',
            '/cmd\.exe/i',
            '/bin\/sh/i',
            
            // 危险函数
            '/base64_decode/i',
            '/eval\(/i',
            '/system\(/i'
        ];
    }

    // 3. 递归检查数据
    private static function check_data($arr, $type) {
        if (!is_array($arr)) return;

        foreach ($arr as $key => $value) {
            if (is_array($value)) {
                self::check_data($value, $type); // 递归检查数组
            } else {
                // 如果是管理员发文章，可能包含代码片段，需要放宽限制（可选）
                // if ($type == 'POST' && strpos($_SERVER['REQUEST_URI'], '/admin') !== false) continue;

                foreach (self::get_patterns() as $pattern) {
                    if (preg_match($pattern, $value) || preg_match($pattern, $key)) {
                        self::block_request("发现恶意特征 [$pattern] 在 $type 参数: $key => $value");
                    }
                }
            }
        }
    }

    // 4. 检查恶意 User-Agent (扫描器/爬虫)
    private static function check_user_agent() {
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $bad_bots = ['sqlmap', 'nikto', 'wpscan', 'python', 'curl', 'wget', 'java/']; // 常见攻击工具
        foreach ($bad_bots as $bot) {
            if (stripos($ua, $bot) !== false) {
                self::block_request("拦截恶意 User-Agent: $ua");
            }
        }
    }

    // 5. 拦截并记录日志
    private static function block_request($reason) {
        if (!file_exists(self::$log_file)) {
        file_put_contents(self::$log_file, "<?php exit(); ?>\n");
    }
        // 记录日志
        $log = sprintf("[%s] IP: %s | URL: %s | %s\n", 
            date('Y-m-d H:i:s'), 
            $_SERVER['REMOTE_ADDR'], 
            $_SERVER['REQUEST_URI'], 
            $reason
        );
        file_put_contents(self::$log_file, $log, FILE_APPEND);

        // 终止执行，返回 403
        http_response_code(403);
        die('
            <div style="text-align:center; margin-top:100px; font-family:sans-serif;">
                <h1 style="color:#ff4757; font-size:40px;">🚫 系统拦截</h1>
                <p style="color:#666; font-size:18px;">您的请求包含非法字符或恶意行为。</p>
                <p style="color:#999; font-size:12px;">ID: ' . md5(time()) . '</p>
            </div>
        ');
    }
}

// 自动运行
WAF::run();
?>