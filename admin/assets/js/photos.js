/**
 * ============================================================================
 * Photo Gallery Manager Logic (Batch Upload Enhanced)
 * ============================================================================
 * @description: 照片管理交互逻辑
 * @update:      2026-02-25
 */

document.addEventListener('DOMContentLoaded', function() {
    
    // 缓存 DOM 元素
    const modal = document.getElementById('uploadModal');
    const form = document.getElementById('uploadForm');
    const previewContainer = document.getElementById('preview-container');
    const uploadPrompt = document.getElementById('upload-prompt');
    const previewGrid = document.getElementById('preview-grid'); // [新增]
    const selectedCountSpan = document.getElementById('selected-count'); // [新增]

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
        
        // [修改] 重置预览区域到初始状态
        if(previewContainer) previewContainer.style.display = 'none';
        if(uploadPrompt) uploadPrompt.style.display = 'block';
        if(previewGrid) previewGrid.innerHTML = ''; 
        if(selectedCountSpan) selectedCountSpan.innerText = '0';

        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    };

    /**
     * 关闭弹窗
     */
    window.closeModal = function(event) {
        if (!modal) return;
        if (event) {
            const isCloseBtn = event.target.closest('[data-close]');
            const isOverlay = event.target === modal;

            if (!isCloseBtn && !isOverlay) {
                return; // 点击的是内容区域，不关闭
            }
        }
        
        modal.classList.remove('active');
        document.body.style.overflow = '';
    };

    // 绑定显式关闭按钮
    const closeBtns = document.querySelectorAll('[data-close]');
    closeBtns.forEach(btn => {
        btn.onclick = (e) => {
            e.stopPropagation();
            window.closeModal(null);
        };
    });


    // ------------------------------------------------------------------------
    // 2. Upload Preview (批量预览逻辑)
    // ------------------------------------------------------------------------
    
    /**
     * [修改] 图片批量预览
     * @param {HTMLInputElement} input 文件输入框
     */
    window.previewImages = function(input) {
        // 清空旧预览
        if(previewGrid) previewGrid.innerHTML = '';
        
        if (input.files && input.files.length > 0) {
            // 隐藏提示，显示预览容器
            if(uploadPrompt) uploadPrompt.style.display = 'none';
            if(previewContainer) previewContainer.style.display = 'block';
            if(selectedCountSpan) selectedCountSpan.innerText = input.files.length;

            // 循环处理选中的文件
            Array.from(input.files).forEach(file => {
                // 仅预览图片类型
                if (!file.type.startsWith('image/')) return;

                const reader = new FileReader();
                reader.onload = function(e) {
                    // 创建缩略图元素
                    const thumbDiv = document.createElement('div');
                    thumbDiv.className = 'preview-thumb';
                    
                    const img = document.createElement('img');
                    img.src = e.target.result;
                    
                    thumbDiv.appendChild(img);
                    previewGrid.appendChild(thumbDiv);
                };
                reader.readAsDataURL(file);
            });

        } else {
            // 没有选择文件 (例如用户点了取消)，恢复初始状态
            if(previewContainer) previewContainer.style.display = 'none';
            if(uploadPrompt) uploadPrompt.style.display = 'block';
            if(selectedCountSpan) selectedCountSpan.innerText = '0';
        }
    };

    // 接管表单提交验证
    if (form) {
        form.addEventListener('submit', function(e) {
            const fileInput = document.getElementById('cover-upload');
            // 如果没选文件
            if (!fileInput.files || fileInput.files.length === 0) {
                e.preventDefault(); // 阻止表单提交
                alert('请至少选择一张图片！');
            }
        });
    }


    // ------------------------------------------------------------------------
    // 3. Batch Operations (批量管理逻辑) - 保持不变
    // ------------------------------------------------------------------------

    window.toggleSelect = function(card) {
        card.classList.toggle('selected');
        const checkbox = card.querySelector('.pc-checkbox');
        if(checkbox) checkbox.checked = card.classList.contains('selected');
    };

    window.toggleAll = function(checked) {
        document.querySelectorAll('.photo-card').forEach(card => {
            const checkbox = card.querySelector('.pc-checkbox');
            card.classList.toggle('selected', checked);
            if(checkbox) checkbox.checked = checked;
        });
    };

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
