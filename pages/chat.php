<?php
require_once 'includes/config.php';
$pdo = getDB();
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
// 加载配置判断开关
$stmt_set = $pdo->query("SELECT * FROM settings WHERE key_name = 'enable_chatroom'");
$enable = $stmt_set->fetch();
if (!$enable || $enable['value'] != '1') {
    die('<div style="text-align:center;padding:50px;">聊天室功能未开启 <a href="index.php">返回首页</a></div>');
}

// 用户信息
$is_user_login = isset($_SESSION['user_id']);
$current_user_id = $_SESSION['user_id'] ?? 0;
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>在线聊天室</title>
    <link href="https://cdn.bootcdn.net/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- 引入外部 CSS 文件 -->
    <link rel="stylesheet" href="/pages/assets/css/chat.css?v=<?php echo time(); ?>">
</head>
<body>

    <div class="chat-navbar">
        <a href="index.php" class="back-btn"><i class="fa-solid fa-chevron-left"></i> 返回</a>
        <div class="chat-title">在线摸鱼室</div>
        <div class="placeholder"></div>
    </div>

    <div class="chat-container" id="chatContainer">
        <div style="text-align:center; color:#999; font-size:12px; margin-top:20px;">加载历史消息...</div>
    </div>

    <div class="input-area">
        <button class="emoji-btn" onclick="toggleEmoji()"><i class="fa-regular fa-face-smile"></i></button>
        <input type="text" class="chat-input" id="chatInput" placeholder="<?= $is_user_login ? '发消息...' : '请先登录' ?>" <?= $is_user_login ? '' : 'disabled' ?>>
        <button class="send-btn" id="sendBtn" onclick="sendMsg()">发送</button>
    </div>
    
    <div class="emoji-panel" id="emojiPanel"></div>

<!-- 引入外部 JavaScript 文件 -->
<!-- 将 PHP 变量传递给 JS -->
<script>
    const chatConfig = {
        currentUserId: <?= json_encode($current_user_id) ?>,
        isLogin: <?= json_encode($is_user_login) ?>
    };
</script>
<script src="/pages/assets/js/chat.js?v=<?php echo time(); ?>"></script>

</body>
</html>
