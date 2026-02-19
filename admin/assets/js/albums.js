/**
 * ============================================================================
 * Albums Manager Logic
 * ============================================================================
 * @description: 相册管理页面的交互逻辑 (弹窗、AJAX详情、全选、图片预览)
 * @author:      jiang shuo
 * @update:      2026-1-1
 */

document.addEventListener('DOMContentLoaded', function() {
    // 缓存常用 DOM 元素
    const modal = document.getElementById('albumModal');
    const placeholder = document.getElementById('uploadPlaceholder');
    const preview = document.getElementById('imgPreview');

    /**
     * 打开新建/编辑弹窗
     * @param {string} mode 'create' 或 'edit'
     * @param {number} id   相册ID (仅 edit 模式需要)
     */
    window.openModal = function(mode, id = 0) {
        // 1. 重置表单
        document.getElementById('albumId').value = 0;
        document.getElementById('albumName').value = '';
        document.getElementById('albumSort').value = 0;
        document.getElementById('albumHidden').checked = false;
        document.getElementById('oldCover').value = '';
        document.getElementById('coverFile').value = ''; // 清空文件选择
        
        // 2. 重置图片预览状态
        if(preview) {
            preview.src = '';
            preview.style.display = 'none';
        }
        if(placeholder) {
            placeholder.style.display = 'flex';
        }

        // 3. 根据模式处理逻辑
        if(mode === 'edit') {
            document.getElementById('modalTitle').innerText = '编辑相册';
            const submitBtn = document.getElementById('submitBtn');
            if(submitBtn) submitBtn.innerText = '保存修改';
            
            // AJAX 获取详情
            fetch(`?action=get_detail&id=${id}`)
                .then(r => r.json())
                .then(res => {
                    if(res.success) {
                        const d = res.data;
                        // 填充表单数据
                        document.getElementById('albumId').value = d.id;
                        document.getElementById('albumName').value = d.name;
                        document.getElementById('albumSort').value = d.sort_order;
                        document.getElementById('oldCover').value = d.cover_image;
                        document.getElementById('albumHidden').checked = (d.is_hidden == 1);
                        
                        // 处理图片回显
                        if(d.cover_image && preview) {
                            preview.src = d.cover_image;
                            preview.style.display = 'block';
                            if(placeholder) placeholder.style.display = 'none';
                        }
                        if(modal) modal.classList.add('active');
                    } else {
                        alert('获取相册详情失败');
                    }
                })
                .catch(err => {
                    console.error('Error fetching detail:', err);
                    alert('网络错误，请重试');
                });
        } else {
            // 新建模式
            document.getElementById('modalTitle').innerText = '新建相册';
            const submitBtn = document.getElementById('submitBtn');
            if(submitBtn) submitBtn.innerText = '立即创建';
            if(modal) modal.classList.add('active');
        }
    };

    /**
     * 关闭弹窗
     */
    window.closeModal = function() {
        if(modal) modal.classList.remove('active');
    };

    /**
     * 图片上传预览
     * @param {HTMLInputElement} input 文件输入框对象
     */
    window.previewImage = function(input) {
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                if(preview) {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                }
                if(placeholder) {
                    placeholder.style.display = 'none';
                }
            }
            reader.readAsDataURL(input.files[0]);
        }
    };

    /**
     * 表格全选/反选
     * @param {boolean} checked 是否选中
     */
    window.toggleAll = function(checked) {
        document.querySelectorAll('.item-check').forEach(el => el.checked = checked);
    };

    /**
     * 提交批量操作
     * @param {string} type 'hide' 或 'show'
     */
    window.submitBatch = function(type) {
        if(document.querySelectorAll('.item-check:checked').length === 0) {
            alert('请先勾选需要操作的相册');
            return;
        }
        if(!confirm('确定要执行批量操作吗？')) return;
        
        document.getElementById('batchType').value = type;
        document.getElementById('batchForm').submit();
    };
});
