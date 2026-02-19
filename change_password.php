<?php
require_once 'includes/config.php';
requireLogin();
// ... PHP 逻辑保持不变 ...
$pdo = getDB();
$msg = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // 省略具体的 PHP 处理代码，与之前一致，只展示 HTML 结构变化
    $old_pwd = $_POST['old_password'];
    $new_pwd = $_POST['new_password'];
    $confirm_pwd = $_POST['confirm_password'];
    $admin_id = $_SESSION['admin_id'];
    $stmt = $pdo->prepare("SELECT * FROM admin WHERE id = ?");
    $stmt->execute([$admin_id]);
    $admin = $stmt->fetch();
    if (password_verify($old_pwd, $admin['password'])) {
        if ($new_pwd === $confirm_pwd) {
            if (strlen($new_pwd) >= 6) {
                $hash = password_hash($new_pwd, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE admin SET password = ? WHERE id = ?");
                $stmt->execute([$hash, $admin_id]);
                $msg = '<div class="alert success"><i class="fa-solid fa-check-circle"></i> 密码修改成功！</div>';
            } else { $msg = '<div class="alert error"><i class="fa-solid fa-circle-exclamation"></i> 新密码至少6位</div>'; }
        } else { $msg = '<div class="alert error"><i class="fa-solid fa-circle-exclamation"></i> 两次密码不一致</div>'; }
    } else { $msg = '<div class="alert error"><i class="fa-solid fa-circle-exclamation"></i> 旧密码错误</div>'; }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>修改密码</title>
    <link href="https://cdn.bootcdn.net/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --container-width: 1200px; --sidebar-width: 240px; --gap: 24px;
            --bg-body: #f3f4f6; --bg-card: #ffffff; --text-main: #111827; --text-sub: #6b7280;
            --primary: #000000; --accent-red: #ef4444; --border: #e5e7eb;
            --radius: 16px; --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        }
        * { margin: 0; padding: 0; box-sizing: border-box; outline: none; }
        body { font-family: 'Inter', sans-serif; background-color: var(--bg-body); color: var(--text-main); display: flex; justify-content: center; padding: 40px 20px; min-height: 100vh; }
        .app-container { width: 100%; max-width: var(--container-width); display: flex; align-items: flex-start; gap: var(--gap); }

        /* 桌面侧边栏 */
        .sidebar-card { width: var(--sidebar-width); background: var(--bg-card); border-radius: var(--radius); box-shadow: var(--shadow); border: 1px solid var(--border); padding: 24px 16px; position: sticky; top: 40px; flex-shrink: 0; display: flex; flex-direction: column; min-height: calc(100vh - 80px); transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1); z-index: 1001; }
        .logo-area { padding-bottom: 24px; margin-bottom: 16px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; }
        .logo { font-size: 22px; font-weight: 800; text-align: center; width: 100%; }
        .close-sidebar-btn { display: none; cursor: pointer; font-size: 18px; color: #666; }
        
        .nav-menu { flex: 1; display: flex; flex-direction: column; gap: 4px; }
        .nav-item { display: flex; align-items: center; padding: 12px 16px; color: var(--text-sub); text-decoration: none; border-radius: 10px; font-size: 14px; font-weight: 500; transition: 0.2s; }
        .nav-item:hover { background: #f9fafb; color: var(--primary); }
        .nav-item.active { background: var(--primary); color: #fff; }
        .nav-item i { width: 24px; font-size: 16px; margin-right: 8px; }
        .sidebar-footer { margin-top: auto; padding-top: 20px; text-align: center; font-size: 12px; color: #9ca3af; border-top: 1px solid var(--border); }

        /* 内容 */
        .main-content { flex: 1; min-width: 0; display: flex; flex-direction: column; }
        .card { background: var(--bg-card); border-radius: var(--radius); box-shadow: var(--shadow); border: 1px solid var(--border); padding: 40px; }
        .page-header { margin-bottom: 30px; border-bottom: 1px solid #f3f4f6; padding-bottom: 20px; }
        .page-title { font-size: 20px; font-weight: 700; }
        .btn { display: inline-flex; align-items: center; justify-content: center; gap: 6px; padding: 12px 24px; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; border: none; background: var(--primary); color: #fff; width: 100%; }
        .form-control { width: 100%; padding: 12px 16px; border: 1px solid var(--border); border-radius: 8px; font-size: 14px; background: #fff; }
        .form-group { margin-bottom: 24px; }
        .input-label { display: block; font-size: 14px; font-weight: 600; margin-bottom: 8px; }
        .alert { padding: 12px 16px; border-radius: 8px; margin-bottom: 24px; font-size: 14px; display: flex; gap: 8px; }
        .alert.success { background: #ecfdf5; color: #047857; border: 1px solid #d1fae5; }
        .alert.error { background: #fef2f2; color: #991b1b; border: 1px solid #fee2e2; }

        .mobile-top-bar { display: none; }
        .menu-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; backdrop-filter: blur(3px); opacity: 0; transition: opacity 0.3s; }

        @media (max-width: 900px) {
            body { padding: 0; display: block; background: #fff; padding-top: 60px; }
            .app-container { gap: 0; flex-direction: column; }
            
            .mobile-top-bar { display: flex; justify-content: space-between; align-items: center; position: fixed; top: 0; left: 0; width: 100%; height: 60px; background: #fff; border-bottom: 1px solid #eee; padding: 0 20px; z-index: 999; }
            .mobile-logo { font-size: 20px; font-weight: 800; }
            .mobile-toggle-btn { font-size: 20px; cursor: pointer; }

            .sidebar-card { position: fixed; top: 0; left: 0; bottom: 0; width: 280px; height: 100vh; margin: 0; border-radius: 0; border: none; border-right: 1px solid #eee; transform: translateX(-100%); }
            .sidebar-card.active { transform: translateX(0); }
            
            .logo-area { display: flex; justify-content: space-between; } .logo { text-align: left; } .close-sidebar-btn { display: block; }
            .menu-overlay.active { display: block; opacity: 1; }
            .main-content { padding: 20px; width: 100%; } .card { padding: 20px; box-shadow: none; border: 1px solid #eee; }
        }
    </style>
</head>
<body>

<div class="mobile-top-bar">
    <div class="mobile-logo">ADMIN.</div>
    <div class="mobile-toggle-btn" onclick="openSidebar()"><i class="fa-solid fa-bars"></i></div>
</div>

<div class="menu-overlay" id="menuOverlay" onclick="closeSidebar()"></div>

<div class="app-container">
    <aside class="sidebar-card" id="sidebar">
        <div class="logo-area">
            <div class="logo">ADMIN.</div>
            <div class="close-sidebar-btn" onclick="closeSidebar()"><i class="fa-solid fa-xmark"></i></div>
        </div>
        <nav class="nav-menu">
            <!-- 注意路径指向 admin/ -->
            <a href="admin/index.php" class="nav-item"><i class="fa-solid fa-gauge"></i> 仪表盘</a>
            <a href="admin/categories.php" class="nav-item"><i class="fa-solid fa-layer-group"></i> 分类管理</a>
            <a href="admin/users.php" class="nav-item"><i class="fa-solid fa-users"></i> 用户管理</a>
            <a href="admin/settings.php" class="nav-item"><i class="fa-solid fa-gear"></i> 网站设置</a>
            <div style="height:1px; background:var(--border); margin: 10px 0;"></div>
            <a href="change_password.php" class="nav-item active"><i class="fa-solid fa-key"></i> 修改密码</a>
            <a href="logout.php" class="nav-item" style="color: var(--accent-red);"><i class="fa-solid fa-right-from-bracket"></i> 退出登录</a>
        </nav>
        <div class="sidebar-footer">© <?= date('Y') ?> Admin</div>
    </aside>

    <script>
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('menuOverlay');
        function openSidebar() { sidebar.classList.add('active'); overlay.classList.add('active'); }
        function closeSidebar() { sidebar.classList.remove('active'); overlay.classList.remove('active'); }
    </script>

    <main class="main-content">
        <div class="card" style="max-width: 600px; margin: 0 auto; width: 100%;">
            <div class="page-header"><div class="page-title">修改管理员密码</div></div>
            <?= $msg ?>
            <form method="POST">
                <div class="form-group"><label class="input-label">旧密码</label><input type="password" name="old_password" class="form-control" required></div>
                <div class="form-group"><label class="input-label">新密码</label><input type="password" name="new_password" class="form-control" required placeholder="至少 6 位"></div>
                <div class="form-group"><label class="input-label">确认新密码</label><input type="password" name="confirm_password" class="form-control" required></div>
                <button type="submit" class="btn"><i class="fa-solid fa-check"></i> 确认修改</button>
            </form>
        </div>
    </main>
</div>
</body>
</html>
