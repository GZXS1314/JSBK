<?php
// --- 0. 引入配置及通用函数 ---
require_once '../includes/config.php';
requireLogin();
$pdo = getDB();
$redis = getRedis(); // [新增] 获取 Redis 连接

// --- [新增] 辅助函数：向聊天频道发布事件 ---
/**
 * 向 Redis 的 Pub/Sub 频道发布一个事件，通知前端进行相应的操作。
 * @param string $type 事件类型 (例如: 'delete_message', 'edit_message')
 * @param array $data 事件相关的数据
 */
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
function publishChatEvent($type, $data) {
    global $redis;
    // 如果 Redis 未连接或未启用，则直接返回
    if (!$redis) {
        return;
    }

    // 1. 定义一个统一的事件结构
    $eventPayload = json_encode([
        'type' => $type,
        'data' => $data
    ]);

    // 2. 发布到约定的频道 (这里假设频道名为 'chat_channel')
    try {
        $redis->publish('chat_channel', $eventPayload);
    } catch (Exception $e) {
        // 记录错误日志，但不要中断程序
        error_log('Redis publish error in chat_manage: ' . $e->getMessage());
    }
}


// --- 1. 处理删除 (单个/批量) ---
if ((isset($_GET['action']) && $_GET['action'] == 'delete') || (isset($_POST['action']) && $_POST['action'] == 'batch_delete')) {
    $ids = [];
    if (isset($_GET['id'])) {
        $ids[] = intval($_GET['id']);
    } elseif (isset($_POST['ids']) && is_array($_POST['ids'])) {
        $ids = array_map('intval', $_POST['ids']);
    }

    if (!empty($ids)) {
        $in  = str_repeat('?,', count($ids) - 1) . '?';
        $pdo->prepare("DELETE FROM chat_messages WHERE id IN ($in)")->execute($ids);
        
        // [新增] 发布消息删除事件
        publishChatEvent('delete_message', ['ids' => $ids]);
    }
    
    // 如果是批量POST请求，最好返回JSON而不是重定向，但为了保持原逻辑，这里还是重定向
    if (isset($_POST['action'])) {
         // 避免浏览器缓存，可以附加一个时间戳
        header("Location: chat_manage.php?t=" . time());
        exit;
    }
    header("Location: chat_manage.php"); 
    exit;
}

