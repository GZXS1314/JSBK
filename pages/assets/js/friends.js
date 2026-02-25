document.addEventListener('DOMContentLoaded', function() {

    const modal = document.getElementById('friendModal');
    const applyForm = document.getElementById('applyForm');
    const openModalBtn = document.getElementById('applyBtn');

    // 弹窗控制函数
    function toggleModal(show) {
        if (!modal) return;
        if (show) {
            modal.classList.add('active');
        } else {
            modal.classList.remove('active');
        }
    }

    // 为“申请友链”按钮绑定打开弹窗事件
    if (openModalBtn) {
        openModalBtn.addEventListener('click', () => toggleModal(true));
    }

    // 点击弹窗背景区域关闭弹窗
    if (modal) {
        modal.addEventListener('click', function(event) {
            if (event.target === this) {
                toggleModal(false);
            }
        });
    }

    // 表单提交逻辑
    if (applyForm) {
        applyForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const btn = document.getElementById('subBtn');
            const originalText = btn.innerText;
            btn.disabled = true;
            btn.innerText = '提交中...';

            fetch('?action=apply', {
                method: 'POST',
                body: new FormData(this)
            })
            .then(response => {
                // 检查响应是否OK，否则先处理HTTP错误
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                // 尝试解析JSON，如果失败则输出原始文本
                return response.text().then(text => {
                    try {
                        return JSON.parse(text);
                    } catch (err) {
                        console.error('Server Debug (Non-JSON Response):', text);
                        throw new Error('服务器返回格式错误，请检查控制台');
                    }
                });
            })
            .then(data => {
                alert(data.msg);
                if (data.success) {
                    toggleModal(false);
                    applyForm.reset();
                }
            })
            .catch(err => {
                // 统一处理所有错误（网络错误、解析错误等）
                console.error('Fetch Error:', err);
                alert('发生错误：' + err.message);
            })
            .finally(() => {
                // 无论成功或失败，都恢复按钮状态
                btn.disabled = false;
                btn.innerText = originalText;
            });
        });
    }
});
