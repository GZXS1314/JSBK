-- phpMyAdmin SQL Dump
-- 系统版本： 1.1.9 (包含主题系统、第三方登录与积分系统、微信中控代理扫码)

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

-- --------------------------------------------------------

--
-- 表的结构 `admin`
--

DROP TABLE IF EXISTS `admin`;
CREATE TABLE `admin` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- 表的结构 `albums`
--

DROP TABLE IF EXISTS `albums`;
CREATE TABLE `albums` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `cover_image` varchar(255) DEFAULT NULL,
  `sort_order` int(11) DEFAULT '0',
  `is_hidden` tinyint(1) DEFAULT '0' COMMENT '0显示 1隐藏',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- 表的结构 `articles`
--

DROP TABLE IF EXISTS `articles`;
CREATE TABLE `articles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(200) NOT NULL,
  `content` text NOT NULL,
  `summary` varchar(500) DEFAULT NULL,
  `category` varchar(50) DEFAULT 'tech',
  `cover_image` varchar(255) DEFAULT NULL,
  `views` int(11) DEFAULT '0',
  `likes` int(11) DEFAULT '0',
  `is_hidden` tinyint(1) DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL,
  `is_recommended` tinyint(1) DEFAULT '0' COMMENT '1:推荐 0:普通',
  `media_type` varchar(20) DEFAULT 'images' COMMENT 'images 或 video',
  `media_data` text COMMENT '多图URL数组 或 视频数据JSON',
  `resource_data` text COMMENT '存储资源名称和链接JSON',
  `password` varchar(255) DEFAULT NULL COMMENT '文章访问密码',
  `view_points` int(11) NOT NULL DEFAULT '0' COMMENT '查看文章所需积分',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- 表的结构 `article_likes`
--

DROP TABLE IF EXISTS `article_likes`;
CREATE TABLE `article_likes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `article_id` int(11) NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_article` (`user_id`,`article_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- 表的结构 `categories`
--

DROP TABLE IF EXISTS `categories`;
CREATE TABLE `categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `sort_order` int(11) DEFAULT '0' COMMENT '排序，数字越小越靠前',
  `is_hidden` tinyint(1) DEFAULT '0' COMMENT '1:隐藏 0:显示',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- 插入默认分类
--

INSERT INTO `categories` (`id`, `name`, `sort_order`, `is_hidden`) VALUES
(1, '默认分类', 1, 0);

-- --------------------------------------------------------

--
-- 表的结构 `chat_messages`
--

DROP TABLE IF EXISTS `chat_messages`;
CREATE TABLE `chat_messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- 表的结构 `comments`
--

