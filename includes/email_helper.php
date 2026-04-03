<?php
// includes/email_helper.php
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
class SmtpMailer {
    private $host;
    private $port;
    private $user;
    private $pass;
    private $from_name;
    public $error;

    public function __construct($host, $port, $user, $pass, $from_name) {
        $this->host = $host;
        $this->port = $port;
        $this->user = $user;
        $this->pass = $pass;
        $this->from_name = $from_name;
    }

    public function send($to, $subject, $body) {
        // 使用 ssl:// 协议连接
        $handle = @fsockopen("ssl://{$this->host}", $this->port, $errno, $errstr, 10);
        if (!$handle) {
            $this->error = "连接服务器失败: $errstr ($errno)。请检查是否开启了 PHP OpenSSL 扩展。";
            return false;
        }

        $this->get_lines($handle);
        if (!$this->cmd($handle, "EHLO {$this->host}", 250)) return false;
        if (!$this->cmd($handle, "AUTH LOGIN", 334)) return false;
        if (!$this->cmd($handle, base64_encode($this->user), 334)) return false;
        if (!$this->cmd($handle, base64_encode($this->pass), 235)) return false;
        if (!$this->cmd($handle, "MAIL FROM: <{$this->user}>", 250)) return false;
        if (!$this->cmd($handle, "RCPT TO: <{$to}>", 250)) return false;
        if (!$this->cmd($handle, "DATA", 354)) return false;

        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "From: =?UTF-8?B?" . base64_encode($this->from_name) . "?= <{$this->user}>\r\n";
        $headers .= "To: <{$to}>\r\n";
        $headers .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
        
        $full_msg = $headers . "\r\n" . $body . "\r\n.\r\n";
        fputs($handle, $full_msg);
        $response = $this->get_lines($handle);
        
        if (substr($response, 0, 3) != '250') {
            $this->error = "邮件内容被拒绝: $response";
            fclose($handle);
            return false;
        }

        fputs($handle, "QUIT\r\n");
        fclose($handle);
        return true;
    }

    private function cmd($handle, $cmd, $expect_code) {
        fputs($handle, $cmd . "\r\n");
        $response = $this->get_lines($handle);
        if (substr($response, 0, 3) != $expect_code) {
            $this->error = "SMTP错误 [期望 $expect_code]: $response";
            fclose($handle);
            return false;
        }
        return true;
    }

    private function get_lines($handle) {
        $response = "";
        while ($str = fgets($handle, 512)) {
            $response .= $str;
            if (substr($str, 3, 1) == " ") break;
        }
        return trim($response);
    }
}

function sendEmailCode($to, $code) {
    global $email_error_msg; // 声明全局变量以供外部获取错误
    $pdo = getDB();

    // 从数据库 settings 表读取配置
    $stmt = $pdo->query("SELECT key_name, value FROM settings WHERE key_name IN ('smtp_host', 'smtp_port', 'smtp_user', 'smtp_pass', 'smtp_from_name')");
    $conf = [];
    while($r = $stmt->fetch()) $conf[$r['key_name']] = $r['value'];

    $host = $conf['smtp_host'] ?? '';
    $port = $conf['smtp_port'] ?? 465;
    $user = $conf['smtp_user'] ?? '';
    $pass = $conf['smtp_pass'] ?? '';
    $from = $conf['smtp_from_name'] ?? 'BKCS系统';

    if (!$host || !$user || !$pass) {
        $email_error_msg = "数据库配置缺失：请先在后台『网站设置』中填写完整的 SMTP 信息。";
        return false;
    }
    
    $mailer = new SmtpMailer($host, $port, $user, $pass, $from);
    $subject = "注册验证码 - " . $from;
    $body = "<div style='padding:20px; border:1px solid #eee;'>您的验证码是：<b style='font-size:24px; color:#007aff;'>{$code}</b><br>有效期5分钟，请勿泄露。</div>";
    
    if ($mailer->send($to, $subject, $body)) {
        return true;
    } else {
        $email_error_msg = $mailer->error;
        return false;
    }
}