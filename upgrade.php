<?php
/**
 * BKCS 阶梯式增量更新引擎
 * 运行环境：由 admin/updater.php 在解压后自动 require 执行
 */

$pdo = getDB();

// 获取项目根目录，兼容不同环境
$root_path = defined('ROOT_PATH') ? ROOT_PATH : __DIR__;

// 1. 获取用户当前的数据库版本
try {
    $stmt = $pdo->query("SELECT value FROM settings WHERE key_name = 'db_version'");
    $current_db_version = $stmt->fetchColumn();
} catch (Exception $e) {
    $current_db_version = false; 
}


if (!$current_db_version) {
    $current_db_version = '1.0.5';
    $pdo->exec("INSERT IGNORE INTO settings (key_name, value) VALUES ('db_version', '1.0.5')");
}

// =============================================================================
// 【目标版本 1.0.6】：补齐密码字段、资源字段、以及关于页面的默认配置
// =============================================================================
if (version_compare($current_db_version, '1.0.6', '<')) {
    
    $check_res = $pdo->query("SHOW COLUMNS FROM articles LIKE 'resource_data'");
    if ($check_res->rowCount() == 0) {
        $pdo->exec("ALTER TABLE articles ADD COLUMN resource_data TEXT DEFAULT NULL COMMENT '资源下载信息JSON'");
    }
    
    $check_pwd = $pdo->query("SHOW COLUMNS FROM articles LIKE 'password'");
    if ($check_pwd->rowCount() == 0) {
        $pdo->exec("ALTER TABLE articles ADD COLUMN password VARCHAR(255) DEFAULT NULL COMMENT '文章访问密码'");
    }

    $about_settings = [
        "about_avatar_tags" => '["全栈开发一条龙", "架构设计爱好者", "极客安全狂热粉", "疑难杂症清道夫", "细节强迫症晚期", "热爱开源与分享", "代码如诗行动派", "终身学习践行者"]',
        "about_motto_title" => '源于<br>热爱而去创造',
        "about_motto_tag" => '代码与设计',
        "about_mbti_name" => '调停者',
        "about_mbti_type" => 'INFP-T',
        "about_mbti_icon" => 'fa-leaf',
        "about_belief_title" => '披荆斩棘之路，<br>劈风斩浪。',
        "about_specialty_title" => '高质感 UI<br>全栈开发<br>折腾能力 大师级',
        "about_game_title" => '单机与主机游戏',
        "about_game_bg" => 'https://placehold.co/600x300/a8c0ff/ffffff?text=Gaming',
        "about_tech_title" => '极客外设控',
        "about_tech_bg" => 'https://placehold.co/600x300/ffecd2/ffffff?text=Tech',
        "about_music_title" => '许嵩、民谣、华语流行',
        "about_music_bg" => 'https://placehold.co/600x300/3b82f6/ffffff?text=Music',
        "about_anime_covers" => '["https://placehold.co/200x400/8a2be2/ffffff?text=Anime+1", "https://placehold.co/200x400/ff6b6b/ffffff?text=Anime+2", "https://placehold.co/200x400/1dd1a1/ffffff?text=Anime+3", "https://placehold.co/200x400/feca57/ffffff?text=Anime+4", "https://placehold.co/200x400/5f27cd/ffffff?text=Anime+5"]',
        "about_location_city" => '中国',
        "about_loc_birth" => '199X 出生',
        "about_loc_major" => '产品设计 / 计算机',
        "about_loc_job" => 'UI设计 / 全栈开发',
        "about_journey_content" => '<p>建立这个站点的初衷...</p>',
        "about_career_events" => '[{"title":"某某理工大学","icon":"fa-graduation-cap","color":"bg-blue","left":"0","width":"42","top":"15","pos":"t-top"},{"title":"某互联网科技公司","icon":"fa-building","color":"bg-red","left":"38","width":"32","top":"45","pos":"t-bottom"},{"title":"独立开发 \/ BKCS 系统","icon":"fa-rocket","color":"bg-red","left":"65","width":"35","top":"15","pos":"t-top"}]',
        "about_career_axis" => '[{"text":"2018","left":"0"},{"text":"2022","left":"38"},{"text":"2024","left":"65"},{"text":"现在","left":"100"}]'
    ];

    $stmt_insert = $pdo->prepare("INSERT IGNORE INTO settings (key_name, value) VALUES (?, ?)");
    foreach ($about_settings as $k => $v) {
        $stmt_insert->execute([$k, $v]);
    }

    $pdo->exec("UPDATE settings SET value = '1.0.6' WHERE key_name = 'db_version'");
    $current_db_version = '1.0.6'; 
}

