/**
 * ============================================================================
 * Chat Manager Stylesheet
 * ============================================================================
 * @description: 聊天记录管理页面样式
 * @author:      jiang shuo
 * @update:      2026-1-1
 */

document.addEventListener('DOMContentLoaded', function() {
    
    // --- 1. 批量操作逻辑 ---
    
    /**
     * 全选/反选
     * @param {boolean} checked 是否选中
     */
    window.toggleAll = function(checked) {
        const checkboxes = document.querySelectorAll('.item-check');
        checkboxes.forEach(c => c.checked = checked);
    };

    /**
     * 批量删除提交
     */
    window.batchDelete = function() {
        const checked = document.querySelectorAll('.item-check:checked');
        
        if (checked.length === 0) {
            alert('请先勾选要删除的消息。');
            return;
        }

        if (confirm(`确定要永久删除选中的 ${checked.length} 条消息吗？\n此操作不可恢复。`)) {
            // 创建一个隐藏表单来提交
            const form = document.getElementById('batchForm');
            // 确保 action 是 batch_delete
            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'batch_delete';
            form.appendChild(actionInput);
            
            form.submit();
        }
    };


    // --- 2. 弹窗编辑逻辑 ---
    
    const modal = document.getElementById('editModal');
    const textarea = document.getElementById('editContent');
    const idInput = document.getElementById('editId');
    const form = document.getElementById('editForm'); // 假设弹窗里的表单ID为 editForm

    /**
     * 打开编辑弹窗
     * @param {number} id 消息ID
     */
    window.openEditModal = async function(id) {
        // 重置状态
        textarea.value = '正在加载...';
        textarea.disabled = true;
        idInput.value = id;
        
        // 显示弹窗
        modal.classList.add('active');
        document.body.style.overflow = 'hidden'; // 禁止背景滚动

        try {
            const response = await fetch(`chat_manage.php?action=get_msg&id=${id}`);
            if (!response.ok) throw new Error('Network response was not ok');
            
            const res = await response.json();
            
            if (res.success) {
                textarea.value = res.data.message;
                textarea.disabled = false;
                textarea.focus();
                // 将光标移到末尾
                textarea.setSelectionRange(textarea.value.length, textarea.value.length);
            } else {
                alert('获取消息失败，该消息可能已被删除。');
                closeEditModal();
            }
        } catch (error) {
            console.error('Fetch error:', error);
            alert('网络请求失败，请检查网络连接。');
            closeEditModal();
        }
    };

    /**
     * 关闭编辑弹窗
     */
    window.closeEditModal = function(event) {
        // 如果是点击遮罩层，且不是点击内容区域，则关闭
        if (event && !event.target.classList.contains('modal-overlay') && !event.target.closest('[data-close]')) {
            return; 
        }
        
        modal.classList.remove('active');
        document.body.style.overflow = '';
    };

    // 绑定关闭按钮事件
    const closeButtons = document.querySelectorAll('[data-close="modal"]');
    closeButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            modal.classList.remove('active');
            document.body.style.overflow = '';
        });
    });

    // 防止点击 modal-content 关闭弹窗
    const modalContent = document.querySelector('.modal-content');
    if(modalContent) {
        modalContent.addEventListener('click', function(e) {
            e.stopPropagation();
        });
    }

});
