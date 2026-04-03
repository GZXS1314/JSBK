<?php
/**
 * BKCS 后台 - 外观主题管理
 */
require_once '../includes/config.php';

// 【引入后台核心框架头部】
require_once 'header.php'; 

$pdo = getDB(); // 确保数据库连接已建立

// ==========================================
// 1. 处理启用主题的 POST 请求
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'activate') {
    $new_theme = preg_replace('/[^a-zA-Z0-9_-]/', '', $_POST['theme_alias']); // 安全过滤
    
    // 检查主题目录是否真的存在
    if (is_dir("../themes/" . $new_theme)) {
        // 更新数据库设置 (利用 MySQL 特性：存在则更新，不存在则插入)
        $stmt = $pdo->prepare("INSERT INTO settings (key_name, value) VALUES ('active_theme', ?) ON DUPLICATE KEY UPDATE value = ?");
        $stmt->execute([$new_theme, $new_theme]);
        
        // 【关键】清理 Redis/文件缓存，让前端立即生效
        require_once '../includes/redis_helper.php';
        Cache::delete('site_settings'); 
        
        $success_msg = "主题 [{$new_theme}] 已成功启用！前端页面已切换。";
    } else {
        $error_msg = "启用失败：主题目录不存在！";
    }
}

// ==========================================
// 2. 获取当前正在使用的主题
// ==========================================
$stmt = $pdo->query("SELECT value FROM settings WHERE key_name = 'active_theme'");
$active_theme = $stmt->fetchColumn();
if (!$active_theme || !is_dir("../themes/" . $active_theme)) {
    $active_theme = 'default'; // 保底防御
}

// ==========================================
// 3. 扫描本地主题目录
// ==========================================
$themes = [];
$theme_dirs = glob('../themes/*', GLOB_ONLYDIR); // 只扫描文件夹

foreach ($theme_dirs as $dir) {
    $alias = basename($dir);
    $info_file = $dir . '/info.json';
    
    if (file_exists($info_file)) {
        // 解析 info.json
        $info = json_decode(file_get_contents($info_file), true);
        
        // 容错处理：如果 json 格式错误或缺失字段
        $themes[] = [
            'alias'       => $alias,
            'name'        => $info['name'] ?? $alias,
            'version'     => $info['version'] ?? '1.0.0',
            'author'      => $info['author'] ?? '未知作者',
            'description' => $info['description'] ?? '暂无描述',
            // 自动检测预览图，没有则用占位图
            'preview'     => file_exists($dir . '/preview.png') ? "../themes/{$alias}/preview.png" : "https://placehold.co/600x400/f8f9fa/a18cd1?text=" . urlencode('暂无预览图')
        ];
    }
}
?>

