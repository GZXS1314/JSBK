/**
 * ============================================================================
 * Friends Link Manager Logic
 * ============================================================================
 * @description: 友情链接管理交互逻辑 (弹窗、批量操作、预览)
 * @author:      jiang shuo
 * @update:      2026-1-1
 */

document.addEventListener('DOMContentLoaded', function() {
    
    // 缓存 DOM 元素
    const modal = document.getElementById('friendModal');
    const title = document.getElementById('modalTitle');
    const preview = document.getElementById('avatarPreview');

    // ------------------------------------------------------------------------
    // 1. Modal Logic (弹窗逻辑)
    // ------------------------------------------------------------------------
    
    /**
     * 打开弹窗 (新建或编辑)
     * @param {string} mode 'create' 或 'edit'
     * @param {object|null} data 编辑时传入的友链数据对象
     */
    window.openModal = function(mode, data = null) {
        // 重置表单
        document.getElementById('f_id').value = 0;
        document.getElementById('f_name').value = '';
        document.getElementById('f_url').value = '';
        document.getElementById('f_avatar').value = '';
        document.getElementById('f_desc').value = '';
        document.getElementById('f_status').value = 1; // 默认已通过
        
        // 重置预览图
        if (preview) preview.src = 'https://placehold.co/40x40?text=Img';

        if (mode === 'edit' && data) {
            if (title) title.textContent = '编辑友链';
            const submitBtn = document.getElementById('submitBtn');
            if (submitBtn) submitBtn.textContent = '保存修改';
            
            // 填充数据
            document.getElementById('f_id').value = data.id;
            document.getElementById('f_name').value = data.site_name;
            document.getElementById('f_url').value = data.site_url;
            document.getElementById('f_avatar').value = data.site_avatar;
            document.getElementById('f_desc').value = data.site_desc;
            document.getElementById('f_status').value = data.status;
            
            if(data.site_avatar && preview) preview.src = data.site_avatar;
        } else {
            if (title) title.textContent = '添加友链';
            const submitBtn = document.getElementById('submitBtn');
            if (submitBtn) submitBtn.textContent = '立即添加';
        }
        
        if (modal) modal.classList.add('active');
    };

    /**
     * 关闭弹窗
     */
    window.closeModal = function() {
        if (modal) modal.classList.remove('active');
    };

    /**
     * 头像实时预览
     * @param {string} url 图片链接
     */
    window.updatePreview = function(url) {
        if (preview) {
            preview.src = url || 'https://placehold.co/40x40?text=Img';
        }
    };

    // ------------------------------------------------------------------------
    // 2. Batch Operations (批量操作逻辑)
    // ------------------------------------------------------------------------

    /**
     * 全选/反选
     * @param {boolean} checked 是否选中
     */
    window.toggleAll = function(checked) {
        document.querySelectorAll('.item-check').forEach(c => c.checked = checked);
    };

    /**
     * 提交批量操作
     * @param {string} type 操作类型 ('delete', 'approve' 等)
     */
    window.submitBatch = function(type) {
        if (!document.querySelectorAll('.item-check:checked').length) {
            return alert('请先勾选需要操作的项目');
        }
        
        if(type === 'delete' && !confirm('确定要删除选中的友链吗？此操作不可恢复。')) {
            return;
        }
        
        document.getElementById('batchType').value = type; 
        document.getElementById('batchForm').submit();
    };

});
