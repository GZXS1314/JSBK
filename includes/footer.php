<?php
// includes/footer.php
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
?>

<!-- === Prism.js 代码高亮 (换成了 BootCDN 以解决 CSP 报错) === -->
<link href="../pages/assets/css/prism-tomorrow.min.css" rel="stylesheet">
<script src="../pages/assets/js/prism.min.js"></script>
<script src="../pages/assets/js/prism-autoloader.min.js"></script>

<footer class="minimal-footer">
    <div class="container footer-inner">
        <div class="f-left">
            <span class="f-logo"><?= conf('site_name', 'BLOG.') ?></span>
            <span class="f-divider">/</span>
            <span class="f-copy">© <?= date('Y') ?> All Rights Reserved.</span>
        </div>

        <div class="f-right">
            <?php if($icp = conf('site_icp')): ?>
                <a href="https://beian.miit.gov.cn/" target="_blank" class="f-icp"><?= $icp ?></a>
            <?php else: ?>
                <span class="f-icp">未备案</span>
            <?php endif; ?>
            
            <a href="pages/admin_login.php" class="f-admin-btn" title="管理后台"><i class="fa-solid fa-lock"></i></a>
        </div>
    </div>
</footer>
<?php
// 获取全局配置 (确保 footer 在 header 之后加载能获取到变量)
global $site_config;
$custom_js = $site_config['custom_js'] ?? '';
if (!empty($custom_js)): 
?>
<!-- 自定义 JS -->
<script>
    <?= $custom_js ?>
</script>
<?php endif; ?>
