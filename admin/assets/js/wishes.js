/**
 * ====================================================================
 * 项目名称：Wishes Management Script
 * 文件名称：wishes.js
 *  * @author:      jiang shuo
 * @update:      2026-1-1
 * 描述：祝福留言管理页面的交互逻辑（全选、批量操作、弹窗编辑）
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
 * 提交批量操作
 * @param {string} type - 操作类型：'delete' | 'copy'
 */
function submitBatch(type) {
    const checked = document.querySelectorAll('.item-check:checked');
    
    // 1. 检查选中数量
    if (checked.length === 0) {
        alert('请先勾选要操作的留言。');
        return;
    }

    // 2. 删除前的确认
    if (type === 'delete') {
        const confirmMsg = `确定要永久删除选中的 ${checked.length} 条留言吗？\n此操作不可恢复。`;
        if (!confirm(confirmMsg)) {
            return;
        }
    }

    // 3. 提交表单
    const form = document.getElementById('batchForm');
    const typeInput = document.getElementById('batchType');
    
    if (form && typeInput) {
        typeInput.value = type;
        form.submit();
    }
}

// ================= 弹窗 (Modal) 逻辑 =================

const modal = document.getElementById('editModal');
const editForm = document.getElementById('editForm');

/**
 * 打开编辑弹窗
 * @param {number} id - 留言 ID
 * @param {string} content - 留言内容（注意转义问题，通常由 PHP addslashes 处理后传入）
 */
function openEditModal(id, content) {
    if (!modal) return;

    // 填充数据
    const idInput = document.getElementById('editId');
    const contentInput = document.getElementById('editContent');

    if (idInput) idInput.value = id;
    if (contentInput) {
        // 解码可能被 HTML 实体化的内容（可选，视后端输出而定）
        // 这里直接赋值，假设后端已处理好
        contentInput.value = content;
    }

    // 显示弹窗
    modal.classList.add('active');
    document.body.style.overflow = 'hidden'; // 禁止背景滚动
}

/**
 * 关闭编辑弹窗
 * @param {Event} event - 点击事件对象（用于判断点击背景关闭）
 */
function closeEditModal(event) {
    if (!modal) return;
    
    // 如果传入了 event，且点击的目标不是 modal 背景本身（即点击了内部内容），则不关闭
    if (event && event.target !== modal) return;

    modal.classList.remove('active');
    document.body.style.overflow = ''; // 恢复背景滚动
}
