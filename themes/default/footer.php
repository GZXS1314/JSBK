<?php
// themes/default/footer.php
/**
 * default 主题的全局底部视图文件
 **/
?>

<link href="<?= THEME_URL ?>/assets/css/prism-tomorrow.min.css" rel="stylesheet">
<script src="<?= THEME_URL ?>/assets/js/prism.min.js"></script>
<script src="<?= THEME_URL ?>/assets/js/prism-autoloader.min.js"></script>

<style>
    /* =========================================================
        超现代悬浮毛玻璃页脚 (Floating Glassmorphism Footer)
    ========================================================= */
    .glass-footer-wrapper {
        width: 100%;
        /*padding: 0 20px;*/
        margin: 60px 0 40px 0;
        box-sizing: border-box;
        position: relative;
        z-index: 10;
        clear: both;
    }

    .glass-footer-card {
        /* 与头部严格对齐的核心宽度 */
        max-width: var(--content-width, 1280px);
        margin: 0 auto;
        padding: 22px 35px;
        
        /* 极致的毛玻璃拟态效果 */
        background: var(--card-bg, rgba(255, 255, 255, 0.65));
        backdrop-filter: blur(25px) saturate(180%);
        -webkit-backdrop-filter: blur(25px) saturate(180%);
        
        /* 拟态高光边框与立体阴影 */
        border: 1px solid rgba(255, 255, 255, 0.4);
        border-radius: 24px;
        box-shadow: 
            0 15px 35px -5px rgba(0, 0, 0, 0.05), 
            inset 0 1px 0 rgba(255, 255, 255, 0.6);
            
        display: flex;
        justify-content: space-between;
        align-items: center;
        transition: all 0.4s cubic-bezier(0.2, 1, 0.3, 1);
        font-family: "PingFang SC", "Microsoft YaHei", -apple-system, sans-serif;
    }

    .glass-footer-card:hover {
        transform: translateY(-3px);
        box-shadow: 
            0 20px 40px -5px rgba(0, 0, 0, 0.08), 
            inset 0 1px 0 rgba(255, 255, 255, 0.8);
    }

    /* === 左侧：Logo 与版权 === */
    .gf-left {
        display: flex;
        align-items: center;
        gap: 16px;
    }

    .gf-logo {
        font-size: 16px;
        font-weight: 900;
        letter-spacing: -0.5px;
        color: var(--text-main, #1a1a1a);
    }

    .gf-divider {
        width: 4px;
        height: 4px;
        background: var(--text-sub, #94a3b8);
        border-radius: 50%;
        opacity: 0.5;
    }

    .gf-text {
        font-size: 13px;
        color: var(--text-sub, #64748b);
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    /* === 右侧：功能与备案 === */
    .gf-right {
        display: flex;
        align-items: center;
        gap: 18px;
    }

    .gf-link {
        font-size: 13px;
        color: var(--text-sub, #64748b);
        text-decoration: none;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 6px;
        transition: color 0.3s ease;
    }

    .gf-link:hover {
        color: var(--text-main, #1a1a1a);
    }

    .gf-admin {
        width: 36px;
        height: 36px;
        border-radius: 12px;
        background: rgba(150, 150, 150, 0.1);
        color: var(--text-sub, #64748b);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 14px;
        text-decoration: none;
        transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
        border: 1px solid rgba(255, 255, 255, 0.2);
    }

    .gf-admin:hover {
        background: var(--text-main, #1a1a1a);
        color: var(--card-bg, #fff);
        transform: rotate(8deg) scale(1.1);
        border-color: transparent;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
    }

    /* 移动端适配 */
    @media (max-width: 768px) {
        .glass-footer-wrapper {
            margin: 40px 0 20px 0;
            padding: 0 15px;
        }
        .glass-footer-card {
            flex-direction: column;
            justify-content: center;
            padding: 20px;
            gap: 15px;
            border-radius: 20px;
        }
        .gf-left {
            flex-direction: column;
            gap: 8px;
            text-align: center;
        }
        .gf-divider {
            display: none;
        }
        .gf-right {
            margin-top: 5px;
        }
    }
</style>

<div class="glass-footer-wrapper">
    <footer class="glass-footer-card">
        <div class="gf-left">
            <span class="gf-logo"><?= conf('site_name', 'JS·BLOG') ?></span>
            <span class="gf-divider"></span>
            <span class="gf-text">
                © <?= date('Y') ?> Crafted with <i class="fa-solid fa-heart fa-beat" style="color: #ef4444; --fa-animation-duration: 2s;"></i> by <?= conf('author_name', 'Author') ?>
            </span>
        </div>

        <div class="gf-right">
            <?php if($icp = conf('site_icp')): ?>
                <a href="https://beian.miit.gov.cn/" target="_blank" class="gf-link">
                    <i class="fa-solid fa-shield-halved"></i> <?= $icp ?>
                </a>
            <?php endif; ?>
            
            <a href="/admin-login" class="gf-admin" title="系统终端">
                <i class="fa-solid fa-terminal"></i>
            </a>
        </div>
    </footer>
</div>

<?php
// 获取全局配置并注入自定义 JS
global $site_config;
$custom_js = $site_config['custom_js'] ?? '';
if (!empty($custom_js)): 
?>
<script>
    <?= $custom_js ?>
</script>
<?php endif; ?>