DROP TABLE IF EXISTS `comments`;
CREATE TABLE `comments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `article_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT '0',
  `username` varchar(50) DEFAULT '访客',
  `content` text NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `article_id` (`article_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- 表的结构 `friends`
--

DROP TABLE IF EXISTS `friends`;
CREATE TABLE `friends` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `site_name` varchar(100) NOT NULL COMMENT '站点名称',
  `site_url` varchar(255) NOT NULL COMMENT '站点链接',
  `site_avatar` varchar(255) DEFAULT NULL COMMENT '站点图标/头像',
  `site_desc` varchar(255) DEFAULT NULL COMMENT '站点描述',
  `status` tinyint(1) DEFAULT '0' COMMENT '0:待审核 1:已通过 2:未通过',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- 表的结构 `love_events`
--

DROP TABLE IF EXISTS `love_events`;
CREATE TABLE `love_events` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(100) NOT NULL COMMENT '事件标题',
  `description` text COMMENT '详细描述',
  `event_date` date NOT NULL COMMENT '发生日期',
  `image_url` text,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- 表的结构 `love_wishes`
--

DROP TABLE IF EXISTS `love_wishes`;
CREATE TABLE `love_wishes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `nickname` varchar(50) NOT NULL,
  `avatar` varchar(255) NOT NULL,
  `content` varchar(255) NOT NULL,
  `image_url` varchar(255) DEFAULT NULL COMMENT '祝福配图',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- 表的结构 `photos`
--

DROP TABLE IF EXISTS `photos`;
CREATE TABLE `photos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `album_id` int(11) NOT NULL,
  `title` varchar(100) DEFAULT NULL,
  `device` varchar(100) DEFAULT NULL,
  `image_url` varchar(255) NOT NULL,
  `is_featured` tinyint(1) DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `is_hidden` tinyint(1) DEFAULT '0' COMMENT '0显示 1隐藏',
  `video_cover` varchar(255) DEFAULT NULL COMMENT '视频封面',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- 表的结构 `points_log`
--

DROP TABLE IF EXISTS `points_log`;
CREATE TABLE `points_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `action` varchar(50) NOT NULL COMMENT '变动类型(例如: daily_login, admin_add)',
  `points_change` int(11) NOT NULL COMMENT '变动数量(正数增加，负数扣除)',
  `description` varchar(255) DEFAULT NULL COMMENT '详细说明',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户积分变动日志';

-- --------------------------------------------------------

--
-- 表的结构 `settings`
--

DROP TABLE IF EXISTS `settings`;
CREATE TABLE `settings` (
  `key_name` varchar(50) NOT NULL COMMENT '配置键名',
  `value` text COMMENT '配置值',
  PRIMARY KEY (`key_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- 转存表中的数据 `settings`
--

INSERT INTO `settings` (`key_name`, `value`) VALUES
('ai_api_key', ''),
('ai_api_url', 'https://api.bltcy.ai/v1/chat/completions'),
('ai_model_name', 'gpt-4o-mini'),
('author_avatar', 'https://images.unsplash.com/photo-1535713875002-d1d0cf377fde?ixlib=rb-4.0.3&auto=format&fit=crop&w=200&q=80'),
('author_bio', '全栈开发者 / UI设计爱好者'),
('author_name', 'My Blog'),
('baidu_verify', ''),
('chatroom_muted', '0'),
('cos_bucket', ''),
('cos_domain', ''),
('cos_enabled', '0'),
('cos_region', ''),
('cos_secret_id', ''),
('cos_secret_key', ''),
('enable_chatroom', '1'),
('enable_friend_links', '1'),
('enable_hot_tags', '1'),
('enable_loading_anim', '1'),
('friend_links', '[]'),
('google_verify', ''),
('home_btn1_link', ''),
('home_btn1_text', ''),
('home_btn2_link', ''),
('home_btn2_text', ''),
('home_btn3_link', ''),
('home_btn3_text', ''),
('home_slogan_main', 'Welcome'),
('home_slogan_sub', 'To My World'),
('hot_tags', ''),
('love_bg', ''),
('love_boy', 'Boy'),
('love_boy_avatar', 'https://api.dicebear.com/7.x/avataaars/svg?seed=Felix'),
('love_girl', 'Girl'),
('love_girl_avatar', 'https://api.dicebear.com/7.x/avataaars/svg?seed=Aneka'),
('love_letter_content', ''),
('love_letter_enabled', '0'),
('love_letter_music', ''),
('love_start_date', '2025-01-01'),
('music_api_url', ''),
('music_playlist_id', ''),
('site_bg_gradient_end', '#fbc2eb'),
('site_bg_gradient_start', '#a18cd1'),
('site_bg_overlay_opacity', '1'),
('site_bg_type', 'color'),
('site_bg_value', '#f7f7f7'),
('site_description', ''),
('site_icp', ''),
('site_keywords', ''),
('site_name', 'My New Blog'),
('smtp_from_name', ''),
('smtp_host', ''),
('smtp_pass', ''),
('smtp_port', ''),
('smtp_user', ''),
('social_email', ''),
('social_github', ''),
('social_twitter', ''),
('wechat_qrcode', ''),
('about_avatar_tags', '["全栈开发一条龙", "架构设计爱好者", "极客安全狂热粉", "疑难杂症清道夫", "细节强迫症晚期", "热爱开源与分享", "代码如诗行动派", "终身学习践行者"]'),
('about_motto_title', '源于<br>热爱而去创造'),
('about_motto_tag', '代码与设计'),
('about_mbti_name', '调停者'),
('about_mbti_type', 'INFP-T'),
('about_mbti_icon', 'fa-leaf'),
('about_belief_title', '披荆斩棘之路，<br>劈风斩浪。'),
('about_specialty_title', '高质感 UI<br>全栈开发<br>折腾能力 大师级'),
('about_game_title', '单机与主机游戏'),
('about_game_bg', 'https://placehold.co/600x300/a8c0ff/ffffff?text=Gaming'),
('about_tech_title', '极客外设控'),
('about_tech_bg', 'https://placehold.co/600x300/ffecd2/ffffff?text=Tech'),
('about_music_title', '许嵩、民谣、华语流行'),
('about_music_bg', 'https://placehold.co/600x300/3b82f6/ffffff?text=Music'),
('about_anime_covers', '["https://placehold.co/200x400/8a2be2/ffffff?text=Anime+1", "https://placehold.co/200x400/ff6b6b/ffffff?text=Anime+2", "https://placehold.co/200x400/1dd1a1/ffffff?text=Anime+3", "https://placehold.co/200x400/feca57/ffffff?text=Anime+4", "https://placehold.co/200x400/5f27cd/ffffff?text=Anime+5"]'),
('about_location_city', '中国'),
('about_loc_birth', '199X 出生'),
('about_loc_major', '产品设计 / 计算机'),
('about_loc_job', 'UI设计 / 全栈开发'),
('about_journey_content', '<p>建立这个站点的初衷，是希望有一个属于自己的<strong>数字后花园</strong>。在这里，我可以不受限于各大平台的规则，自由地分享技术、沉淀思考、记录生活。</p><p>从前端 UI 的打磨，到后端 PHP + MySQL 的底层架构，再到 Redis 缓存优化与 WAF 安全机制的引入，每一行代码都是一次创造的乐趣。网络世界瞬息万变，希望这里能成为记录我个人成长轨迹的最安稳的港湾。</p><p>这也是我与世界沟通的桥梁，感谢你能访问这里，愿我们共同留下美好的记忆。</p>'),
('about_career_events', '[{"title":"某某理工大学","icon":"fa-graduation-cap","color":"bg-blue","left":"0","width":"42","top":"15","pos":"t-top"},{"title":"某互联网科技公司","icon":"fa-building","color":"bg-red","left":"38","width":"32","top":"45","pos":"t-bottom"},{"title":"独立开发 \\/ 系统建设","icon":"fa-rocket","color":"bg-red","left":"65","width":"35","top":"15","pos":"t-top"}]'),
('about_career_axis', '[{"text":"2018","left":"0"},{"text":"2022","left":"38"},{"text":"2024","left":"65"},{"text":"现在","left":"100"}]'),
('enable_login_qq', '1'),
('enable_login_wx', '1'),
('enable_login_dy', '1'),
('social_login_mode', 'aggregated'),
('social_login_url', 'http://pt.jx1314.cc/'),
('social_appid', '1000'),
('social_appkey', 'c1cc5f2fd44d04e1c2eafa7267549ad6'),
('user_levels_config', '[{"level":1,"points":0,"name":"青铜会员"},{"level":2,"points":100,"name":"白银会员"},{"level":3,"points":500,"name":"黄金会员"},{"level":4,"points":1500,"name":"钻石会员"},{"level":5,"points":5000,"name":"星耀会员"}]'),
('points_register', '50'),
('points_login', '10'),
('points_comment', '2'),
('points_like', '1'),
('points_share', '5'),
('points_exchange_rate', '100'),
('pay_channel', 'epay'),
('epay_url', ''),
('epay_pid', ''),
('epay_key', ''),
('alipay_appid', ''),
('alipay_private_key', ''),
('alipay_public_key', ''),
('wxpay_appid', ''),
('wxpay_mchid', ''),
('wxpay_serial_no', ''),
('wxpay_private_key', ''),
('wxpay_key', ''),
('active_theme', 'default'),
('custom_css', ''),
('custom_js', ''),
('about_photo_bg', 'https://yunxiaoquan-1259323713.cos.ap-chengdu.myqcloud.com/uploads/202603/20260301_181411_69a4117341dd4.jpg'),
('official_qq_appid', ''),
('official_qq_appkey', ''),
('official_wx_appid', ''),
('official_wx_appsecret', ''),
('official_mp_wx_appid', ''),
('official_mp_wx_appsecret', ''),
('official_mp_wx_token', ''),
('official_dy_clientkey', ''),
('official_dy_clientsecret', ''),
('wx_proxy_enabled', '0'),
('wx_proxy_api_url', 'http://api.jx1314.cc'),
('theme_options_default', '{"home_slogan_main":"人生如棋<br>落子无悔","home_slogan_sub":"code with passing","author_name":"江硕","author_avatar":"https://yunxiaoquan-1259323713.cos.ap-chengdu.myqcloud.com/2025/12/20251222050918327.png","author_bio":"蓦然回首，那人却在灯火阑珊处~","home_btn1_text":"","home_btn1_link":"","home_btn2_text":"","home_btn2_link":"","home_btn3_text":"","home_btn3_link":"","social_github":"https://github.com/GZXS1314","social_email":"3026019154@qq.com","social_twitter":"https://twitter.com","wechat_qrcode":"https://yunxiaoquan-1259323713.cos.ap-chengdu.myqcloud.com/2026/02/20260218155921256.png","hot_tags":"","enable_hot_tags":"1","enable_chatroom":"1","enable_friend_links":"1","site_bg_type":"color","site_bg_overlay_opacity":"1","site_bg_value":"#f8f7f7","site_bg_gradient_start":"#a18cd1","site_bg_gradient_end":"#fbc2eb","site_bg_value_image":"","enable_loading_anim":"1","love_letter_enabled":"1","love_boy":"Boy","love_girl":"Girl","love_boy_avatar":"https://yunxiaoquan-1259323713.cos.ap-chengdu.myqcloud.com/2025/12/20251222050918327.png","love_girl_avatar":"https://yunxiaoquan-1259323713.cos.ap-chengdu.myqcloud.com/2026/02/20260219071321988.png","love_start_date":"2025-10-01","love_bg":"https://yunxiaoquan-1259323713.cos.ap-chengdu.myqcloud.com/2026/02/20260219080512732.png","love_letter_music":"https://gz.jx1314.cc/assets/uploads/Gentleman.mp3"}'),
('db_version', '1.1.9');

--
-- 表的结构 `tags`
--

DROP TABLE IF EXISTS `tags`;
CREATE TABLE `tags` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `article_id` int(11) NOT NULL,
  `tag_name` varchar(50) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `article_id` (`article_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- 表的结构 `users`
--

DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL DEFAULT '',
  `email` varchar(100) NOT NULL,
  `nickname` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `avatar` varchar(255) DEFAULT 'https://ui-avatars.com/api/?background=random&name=User',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `is_banned` tinyint(1) DEFAULT '0' COMMENT '0正常 1封禁',
  `points` int(11) NOT NULL DEFAULT '0' COMMENT '用户当前积分',
  `level` int(11) NOT NULL DEFAULT '1' COMMENT '用户当前等级',
  `qq_uid` varchar(100) DEFAULT NULL COMMENT 'QQ登录UID',
  `wx_uid` varchar(100) DEFAULT NULL COMMENT '微信登录UID',
  `douyin_uid` varchar(100) DEFAULT NULL COMMENT '抖音登录UID',
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `idx_qq_uid` (`qq_uid`),
  UNIQUE KEY `idx_wx_uid` (`wx_uid`),
  UNIQUE KEY `idx_douyin_uid` (`douyin_uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- 表的结构 `recharge_orders`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `recharge_orders`;
CREATE TABLE `recharge_orders` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='积分充值订单表';

-- --------------------------------------------------------
-- 表的结构 `wx_qr_tasks`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `wx_qr_tasks` (
  `scene_str` varchar(64) NOT NULL COMMENT '二维码场景值',
  `status` varchar(20) DEFAULT 'pending' COMMENT '状态: pending, scanned, success',
  `user_id` int(11) DEFAULT '0' COMMENT '登录成功的用户ID',
  `expire_at` datetime NOT NULL COMMENT '过期时间',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`scene_str`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='微信扫码登录轮询表';

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;