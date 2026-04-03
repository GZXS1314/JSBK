<?php
// /themes/default/user/login.php (纯视图模板)
// 注意：不要在这里写复杂的数据库查询，也不需要 require config.php，外层已经加载过了。
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>用户登录 - <?= getThemeOption('site_name') ?></title>
    <link rel="stylesheet" href="../themes/<?= $active_theme ?>/assets/css/style.css">
</head>
<body>

<div class="login-container">
    <h2>欢迎回来</h2>
    
    <?php if (!empty($error_msg)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error_msg) ?></div>
    <?php endif; ?>

    <form method="POST" action="">
        <div class="form-group">
            <label>用户名</label>
            <input type="text" name="username" required>
        </div>
        <div class="form-group">
            <label>密码</label>
            <input type="password" name="password" required>
        </div>
        <button type="submit" class="btn btn-primary">登录</button>
    </form>
</div>

</body>
</html>