<?php
/**
 * api/pay.php - 终极支付网关 (支持易支付 + 原生支付宝当面付/网页 + 原生微信 V3 扫码)
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/core.php';

if (session_status() === PHP_SESSION_NONE) session_start();
$action = $_GET['action'] ?? '';
$pdo = getDB();

function getPayConf($key) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT value FROM settings WHERE key_name = ?");
    $stmt->execute([$key]);
    return trim($stmt->fetchColumn() ?? '');
}

function getBaseUrl() {
    $is_https = (isset($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) === 'on') || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
    return ($is_https ? "https://" : "http://") . ($_SERVER['HTTP_HOST'] ?? 'localhost');
}

// 格式化密钥
function formatKey($key, $type = 'private') {
    $key = str_replace(["\r", "\n", "-----BEGIN PRIVATE KEY-----", "-----END PRIVATE KEY-----", "-----BEGIN PUBLIC KEY-----", "-----END PUBLIC KEY-----", "-----BEGIN RSA PRIVATE KEY-----", "-----END RSA PRIVATE KEY-----"], '', $key);
    $header = $type == 'private' ? "-----BEGIN PRIVATE KEY-----\n" : "-----BEGIN PUBLIC KEY-----\n";
    $footer = $type == 'private' ? "\n-----END PRIVATE KEY-----" : "\n-----END PUBLIC KEY-----";
    return $header . wordwrap($key, 64, "\n", true) . $footer;
}

// ---------------------------------------------------------
// 发起支付请求
// ---------------------------------------------------------
if ($action === 'submit') {
    if (empty($_SESSION['user_id'])) die("请先登录");
    
    $amount = floatval($_POST['amount'] ?? 0);
    $pay_type = trim($_POST['pay_type'] ?? 'alipay'); 
    if ($amount < 1) die("充值金额最低为 1 元");

    $user_id = $_SESSION['user_id'];
    $rate = intval(getPayConf('points_exchange_rate')) ?: 100;
    $points = floor($amount * $rate);
    $order_no = 'R' . date('YmdHis') . rand(1000, 9999) . $user_id;

    $pdo->prepare("INSERT INTO recharge_orders (order_no, user_id, amount, points, pay_type, status) VALUES (?, ?, ?, ?, ?, 0)")->execute([$order_no, $user_id, $amount, $points, $pay_type]);
    $channel = getPayConf('pay_channel');

    // === 【通道 1：彩虹易支付】 ===
    if ($channel === 'epay') {
        $epay_url = rtrim(getPayConf('epay_url'), '/');
        $epay_key = getPayConf('epay_key');
        $params = [
            "pid" => getPayConf('epay_pid'), "type" => ($pay_type == 'alipay_f2f' ? 'alipay' : $pay_type), "out_trade_no" => $order_no,
            "notify_url" => getBaseUrl() . "/api/pay.php?action=notify", "return_url" => getBaseUrl() . "/api/pay.php?action=return",
            "name" => "积分充值-{$points}积分", "money" => number_format($amount, 2, '.', ''), "clientip" => $_SERVER['REMOTE_ADDR']
        ];
        ksort($params); $str = ''; foreach ($params as $k => $v) { if ($v != '') $str .= $k . '=' . $v . '&'; }
        $params['sign'] = md5(rtrim($str, '&') . $epay_key); $params['sign_type'] = "MD5";

        $html = "<form id='payForm' action='{$epay_url}/submit.php' method='POST'>";
        foreach ($params as $k => $v) { $html .= "<input type='hidden' name='{$k}' value='{$v}'/>"; }
        echo $html . "</form><script>document.getElementById('payForm').submit();</script>跳转支付中...";
        exit;
    } 
    // === 【通道 2：官方支付宝 & 微信 APIv3】 ===
    else if ($channel === 'official') {
        
        // --- 支付宝 (电脑网页 / 手机网页 / 当面付) ---
        if (strpos($pay_type, 'alipay') !== false) {
            $appid = getPayConf('alipay_appid');
            $private_key = formatKey(getPayConf('alipay_private_key'), 'private');
            
            $method = 'alipay.trade.page.pay'; // 默认 PC 网页支付
            if ($pay_type === 'alipay_f2f') $method = 'alipay.trade.precreate'; // 当面付
            else if (preg_match("/(android|iphone|ipad)/i", $_SERVER["HTTP_USER_AGENT"])) $method = 'alipay.trade.wap.pay'; // 手机网页
            
            $biz_content = json_encode([
                "out_trade_no" => $order_no, "total_amount" => number_format($amount, 2, '.', ''), "subject" => "充值-{$points}积分",
                "product_code" => ($method == 'alipay.trade.page.pay' ? "FAST_INSTANT_TRADE_PAY" : ($method == 'alipay.trade.wap.pay' ? "QUICK_WAP_WAY" : "FACE_TO_FACE_PAYMENT"))
            ]);

            $sysParams = [
                "app_id" => $appid, "method" => $method, "format" => "JSON", "charset" => "utf-8",
                "sign_type" => "RSA2", "timestamp" => date("Y-m-d H:i:s"), "version" => "1.0",
                "notify_url" => getBaseUrl() . "/api/pay.php?action=notify", "biz_content" => $biz_content
            ];
            if ($method != 'alipay.trade.precreate') $sysParams["return_url"] = getBaseUrl() . "/api/pay.php?action=return";

            ksort($sysParams); $str = ""; foreach ($sysParams as $k => $v) { if ($v !== '') $str .= "$k=$v&"; }
            openssl_sign(rtrim($str, '&'), $sign, $private_key, OPENSSL_ALGO_SHA256);
            $sysParams['sign'] = base64_encode($sign);

            // 当面付处理 (需请求接口获取二维码)
            if ($method === 'alipay.trade.precreate') {
                $url = "https://openapi.alipay.com/gateway.do?" . http_build_query($sysParams);
                $res = json_decode(file_get_contents($url), true);
                if (isset($res['alipay_trade_precreate_response']['code']) && $res['alipay_trade_precreate_response']['code'] == '10000') {
                    $qr_url = $res['alipay_trade_precreate_response']['qr_code'];
                    renderQrCodePage('支付宝', $amount, $qr_url, $order_no);
                } else die("支付宝当面付请求失败: " . print_r($res, true));
            } else {
                // 网页/手机支付处理 (直接表单跳转)
                $html = "<form id='alipaysubmit' name='alipaysubmit' action='https://openapi.alipay.com/gateway.do?charset=utf-8' method='POST'>";
                foreach ($sysParams as $k => $v) { $html .= "<input type='hidden' name='" . $k . "' value='" . htmlspecialchars($v, ENT_QUOTES) . "'/>"; }
                echo $html . "</form><script>document.forms['alipaysubmit'].submit();</script>正在呼起支付宝...";
            }
            exit;
        }

        // --- 微信支付 (API v3 Native 扫码) ---
        if ($pay_type === 'wxpay') {
            $mchid = getPayConf('wxpay_mchid'); $appid = getPayConf('wxpay_appid');
            $serial_no = getPayConf('wxpay_serial_no');
            $private_key = formatKey(getPayConf('wxpay_private_key'), 'private');
            
            $url = "https://api.mch.weixin.qq.com/v3/pay/transactions/native";
            $body = json_encode([
                "mchid" => $mchid, "out_trade_no" => $order_no, "appid" => $appid,
                "description" => "充值-{$points}积分", "notify_url" => getBaseUrl() . "/api/pay.php?action=notify",
                "amount" => ["total" => intval($amount * 100), "currency" => "CNY"]
            ]);

            $timestamp = time(); $nonceStr = bin2hex(random_bytes(16));
            $message = "POST\n/v3/pay/transactions/native\n{$timestamp}\n{$nonceStr}\n{$body}\n";
            openssl_sign($message, $raw_sign, $private_key, OPENSSL_ALGO_SHA256);
            $sign = base64_encode($raw_sign);
            $auth = 'WECHATPAY2-SHA256-RSA2048 mchid="'.$mchid.'",nonce_str="'.$nonceStr.'",timestamp="'.$timestamp.'",serial_no="'.$serial_no.'",signature="'.$sign.'"';

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_POST, 1); curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json", "Authorization: $auth", "Accept: application/json"]);
            $res = json_decode(curl_exec($ch), true); curl_close($ch);

            if (isset($res['code_url'])) renderQrCodePage('微信支付', $amount, $res['code_url'], $order_no);
            else die("微信支付请求失败: " . print_r($res, true));
            exit;
        }
    }
}

// ---------------------------------------------------------
// 异步回调通知 (接收支付宝/微信/易支付推送)
// ---------------------------------------------------------
if ($action === 'notify') {
    $channel = getPayConf('pay_channel');
    $order_no = ''; $trade_no = ''; $is_success = false;

    // --- 支付宝异步回调 ---
    if (isset($_POST['notify_type']) && $_POST['notify_type'] == 'trade_status_sync') {
        $params = $_POST; $sign = $params['sign']; unset($params['sign'], $params['sign_type']);
        ksort($params); $str = ""; foreach ($params as $k => $v) { if ($v !== '') $str .= "$k=$v&"; }
        $public_key = formatKey(getPayConf('alipay_public_key'), 'public');
        if (openssl_verify(rtrim($str, '&'), base64_decode($sign), $public_key, OPENSSL_ALGO_SHA256)) {
            if ($_POST['trade_status'] == 'TRADE_SUCCESS' || $_POST['trade_status'] == 'TRADE_FINISHED') {
                $order_no = $_POST['out_trade_no']; $trade_no = $_POST['trade_no']; $is_success = true;
            }
        }
        $response_msg = "success";
    } 
    // --- 微信 V3 异步回调 (AES-256-GCM 解密) ---
    else if ($json = file_get_contents('php://input')) {
        $data = json_decode($json, true);
        if (isset($data['resource']['ciphertext'])) {
            $aesKey = getPayConf('wxpay_key');
            $ciphertext = base64_decode($data['resource']['ciphertext']);
            $ctext = substr($ciphertext, 0, -16); $authTag = substr($ciphertext, -16);
            $decrypted = openssl_decrypt($ctext, 'aes-256-gcm', $aesKey, OPENSSL_RAW_DATA, $data['resource']['nonce'], $authTag, $data['resource']['associated_data']);
            if ($decrypted) {
                $orderData = json_decode($decrypted, true);
                if ($orderData['trade_state'] === 'SUCCESS') {
                    $order_no = $orderData['out_trade_no']; $trade_no = $orderData['transaction_id']; $is_success = true;
                }
            }
        }
        $response_msg = json_encode(["code" => "SUCCESS", "message" => "OK"]);
    }
    // --- 易支付异步回调 ---
    else if (isset($_GET['trade_status'])) {
        $params = $_GET; unset($params['action'], $params['sign'], $params['sign_type']);
        ksort($params); $str = ''; foreach ($params as $k => $v) { if ($v != '') $str .= $k . '=' . $v . '&'; }
        if (md5(rtrim($str, '&') . getPayConf('epay_key')) === $_GET['sign'] && $_GET['trade_status'] == 'TRADE_SUCCESS') {
            $order_no = $_GET['out_trade_no']; $trade_no = $_GET['trade_no']; $is_success = true;
        }
        $response_msg = "success";
    }

    // --- 执行入账逻辑 ---
    if ($is_success && $order_no) {
        $stmt = $pdo->prepare("SELECT * FROM recharge_orders WHERE order_no = ? AND status = 0");
        $stmt->execute([$order_no]);
        if ($order = $stmt->fetch()) {
            $pdo->beginTransaction();
            try {
                $pdo->prepare("UPDATE recharge_orders SET status = 1, trade_no = ?, paid_at = NOW() WHERE id = ?")->execute([$trade_no, $order['id']]);
                $pdo->prepare("UPDATE users SET points = points + ? WHERE id = ?")->execute([$order['points'], $order['user_id']]);
                $pdo->prepare("INSERT INTO points_log (user_id, action, points_change, description) VALUES (?, 'recharge', ?, ?)")->execute([$order['user_id'], $order['points'], "充值订单: {$order_no}"]);
                $pdo->commit();
            } catch (Exception $e) { $pdo->rollBack(); }
        }
    }
    die($response_msg ?? 'fail');
}

// ---------------------------------------------------------
// 网页同步回调跳转 (仅支付宝网页 / 易支付有用)
// ---------------------------------------------------------
if ($action === 'return') {
    header("Location: ../user/dashboard.php?recharge_success=1"); exit;
}

// ---------------------------------------------------------
// 扫码页面 AJAX 轮询查单接口
// ---------------------------------------------------------
if ($action === 'check_status') {
    header('Content-Type: application/json');
    $order_no = trim($_GET['order_no'] ?? '');
    $stmt = $pdo->prepare("SELECT status FROM recharge_orders WHERE order_no = ?");
    $stmt->execute([$order_no]);
    echo json_encode(['paid' => ($stmt->fetchColumn() == 1)]);
    exit;
}

// ---------------------------------------------------------
// 渲染漂亮的收银台二维码页面
// ---------------------------------------------------------
function renderQrCodePage($name, $amount, $qr_url, $order_no) {
    $color = $name == '微信支付' ? '#07c160' : '#00a1d6';
    $icon = $name == '微信支付' ? '<i class="fab fa-weixin"></i>' : '<i class="fab fa-alipay"></i>';
    echo <<<HTML
    <!DOCTYPE html><html lang="zh-CN"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>{$name}收银台</title>
    <link href="https://cdn.bootcdn.net/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.bootcdn.net/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <style>body{background:#f8fafc;font-family:sans-serif;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;}
    .card{background:#fff;padding:40px;border-radius:24px;box-shadow:0 10px 30px rgba(0,0,0,0.05);text-align:center;width:90%;max-width:360px;}
    .qr-box{width:220px;height:220px;margin:20px auto;padding:10px;border:1px solid #e2e8f0;border-radius:16px;}
    .amount{font-size:36px;font-weight:900;color:#0f172a;margin:15px 0;} .amount span{font-size:20px;}</style></head>
    <body>
        <div class="card">
            <div style="font-size:24px;color:{$color};">{$icon} {$name}</div>
            <div class="amount"><span>￥</span>{$amount}</div>
            <div class="qr-box" id="qrcode"></div>
            <div style="color:#64748b;font-size:14px;margin-top:20px;"><i class="fa-solid fa-spinner fa-spin"></i> 请打开手机{$name}扫一扫<br>支付成功后将自动跳转</div>
        </div>
        <script>
            new QRCode(document.getElementById("qrcode"), {text: "{$qr_url}", width: 200, height: 200});
            setInterval(() => {
                fetch('pay.php?action=check_status&order_no={$order_no}').then(r=>r.json()).then(d=>{
                    if(d.paid) window.location.href='../user/dashboard.php?recharge_success=1';
                });
            }, 3000);
        </script>
    </body></html>
HTML;
}
?>