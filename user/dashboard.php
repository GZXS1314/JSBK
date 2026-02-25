<?php
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
// 1. 开启错误提示 (调试完成后可注释掉)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 2. 禁止浏览器缓存
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

require_once '../includes/config.php';

// 3. 严格的登录检查
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$pdo = getDB();
$user_id = $_SESSION['user_id'];
$msg = '';
$msg_type = '';

// --- 【关键修复】在此处手动加载网站配置，防止 conf() 报错 ---
$stmt_set = $pdo->query("SELECT * FROM settings");
$site_config = [];
while ($row = $stmt_set->fetch()) {
    $site_config[$row['key_name']] = $row['value'];
}

// 定义 conf 函数
if (!function_exists('conf')) {
    function conf($key, $default = '') {
        global $site_config;
        return isset($site_config[$key]) && $site_config[$key] !== '' ? htmlspecialchars($site_config[$key]) : $default;
    }
}
// -----------------------------------------------------------

// 4. 处理资料更新
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nickname = trim($_POST['nickname']);
    $password = $_POST['password'];
    $avatar = trim($_POST['avatar']); 

    if (empty($nickname)) {
        $msg = "昵称不能为空";
        $msg_type = 'error';
    } else {
        try {
            if (!empty($password)) {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET nickname = ?, avatar = ?, password = ? WHERE id = ?");
                $stmt->execute([$nickname, $avatar, $hash, $user_id]);
            } else {
                $stmt = $pdo->prepare("UPDATE users SET nickname = ?, avatar = ? WHERE id = ?");
                $stmt->execute([$nickname, $avatar, $user_id]);
            }
            
            $_SESSION['nickname'] = $nickname;
            $_SESSION['avatar'] = $avatar;
            
            $msg = "个人资料已更新 ✨";
            $msg_type = 'success';
        } catch (Exception $e) {
            $msg = "保存失败：" . $e->getMessage();
            $msg_type = 'error';
        }
    }
}