<style>
    .page-header {
        margin-bottom: 24px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .page-title {
        font-size: 24px;
        font-weight: 700;
        color: var(--text-main);
        display: flex;
        align-items: center;
        gap: 12px;
    }
    .page-desc {
        color: var(--text-secondary);
        font-size: 14px;
        margin-top: 8px;
    }

    /* 独立的主题卡片样式 */
    .theme-grid { 
        display: grid; 
        grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); 
        gap: 25px; 
    }
    
    .theme-card { 
        background: #fff; 
        border-radius: var(--radius-card); 
        overflow: hidden; 
        box-shadow: var(--card-shadow); 
        border: 2px solid transparent; 
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); 
        display: flex; 
        flex-direction: column; 
    }
    .theme-card:hover { 
        transform: translateY(-5px); 
        box-shadow: 0 10px 25px rgba(0,0,0,0.1); 
    }
    .theme-card.is-active { 
        border-color: var(--primary); 
        box-shadow: 0 0 0 4px var(--primary-bg); 
    }
    
    .theme-preview { 
        width: 100%; 
        height: 200px; 
        object-fit: cover; 
        border-bottom: 1px solid rgba(0,0,0,0.05); 
        background: #f8f9fa; 
    }
    
    .theme-info { 
        padding: 20px; 
        flex: 1; 
        display: flex; 
        flex-direction: column; 
    }
    .theme-header { 
        display: flex; 
        justify-content: space-between; 
        align-items: center; 
        margin-bottom: 10px; 
    }
    .theme-title { 
        font-size: 18px; 
        font-weight: 700; 
        margin: 0; 
        color: var(--text-main); 
    }
    .theme-version { 
        font-size: 12px; 
        background: #f1f5f9; 
        padding: 2px 8px; 
        border-radius: 10px; 
        color: var(--text-secondary); 
        font-weight: 600;
    }
    .theme-desc { 
        font-size: 13px; 
        color: var(--text-secondary); 
        line-height: 1.6; 
        margin-bottom: 15px; 
        flex: 1; 
    }
    .theme-meta { 
        font-size: 12px; 
        color: var(--text-tertiary); 
        margin-bottom: 15px; 
        display: flex;
        align-items: center;
        gap: 15px;
    }
    
    .theme-actions { 
        display: flex; 
        gap: 10px; 
        border-top: 1px solid rgba(0,0,0,0.05); 
        padding-top: 15px; 
    }
    
    .btn-action { 
        flex: 1; 
        padding: 10px; 
        border: none; 
        border-radius: 8px; 
        cursor: pointer; 
        font-size: 13px; 
        font-weight: 600; 
        transition: 0.2s; 
        text-align: center;
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 6px;
    }
    .btn-active { 
        background: #f1f5f9; 
        color: var(--text-tertiary); 
        cursor: default; 
    }
    .btn-activate { 
        background: var(--text-main); 
        color: #fff; 
    }
    .btn-activate:hover { 
        background: #000; 
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }
    .btn-config { 
        background: #fff; 
        color: var(--text-main); 
        border: 1px solid rgba(0,0,0,0.1); 
        flex: 0 0 auto; 
        width: 40px; 
    }
    .btn-config:hover { 
        background: #f8fafc; 
        color: var(--primary);
        border-color: var(--primary);
    }
    
    .alert { 
        padding: 16px 20px; 
        border-radius: 12px; 
        margin-bottom: 24px; 
        font-size: 14px;
        font-weight: 500; 
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .alert-success { background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; }
    .alert-error { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }
</style>

<div class="page-header">
    <div>
        <div class="page-title"><i class="fa-solid fa-wand-magic-sparkles" style="color: var(--primary);"></i> 外观主题管理</div>
        <div class="page-desc">在这里管理和切换网站的前端皮肤。将下载的主题包解压到 <code>themes/</code> 目录即可自动识别。</div>
    </div>
    <button class="tool-btn" style="width: auto; padding: 0 16px; display: flex; gap: 8px; background: var(--primary); color: white; border: none;" onclick="alert('主题商城云端接口开发中...')">
        <i class="fa-solid fa-cloud-arrow-down"></i> 获取更多主题
    </button>
</div>

<?php if (!empty($success_msg)): ?>
    <div class="alert alert-success"><i class="fa-solid fa-circle-check"></i> <?= $success_msg ?></div>
<?php endif; ?>
<?php if (!empty($error_msg)): ?>
    <div class="alert alert-error"><i class="fa-solid fa-circle-exclamation"></i> <?= $error_msg ?></div>
<?php endif; ?>

<div class="theme-grid">
    <?php foreach ($themes as $theme): ?>
        <?php $isActive = ($theme['alias'] === $active_theme); ?>
        <div class="theme-card <?= $isActive ? 'is-active' : '' ?>">
            <img src="<?= htmlspecialchars($theme['preview']) ?>" class="theme-preview" alt="预览图">
            <div class="theme-info">
                <div class="theme-header">
                    <h3 class="theme-title"><?= htmlspecialchars($theme['name']) ?></h3>
                    <span class="theme-version">v<?= htmlspecialchars($theme['version']) ?></span>
                </div>
                
                <div class="theme-meta">
                    <span><i class="fa-solid fa-user-pen"></i> <?= htmlspecialchars($theme['author']) ?></span>
                    <span><i class="fa-solid fa-folder"></i> <?= htmlspecialchars($theme['alias']) ?></span>
                </div>
                
                <div class="theme-desc"><?= htmlspecialchars($theme['description']) ?></div>
                
                <div class="theme-actions">
                    <?php if ($isActive): ?>
                        <button class="btn-action btn-active" disabled><i class="fa-solid fa-check"></i> 正在使用</button>
                    <?php else: ?>
                        <form method="POST" style="flex: 1; display: flex;">
                            <input type="hidden" name="action" value="activate">
                            <input type="hidden" name="theme_alias" value="<?= htmlspecialchars($theme['alias']) ?>">
                            <button type="submit" class="btn-action btn-activate" onclick="return confirm('确定要启用此主题吗？前端页面将立即切换。')"><i class="fa-solid fa-bolt"></i> 启用</button>
                        </form>
                    <?php endif; ?>
                    
                    <a href="theme_options.php?theme=<?= urlencode($theme['alias']) ?>" class="btn-action btn-config" title="主题独立设置">
                        <i class="fa-solid fa-sliders"></i>
                    </a>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

</div> </main> </div> </body>
</html>