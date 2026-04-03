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
        openModalBtn.addEventListener('click', () => {
            // 这里加入基础拦截：如果没有包含全局对象，说明可能未登录
            if (typeof window.siteData !== 'undefined' && !window.siteData.isUserLogin) {
                if (typeof openAuthModal === 'function') {
                    openAuthModal('login');
                    return;
                }
            }
            toggleModal(true);
        });
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
                if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
                return response.text().then(text => {
                    try {
                        return JSON.parse(text);
                    } catch (err) {
                        console.error('Server Response:', text);
                        throw new Error('服务器返回格式异常，请稍后重试');
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
                alert('网络发生错误：' + err.message);
            })
            .finally(() => {
                btn.disabled = false;
                btn.innerText = originalText;
            });
        });
    }
});