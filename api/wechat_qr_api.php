<?php
// api/wechat_qr_api.php
// 关闭可能破坏 JSON 结构的 HTML 报错输出
ini_set('display_errors', 0);
ini_set('session.cookie_path', '/');
ini_set('session.cookie_secure', '1');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');

require_once '../includes/config.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }

header('Content-Type: application/json; charset=utf-8');

try {
    $pdo = getDB();
    $action = $_GET['action'] ?? '';

    // 辅助方法：获取单个配置
    function getConf($key, $pdo) {
        $stmt = $pdo->prepare("SELECT value FROM settings WHERE key_name = ?");
        $stmt->execute([$key]);
        return trim($stmt->fetchColumn() ?? '');
    }

    // 封装 CURL 请求
    function curl_request($url, $postData = null) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        
        if ($postData !== null) {
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($postData) ? json_encode($postData, JSON_UNESCAPED_UNICODE) : $postData);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        }
        
        $data = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);
        
        if ($err) throw new Exception("网络请求失败: " . $err);
        return $data;
    }

    // 获取公众号 Access Token (兼容中控代理)
    function getMpAccessToken($pdo) {
        $stmt = $pdo->prepare("SELECT key_name, value FROM settings WHERE key_name IN ('wx_mp_token', 'wx_mp_token_expire')");
        $stmt->execute();
        $cache = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        if (!empty($cache['wx_mp_token']) && !empty($cache['wx_mp_token_expire']) && time() < intval($cache['wx_mp_token_expire'])) {
            return $cache['wx_mp_token'];
        }

        $appid = getConf('official_mp_wx_appid', $pdo);
        $secret = getConf('official_mp_wx_appsecret', $pdo);
        if(empty($appid) || empty($secret)) { throw new Exception("后台未配置公众号的 AppID 或 AppSecret"); }

        $proxy_on = getConf('wx_proxy_enabled', $pdo) == '1';
        $proxy_url = rtrim(getConf('wx_proxy_api_url', $pdo), '/');
        
        if ($proxy_on && !empty($proxy_url)) {
            $url = "{$proxy_url}/token?grant_type=client_credential&appid={$appid}&secret={$secret}";
        } else {
            $url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid={$appid}&secret={$secret}";
        }

        $raw = curl_request($url);
        $res = json_decode($raw, true);
        
        if (isset($res['access_token'])) {
            $token = $res['access_token'];
            $expire = time() + 7000;
            $pdo->prepare("INSERT INTO settings (key_name, value) VALUES ('wx_mp_token', ?), ('wx_mp_token_expire', ?) ON DUPLICATE KEY UPDATE value = VALUES(value)")->execute([$token, $expire]);
            return $token;
        }
        
        throw new Exception("Token获取失败，接口返回: " . $raw);
    }

    // --- 动作 1：生成二维码 ---
    if ($action === 'get_qr') {
        $token = getMpAccessToken($pdo);
        $scene_str = 'login_' . md5(uniqid(mt_rand(), true));
        
        // 生成二维码 Ticket (此步通常直连官方接口即可)
        $url = "https://api.weixin.qq.com/cgi-bin/qrcode/create?access_token={$token}";
        $data = [
            "expire_seconds" => 300,
            "action_name" => "QR_STR_SCENE",
            "action_info" => ["scene" => ["scene_str" => $scene_str]]
        ];
        
        $raw = curl_request($url, $data);
        $res = json_decode($raw, true);

        if (isset($res['ticket'])) {
            $expire_at = date('Y-m-d H:i:s', time() + 300);
            // 写入数据库 (如果没有创建表，这里会触发 PDOException 被底部的 catch 捕获)
            $pdo->prepare("INSERT INTO wx_qr_tasks (scene_str, expire_at) VALUES (?, ?)")->execute([$scene_str, $expire_at]);
            
            echo json_encode([
                'success' => true,
                'qr_url' => "https://mp.weixin.qq.com/cgi-bin/showqrcode?ticket=" . urlencode($res['ticket']),
                'scene_str' => $scene_str
            ]);
        } else {
            throw new Exception("二维码生成失败，接口返回: " . $raw);
        }
        exit;
    }

    // --- 动作 2：轮询状态 ---
    if ($action === 'poll') {
        $scene_str = $_GET['scene_str'] ?? '';
        if (empty($scene_str)) { throw new Exception("缺少参数"); }

        // 清理过期数据
        $pdo->exec("DELETE FROM wx_qr_tasks WHERE expire_at < NOW()");

        $stmt = $pdo->prepare("SELECT status, user_id FROM wx_qr_tasks WHERE scene_str = ?");
        $stmt->execute([$scene_str]);
        $task = $stmt->fetch();

        if (!$task) {
            echo json_encode(['success' => false, 'status' => 'expired']); exit;
        }

        if ($task['status'] === 'success' && $task['user_id'] > 0) {
            $userStmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $userStmt->execute([$task['user_id']]);
            $user = $userStmt->fetch();

            if ($user && $user['is_banned'] == 0) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['nickname'] = $user['nickname'];
                $_SESSION['avatar'] = $user['avatar'];
                
                // 每日登录积分奖励
                $points_login = (int)getConf('points_login', $pdo);
                if ($points_login > 0) {
                    $checkStmt = $pdo->prepare("SELECT id FROM points_log WHERE user_id = ? AND action = 'daily_login' AND DATE(created_at) = CURDATE()");
                    $checkStmt->execute([$user['id']]);
                    if (!$checkStmt->fetch()) {
                        $pdo->prepare("UPDATE users SET points = points + ? WHERE id = ?")->execute([$points_login, $user['id']]);
                        $pdo->prepare("INSERT INTO points_log (user_id, action, points_change, description) VALUES (?, 'daily_login', ?, '每日首次登录奖励')")->execute([$user['id'], $points_login]);
                    }
                }

                session_write_close(); 
                $pdo->prepare("DELETE FROM wx_qr_tasks WHERE scene_str = ?")->execute([$scene_str]);
                echo json_encode(['success' => true, 'status' => 'success']);
            } else {
                echo json_encode(['success' => false, 'status' => 'banned', 'msg' => '账号被封禁']);
            }
        } else {
            echo json_encode(['success' => true, 'status' => 'pending']);
        }
        exit;
    }

} catch (PDOException $e) {
    // 捕获数据库错误 (如忘记创建表)
    echo json_encode(['success' => false, 'msg' => '数据库错误，请检查表结构: ' . $e->getMessage()]);
} catch (Exception $e) {
    // 捕获其他业务错误
    echo json_encode(['success' => false, 'msg' => $e->getMessage()]);
}