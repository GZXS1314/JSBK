<?php
/**
 * BKCS 主题全局设置中心 (集成基础设置、关于页面、友情链接、情侣空间、祝福留言)
 */
ob_start();
require_once '../includes/config.php';
requireLogin();
$pdo = getDB();
$redis = getRedis();

function getCosThumb($url, $width = 200) {
    if (empty($url)) return '';
    if (strpos($url, 'http') !== 0 || strpos($url, '?') !== false) return $url;
    return $url . '?imageMogr2/thumbnail/' . $width . 'x/interlace/1/q/80';
}
function clearLoveModuleCache() {
    global $redis;
    if (!$redis) return;
    $pipe = $redis->pipeline();
    $pipe->del('bkcs:love_wishes_list'); 
    $pipe->del('bkcs:love_settings');
    $pipe->del('bkcs:love_events_list');
    $pipe->del('bkcs:love:events');
    $pipe->del('bkcs:love:config');
    $pipe->execute();
}
$stmt_cos = $pdo->prepare("SELECT value FROM settings WHERE key_name = 'cos_enabled'");
$stmt_cos->execute();
$cosEnabled = $stmt_cos->fetchColumn(); 

$theme_alias = isset($_GET['theme']) ? preg_replace('/[^a-zA-Z0-9_-]/', '', $_GET['theme']) : 'default';
$options_file = "../themes/{$theme_alias}/options.json";
if (!file_exists($options_file)) die("该主题没有提供 options.json 配置文件，无需进行独立设置。");
$schema = json_decode(file_get_contents($options_file), true);
$db_setting_key = "theme_options_" . $theme_alias;

$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // [A] 处理情侣空间动态发布
    if (isset($_POST['action']) && $_POST['action'] === 'add_event') {
        $img_list = [];
        if (!empty($_FILES['local_images']['name'][0])) { 
            $upload_dir_rel = '../assets/uploads/';
            $upload_dir_web = '/assets/uploads/';
            if (!is_dir($upload_dir_rel)) @mkdir($upload_dir_rel, 0755, true);
            foreach($_FILES['local_images']['tmp_name'] as $key => $tmp_name) {
                if ($_FILES['local_images']['error'][$key] === UPLOAD_ERR_OK) {
                    $ext = strtolower(pathinfo($_FILES['local_images']['name'][$key], PATHINFO_EXTENSION));
                    if(in_array($ext, ['jpg','jpeg','png','gif','webp'])) {
                        $new_name = 'love_' . date('Ymd_His') . '_' . uniqid() . '.' . $ext;
                        $final_url = '';
                        if ($cosEnabled == '1') {
                            require_once '../includes/cos_helper.php';
                            $cosPath = 'uploads/' . date('Ym') . '/' . $new_name; 
                            $cosUrl = uploadToCOS($tmp_name, $cosPath);
                            if ($cosUrl) $final_url = $cosUrl;
                        }
                        if (empty($final_url)) {
                            if (move_uploaded_file($tmp_name, $upload_dir_rel . $new_name)) { $final_url = $upload_dir_web . $new_name; }
                        }
                        if (!empty($final_url)) $img_list[] = $final_url;
                    }
                }
            }
        } elseif (!empty($_POST['net_images'])) {
            $urls = explode("\n", str_replace("\r", "", $_POST['net_images']));
            foreach($urls as $u) { if($u = trim($u)) $img_list[] = $u; }
        }
        $img_json = !empty($img_list) ? json_encode(array_slice($img_list, 0, 9)) : '';
        $pdo->prepare("INSERT INTO love_events (title, description, event_date, image_url) VALUES (?, ?, ?, ?)")->execute([trim($_POST['title']), trim($_POST['description']), $_POST['event_date'], $img_json]);
        clearLoveModuleCache();
        ob_end_clean(); header("Location: theme_options.php?theme={$theme_alias}&success=event_added"); exit;
    }

    // [B] 处理祝福留言批量操作
    if (isset($_POST['action']) && $_POST['action'] === 'batch_ops_wishes') {
        $ids = $_POST['ids'] ?? [];
        $type = $_POST['batch_type'] ?? '';
        if (!empty($ids) && is_array($ids)) {
            $in  = str_repeat('?,', count($ids) - 1) . '?';
            if ($type == 'delete') {
                $pdo->prepare("DELETE FROM love_wishes WHERE id IN ($in)")->execute($ids);
            } elseif ($type == 'copy') {
                $stmt = $pdo->prepare("SELECT * FROM love_wishes WHERE id = ?");
                $insStmt = $pdo->prepare("INSERT INTO love_wishes (user_id, nickname, avatar, content) VALUES (?, ?, ?, ?)");
                foreach ($ids as $id) {
                    $stmt->execute([$id]);
                    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        $insStmt->execute([$row['user_id'], $row['nickname'], $row['avatar'], $row['content']]); 
                    }
                }
            }
            clearLoveModuleCache();
        }
        ob_end_clean(); header("Location: theme_options.php?theme={$theme_alias}&success=wishes_updated"); exit;
    }

    // [C] 处理祝福留言单条编辑
    if (isset($_POST['action']) && $_POST['action'] === 'edit_wish') {
        $id = intval($_POST['id']);
        $content = trim($_POST['content']);
        if ($id > 0) {
            $pdo->prepare("UPDATE love_wishes SET content = ? WHERE id = ?")->execute([$content, $id]);
            clearLoveModuleCache();
        }
        ob_end_clean(); header("Location: theme_options.php?theme={$theme_alias}&success=wishes_updated"); exit;
    }

    // [D] 处理主题全局设置保存
    if (isset($_POST['action']) && $_POST['action'] === 'save_options') {
        try {
            $posted_options = $_POST['options'] ?? [];
            foreach ($schema as $tab) { foreach ($tab['fields'] as $field) { if (isset($field['type']) && $field['type'] === 'switch') { $posted_options[$field['name']] = isset($posted_options[$field['name']]) ? '1' : '0'; } } }

            // 处理特殊数组结构
            if (isset($_POST['fl_name']) && isset($_POST['fl_url'])) {
                $fl_data = [];
                for($i=0; $i<count($_POST['fl_name']); $i++) { if(!empty($_POST['fl_name'][$i]) && !empty($_POST['fl_url'][$i])) { $fl_data[] = ['name' => $_POST['fl_name'][$i], 'url' => $_POST['fl_url'][$i]]; } }
                $posted_options['friend_links'] = $fl_data;
            }
            if (isset($_POST['about_avatar_tags'])) { $posted_options['about_avatar_tags'] = array_map('trim', $_POST['about_avatar_tags']); }
            if (isset($_POST['about_anime_covers'])) { $posted_options['about_anime_covers'] = array_values(array_filter(array_map('trim', $_POST['about_anime_covers']))); } else { $posted_options['about_anime_covers'] = []; }
            if (isset($_POST['career_event_title'])) {
                $events = [];
                for($i=0; $i<count($_POST['career_event_title']); $i++) {
                    if(trim($_POST['career_event_title'][$i]) === '') continue;
                    $events[] = ['title' => trim($_POST['career_event_title'][$i]),'icon' => trim($_POST['career_event_icon'][$i]),'color' => trim($_POST['career_event_color'][$i]),'left' => trim($_POST['career_event_left'][$i]),'width' => trim($_POST['career_event_width'][$i]),'top' => trim($_POST['career_event_top'][$i]),'pos' => trim($_POST['career_event_pos'][$i])];
                }
                $posted_options['about_career_events'] = $events;
            } else { $posted_options['about_career_events'] = []; }
            if (isset($_POST['career_axis_text'])) {
                $axis = [];
                for($i=0; $i<count($_POST['career_axis_text']); $i++) {
                    if(trim($_POST['career_axis_text'][$i]) === '') continue;
                    $axis[] = ['text' => trim($_POST['career_axis_text'][$i]), 'left' => trim($_POST['career_axis_left'][$i])];
                }
                $posted_options['about_career_axis'] = $axis;
            } else { $posted_options['about_career_axis'] = []; }

            $json_value = json_encode($posted_options, JSON_UNESCAPED_UNICODE);
            $stmt = $pdo->prepare("INSERT INTO settings (key_name, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = ?");
            $stmt->execute([$db_setting_key, $json_value, $json_value]);
            
            if ($redis) { $redis->del(CACHE_PREFIX . 'site_settings'); $redis->del(CACHE_PREFIX . 'love:config'); }
            
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                header('Content-Type: application/json'); echo json_encode(['success' => true, 'message' => '主题配置已保存成功']); exit;
            }
            $msg = '<div class="alert alert-success">主题配置已保存成功！</div>';
        } catch (Exception $e) {
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) { header('Content-Type: application/json'); echo json_encode(['success' => false, 'message' => $e->getMessage()]); exit; }
            $msg = '<div class="alert alert-error">保存失败: ' . $e->getMessage() . '</div>';
        }
    }
}

