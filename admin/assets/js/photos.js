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
     * [修改] 图片和视频批量预览
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
                const isImage = file.type.startsWith('image/');
                const isVideo = file.type.startsWith('video/');

                // 仅预览图片或视频类型
                if (!isImage && !isVideo) return;

                // 创建缩略图容器
                const thumbDiv = document.createElement('div');
                thumbDiv.className = 'preview-thumb';
                
                // 使用 URL.createObjectURL 替代 FileReader，处理大视频文件时性能更好、不卡顿
                const fileUrl = URL.createObjectURL(file);

                if (isImage) {
                    const img = document.createElement('img');
                    img.src = fileUrl;
                    // 图片加载完后释放内存
                    img.onload = () => URL.revokeObjectURL(img.src); 
                    
                    thumbDiv.appendChild(img);
                } else if (isVideo) {
                    const video = document.createElement('video');
                    video.src = fileUrl;
                    video.muted = true;      // 必须静音才能自动播放
                    video.autoplay = true;   // 自动播放预览
                    video.loop = true;       // 循环播放
                    // 同样可以释放内存，但视频通常需要持续读取，这里交给浏览器自动管理或在关闭弹窗时释放
                    
                    // 视频专属的小图标角标
                    const icon = document.createElement('i');
                    icon.className = 'fas fa-video';
                    icon.style.cssText = 'position:absolute; bottom:6px; right:6px; color:white; font-size:12px; filter: drop-shadow(0 1px 2px rgba(0,0,0,0.8)); z-index:2;';
                    
                    thumbDiv.appendChild(video);
                    thumbDiv.appendChild(icon);
                }
                
                previewGrid.appendChild(thumbDiv);
            });

        } else {
            // 没有选择文件 (例如用户点了取消)，恢复初始状态
            if(previewContainer) previewContainer.style.display = 'none';
            if(uploadPrompt) uploadPrompt.style.display = 'block';
            if(selectedCountSpan) selectedCountSpan.innerText = '0';
        }
    };

    // ------------------------------------------------------------------------
    // 3. Tab Switching & AJAX Upload with Progress
    // ------------------------------------------------------------------------
    
    // 选项卡切换逻辑
    document.querySelectorAll('.upload-tab').forEach(tab => {
        tab.addEventListener('click', function() {
            // 移除所有 active
            document.querySelectorAll('.upload-tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.upload-section').forEach(s => s.style.display = 'none');
            
            // 激活当前
            this.classList.add('active');
            document.getElementById(this.dataset.target).style.display = 'block';
        });
    });

    // 接管表单提交 (使用原生 XMLHttpRequest 以支持进度条)
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault(); // 阻止默认的网页刷新提交
            
            const activeTab = document.querySelector('.upload-tab.active').dataset.target;
            const fileInput = document.getElementById('cover-upload');
            const networkInput = document.querySelector('textarea[name="network_url"]');
            const submitBtn = form.querySelector('button[type="submit"]');
            
            // 验证
            if (activeTab === 'local-upload-area' && (!fileInput.files || fileInput.files.length === 0)) {
                return alert('请至少选择一个本地文件！');
            }
            if (activeTab === 'network-upload-area' && !networkInput.value.trim()) {
                return alert('请输入网络媒体链接！');
            }

            // 构建 FormData 并标记为 AJAX 请求
            const formData = new FormData(form);
            formData.append('is_ajax', '1');
            
            // 如果是在网络上传模式，清空 file input 的数据，避免不必要的提交
            if (activeTab === 'network-upload-area') {
                formData.delete('image_files[]');
            }

            // XHR 设置
            const xhr = new XMLHttpRequest();
            xhr.open('POST', 'photos.php', true);

            // 获取进度条 DOM
            const progressContainer = document.getElementById('upload-progress-container');
            const progressBar = document.getElementById('upload-progress-bar');
            const progressText = document.getElementById('progress-text');

            // 监听上传进度 (只有本地文件上传才会有明显的进度)
            xhr.upload.onprogress = function(event) {
                if (event.lengthComputable) {
                    const percentComplete = Math.round((event.loaded / event.total) * 100);
                    progressBar.style.width = percentComplete + '%';
                    progressText.innerText = `上传进度: ${percentComplete}%`;
                }
            };

            // 开始上传时
            xhr.onloadstart = function() {
                progressContainer.style.display = 'block';
                progressBar.style.width = '0%';
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 上传处理中...';
            };

            // 上传完成并收到响应时
            xhr.onload = function() {
                if (xhr.status === 200) {
                    try {
                        const res = JSON.parse(xhr.responseText);
                        if (res.status === 'success') {
                            progressText.innerText = '上传完成，正在刷新数据...';
                            progressBar.style.width = '100%';
                            progressBar.style.background = '#10b981'; // 成功变成绿色
                            // 延迟半秒刷新页面
                            setTimeout(() => window.location.reload(), 500);
                        } else {
                            throw new Error('Server returned error status');
                        }
                    } catch (err) {
                        alert('服务器返回异常。');
                        resetSubmitBtn(submitBtn);
                    }
                } else {
                    alert('上传失败：HTTP ' + xhr.status);
                    resetSubmitBtn(submitBtn);
                }
            };

            // 网络错误
            xhr.onerror = function() {
                alert('网络请求失败，请检查连接。');
                resetSubmitBtn(submitBtn);
            };

            // 发送请求
            xhr.send(formData);
        });
        
        // 恢复按钮状态的辅助函数
        function resetSubmitBtn(btn) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-paper-plane"></i> 开始上传';
            document.getElementById('upload-progress-container').style.display = 'none';
        }
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
