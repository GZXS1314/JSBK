<?php
require_once '../includes/config.php';
requireLogin();
// 为了统一用户体验，引导用户回到 index.php 使用弹窗发布
header("Location: index.php");
exit;
?>