if (isset($_GET['action']) && $_GET['action'] == 'delete_event' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    if ($id > 0) { $pdo->prepare("DELETE FROM love_events WHERE id = ?")->execute([$id]); clearLoveModuleCache(); }
    ob_end_clean(); header("Location: theme_options.php?theme={$theme_alias}"); exit;
}
if (isset($_GET['success'])) {
    if ($_GET['success'] == 'event_added') $msg = '<div class="alert alert-success"><i class="fas fa-check-circle"></i> 动态发布成功！</div>';
    elseif ($_GET['success'] == 'wishes_updated') $msg = '<div class="alert alert-success"><i class="fas fa-check-circle"></i> 祝福留言更新成功！</div>';
}

// --- 读取数据 ---
$stmt = $pdo->prepare("SELECT value FROM settings WHERE key_name = ?");
$stmt->execute([$db_setting_key]);
$saved_data_row = $stmt->fetch();
$saved_options = $saved_data_row ? json_decode($saved_data_row['value'], true) : [];

$stmt_global = $pdo->query("SELECT key_name, value FROM settings WHERE key_name NOT LIKE 'theme_options_%'");
$global_settings = [];
while ($row = $stmt_global->fetch()) { $global_settings[$row['key_name']] = $row['value']; }

function getThemeOptionVal($name, $default = '', $saved_options = [], $global_settings = []) {
    if (isset($saved_options[$name])) return htmlspecialchars($saved_options[$name]);
    if (isset($global_settings[$name])) return htmlspecialchars($global_settings[$name]);
    return htmlspecialchars($default);
}

// 数组读取并兼容旧版
$friend_links = $saved_options['friend_links'] ?? json_decode($global_settings['friend_links'] ?? '[]', true);
$events = $pdo->query("SELECT * FROM love_events ORDER BY event_date DESC, id DESC")->fetchAll();
$wishes = $pdo->query("SELECT * FROM love_wishes ORDER BY created_at DESC")->fetchAll(); // 读取祝福留言
$avatar_tags = $saved_options['about_avatar_tags'] ?? json_decode($global_settings['about_avatar_tags'] ?? '[]', true) ?: [];
$avatar_tags = array_pad($avatar_tags, 8, '');
$anime_covers = $saved_options['about_anime_covers'] ?? json_decode($global_settings['about_anime_covers'] ?? '[]', true) ?: [];
$career_events = $saved_options['about_career_events'] ?? json_decode($global_settings['about_career_events'] ?? '[]', true) ?: [];
$career_axis = $saved_options['about_career_axis'] ?? json_decode($global_settings['about_career_axis'] ?? '[]', true) ?: [];

require 'header.php';
ob_end_flush();
?>

