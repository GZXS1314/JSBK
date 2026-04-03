<?php
// includes/points_helper.php
/**
 * ==========================================================
 * BKCS 积分与等级核心引擎助手
 * ==========================================================
 */

/**
 * 修改用户积分并自动计算等级
 * * @param int    $user_id       用户ID
 * @param int    $points_change 变动积分（加分传正数，扣分传负数）
 * @param string $action        变动类型（如 'daily_login', 'post_article'）
 * @param string $description   中文说明（如 '每日首次登录奖励'）
 * @return bool
 */
function changeUserPoints($user_id, $points_change, $action, $description = '') {
    // 获取全局的 PDO 对象 (前提是调用此函数的文件已经引入了 config.php)
    $pdo = getDB();
    
    // 1. 更新用户表总积分
    $stmt = $pdo->prepare("UPDATE users SET points = points + ? WHERE id = ?");
    $stmt->execute([$points_change, $user_id]);

    // 2. 写入变动日志
    $stmtLog = $pdo->prepare("INSERT INTO points_log (user_id, action, points_change, description) VALUES (?, ?, ?, ?)");
    $stmtLog->execute([$user_id, $action, $points_change, $description]);

    // 3. 动态计算并更新等级 (当前规则：每 100 积分升 1 级)
    $stmtGet = $pdo->prepare("SELECT points FROM users WHERE id = ?");
    $stmtGet->execute([$user_id]);
    $current_points = $stmtGet->fetchColumn();

    // 确保积分不为负数，等级最低为 1 级
    $new_level = floor(max(0, $current_points) / 100) + 1;
    
    $stmtLevel = $pdo->prepare("UPDATE users SET level = ? WHERE id = ?");
    $stmtLevel->execute([$new_level, $user_id]);

    return true;
}
?>