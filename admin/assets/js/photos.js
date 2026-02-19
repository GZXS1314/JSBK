/**
 * ============================================================================
 * Photo Gallery Manager Logic
 * ============================================================================
 * @description: 照片管理交互逻辑 (上传、预览、批量操作)
 * @author:      jiang shuo
 * @update:      2026-1-1
 */

document.addEventListener('DOMContentLoaded', function() {
    
    // 缓存 DOM 元素
    const modal = document.getElementById('uploadModal');
    const form = document.getElementById('uploadForm');
    const previewContainer = document.getElementById('preview-container');
    const uploadPrompt = document.getElementById('upload-prompt');
    const previewImg = document.getElementById('preview-img');

    // ------------------------------------------------------------------------
    // 1. Modal Logic (弹窗逻辑)
    // ------------------------------------------------------------------------

    /**
     * 打开上传弹窗
     */
    window.openModal = function() {
        if (!modal) return;
        
        // 重置表单状态
        if(form) form.reset();
        if(previewContainer) previewContainer.style.display = 'none';
        if(uploadPrompt) uploadPrompt.style.display = 'block';
        if(previewImg) previewImg.src = '';

        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    };

    /**
     * 关闭弹窗
     * @param {Event|null} event 触发事件
     */
    window.closeModal = function(event) {
        if (!modal) return;
        
        // 如果事件存在，且点击的是 modal-content 内部，则不关闭
        // 注意：HTML中 onclick="closeModal(event)" 绑定在 overlay 上
        // 我们只需要判断 event.target 是否等于 modal (overlay本身) 或者是关闭按钮
        if (event) {
            // 如果点击的是带有 data-close 属性的元素(关闭按钮)
            const isCloseBtn = event.target.closest('[data-close]');
            // 如果点击的是 Overlay 本身
            const isOverlay = event.target === modal;

            if (!isCloseBtn && !isOverlay) {
                return; // 点击的是内容区域，不关闭
            }
        }
        
        modal.classList.remove('active');
        document.body.style.overflow = '';
    };

    // 绑定显式关闭按钮 (防止HTML inline onclick 覆盖或未触发)
    const closeBtns = document.querySelectorAll('[data-close]');
    closeBtns.forEach(btn => {
        btn.onclick = (e) => {
            e.stopPropagation(); // 防止冒泡
            window.closeModal(null); // 强制关闭
        };
    });


    // ------------------------------------------------------------------------
    // 2. Upload Preview (上传预览) & Form Validation (表单验证)
    // ------------------------------------------------------------------------
    
    /**
     * 图片上传预览
     * @param {HTMLInputElement} input 文件输入框
     */
    window.previewImage = function(input) {
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = e => {
                if(previewImg) previewImg.src = e.target.result;
                if(previewContainer) previewContainer.style.display = 'block';
                if(uploadPrompt) uploadPrompt.style.display = 'none';
            }
            reader.readAsDataURL(input.files[0]);
        } else {
            // 【修复】如果用户在文件选择框点了"取消"，要清空预览恢复原状
            if(previewImg) previewImg.src = '';
            if(previewContainer) previewContainer.style.display = 'none';
            if(uploadPrompt) uploadPrompt.style.display = 'block';
        }
    };

    // 【新增】接管表单提交验证，避免触碰浏览器底层的隐藏焦点报错
    if (form) {
        form.addEventListener('submit', function(e) {
            const fileInput = document.getElementById('cover-upload');
            // 如果没选文件，拦截提交并弹窗提示
            if (!fileInput.value) {
                e.preventDefault(); // 阻止表单提交
                alert('请先点击虚线框选择需要上传的图片文件！');
            }
        });
    }


    // ------------------------------------------------------------------------
    // 3. Batch Operations (批量操作)
    // ------------------------------------------------------------------------

    /**
     * 单选/取消选择照片卡片
     * @param {HTMLElement} card 卡片元素
     */
    window.toggleSelect = function(card) {
        card.classList.toggle('selected');
        const checkbox = card.querySelector('.pc-checkbox');
        if(checkbox) checkbox.checked = card.classList.contains('selected');
    };

    /**
     * 全选/反选
     * @param {boolean} checked 是否选中
     */
    window.toggleAll = function(checked) {
        document.querySelectorAll('.photo-card').forEach(card => {
            const checkbox = card.querySelector('.pc-checkbox');
            card.classList.toggle('selected', checked);
            if(checkbox) checkbox.checked = checked;
        });
    };

    /**
     * 提交批量操作
     * @param {string} type 操作类型
     */
    window.submitBatch = function(type) {
        const checked = document.querySelectorAll('.pc-checkbox:checked');
        
        if (checked.length === 0) {
            return alert('请先点击卡片选择照片');
        }
        
        if (type === 'delete' && !confirm(`确定要永久删除这 ${checked.length} 张照片吗？\n此操作不可恢复。`)) {
            return;
        }
        
        document.getElementById('batchType').value = type;
        document.getElementById('batchForm').submit();
    };

});