<link rel="stylesheet" href="assets/css/settings.css?v=<?= time() ?>">
<style>
    /* ========== 智能 12 栅格与核心 UI ========== */
    .smart-grid { display: grid; grid-template-columns: repeat(12, 1fr); gap: 20px; align-items: start; }
    .col-12 { grid-column: span 12; } .col-6 { grid-column: span 6; } .col-4 { grid-column: span 4; } .col-3 { grid-column: span 3; }
    @media (max-width: 768px) { .col-6, .col-4, .col-3 { grid-column: span 12; } }

    /* 通用基础样式 */
    .input-group { display: flex; align-items: stretch; width: 100%;}
    .input-group .form-control { border-top-right-radius: 0; border-bottom-right-radius: 0; border-right: none; flex: 1;}
    .btn-upload { background: #f8fafc; border: 1px solid #e2e8f0; padding: 0 16px; border-top-right-radius: 8px; border-bottom-right-radius: 8px; cursor: pointer; color: #64748b; transition: 0.2s; font-size: 13px; font-weight: 500; white-space: nowrap;}
    .btn-upload:hover { background: #e2e8f0; color: var(--primary); }
    .btn-icon { width: 36px; height: 36px; border-radius: 8px; border: none; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: 0.2s; font-size: 14px; flex-shrink:0;}
    .btn-icon.upload { background: #eff6ff; color: #3b82f6; border: 1px solid #bfdbfe; }
    .btn-icon.upload:hover { background: #3b82f6; color: #fff; }
    .btn-icon.delete { background: #fef2f2; color: #ef4444; border: 1px solid #fecaca; }
    .btn-icon.delete:hover { background: #ef4444; color: #fff; }

    /* 下拉菜单 (Dropdown) */
    .dropdown { position: relative; display: inline-block; }
    .dropdown-menu { display: none; position: absolute; right: 0; background-color: #fff; min-width: 140px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); z-index: 100; border: 1px solid #e2e8f0; border-radius: 8px; padding: 6px 0; top: 100%; margin-top: 5px; animation: fadeIn 0.2s ease; }
    .dropdown:hover .dropdown-menu { display: block; }
    .dropdown-menu::before { content: ""; position: absolute; top: -10px; left: 0; width: 100%; height: 10px; background: transparent; }
    .dropdown-item { padding: 10px 16px; text-decoration: none; display: flex; align-items: center; gap: 10px; color: #333; font-size: 13px; cursor: pointer; transition: 0.1s; background: none; border: none; width: 100%; text-align: left; }
    .dropdown-item:hover { background-color: #f8fafc; color: var(--primary); }
    .dropdown-item.danger { color: #ef4444; }
    .dropdown-item.danger:hover { background-color: #fef2f2; }
    .dropdown-divider { height: 1px; background: #f1f5f9; margin: 4px 0; }

    /* 动态 List (海报/时间轴) */
    .dynamic-list { display: flex; flex-direction: column; gap: 12px; }
    .dynamic-item { display: flex; align-items: center; gap: 15px; background: #f8fafc; padding: 12px; border-radius: 12px; border: 1px solid #e2e8f0; transition: 0.3s; }
    .dynamic-item:hover { background: #fff; box-shadow: 0 4px 15px rgba(0,0,0,0.05); border-color: #cbd5e1; }
    .item-preview { width: 50px; height: 75px; object-fit: cover; border-radius: 6px; background: #e2e8f0; border: 1px solid #cbd5e1; flex-shrink: 0; }
    
    /* 复杂组件：履历节点 */
    .career-item { background: #fff; padding: 15px; border-radius: 12px; border: 1px solid #e2e8f0; margin-bottom: 15px; box-shadow: 0 2px 5px rgba(0,0,0,0.02); }
    .career-item-header { display: flex; justify-content: space-between; font-weight: bold; margin-bottom: 10px; color: #1e293b; font-size: 14px;}
    .c-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; }
    .c-grid .form-group { margin-bottom: 0; }

    /* 关于页面头像标签 */
    .tag-group-title { font-size: 13px; font-weight: 600; color: #64748b; margin-bottom: 10px; padding-bottom: 5px; border-bottom: 1px dashed #e2e8f0;}
    .input-prefix { background: #f1f5f9; border: 1px solid #d1d5db; border-right: none; padding: 0 12px; display: flex; align-items: center; justify-content: center; font-size: 12px; color: #64748b; font-weight: bold; border-top-left-radius: 8px; border-bottom-left-radius: 8px;}
    .prefixed-input { border-top-left-radius: 0; border-bottom-left-radius: 0; }

    /* Icon Picker 弹窗 */
    .icon-modal-overlay { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 99999; display: none; align-items: center; justify-content: center; backdrop-filter: blur(4px); opacity: 0; transition: opacity 0.3s; }
    .icon-modal-overlay.active { display: flex !important; opacity: 1; }
    .icon-modal-box { background: #fff; width: 500px; max-width: 90%; margin: auto; border-radius: 16px; box-shadow: 0 20px 40px rgba(0,0,0,0.2); display: flex; flex-direction: column; overflow: hidden; transform: scale(0.9); transition: transform 0.3s; }
    .icon-modal-overlay.active .icon-modal-box { transform: scale(1); }
    .icon-modal-header { padding: 15px 20px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; }
    .icon-modal-title { font-weight: bold; font-size: 16px; color: #333; }
    .icon-modal-close { cursor: pointer; color: #999; font-size: 20px; transition: 0.2s; }
    .icon-modal-close:hover { color: #ef4444; transform: rotate(90deg); }
    .icon-search-bar { padding: 15px 20px; border-bottom: 1px solid #f5f5f5; }
    .icon-search-bar input { width: 100%; padding: 10px 15px; border-radius: 20px; border: 1px solid #ddd; outline: none; background: #f9f9f9; }
    .icon-search-bar input:focus { border-color: #3b82f6; background: #fff; box-shadow: 0 0 0 3px rgba(59,130,246,0.1); }
    .icon-grid { padding: 15px 20px; display: grid; grid-template-columns: repeat(auto-fill, minmax(45px, 1fr)); gap: 10px; max-height: 350px; overflow-y: auto; scrollbar-width: thin; }
    .icon-item-btn { display: flex; align-items: center; justify-content: center; font-size: 20px; color: #475569; width: 45px; height: 45px; border-radius: 8px; border: 1px solid #e2e8f0; cursor: pointer; transition: 0.2s; }
    .icon-item-btn:hover { background: #eff6ff; color: #3b82f6; border-color: #bfdbfe; transform: translateY(-2px); }

    /* 开关卡片式美化 */
    .switch-card { display: flex; align-items: center; justify-content: space-between; background: #f8fafc; border: 1px solid #e2e8f0; padding: 12px 16px; border-radius: 8px; cursor: pointer; transition: all 0.2s; height: 100%; margin: 0;}
    .switch-card:hover { border-color: var(--primary); box-shadow: 0 2px 8px rgba(79, 70, 229, 0.05); }
    .switch-card .form-label { margin: 0; font-size: 14px; color: #1e293b; }

    /* 情侣空间/表格 样式 */
    :root { --love-pink: #ec4899; }
    .btn-love { background-color: var(--love-pink); color: #fff; border: none; }
    .btn-love:hover { background-color: #db2777; }
    .data-table { width: 100%; border-collapse: collapse; min-width: 600px; }
    .data-table td, .data-table th { padding: 12px 16px; text-align: left; border-bottom: 1px solid #eef2ff; vertical-align: middle; }
    .data-table th { font-size: 13px; font-weight: 600; color: #64748b; background-color: #f8fafc; }
    .img-stack { display: flex; align-items: center; }
    .img-preview-sm { width: 40px; height: 40px; border-radius: 6px; object-fit: cover; background: #f1f5f9; border: 2px solid white; margin-left: -12px; }
    .img-preview-sm:first-child { margin-left: 0; }
    .modal-overlay { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(30, 41, 59, 0.5); backdrop-filter: blur(4px); z-index: 9999; display: none; align-items: center; justify-content: center; padding: 1rem; opacity: 0; transition: opacity 0.3s ease; }
    .modal-overlay.active { display: flex; opacity: 1; }
    .modal-content { background: #fff; border-radius: 16px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1); width: 100%; max-width: 680px; transform: scale(0.95); transition: transform 0.3s; display: flex; flex-direction: column; max-height: 90vh; }
    .modal-overlay.active .modal-content { transform: scale(1); }
    .modal-header { padding: 20px 24px; border-bottom: 1px solid #eef2ff; display: flex; justify-content: space-between; align-items: center; }
    .modal-title { font-size: 16px; font-weight: 600; color: #1e293b; display: flex; align-items: center; gap: 8px; margin: 0; }
    .modal-body { padding: 24px; overflow-y: auto; flex-grow: 1; }
    .modal-footer { padding: 16px 24px; border-top: 1px solid #eef2ff; display: flex; justify-content: flex-end; gap: 12px; background: #f8fafc; border-radius: 0 0 16px 16px; }
    .upload-tabs { display: flex; border-bottom: 1px solid #eef2ff; margin-bottom: 16px; }
    .upload-tab { padding: 8px 12px; margin-bottom: -1px; border-bottom: 2px solid transparent; color: #64748b; cursor: pointer; font-size: 14px; }
    .upload-tab.active { color: var(--primary); border-color: var(--primary); font-weight: 600; }
    .upload-pane { display: none; }
    .upload-pane.active { display: block; }
</style>

<div class="settings-wrapper">
    <div class="settings-header">
        <h1 class="page-title"><i class="fas fa-magic"></i> 主题设置：<?= htmlspecialchars($theme_alias) ?></h1>
        <div class="header-actions">
            <a href="themes.php" class="btn btn-ghost"><i class="fas fa-arrow-left"></i> 返回</a>
            <button type="button" class="btn btn-primary btn-save-desktop" onclick="saveThemeSettings(this)">
                <i class="fas fa-save"></i> 保存设置
            </button>
        </div>
    </div>

    <?= $msg ?>

    <div class="tabs-wrapper">
        <div class="tabs-header">
            <?php foreach ($schema as $index => $tab): ?>
                <div class="tab-btn <?= $index === 0 ? 'active' : '' ?>" onclick="switchTab('tab-<?= $tab['tab_id'] ?>', event)">
                    <i class="<?= htmlspecialchars($tab['tab_icon']) ?>"></i> <?= htmlspecialchars($tab['tab_name']) ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <form id="themeOptionsForm" method="POST">
        <input type="hidden" name="action" value="save_options">
        
        <?php foreach ($schema as $index => $tab): ?>
            <div id="tab-<?= $tab['tab_id'] ?>" class="tab-content <?= $index === 0 ? 'active' : '' ?>">
                
                <?php if ($tab['tab_id'] === 'love'): ?>
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding: 0 10px;">
                        <h3 style="font-size: 18px; font-weight: 600; color: #1e293b; margin:0;"><i class="fas fa-heart" style="color: var(--love-pink); margin-right: 8px;"></i>情侣空间配置</h3>
                        <div style="display: flex; align-items: center; gap: 15px;">
                            <?php $letter_val = getThemeOptionVal('love_letter_enabled', '1', $saved_options, $global_settings); ?>
                            <div style="display: flex; align-items: center; gap: 10px; background: #f8fafc; padding: 6px 16px; border-radius: 8px; border: 1px solid #e2e8f0;">
                                <span style="font-size: 14px; font-weight: 600; color: #334155;">开启时光情书</span>
                                <label class="switch" style="margin: 0; cursor: pointer;">
                                    <input type="checkbox" name="options[love_letter_enabled]" value="1" <?= $letter_val == '1' ? 'checked' : '' ?>>
                                    <span class="slider"></span>
                                </label>
                            </div>
                            <button type="button" class="btn btn-love" onclick="openModal('eventModal')"><i class="fas fa-paper-plane"></i> 发布新动态</button>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="section-card">
                    <div class="smart-grid">
                        <?php foreach ($tab['fields'] as $field): ?>
                            <?php if (isset($field['name']) && $field['name'] === 'love_letter_enabled') continue; ?>

                            <?php if ($field['type'] === 'friend_links'): ?>
                                <div class="col-12" style="margin-top: 10px; border-top: 1px dashed #e2e8f0; padding-top: 20px;">
                                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
                                        <label class="form-label" style="margin:0;"><i class="fas fa-link"></i> <?= htmlspecialchars($field['label']) ?></label>
                                        <button type="button" class="btn btn-ghost" onclick="addLink()" style="font-size:12px; padding:4px 10px;"><i class="fas fa-plus"></i> 添加</button>
                                    </div>
                                    <div id="fl-container">
                                        <?php foreach($friend_links as $link): ?>
                                        <div class="fl-item" style="display:flex; gap:10px; margin-bottom:10px;">
                                            <input type="text" name="fl_name[]" class="form-control" value="<?= htmlspecialchars($link['name']) ?>" placeholder="网站名称" style="flex:1">
                                            <input type="text" name="fl_url[]" class="form-control" value="<?= htmlspecialchars($link['url']) ?>" placeholder="URL" style="flex:2">
                                            <button type="button" class="btn btn-danger-ghost" onclick="removeLink(this)" style="padding:0 10px;"><i class="fas fa-times"></i></button>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            
                            <?php elseif ($field['type'] === 'love_events'): ?>
                                <div class="col-12" style="margin-top: 20px; border-top: 1px dashed #e2e8f0; padding-top: 20px;">
                                    <label class="form-label"><i class="fas fa-list"></i> <?= htmlspecialchars($field['label']) ?></label>
                                    <div style="overflow-x: auto; border: 1px solid #e2e8f0; border-radius: 8px;">
                                        <table class="data-table">
                                            <thead><tr><th style="width:120px;">日期</th><th style="width:160px;">照片</th><th>内容</th><th style="width:80px; text-align:right;">操作</th></tr></thead>
                                            <tbody>
                                                <?php if(empty($events)): ?>
                                                    <tr><td colspan="4" style="text-align: center; padding: 40px; color: #94a3b8;">暂无动态。</td></tr>
                                                <?php else: foreach($events as $e): ?>
                                                <tr>
                                                    <td style="font-size:13px; color:#64748b;"><?= $e['event_date'] ?></td>
                                                    <td>
                                                        <?php $imgs = json_decode($e['image_url'], true) ?: []; if(!empty($imgs)): ?>
                                                        <div class="img-stack">
                                                            <?php foreach(array_slice($imgs, 0, 3) as $img): ?>
                                                                <img src="<?= getCosThumb(htmlspecialchars($img), 200) ?>" class="img-preview-sm" loading="lazy">
                                                            <?php endforeach; ?>
                                                            <?php if(count($imgs) > 3): ?><span style="margin-left:4px; font-size:12px; color:#94a3b8">+<?= count($imgs) - 3 ?></span><?php endif; ?>
                                                        </div>
                                                        <?php else: ?><span style="font-size:12px; color: #94a3b8;">无图</span><?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <div style="font-weight: 600; color: #1e293b;"><?= htmlspecialchars($e['title']) ?></div>
                                                        <p style="font-size: 13px; color: #64748b; margin:4px 0 0; max-width: 300px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?= htmlspecialchars($e['description']) ?></p>
                                                    </td>
                                                    <td style="text-align: right;">
                                                        <a href="?theme=<?= $theme_alias ?>&action=delete_event&id=<?= $e['id'] ?>" class="btn btn-ghost" style="color: var(--danger); padding:6px 10px;" onclick="return confirm('确认删除这条回忆吗?')" title="删除"><i class="fas fa-trash-alt"></i></a>
                                                    </td>
                                                </tr>
                                                <?php endforeach; endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                
                            <?php elseif ($field['type'] === 'love_wishes'): ?>
                                <div class="col-12" style="margin-top: 20px; border-top: 1px dashed #e2e8f0; padding-top: 20px;">
                                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
                                        <label class="form-label" style="margin:0;"><i class="fas fa-comment-dots" style="color: var(--love-pink);"></i> <?= htmlspecialchars($field['label']) ?> (<?= count($wishes) ?>)</label>
                                        <div class="dropdown">
                                            <button type="button" class="btn btn-ghost dropdown-toggle" style="font-size:12px; padding:4px 10px;">
                                                批量操作 <i class="fas fa-angle-down"></i>
                                            </button>
                                            <div class="dropdown-menu">
                                                <button type="button" class="dropdown-item" onclick="submitBatchWishes('copy')"><i class="far fa-copy"></i> 复制选中记录</button>
                                                <div class="dropdown-divider"></div>
                                                <button type="button" class="dropdown-item danger" onclick="submitBatchWishes('delete')"><i class="fas fa-trash-alt"></i> 删除选中记录</button>
                                            </div>
                                        </div>
                                    </div>
                                    <div style="overflow-x: auto; border: 1px solid #e2e8f0; border-radius: 8px; max-height: 400px;">
                                        <table class="data-table" style="margin: 0;">
                                            <thead style="position: sticky; top: 0; z-index: 10;">
                                                <tr>
                                                    <th style="width: 40px; text-align: center;"><input type="checkbox" onchange="toggleAllWishes(this.checked)" style="accent-color: var(--primary);"></th>
                                                    <th style="width: 180px;">用户</th>
                                                    <th>祝福内容</th>
                                                    <th style="width: 140px;">时间</th>
                                                    <th style="width: 70px; text-align:right;">操作</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if(empty($wishes)): ?>
                                                    <tr><td colspan="5" style="text-align: center; padding: 40px; color: #94a3b8;">暂无祝福留言。</td></tr>
                                                <?php else: foreach($wishes as $w): ?>
                                                <tr>
                                                    <td style="text-align: center;"><input type="checkbox" value="<?= $w['id'] ?>" class="wish-item-check" style="accent-color: var(--primary);"></td>
                                                    <td>
                                                        <div style="display: flex; align-items: center; gap: 10px;">
                                                            <img src="<?= htmlspecialchars($w['avatar']) ?>" style="width:32px; height:32px; border-radius:50%; object-fit:cover; background:#f1f5f9;" onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($w['nickname']) ?>&background=random'">
                                                            <span style="font-weight:600; font-size:13px; color:#1e293b;"><?= htmlspecialchars($w['nickname']) ?></span>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <p style="font-size:13px; color:#64748b; margin:0; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden;" title="<?= htmlspecialchars($w['content']) ?>"><?= htmlspecialchars($w['content']) ?></p>
                                                    </td>
                                                    <td style="font-size:12px; color:#94a3b8;"><?= date('Y-m-d H:i', strtotime($w['created_at'])) ?></td>
                                                    <td style="text-align: right;">
                                                        <button type="button" class="btn btn-ghost" style="padding:6px 10px; color: var(--primary);" onclick="openWishEditModal(<?= $w['id'] ?>, '<?= htmlspecialchars(addslashes($w['content'])) ?>')" title="编辑"><i class="fas fa-pen"></i></button>
                                                    </td>
                                                </tr>
                                                <?php endforeach; endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                            <?php elseif ($field['type'] === 'about_avatar_tags'): ?>
                                <div class="col-12">
                                    <label class="form-label"><?= htmlspecialchars($field['label']) ?> (精确控制左右位置)</label>
                                    <div class="grid-2" style="background: #fafafa; padding: 20px; border-radius: 12px; border: 1px solid #eee; display:grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                                        <div>
                                            <div class="tag-group-title">左侧标签组 (L1 - L4)</div>
                                            <?php for($i=0; $i<4; $i++): ?>
                                            <div class="input-group" style="margin-bottom: 12px;">
                                                <div class="input-prefix" style="width: 40px;">L<?= $i+1 ?></div>
                                                <input type="text" name="about_avatar_tags[]" class="form-control prefixed-input" value="<?= htmlspecialchars($avatar_tags[$i]) ?>">
                                            </div>
                                            <?php endfor; ?>
                                        </div>
                                        <div>
                                            <div class="tag-group-title">右侧标签组 (R1 - R4)</div>
                                            <?php for($i=4; $i<8; $i++): ?>
                                            <div class="input-group" style="margin-bottom: 12px;">
                                                <div class="input-prefix" style="width: 40px;">R<?= $i-3 ?></div>
                                                <input type="text" name="about_avatar_tags[]" class="form-control prefixed-input" value="<?= htmlspecialchars($avatar_tags[$i]) ?>">
                                            </div>
                                            <?php endfor; ?>
                                        </div>
                                    </div>
                                </div>

                            <?php elseif ($field['type'] === 'about_career_events'): ?>
                                <div class="col-12" style="margin-top: 20px;">
                                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                                        <label class="form-label" style="margin:0;"><i class="fas fa-stream" style="color:#0ea5e9;"></i> <?= htmlspecialchars($field['label']) ?> (进度条)</label>
                                        <button type="button" class="btn btn-ghost" onclick="addCareerEvent()" style="font-size:12px; padding:4px 10px;"><i class="fas fa-plus"></i> 新增阶段</button>
                                    </div>
                                    <div id="careerEventsContainer" style="background:#f8fafc; padding:15px; border-radius:10px; border:1px solid #e2e8f0;">
                                        <?php foreach($career_events as $idx => $ev): ?>
                                        <div class="career-item">
                                            <div class="career-item-header">阶段 <?= $idx+1 ?> <button type="button" class="btn-icon delete" style="width:24px;height:24px;font-size:12px;" onclick="this.closest('.career-item').remove()"><i class="fas fa-times"></i></button></div>
                                            <div class="c-grid">
                                                <div class="form-group"><label>标题</label><input type="text" name="career_event_title[]" class="form-control" value="<?= htmlspecialchars($ev['title']) ?>"></div>
                                                <div class="form-group">
                                                    <label>图标</label>
                                                    <div class="input-group">
                                                        <div class="input-prefix" style="width: 36px;"><i id="preview_cev_<?= $idx ?>" class="fa-solid <?= htmlspecialchars($ev['icon']) ?>"></i></div>
                                                        <input type="text" id="ipt_cev_<?= $idx ?>" name="career_event_icon[]" class="form-control prefixed-input" value="<?= htmlspecialchars($ev['icon']) ?>" oninput="document.getElementById('preview_cev_<?= $idx ?>').className='fa-solid ' + this.value">
                                                        <button type="button" class="btn-upload" onclick="openIconPicker('ipt_cev_<?= $idx ?>', 'preview_cev_<?= $idx ?>')">选</button>
                                                    </div>
                                                </div>
                                                <div class="form-group"><label>颜色</label><select name="career_event_color[]" class="form-control"><option value="bg-blue" <?= $ev['color']=='bg-blue'?'selected':'' ?>>科技蓝</option><option value="bg-red" <?= $ev['color']=='bg-red'?'selected':'' ?>>活力红</option></select></div>
                                                <div class="form-group"><label>位置</label><select name="career_event_pos[]" class="form-control"><option value="t-top" <?= $ev['pos']=='t-top'?'selected':'' ?>>上方</option><option value="t-bottom" <?= $ev['pos']=='t-bottom'?'selected':'' ?>>下方</option></select></div>
                                                <div class="form-group"><label>起点(%)</label><input type="number" name="career_event_left[]" class="form-control" value="<?= htmlspecialchars($ev['left']) ?>"></div>
                                                <div class="form-group"><label>宽度(%)</label><input type="number" name="career_event_width[]" class="form-control" value="<?= htmlspecialchars($ev['width']) ?>"></div>
                                                <div class="form-group"><label>下沉(px)</label><input type="number" name="career_event_top[]" class="form-control" value="<?= htmlspecialchars($ev['top']) ?>"></div>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>

                            <?php elseif ($field['type'] === 'about_career_axis'): ?>
                                <div class="col-12" style="margin-top: 10px;">
                                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                                        <label class="form-label" style="margin:0;"><i class="fas fa-ruler-horizontal" style="color:#64748b;"></i> <?= htmlspecialchars($field['label']) ?></label>
                                        <button type="button" class="btn btn-ghost" onclick="addCareerAxis()" style="font-size:12px; padding:4px 10px;"><i class="fas fa-plus"></i> 新增锚点</button>
                                    </div>
                                    <div id="careerAxisContainer" class="dynamic-list">
                                        <?php foreach($career_axis as $ax): ?>
                                        <div class="dynamic-item" style="padding: 10px;">
                                            <div class="item-input" style="display:flex; gap:10px; width:100%;">
                                                <input type="text" name="career_axis_text[]" class="form-control" value="<?= htmlspecialchars($ax['text']) ?>" placeholder="年份文本 (如 2018)">
                                                <input type="number" name="career_axis_left[]" class="form-control" value="<?= htmlspecialchars($ax['left']) ?>" placeholder="左侧位置 % (0-100)">
                                            </div>
                                            <button type="button" class="btn-icon delete" onclick="this.closest('.dynamic-item').remove()"><i class="fas fa-trash"></i></button>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>

                            <?php elseif ($field['type'] === 'about_anime_covers'): ?>
                                <div class="col-12" style="margin-top: 20px;">
                                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                                        <label class="form-label" style="margin:0;"><i class="fas fa-film" style="color:#10b981;"></i> <?= htmlspecialchars($field['label']) ?></label>
                                        <button type="button" class="btn btn-ghost" onclick="addAnimeCover()" style="font-size:12px; padding:4px 10px;"><i class="fas fa-plus"></i> 新增海报</button>
                                    </div>
                                    <div id="animeListContainer" class="dynamic-list">
                                        <?php foreach($anime_covers as $index => $cover): ?>
                                        <div class="dynamic-item">
                                            <img src="<?= htmlspecialchars($cover) ?>" class="item-preview" onerror="this.src='https://placehold.co/100x150?text=No+Img'">
                                            <div class="item-input">
                                                <input type="text" name="about_anime_covers[]" class="form-control" value="<?= htmlspecialchars($cover) ?>" oninput="updatePreview(this)" id="anime_cover_<?= $index ?>">
                                            </div>
                                            <div style="display:flex; gap: 8px;">
                                                <button type="button" class="btn-icon upload" onclick="triggerUpload('anime_cover_<?= $index ?>', false, true)"><i class="fas fa-upload"></i></button>
                                                <button type="button" class="btn-icon delete" onclick="this.closest('.dynamic-item').remove()"><i class="fas fa-trash"></i></button>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            
                            <?php else: ?>
                                <?php 
                                    $val = getThemeOptionVal($field['name'], $field['default'] ?? '', $saved_options, $global_settings);
                                    $col_class = 'col-6';
                                    if ($field['type'] === 'textarea') $col_class = 'col-12';
                                    elseif ($field['type'] === 'switch') $col_class = 'col-4';
                                    if (isset($field['col'])) $col_class = 'col-' . $field['col'];
                                ?>
                                
                                <div class="form-group <?= $col_class ?>" style="margin-bottom: 0;">
                                    <?php if ($field['type'] === 'switch'): ?>
                                        <label class="switch-card">
                                            <div>
                                                <div class="form-label" style="margin:0;"><?= htmlspecialchars($field['label']) ?></div>
                                                <?php if(!empty($field['desc'])): ?><div style="font-size:12px; color:#94a3b8; font-weight:normal; margin-top:4px;"><?= htmlspecialchars($field['desc']) ?></div><?php endif; ?>
                                            </div>
                                            <div class="switch" style="margin-left: 15px;">
                                                <input type="checkbox" name="options[<?= $field['name'] ?>]" value="1" <?= $val == '1' ? 'checked' : '' ?>>
                                                <span class="slider"></span>
                                            </div>
                                        </label>
                                    <?php else: ?>
                                        <label class="form-label" style="margin-bottom: 8px;">
                                            <?= htmlspecialchars($field['label'] ?? '') ?>
                                            <?php if(!empty($field['desc'])): ?><span style="font-size: 12px; color: #94a3b8; font-weight: normal; margin-left: 5px;">(<?= htmlspecialchars($field['desc']) ?>)</span><?php endif; ?>
                                        </label>
                                        <?php if ($field['type'] === 'text' || $field['type'] === 'date'): ?>
                                            <input type="<?= $field['type'] ?>" name="options[<?= $field['name'] ?>]" class="form-control" value="<?= $val ?>">
                                        <?php elseif ($field['type'] === 'text_upload'): ?>
                                            <div class="input-group">
                                                <input type="text" id="ipt_<?= $field['name'] ?>" name="options[<?= $field['name'] ?>]" class="form-control" value="<?= $val ?>">
                                                <button type="button" class="btn-upload" onclick="triggerUpload('ipt_<?= $field['name'] ?>', false)"><i class="fas fa-upload"></i> 上传</button>
                                            </div>
                                        <?php elseif ($field['type'] === 'color'): ?>
                                            <div style="display: flex; gap: 10px;">
                                                <input type="color" class="form-control" style="width:50px; padding:2px; height:42px;" value="<?= $val ?>" onchange="this.nextElementSibling.value = this.value">
                                                <input type="text" name="options[<?= $field['name'] ?>]" class="form-control" value="<?= $val ?>" style="flex:1;">
                                            </div>
                                        <?php elseif ($field['type'] === 'icon_picker'): ?>
                                            <div class="input-group">
                                                <div class="input-prefix" style="width: 40px;"><i id="preview_<?= $field['name'] ?>" class="fa-solid <?= $val ?>"></i></div>
                                                <input type="text" id="ipt_<?= $field['name'] ?>" name="options[<?= $field['name'] ?>]" class="form-control prefixed-input" value="<?= $val ?>" oninput="document.getElementById('preview_<?= $field['name'] ?>').className='fa-solid ' + this.value">
                                                <button type="button" class="btn-upload" onclick="openIconPicker('ipt_<?= $field['name'] ?>', 'preview_<?= $field['name'] ?>')"><i class="fas fa-search"></i> 选择</button>
                                            </div>
                                        <?php elseif ($field['type'] === 'textarea'): ?>
                                            <textarea name="options[<?= $field['name'] ?>]" class="form-control code-editor" rows="4"><?= $val ?></textarea>
                                        <?php elseif ($field['type'] === 'select'): ?>
                                            <select name="options[<?= $field['name'] ?>]" class="form-control">
                                                <?php foreach ($field['options'] as $opt_val => $opt_label): ?>
                                                    <option value="<?= htmlspecialchars($opt_val) ?>" <?= $val === (string)$opt_val ? 'selected' : '' ?>><?= htmlspecialchars($opt_label) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </form>
</div>

<div class="mobile-save-bar"><button type="button" class="btn btn-primary" onclick="saveThemeSettings(this)"><i class="fas fa-save"></i> 保存设置</button></div>
<div id="toast" class="toast"><i class="fas fa-check-circle"></i> <span></span></div>

<form id="hiddenBatchWishesForm" method="POST" style="display:none;">
    <input type="hidden" name="action" value="batch_ops_wishes">
    <input type="hidden" name="batch_type" id="hiddenBatchWishesType">
    <div id="hiddenBatchWishesIds"></div>
</form>

<input type="file" id="hiddenImageUploader" accept="image/*" style="display: none;">

<div class="icon-modal-overlay" id="iconPickerModal">
    <div class="icon-modal-box">
        <div class="icon-modal-header">
            <div class="icon-modal-title"><i class="fas fa-icons"></i> 选择一个图标</div>
            <i class="fas fa-times icon-modal-close" onclick="closeIconPicker()"></i>
        </div>
        <div class="icon-search-bar"><input type="text" id="iconSearchInput" placeholder="输入关键字搜索 (如: star, user...)" onkeyup="filterIcons()"></div>
        <div class="icon-grid" id="iconGridContainer"></div>
    </div>
</div>

<div class="modal-overlay" id="eventModal" onclick="closeModal(event)">
    <div class="modal-content">
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="add_event">
            <div class="modal-header">
                <h3 class="modal-title"><i class="fas fa-paper-plane" style="color:var(--primary)"></i> 发布新动态</h3>
                <button type="button" class="btn btn-ghost" style="padding:4px 8px; border: none;" onclick="closeModal()"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body">
                <div class="form-group"><label class="form-label">日期</label><input type="date" name="event_date" class="form-control" required value="<?= date('Y-m-d') ?>"></div>
                <div class="form-group"><label class="form-label">标题</label><input type="text" name="title" class="form-control" placeholder="今天发生了什么美好？" required></div>
                <div class="form-group"><label class="form-label">详细描述</label><textarea name="description" class="form-control" rows="3" placeholder="写下这一刻的心情..."></textarea></div>
                <div class="form-group">
                    <label class="form-label">图片上传 (最多9张)</label>
                    <div class="upload-tabs">
                        <div class="upload-tab active" onclick="switchUploadTab('local', this)">本地上传</div>
                        <div class="upload-tab" onclick="switchUploadTab('net', this)">网络链接</div>
                    </div>
                    <div id="pane-local" class="upload-pane active"><input type="file" name="local_images[]" class="form-control" multiple accept="image/*"></div>
                    <div id="pane-net" class="upload-pane"><textarea name="net_images" class="form-control" rows="3" placeholder="每行一个图片链接..."></textarea></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" style="border:none;" onclick="closeModal()">取消</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-check"></i> 发布</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-overlay" id="wishEditModal" onclick="closeModal(event)">
    <div class="modal-content">
        <form method="POST">
            <input type="hidden" name="action" value="edit_wish">
            <input type="hidden" name="id" id="editWishId">
            <div class="modal-header">
                <h3 class="modal-title"><i class="fas fa-comment-dots" style="color:var(--primary)"></i> 编辑祝福留言</h3>
                <button type="button" class="btn btn-ghost" style="padding:4px 8px; border: none;" onclick="closeModal()"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">留言内容</label>
                    <textarea id="editWishContent" name="content" class="form-control" rows="5" required></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" style="border:none;" onclick="closeModal()">取消</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> 保存修改</button>
            </div>
        </form>
    </div>
</div>

<script>
    // Tab 切换逻辑
    function switchTab(id, event) {
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
        if(event && event.currentTarget) event.currentTarget.classList.add('active');
        const targetContent = document.getElementById(id);
        if(targetContent) targetContent.classList.add('active');
    }

    // 各种弹窗控制
    function openModal(modalId) { const modal = document.getElementById(modalId); if (modal) { modal.classList.add('active'); document.body.style.overflow = 'hidden'; } }
    function closeModal(event) { if (event && event.target.classList.contains('modal-overlay')) { event.target.classList.remove('active'); document.body.style.overflow = ''; } else if (!event) { document.querySelectorAll('.modal-overlay.active').forEach(m => m.classList.remove('active')); document.body.style.overflow = ''; } }
    function switchUploadTab(type, btn) { const context = btn.closest('.modal-body'); context.querySelectorAll('.upload-tab').forEach(t => t.classList.remove('active')); btn.classList.add('active'); context.querySelectorAll('.upload-pane').forEach(p => p.classList.remove('active')); context.querySelector('#pane-' + type).classList.add('active'); }
    function showToast(msg, type) { const t = document.getElementById('toast'); t.className = `toast ${type} active`; t.querySelector('span').innerText = msg; t.querySelector('i').className = type === 'success' ? 'fas fa-check-circle' : 'fas fa-exclamation-triangle'; setTimeout(() => t.classList.remove('active'), 3000); }

    // Ajax 统一保存
    function saveThemeSettings(btn) {
        const allBtns = document.querySelectorAll('.btn-save-desktop, .mobile-save-bar .btn');
        allBtns.forEach(b => { b.dataset.original = b.innerHTML; b.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 保存中...'; b.disabled = true; });
        const form = document.getElementById('themeOptionsForm');
        fetch('theme_options.php?theme=<?= $theme_alias ?>', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: new FormData(form) })
        .then(r => r.json()).then(res => { showToast(res.message, res.success ? 'success' : 'error'); })
        .catch(err => { showToast('保存失败: 网络错误', 'error'); })
        .finally(() => { allBtns.forEach(b => { b.innerHTML = b.dataset.original; b.disabled = false; }); });
    }

    // --- 图标选择器逻辑 ---
    const faIcons = ['fa-user', 'fa-users', 'fa-code', 'fa-server', 'fa-database', 'fa-cloud', 'fa-shield-halved', 'fa-graduation-cap', 'fa-briefcase', 'fa-rocket', 'fa-heart', 'fa-leaf', 'fa-fire', 'fa-map-marker-alt', 'fa-image', 'fa-film', 'fa-music', 'fa-gamepad', 'fa-mobile-screen', 'fa-robot', 'fa-brain', 'fa-award', 'fa-trophy'];
    let currentIconTargetInput = '', currentIconTargetPreview = '';
    const iconModal = document.getElementById('iconPickerModal'), iconGrid = document.getElementById('iconGridContainer');

    function openIconPicker(inputId, previewId) { currentIconTargetInput = inputId; currentIconTargetPreview = previewId; renderIcons(faIcons); iconModal.classList.add('active'); document.getElementById('iconSearchInput').value = ''; document.getElementById('iconSearchInput').focus(); }
    function closeIconPicker() { iconModal.classList.remove('active'); }
    function selectIcon(iconClass) { document.getElementById(currentIconTargetInput).value = iconClass; document.getElementById(currentIconTargetPreview).className = 'fa-solid ' + iconClass; closeIconPicker(); }
    function renderIcons(iconsArray) { let html = ''; iconsArray.forEach(icon => { html += `<div class="icon-item-btn" title="${icon}" onclick="selectIcon('${icon}')"><i class="fa-solid ${icon}"></i></div>`; }); iconGrid.innerHTML = html; }
    function filterIcons() { const keyword = document.getElementById('iconSearchInput').value.toLowerCase(); renderIcons(faIcons.filter(icon => icon.toLowerCase().includes(keyword))); }
    iconModal.addEventListener('click', function(e) { if(e.target === iconModal) closeIconPicker(); });

    // --- 通用图片直传逻辑 ---
    let currentUploadTarget = '', isNeedPreviewUpdate = false;
    const uploader = document.getElementById('hiddenImageUploader');
    function triggerUpload(targetId, append = false, needPreview = false) { currentUploadTarget = targetId; isNeedPreviewUpdate = needPreview; uploader.click(); }
    uploader.addEventListener('change', function(e) {
        const file = e.target.files[0]; if (!file) return;
        const formData = new FormData(); formData.append('wangeditor-uploaded-image', file); 
        const targetElement = document.getElementById(currentUploadTarget);
        if(!targetElement) return; const originalVal = targetElement.value; targetElement.value = '上传中...';
        fetch('upload.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if (data.errno === 0 && data.data && data.data.url) {
                targetElement.value = data.data.url; showToast('图片上传成功', 'success');
                if (isNeedPreviewUpdate) updatePreview(targetElement);
            } else { targetElement.value = originalVal; showToast('上传失败', 'error'); }
        }).catch(err => { targetElement.value = originalVal; showToast('请求失败', 'error'); }).finally(() => { uploader.value = ''; });
    });

    // --- 动态 List 添加/删除逻辑 ---
    let careerIdx = <?= count($career_events) + 10 ?>; 
    let coverIndex = <?= count($anime_covers) + 10 ?>;

    function addLink() { const container = document.getElementById('fl-container'); const div = document.createElement('div'); div.className = 'fl-item'; div.style.cssText = 'display:flex; gap:10px; margin-bottom:10px;'; div.innerHTML = `<input type="text" name="fl_name[]" class="form-control" placeholder="网站名称" style="flex:1"><input type="text" name="fl_url[]" class="form-control" placeholder="URL" style="flex:2"><button type="button" class="btn btn-danger-ghost" onclick="removeLink(this)" style="padding:0 10px;"><i class="fas fa-times"></i></button>`; container.appendChild(div); }
    function removeLink(btn) { if(confirm('确定删除此链接吗？')) btn.parentElement.remove(); }

    function addCareerEvent() { careerIdx++; const container = document.getElementById('careerEventsContainer'); const html = `<div class="career-item"><div class="career-item-header">新阶段 <button type="button" class="btn-icon delete" style="width:24px;height:24px;font-size:12px;" onclick="this.closest('.career-item').remove()"><i class="fas fa-times"></i></button></div><div class="c-grid"><div class="form-group"><label>标题</label><input type="text" name="career_event_title[]" class="form-control" value="新阶段"></div><div class="form-group"><label>图标选择</label><div class="input-group"><div class="input-prefix" style="width: 36px;"><i id="preview_cev_${careerIdx}" class="fa-solid fa-circle"></i></div><input type="text" id="ipt_cev_${careerIdx}" name="career_event_icon[]" class="form-control prefixed-input" value="fa-circle" oninput="document.getElementById('preview_cev_${careerIdx}').className='fa-solid ' + this.value"><button type="button" class="btn-upload" onclick="openIconPicker('ipt_cev_${careerIdx}', 'preview_cev_${careerIdx}')" style="padding: 0 10px;">选</button></div></div><div class="form-group"><label>颜色</label><select name="career_event_color[]" class="form-control"><option value="bg-blue">科技蓝</option><option value="bg-red">活力红</option></select></div><div class="form-group"><label>文字位置</label><select name="career_event_pos[]" class="form-control"><option value="t-top">上方</option><option value="t-bottom">下方</option></select></div><div class="form-group"><label>左侧起点(%)</label><input type="number" name="career_event_left[]" class="form-control" value="0"></div><div class="form-group"><label>总宽度(%)</label><input type="number" name="career_event_width[]" class="form-control" value="30"></div><div class="form-group"><label>下沉量(px)</label><input type="number" name="career_event_top[]" class="form-control" value="15"></div></div></div>`; container.insertAdjacentHTML('beforeend', html); }
    
    function addCareerAxis() { const container = document.getElementById('careerAxisContainer'); const html = `<div class="dynamic-item" style="padding: 10px;"><div class="item-input" style="display:flex; gap:10px; width:100%;"><input type="text" name="career_axis_text[]" class="form-control" placeholder="年份文本"><input type="number" name="career_axis_left[]" class="form-control" placeholder="左侧位置 % (0-100)"></div><button type="button" class="btn-icon delete" onclick="this.closest('.dynamic-item').remove()"><i class="fas fa-trash"></i></button></div>`; container.insertAdjacentHTML('beforeend', html); }

    function updatePreview(inputEl) { const previewImg = inputEl.closest('.dynamic-item').querySelector('.item-preview'); if(previewImg) previewImg.src = inputEl.value || 'https://placehold.co/100x150?text=No+Img'; }
    function addAnimeCover() { coverIndex++; const container = document.getElementById('animeListContainer'); const html = `<div class="dynamic-item"><img src="https://placehold.co/100x150?text=Upload" class="item-preview" onerror="this.src='https://placehold.co/100x150?text=Error'"><div class="item-input"><input type="text" name="about_anime_covers[]" class="form-control" value="" oninput="updatePreview(this)" id="anime_cover_${coverIndex}" placeholder="输入图片URL或右侧上传"></div><div style="display:flex; gap: 8px;"><button type="button" class="btn-icon upload" onclick="triggerUpload('anime_cover_${coverIndex}', false, true)" title="上传"><i class="fas fa-upload"></i></button><button type="button" class="btn-icon delete" onclick="this.closest('.dynamic-item').remove()"><i class="fas fa-trash"></i></button></div></div>`; container.insertAdjacentHTML('beforeend', html); }

    // --- 祝福留言相关逻辑 ---
    function toggleAllWishes(checked) { document.querySelectorAll('.wish-item-check').forEach(c => c.checked = checked); }
    function submitBatchWishes(type) {
        const checked = document.querySelectorAll('.wish-item-check:checked');
        if (checked.length === 0) { alert('请先勾选要操作的留言。'); return; }
        if (type === 'delete' && !confirm(`确定要永久删除选中的 ${checked.length} 条留言吗？`)) return;
        
        const hiddenForm = document.getElementById('hiddenBatchWishesForm');
        document.getElementById('hiddenBatchWishesType').value = type;
        const container = document.getElementById('hiddenBatchWishesIds');
        container.innerHTML = '';
        checked.forEach(c => {
            const input = document.createElement('input');
            input.type = 'hidden'; input.name = 'ids[]'; input.value = c.value;
            container.appendChild(input);
        });
        hiddenForm.submit();
    }
    function openWishEditModal(id, content) {
        document.getElementById('editWishId').value = id;
        document.getElementById('editWishContent').value = content;
        openModal('wishEditModal');
    }
</script>

<?php require 'footer.php'; ?>