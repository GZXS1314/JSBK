<?php
// api/wechat_server.php
require_once '../includes/config.php';

$pdo = getDB();

// 动态获取后台配置的 Token
$stmt = $pdo->prepare("SELECT value FROM settings WHERE key_name = 'official_mp_wx_token'");
$stmt->execute();
$wechat_token = trim($stmt->fetchColumn() ?? '');

if (empty($wechat_token)) {
    die('微信 Token 未配置');
}

// 1. 微信服务器接入校验
if (isset($_GET['echostr'])) {
    $signature = $_GET["signature"] ?? '';
    $timestamp = $_GET["timestamp"] ?? '';
    $nonce = $_GET["nonce"] ?? '';
    $tmpArr = array($wechat_token, $timestamp, $nonce);
    sort($tmpArr, SORT_STRING);
    if (sha1(implode($tmpArr)) == $signature) {
        die($_GET['echostr']);
    }
    die('Invalid signature');
}
// 2. 接收微信推送的 XML 数据
$postStr = file_get_contents("php://input");
if (!empty($postStr)) {
    libxml_disable_entity_loader(true);
    $postObj = simplexml_load_string($postStr, 'SimpleXMLElement', LIBXML_NOCDATA);
    $fromUsername = (string)$postObj->FromUserName; // 用户的 OpenID
    $toUsername = (string)$postObj->ToUserName;
    $msgType = (string)$postObj->MsgType;
    $event = (string)$postObj->Event;
    $eventKey = (string)$postObj->EventKey;

    // 监听扫描带参二维码事件 (已关注扫描为 SCAN，未关注扫描为 subscribe)
    if ($msgType == "event" && ($event == "subscribe" || $event == "SCAN")) {
        // subscribe 时的 eventKey 会带有 qrscene_ 前缀
        $scene_str = str_replace('qrscene_', '', $eventKey);
        
        if (!empty($scene_str)) {
            // 3. 处理用户注册/登录逻辑
            $stmt = $pdo->prepare("SELECT * FROM users WHERE wx_uid = ? LIMIT 1");
            $stmt->execute([$fromUsername]);
            $user = $stmt->fetch();

            if (!$user) {
                // 如果是新用户，自动注册 (同 social_login.php 逻辑)
                $username = 'wx_' . substr(md5($fromUsername), 0, 8);
                $email = $username . '@social.local';
                $nickname = '微信用户_' . rand(1000, 9999);
                $avatar = 'https://api.dicebear.com/7.x/avataaars/svg?seed=' . urlencode($nickname);
                $password = password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT);
                
                $insertStmt = $pdo->prepare("INSERT INTO users (username, email, nickname, password, avatar, wx_uid, points, level) VALUES (?, ?, ?, ?, ?, ?, 0, 1)");
                $insertStmt->execute([$username, $email, $nickname, $password, $avatar, $fromUsername]);
                $user_id = $pdo->lastInsertId();
            } else {
                $user_id = $user['id'];
            }

            // 4. 将扫码状态标记为成功，并绑定用户ID
            $updateStmt = $pdo->prepare("UPDATE wx_qr_tasks SET status = 'success', user_id = ? WHERE scene_str = ?");
            $updateStmt->execute([$user_id, $scene_str]);

            // 回复一条友好的欢迎语
            $content = "登录成功！欢迎回来。请在网页端继续操作。";
            $textTpl = "<xml>
                            <ToUserName><![CDATA[%s]]></ToUserName>
                            <FromUserName><![CDATA[%s]]></FromUserName>
                            <CreateTime>%s</CreateTime>
                            <MsgType><![CDATA[text]]></MsgType>
                            <Content><![CDATA[%s]]></Content>
                        </xml>";
            $resultStr = sprintf($textTpl, $fromUsername, $toUsername, time(), $content);
            echo $resultStr;
            exit;
        }
    }
}
echo "success";