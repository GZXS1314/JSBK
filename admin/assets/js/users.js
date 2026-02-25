/**
 * ====================================================================
 * 项目名称：Users Management Script
 * 文件名称：users.js
 * 描述：用户管理页面的交互逻辑（全选、批量删除）
 * ====================================================================
 */

/**
 * 全选/反选表格中的复选框
 * @param {boolean} checked - 全选框的状态
 */
function toggleAll(checked) {
    const checkboxes = document.querySelectorAll('.item-check');
    checkboxes.forEach(c => c.checked = checked);
}

/**
 * 批量删除确认逻辑
 * 1. 检查是否有选中的项
 * 2. 弹出警告确认框
 * 3. 提交表单
 */
function batchDelete() {
    const checked = document.querySelectorAll('.item-check:checked');
    
    // 1. 检查选中数量
    if (checked.length === 0) {
        alert('请先勾选需要删除的用户。');
        return;
    }
    
    // 2. 构造提示信息
    const confirmMsg = `⚠️ 严重警告：确定要删除选中的 ${checked.length} 位用户吗？\n\n此操作将连带删除其所有：\n1. 评论数据\n2. 点赞记录\n\n此操作不可恢复！`;

    // 3. 确认后提交
    if (confirm(confirmMsg)) {
        const form = document.getElementById('listForm');
        if (form) {
            form.submit();
        } else {
            console.error('Form #listForm not found');
        }
    }
}

/**
 * 单个删除确认逻辑（可选，如果不想在 HTML 中写 inline onclick）
 * @param {string} userName - 用户名
 * @param {string} url - 删除链接
 */
function confirmDeleteUser(userName, url) {
    const msg = `⚠️ 警告：确定删除用户“${userName}”吗？\n这将同时删除该用户的所有评论和点赞！`;
    if(confirm(msg)) {
        window.location.href = url;
    }
}
