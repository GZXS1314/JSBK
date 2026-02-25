-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- 生成日期： 2026-02-25
-- 服务器版本： 5.7.44-log
-- PHP 版本： 8.2.28

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- 数据库： `bkcs`
--

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
-- 核心修复：增加了 media_type, media_data, resource_data, password 字段
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
-- 插入默认分类 (推荐保留一些基础分类，以免前台报错)
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
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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
('author_avatar', ''),
('author_bio', ''),
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
('wechat_qrcode', '');

-- --------------------------------------------------------

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
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;