<?php
// includes/waf.php
/**
 * 全局应用防火墙 (Simple PHP WAF) - 修复升级版 (含真实 IP 获取)
 */

class WAF {
    // 绝对不能用 .php 存日志！改为 .log 并配合 Nginx/Apache 禁止访问
    private static $log_file = __DIR__ . '/../logs/security_intercept.log'; 

    public static function run() {
        // 确保在读取 $_SESSION 前，Session 已经启动
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        self::check_user_agent();
        self::check_data($_GET, 'GET');
        self::check_data($_POST, 'POST');
        self::check_data($_COOKIE, 'COOKIE');
    }

    // --- 新增：获取客户端真实 IP ---
    private static function get_real_ip() {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';

        // 优先尝试获取反向代理传递的真实 IP
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            // X-Forwarded-For 可能是逗号分隔的多个 IP，第一个通常是真实客户端
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $ip = trim($ips[0]);
        } elseif (!empty($_SERVER['HTTP_X_REAL_IP'])) {
            $ip = trim($_SERVER['HTTP_X_REAL_IP']);
        }

        // 验证提取出的 IP 是否合法，如果不合法（可能是黑客恶意伪造的头部），则退回使用 REMOTE_ADDR
        return filter_var($ip, FILTER_VALIDATE_IP) ?: ($_SERVER['REMOTE_ADDR'] ?? 'Unknown IP');
    }

    private static function get_patterns() {
        return [
            // SQL 注入 (精简误杀率，移除单纯的 -- )
            '/select\s+.*from/i',
            '/union\s+select/i',
            '/insert\s+into\s+/i', // 增加 \s+ 减少误杀
            '/drop\s+table/i',
            '/information_schema/i',
            '/\/\*.*\*\//',        // 拦截 /* */ 形式的 SQL 注释
            
            // XSS 跨站脚本
            '/<\s*script/i',       // 防绕过：< script
            '/javascript\s*:/i',
            '/on(click|load|error|mouseover|submit)\s*=/i',
            '/<\s*iframe/i',
            
            // 路径遍历 / 系统命令
            '/\.\.\/\.\.\//',      // 必须是连续的 ../../ 才拦截，减少正常路径误杀
            '/etc\/passwd/i',
            '/cmd\.exe/i',
            '/bin\/bash/i',        // bash 比 sh 更常见
            
            // 危险函数执行 (防止一句话木马)
            '/eval\s*\(/i',
            '/system\s*\(/i',
            '/assert\s*\(/i'
        ];
    }

    private static function check_data($arr, $type) {
        if (!is_array($arr)) return;

        // 管理员豁免权（仅限 POST，GET 依然拦截防止被 CSRF 攻击后台）
        if ($type === 'POST' && !empty($_SESSION['admin_logged_in'])) {
            return; 
        }

        foreach ($arr as $key => $value) {
            if (is_array($value)) {
                self::check_data($value, $type);
            } else {
                foreach (self::get_patterns() as $pattern) {
                    // 只检查 value，检查 key 容易导致误杀且意义不大
                    if (preg_match($pattern, $value)) {
                        self::block_request("发现恶意特征在 $type 参数: $key");
                    }
                }
            }
        }
    }

    private static function check_user_agent() {
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        // 移除 python 和 curl，作为面向开发者的博客，可能有正常脚本或 API 测试
        // 保留常见的恶意漏洞扫描器
        $bad_bots = ['sqlmap', 'nikto', 'wpscan', 'dirbuster', 'nmap', 'zmap']; 
        foreach ($bad_bots as $bot) {
            if (stripos($ua, $bot) !== false) {
                self::block_request("拦截恶意扫描器: $bot");
            }
        }
    }

    private static function block_request($reason) {
        // 确保日志目录存在
        $log_dir = dirname(self::$log_file);
        if (!is_dir($log_dir)) {
            mkdir($log_dir, 0755, true);
        }

        // 净化输入，防止日志注入
        $safe_uri = htmlspecialchars($_SERVER['REQUEST_URI'] ?? '', ENT_QUOTES, 'UTF-8');
        $safe_ip  = self::get_real_ip(); // 使用新编写的真实 IP 获取函数
        
        $log = sprintf("[%s] IP: %s | URL: %s | %s\n", 
            date('Y-m-d H:i:s'), 
            $safe_ip, 
            $safe_uri, 
            $reason
        );
        
        // 写入日志文件
        file_put_contents(self::$log_file, $log, FILE_APPEND);

        // 终止执行
        http_response_code(403);
        header('Content-Type: text/html; charset=utf-8');
        die('
            <div style="text-align:center; margin-top:100px; font-family:sans-serif;">
                <h1 style="color:#ff4757; font-size:40px;">🚫 WAF 拦截</h1>
                <p style="color:#666; font-size:18px;">您的请求包含非法字符，已被系统拦截。</p>
                <p style="color:#999; font-size:12px;">事件 ID: ' . uniqid('waf_') . '</p>
            </div>
        ');
    }
}

WAF::run();
?>