// 5. 获取最新用户数据
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    if (!$user) {
        session_destroy();
        header("Location: login.php");
        exit;
    }
} catch (Exception $e) {
    die("数据库连接或查询错误: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>个人中心 | <?= conf('site_name', 'BLOG.') ?></title>
    <link href="https://cdn.bootcdn.net/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --bg-gradient: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            --glass-bg: rgba(255, 255, 255, 0.65);
            --glass-border: 1px solid rgba(255, 255, 255, 0.5);
            --glass-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.1);
            --glass-blur: blur(20px);
            --primary-color: #000;
            --text-main: #2d3436;
            --text-sub: #636e72;
            --danger: #ff7675;
            --success: #00b894;
            --radius: 24px;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; outline: none; -webkit-tap-highlight-color: transparent; }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, "PingFang SC", "Microsoft YaHei", sans-serif;
            background: #eef2f5;
            color: var(--text-main);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            position: relative;
            overflow-x: hidden;
        }

        .art-bg { position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: -1; pointer-events: none; overflow: hidden; }
        .orb { position: absolute; border-radius: 50%; filter: blur(80px); opacity: 0.6; animation: float 10s infinite ease-in-out alternate; }
        .orb-1 { top: -10%; left: -10%; width: 50vh; height: 50vh; background: #a8edea; }
        .orb-2 { bottom: -10%; right: -10%; width: 60vh; height: 60vh; background: #fed6e3; animation-delay: -5s; }
        @keyframes float { 0% { transform: translate(0, 0); } 100% { transform: translate(30px, 50px); } }

        .dashboard-container {
            width: 1000px; max-width: 95%; min-height: 600px;
            background: var(--glass-bg); backdrop-filter: var(--glass-blur); -webkit-backdrop-filter: var(--glass-blur);
            border: var(--glass-border); border-radius: var(--radius);
            box-shadow: var(--glass-shadow); display: flex; overflow: hidden;
            animation: slideUp 0.6s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .sidebar {
            width: 320px; background: rgba(255, 255, 255, 0.4);
            border-right: 1px solid rgba(255, 255, 255, 0.5); padding: 40px 30px;
            display: flex; flex-direction: column; align-items: center; text-align: center; position: relative;
        }

        .avatar-wrapper { position: relative; width: 120px; height: 120px; margin-bottom: 20px; }
        .avatar { width: 100%; height: 100%; border-radius: 50%; object-fit: cover; border: 4px solid #fff; box-shadow: 0 10px 20px rgba(0,0,0,0.1); transition: transform 0.3s; }
        .avatar-wrapper:hover .avatar { transform: scale(1.05) rotate(5deg); }

        .user-name { font-size: 24px; font-weight: 800; color: #1a1a1a; margin-bottom: 5px; }
        .user-id { font-size: 13px; color: var(--text-sub); background: rgba(0,0,0,0.05); padding: 4px 12px; border-radius: 20px; font-family: monospace; }
        
        .nav-menu { margin-top: 40px; width: 100%; display: flex; flex-direction: column; gap: 10px; }
        .nav-btn { display: flex; align-items: center; gap: 15px; padding: 15px 20px; border-radius: 16px; text-decoration: none; color: var(--text-sub); font-weight: 600; transition: all 0.3s; background: transparent; }
        .nav-btn i { width: 20px; text-align: center; }
        .nav-btn:hover { background: #fff; color: #000; box-shadow: 0 5px 15px rgba(0,0,0,0.05); transform: translateX(5px); }
        .nav-btn.active { background: #000; color: #fff; box-shadow: 0 5px 15px rgba(0,0,0,0.2); }
        .nav-btn.logout { color: var(--danger); margin-top: auto; }
        .nav-btn.logout:hover { background: #fff2f2; color: #d63031; }

        .content { flex: 1; padding: 50px; overflow-y: auto; position: relative; }
        .section-title { font-size: 20px; font-weight: 800; margin-bottom: 30px; display: flex; align-items: center; gap: 10px; }
        .section-title::before { content: ''; width: 6px; height: 24px; background: #000; border-radius: 3px; }

        .glass-form { display: flex; flex-direction: column; gap: 25px; max-width: 480px; }
        .form-group { position: relative; }
        .form-label { display: block; font-size: 14px; font-weight: 700; color: var(--text-sub); margin-bottom: 8px; margin-left: 5px; }
        .form-input { width: 100%; padding: 16px 20px; background: #fff; border: 2px solid transparent; border-radius: 16px; font-size: 15px; color: #333; transition: all 0.3s; box-shadow: 0 4px 10px rgba(0,0,0,0.03); }
        .form-input:focus { border-color: #000; box-shadow: 0 4px 20px rgba(0,0,0,0.1); transform: translateY(-2px); }
        .input-hint { font-size: 12px; color: #999; margin-top: 6px; margin-left: 5px; }

        .save-btn { margin-top: 10px; padding: 16px; background: #000; color: #fff; border: none; border-radius: 16px; font-size: 16px; font-weight: 700; cursor: pointer; transition: all 0.3s; box-shadow: 0 10px 20px rgba(0,0,0,0.15); }
        .save-btn:hover { transform: translateY(-3px); box-shadow: 0 15px 30px rgba(0,0,0,0.25); background: #2d3436; }
        .save-btn:active { transform: scale(0.98); }

        .msg-box { position: absolute; top: 20px; right: 20px; padding: 15px 25px; border-radius: 12px; font-weight: 600; font-size: 14px; animation: slideInRight 0.4s ease; box-shadow: 0 10px 30px rgba(0,0,0,0.1); display: flex; align-items: center; gap: 10px; z-index: 100; }
        .msg-success { background: #e3f9e5; color: #27ae60; border: 1px solid #cce5cf; }
        .msg-error { background: #ffeaea; color: #e74c3c; border: 1px solid #fadbd8; }

        .avatar-presets { display: flex; gap: 10px; margin-top: 10px; overflow-x: auto; padding-bottom: 5px; }
        .preset-img { width: 40px; height: 40px; border-radius: 50%; cursor: pointer; border: 2px solid transparent; transition: 0.2s; opacity: 0.7; }
        .preset-img:hover { opacity: 1; transform: scale(1.1); }
        
        @keyframes slideUp { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes slideInRight { from { opacity: 0; transform: translateX(20px); } to { opacity: 1; transform: translateX(0); } }

        @media (max-width: 768px) {
            body { align-items: flex-start; padding: 20px 10px; }
            .dashboard-container { flex-direction: column; min-height: auto; }
            .sidebar { width: 100%; border-right: none; border-bottom: 1px solid rgba(255,255,255,0.5); padding: 30px 20px; flex-direction: row; align-items: center; text-align: left; gap: 20px; }
            .avatar-wrapper { width: 70px; height: 70px; margin-bottom: 0; flex-shrink: 0; }
            .user-info-group { flex: 1; }
            .user-name { font-size: 20px; }
            .nav-menu { display: none; }
            .content { padding: 30px 20px; }
            .section-title { font-size: 18px; }
            .mobile-actions { margin-top: 30px; border-top: 1px solid rgba(0,0,0,0.05); padding-top: 20px; display: flex; gap: 15px; }
            .m-btn { flex: 1; padding: 12px; border-radius: 12px; text-align: center; font-weight: 600; font-size: 14px; text-decoration: none; }
            .m-home { background: #fff; color: #000; }
            .m-logout { background: #ffeaea; color: #ff4757; }
        }
    </style>
</head>
<body>
    <div class="art-bg">
        <div class="orb orb-1"></div>
        <div class="orb orb-2"></div>
    </div>

    <div class="dashboard-container">
        <!-- 侧边栏 -->
        <div class="sidebar">
            <div class="avatar-wrapper">
                <img src="<?= htmlspecialchars($user['avatar']) ?>" class="avatar" id="currentAvatar">
            </div>
            <div class="user-info-group">
                <h2 class="user-name"><?= htmlspecialchars($user['nickname'] ?: $user['username']) ?></h2>
                <span class="user-id">@<?= htmlspecialchars($user['username']) ?></span>
            </div>

            <!-- 桌面端菜单 -->
            <nav class="nav-menu">
                <a href="../index.php" class="nav-btn"><i class="fa-solid fa-house"></i> 返回首页</a>
                <a href="#" class="nav-btn active"><i class="fa-solid fa-user-gear"></i> 资料设置</a>
                <div style="flex:1"></div>
                <a href="../pages/logout.php" class="nav-btn logout"><i class="fa-solid fa-right-from-bracket"></i> 退出登录</a>
            </nav>
        </div>

        <!-- 主内容区 -->
        <div class="content">
            <?php if($msg): ?>
                <div class="msg-box <?= $msg_type == 'success' ? 'msg-success' : 'msg-error' ?>">
                    <i class="<?= $msg_type == 'success' ? 'fa-solid fa-check-circle' : 'fa-solid fa-circle-exclamation' ?>"></i>
                    <?= $msg ?>
                </div>
                <script>setTimeout(() => document.querySelector('.msg-box').style.display='none', 3000);</script>
            <?php endif; ?>

            <div class="section-title">编辑资料</div>

            <form method="POST" class="glass-form">
                <div class="form-group">
                    <label class="form-label">我的昵称</label>
                    <input type="text" name="nickname" class="form-input" value="<?= htmlspecialchars($user['nickname']) ?>" placeholder="给自己起个好听的名字" required>
                </div>

                <div class="form-group">
                    <label class="form-label">头像链接</label>
                    <input type="text" name="avatar" id="avatarInput" class="form-input" value="<?= htmlspecialchars($user['avatar']) ?>" placeholder="https://...">
                    <div class="input-hint">支持 URL 图片链接。推荐使用 DiceBear 随机头像：</div>
                    
                    <!-- 快速头像选择 -->
                    <div class="avatar-presets">
                        <?php 
                        $seeds = ['Felix', 'Aneka', 'Zoe', 'Jack', 'Sam', 'Milo'];
                        foreach($seeds as $seed): 
                            $url = "https://api.dicebear.com/7.x/avataaars/svg?seed=$seed";
                        ?>
                            <img src="<?= $url ?>" class="preset-img" onclick="selectAvatar('<?= $url ?>')">
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">修改密码</label>
                    <input type="password" name="password" class="form-input" placeholder="不修改请留空">
                </div>

                <button type="submit" class="save-btn">保存更改</button>

                <!-- 移动端显示的底部按钮 -->
                <div class="mobile-actions" style="display: none;">
                    <a href="../index.php" class="m-btn m-home">回首页</a>
                    <a href="../pages/logout.php" class="m-btn m-logout">退出登录</a>
                </div>
            </form>
        </div>
    </div>

    <script>
        function selectAvatar(url) {
            document.getElementById('avatarInput').value = url;
            document.getElementById('currentAvatar').src = url;
        }

        document.getElementById('avatarInput').addEventListener('input', function(e) {
            if(e.target.value.length > 10) {
                document.getElementById('currentAvatar').src = e.target.value;
            }
        });
        
        if(window.innerWidth <= 768) {
            document.querySelector('.mobile-actions').style.display = 'flex';
        }
    </script>
</body>
</html>