// =============================================================================
// 【目标版本 1.0.7】：核心架构升级 - 配置文件静默修补
// =============================================================================
if (version_compare($current_db_version, '1.0.7', '<')) {

    $config_path = $root_path . '/includes/config.php';

    if (file_exists($config_path) && is_writable($config_path)) {
        $content = file_get_contents($config_path);
        $modified = false;

        if (strpos($content, 'session.save_handler') === false) {
            $session_fix = "\n// --- Session 强化 (1.0.7 新增防御) ---\n" .
                           "ini_set('session.save_handler', 'files');\n" .
                           "ini_set('session.save_path', sys_get_temp_dir());\n";
            
            if (strpos($content, 'if (session_status() === PHP_SESSION_NONE)') !== false) {
                $content = str_replace('if (session_status() === PHP_SESSION_NONE)', $session_fix . 'if (session_status() === PHP_SESSION_NONE)', $content);
                $modified = true;
            }
        }

        if (strpos($content, 'REDIS_ENABLED') === false) {
            $redis_fix = "\n// --- 2. Redis 配置 (1.0.7 性能开关) ---\n" .
                         "define('REDIS_ENABLED', false); // 升级后默认关闭，请到后台开启\n" .
                         "define('REDIS_HOST', '127.0.0.1');\n" .
                         "define('REDIS_PORT', 6379);\n" .
                         "define('REDIS_PASS', ''); \n" .
                         "define('REDIS_DB', 0);\n";
            
            if (strpos($content, '// --- 1. 数据库配置 ---') !== false) {
                $content = str_replace('// --- 1. 数据库配置 ---', $redis_fix . '// --- 1. 数据库配置 ---', $content);
                $modified = true;
            }
        }

        if ($modified) {
            file_put_contents($config_path, $content);
        }
    }

    $pdo->exec("INSERT IGNORE INTO settings (key_name, value) VALUES ('redis_enabled', '0')");
    $pdo->exec("UPDATE settings SET value = '1.0.7' WHERE key_name = 'db_version'");
    $current_db_version = '1.0.7';
}

// =============================================================================
// 【目标版本 1.0.8】：多媒体画廊升级 - 增加前台/后台视频支持
// =============================================================================
if (version_compare($current_db_version, '1.0.8', '<')) {
    $pdo->exec("UPDATE settings SET value = '1.0.8' WHERE key_name = 'db_version'");
    $current_db_version = '1.0.8';
}