// --- 2. 处理编辑 (AJAX 获取 & POST 保存) ---
if (isset($_GET['action']) && $_GET['action'] == 'get_msg' && isset($_GET['id'])) {
    header('Content-Type: application/json');
    $stmt = $pdo->prepare("SELECT id, message FROM chat_messages WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $msg = $stmt->fetch(PDO::FETCH_ASSOC);
    echo json_encode(['success' => !!$msg, 'data' => $msg]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'save_msg') {
    $id = intval($_POST['id']);
    $content = trim($_POST['content']);
    if ($id > 0 && $content !== '') {
        $pdo->prepare("UPDATE chat_messages SET message = ? WHERE id = ?")->execute([$content, $id]);

        // [新增] 发布消息编辑事件
        publishChatEvent('edit_message', ['id' => $id, 'new_content' => $content]);
    }
    header("Location: chat_manage.php"); 
    exit;
}

// --- 3. 获取列表 ---
$sql = "SELECT c.*, u.nickname, u.username, u.avatar 
        FROM chat_messages c 
        LEFT JOIN users u ON c.user_id = u.id 
        ORDER BY c.created_at DESC";
$msgs = $pdo->query($sql)->fetchAll();

require 'header.php';
?>

<!-- 引入自定义 CSS -->
<link rel="stylesheet" href="assets/css/chat_manage.css">

<!-- 页面主操作区 -->
<div class="page-header">
    <h3 class="page-title">聊天记录管理 <span style="font-weight:400; font-size:14px; color:var(--text-tertiary); margin-left:8px;">(共 <?= count($msgs) ?> 条)</span></h3>
</div>

<!-- 聊天记录列表卡片 -->
<div class="card" style="padding: 0; overflow: hidden;">
    <form id="batchForm" method="POST">
        <!-- 为了处理批量删除，将 action 指向当前页面 -->
        <input type="hidden" name="action" value="batch_delete">
        
        <div class="toolbar">
            <label class="btn btn-ghost" style="padding: 6px 10px;" title="全选/反选">
                <input type="checkbox" onchange="toggleAll(this.checked)" style="width:16px; height:16px; accent-color: var(--primary);">
                <span style="font-size:13px; margin-left:6px;">全选</span>
            </label>
            <div style="height: 16px; width: 1px; background: #e2e8f0; margin: 0 4px;"></div>
            <button type="button" class="btn btn-ghost danger" onclick="batchDelete()">
                <i class="fas fa-trash-alt"></i> 批量删除
            </button>
        </div>

        <div class="table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width:40px;"></th>
                        <th style="width:250px;">发送用户</th>
                        <th>消息内容</th>
                        <th style="width:160px;">发送时间</th>
                        <th style="width:120px; text-align:right">操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($msgs)): ?>
                        <tr><td colspan="5" style="text-align:center; padding: 40px; color: var(--text-tertiary);">暂无聊天记录。</td></tr>
                    <?php else: foreach($msgs as $m): ?>
                        <tr>
                            <td><input type="checkbox" name="ids[]" value="<?= $m['id'] ?>" class="item-check"></td>
                            <td>
                                <div class="user-cell">
                                    <img src="<?= $m['avatar'] ? htmlspecialchars($m['avatar']) : 'https://ui-avatars.com/api/?name='.urlencode($m['nickname'] ?: $m['username'] ?: 'User').'&background=random' ?>" class="chat-avatar">
                                    <div class="user-info">
                                        <div class="user-name"><?= htmlspecialchars($m['nickname'] ?: ($m['username'] ?: '未知用户')) ?></div>
                                        <div class="user-id">ID: <?= $m['user_id'] ?: 'N/A' ?></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="msg-content" title="<?= htmlspecialchars($m['message']) ?>"><?= htmlspecialchars($m['message']) ?></div>
                            </td>
                            <td>
                                <span class="msg-time"><?= date('Y-m-d H:i', strtotime($m['created_at'])) ?></span>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <button type="button" class="btn btn-ghost" onclick="openEditModal(<?= $m['id'] ?>)" title="编辑"><i class="fas fa-pen"></i></button>
                                    <a href="?action=delete&id=<?= $m['id'] ?>" class="btn btn-ghost danger" onclick="return confirm('确定删除这条消息？')" title="删除"><i class="fas fa-trash-alt"></i></a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </form>
</div>

<!-- 编辑弹窗 Modal -->
<div class="modal-overlay" id="editModal" onclick="closeEditModal(event)">
    <div class="modal-content">
        <form id="editForm" method="POST">
            <input type="hidden" name="action" value="save_msg">
            <input type="hidden" name="id" id="editId">
            
            <div class="modal-header">
                <h3 class="modal-title">编辑消息内容</h3>
                <button type="button" class="btn btn-ghost" style="padding: 4px;" data-close="modal"><i class="fas fa-times"></i></button>
            </div>
            
            <div class="modal-body">
                <label style="display:block; margin-bottom:8px; font-size:13px; font-weight:500; color:var(--text-secondary);">消息正文</label>
                <textarea name="content" id="editContent" class="form-textarea" required placeholder="请输入消息内容..."></textarea>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" data-close="modal">取消</button>
                <button type="submit" class="btn btn-primary">保存修改</button>
            </div>
        </form>
    </div>
</div>

<!-- 引入自定义 JS -->
<script src="assets/js/chat_manage.js"></script>

<?php require 'footer.php'; ?>