// =============================================================================
// 【目标版本 1.1.1】：新增第三方登录与用户积分系统基础
// =============================================================================
if (version_compare($current_db_version, '1.1.1', '<')) {

    $columns_to_add = [
        'points'     => "INT(11) NOT NULL DEFAULT '0' COMMENT '用户当前积分'",
        'level'      => "INT(11) NOT NULL DEFAULT '1' COMMENT '用户当前等级'",
        'qq_uid'     => "VARCHAR(100) DEFAULT NULL COMMENT 'QQ登录UID'",
        'wx_uid'     => "VARCHAR(100) DEFAULT NULL COMMENT '微信登录UID'",
        'douyin_uid' => "VARCHAR(100) DEFAULT NULL COMMENT '抖音登录UID'"
    ];

    foreach ($columns_to_add as $col => $def) {
        $check = $pdo->query("SHOW COLUMNS FROM users LIKE '{$col}'");
        if ($check->rowCount() == 0) {
            $pdo->exec("ALTER TABLE users ADD COLUMN {$col} {$def}");
        }
    }

    try { $pdo->exec("ALTER TABLE users ADD UNIQUE KEY `idx_qq_uid` (`qq_uid`)"); } catch(Exception $e) {}
    try { $pdo->exec("ALTER TABLE users ADD UNIQUE KEY `idx_wx_uid` (`wx_uid`)"); } catch(Exception $e) {}
    try { $pdo->exec("ALTER TABLE users ADD UNIQUE KEY `idx_douyin_uid` (`douyin_uid`)"); } catch(Exception $e) {}

    $check_view_points = $pdo->query("SHOW COLUMNS FROM articles LIKE 'view_points'");
    if ($check_view_points->rowCount() == 0) {
        $pdo->exec("ALTER TABLE articles ADD COLUMN view_points INT(11) NOT NULL DEFAULT '0' COMMENT '查看文章所需积分'");
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS `points_log` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `user_id` int(11) NOT NULL,
        `action` varchar(50) NOT NULL COMMENT '变动类型',
        `points_change` int(11) NOT NULL COMMENT '变动数量',
        `description` varchar(255) DEFAULT NULL COMMENT '详细说明',
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `user_id` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户积分变动日志';");

    $new_settings = [
        'enable_login_qq'     => '1',
        'enable_login_wx'     => '1',
        'enable_login_dy'     => '1',
        'social_login_mode'   => 'aggregated',
        'social_login_url'    => '',
        'social_appid'        => '',
        'social_appkey'       => '', 
        'user_levels_config'  => '[{"level":1,"points":0,"name":"青铜会员"},{"level":2,"points":100,"name":"白银会员"},{"level":3,"points":500,"name":"黄金会员"},{"level":4,"points":1500,"name":"钻石会员"},{"level":5,"points":5000,"name":"星耀会员"}]'
    ];

    $stmt_insert = $pdo->prepare("INSERT IGNORE INTO settings (key_name, value) VALUES (?, ?)");
    foreach ($new_settings as $k => $v) {
        $stmt_insert->execute([$k, $v]);
    }

    $pdo->exec("UPDATE settings SET value = '1.1.1' WHERE key_name = 'db_version'");
    $current_db_version = '1.1.1';
}

// =============================================================================
// 【目标版本 1.1.2】：新增积分任务、充值订单表及官方支付通道配置
// =============================================================================
if (version_compare($current_db_version, '1.1.2', '<')) {

    $pdo->exec("CREATE TABLE IF NOT EXISTS `recharge_orders` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `order_no` varchar(50) NOT NULL COMMENT '本地订单号',
      `trade_no` varchar(100) DEFAULT NULL COMMENT '第三方流水号',
      `user_id` int(11) NOT NULL,
      `amount` decimal(10,2) NOT NULL COMMENT '充值金额(元)',
      `points` int(11) NOT NULL COMMENT '兑换的积分',
      `pay_type` varchar(20) NOT NULL COMMENT 'alipay / wxpay / alipay_f2f',
      `status` tinyint(1) DEFAULT '0' COMMENT '0未支付 1已支付',
      `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
      `paid_at` datetime DEFAULT NULL,
      PRIMARY KEY (`id`),
      UNIQUE KEY `order_no` (`order_no`),
      KEY `user_id` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='积分充值订单表';");

    $combined_settings = [
        'points_register' => '50',  
        'points_login'    => '10',  
        'points_comment'  => '2',   
        'points_like'     => '1',   
        'points_share'    => '5',
        'points_exchange_rate' => '100',
        'pay_channel' => 'epay',
        'epay_url' => '',
        'epay_pid' => '',
        'epay_key' => '',
        'alipay_appid' => '',
        'alipay_private_key' => '',
        'alipay_public_key' => '',
        'wxpay_appid' => '',
        'wxpay_mchid' => '',
        'wxpay_serial_no' => '',
        'wxpay_private_key' => '',
        'wxpay_key' => ''
    ];

    $stmt_insert = $pdo->prepare("INSERT IGNORE INTO settings (key_name, value) VALUES (?, ?)");
    foreach ($combined_settings as $k => $v) {
        $stmt_insert->execute([$k, $v]);
    }

    $pdo->exec("UPDATE settings SET value = '1.1.2' WHERE key_name = 'db_version'");
    $current_db_version = '1.1.2';
}

// =============================================================================
// 【目标版本 1.1.6】：主题机制大更新 - 动态迁移老用户的各项配置到 JSON 架构
// =============================================================================
if (version_compare($current_db_version, '1.1.6', '<')) {

    // 1.1 升级 photos 表：增加视频封面字段
    $check_video_cover = $pdo->query("SHOW COLUMNS FROM photos LIKE 'video_cover'");
    if ($check_video_cover->rowCount() == 0) {
        $pdo->exec("ALTER TABLE photos ADD COLUMN video_cover varchar(255) DEFAULT NULL COMMENT '视频封面'");
    }

    // 1.2 动态获取老用户的原有配置信息 (无损迁移机制)
    $stmt_old_settings = $pdo->query("SELECT key_name, value FROM settings");
    $old_settings = [];
    while ($row = $stmt_old_settings->fetch(PDO::FETCH_ASSOC)) {
        $old_settings[$row['key_name']] = $row['value'];
    }

    // 定义哪些旧字符串配置需要被合并到 JSON
    $theme_string_keys = [
        'home_slogan_main', 'home_slogan_sub', 'author_name', 'author_avatar', 'author_bio',
        'home_btn1_text', 'home_btn1_link', 'home_btn2_text', 'home_btn2_link', 'home_btn3_text', 'home_btn3_link',
        'social_github', 'social_email', 'social_twitter', 'wechat_qrcode',
        'hot_tags', 'enable_hot_tags', 'enable_chatroom', 'enable_friend_links',
        'site_bg_type', 'site_bg_overlay_opacity', 'site_bg_value', 'site_bg_gradient_start', 'site_bg_gradient_end', 'site_bg_value_image',
        'enable_loading_anim', 'love_letter_enabled', 'love_boy', 'love_girl', 'love_boy_avatar', 'love_girl_avatar',
        'love_start_date', 'love_bg', 'love_letter_music', 'love_letter_content',
        'about_photo_bg', 'about_motto_tag', 'about_motto_title', 'about_mbti_name', 'about_mbti_type', 'about_mbti_icon',
        'about_belief_title', 'about_specialty_title', 'about_location_city', 'about_loc_birth', 'about_loc_major', 'about_loc_job',
        'about_journey_content', 'about_game_title', 'about_game_bg', 'about_tech_title', 'about_tech_bg', 'about_music_title', 'about_music_bg'
    ];

    // 定义哪些旧 JSON 数组类型配置需要解析后再合并
    $theme_array_keys = [
        'about_avatar_tags', 'about_anime_covers', 'about_career_events', 'about_career_axis'
    ];

    $theme_options_data = [];

    // 迁移老用户的字符串类型配置
    foreach ($theme_string_keys as $key) {
        $theme_options_data[$key] = isset($old_settings[$key]) ? $old_settings[$key] : '';
    }

    // 迁移老用户的数组类型配置
    foreach ($theme_array_keys as $key) {
        if (isset($old_settings[$key]) && !empty($old_settings[$key])) {
            $decoded = json_decode($old_settings[$key], true);
            $theme_options_data[$key] = is_array($decoded) ? $decoded : [];
        } else {
            $theme_options_data[$key] = [];
        }
    }

    // 生成完全继承用户自身数据的 theme_options_default JSON
    $theme_options_json = json_encode($theme_options_data, JSON_UNESCAPED_UNICODE);

    // 插入 1.1.6 基础环境配置 (无损)
    $new_settings_116 = [
        'active_theme'             => 'default',
        'custom_css'               => isset($old_settings['custom_css']) ? $old_settings['custom_css'] : '',
        'custom_js'                => isset($old_settings['custom_js']) ? $old_settings['custom_js'] : '',
        'official_qq_appid'        => '',
        'official_qq_appkey'       => '',
        'official_wx_appid'        => '',
        'official_wx_appsecret'    => '',
        'official_dy_clientkey'    => '',
        'official_dy_clientsecret' => '',
        'theme_options_default'    => $theme_options_json  // 动态生成的继承配置
    ];

    $stmt_insert = $pdo->prepare("INSERT IGNORE INTO settings (key_name, value) VALUES (?, ?)");
    foreach ($new_settings_116 as $k => $v) {
        $stmt_insert->execute([$k, $v]);
    }
    
    // 如果插入被忽略了（之前存在过），我们也主动 UPDATE 更新一次 JSON 保证旧数据已被打包
    $stmt_update_json = $pdo->prepare("UPDATE settings SET value = ? WHERE key_name = 'theme_options_default'");
    $stmt_update_json->execute([$theme_options_json]);

    // 1.3 文件系统瘦身
    if (!function_exists('bkcs_delete_dir_recursive')) {
        function bkcs_delete_dir_recursive($dir) {
            if (!is_dir($dir)) return true;
            $files = array_diff(scandir($dir), array('.', '..'));
            foreach ($files as $file) {
                $path = "$dir/$file";
                is_dir($path) ? bkcs_delete_dir_recursive($path) : @unlink($path);
            }
            return @rmdir($dir);
        }
    }

    $obsolete_paths = [
        'pages',                  
        'user',                   
        'proxy.php',              
        'includes/footer.php',    
        'admin/about_settings.php',
        'admin/wishes.php',
        'admin/assets/css/wishes.css',
        'admin/assets/css/love.css',
        'admin/assets/js/wishes.js',
        'admin/assets/js/love.js'
    ];

    foreach ($obsolete_paths as $path) {
        $fullPath = $root_path . '/' . $path;
        if (is_file($fullPath)) {
            @unlink($fullPath);
        } elseif (is_dir($fullPath)) {
            bkcs_delete_dir_recursive($fullPath);
        }
    }

    $pdo->exec("UPDATE settings SET value = '1.1.6' WHERE key_name = 'db_version'");
    $current_db_version = '1.1.6';
}

// =============================================================================
// 【目标版本 1.1.7】：清理结构调整带来的废弃文件 (将 auth_modal 下放至主题)
// =============================================================================
if (version_compare($current_db_version, '1.1.7', '<')) {

    $old_auth_modal = $root_path . '/includes/auth_modal.php';
    if (is_file($old_auth_modal)) {
        @unlink($old_auth_modal);
    }

    $pdo->exec("UPDATE settings SET value = '1.1.7' WHERE key_name = 'db_version'");
    $current_db_version = '1.1.7';
}

// =============================================================================
// 【目标版本 1.1.8】：新增微信公众号扫码登录设置
// =============================================================================
if (version_compare($current_db_version, '1.1.8', '<')) {

    $new_settings_118 = [
        'official_mp_wx_appid'     => '',
        'official_mp_wx_appsecret' => ''
    ];

    $stmt_insert = $pdo->prepare("INSERT IGNORE INTO settings (key_name, value) VALUES (?, ?)");
    foreach ($new_settings_118 as $k => $v) {
        $stmt_insert->execute([$k, $v]);
    }
    $pdo->exec("UPDATE settings SET value = '1.1.8' WHERE key_name = 'db_version'");
    $current_db_version = '1.1.8';
}

// =============================================================================
// 【目标版本 1.1.9】：新增微信扫码登录任务表与中控代理配置
// =============================================================================
if (version_compare($current_db_version, '1.1.9', '<')) {

    // 1. 创建二维码轮询任务表 (与线上结构保持一致：scene_str 为主键，无自增 id)
    $pdo->exec("CREATE TABLE IF NOT EXISTS `wx_qr_tasks` (
      `scene_str` varchar(64) NOT NULL COMMENT '二维码场景值',
      `status` varchar(20) DEFAULT 'pending' COMMENT '状态: pending, scanned, success',
      `user_id` int(11) DEFAULT '0' COMMENT '登录成功的用户ID',
      `expire_at` datetime NOT NULL COMMENT '过期时间',
      `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`scene_str`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='微信扫码登录轮询表';");

    // 2. 补齐公众号与中控代理的默认配置项
    $new_settings_119 = [
        'official_mp_wx_token' => '',
        'wx_proxy_enabled'     => '0',
        'wx_proxy_api_url'     => ''
    ];

    $stmt_insert = $pdo->prepare("INSERT IGNORE INTO settings (key_name, value) VALUES (?, ?)");
    foreach ($new_settings_119 as $k => $v) {
        $stmt_insert->execute([$k, $v]);
    }

    // 3. 推进版本号
    $pdo->exec("UPDATE settings SET value = '1.1.9' WHERE key_name = 'db_version'");
    $current_db_version = '1.1.9';
}

?>