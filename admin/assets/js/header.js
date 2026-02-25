/**
 * ============================================================================
 * Admin Header Logic
 * ============================================================================
 * @description: 后台布局交互逻辑 (侧边栏切换)
  * @author:      jiang shuo
 * @update:      2026-1-1
 */

document.addEventListener('DOMContentLoaded', function() {
    
    // 缓存 DOM 元素
    const sidebar = document.getElementById('sidebar');
    const overlay = document.querySelector('.sidebar-overlay');
    const toggleBtn = document.querySelector('.menu-toggle');

    /**
     * 切换侧边栏状态 (显示/隐藏)
     */
    window.toggleSidebar = function() {
        if (!sidebar || !overlay) {
            console.error('Sidebar elements not found.');
            return;
        }
        sidebar.classList.toggle('active');
        overlay.classList.toggle('active');
    };

    // 绑定点击事件 (如果 toggleSidebar 没有直接在 HTML onclick 中使用)
    // 注意：原代码直接在 HTML 中使用了 onclick="toggleSidebar()"
    // 这里为了防止重复绑定，主要作为备用或增强逻辑
    if(toggleBtn) {
        // toggleBtn.addEventListener('click', window.toggleSidebar);
    }

    if(overlay) {
        // overlay.addEventListener('click', window.toggleSidebar);
    }
});
// admin/assets/js/header.js 追加代码

// ================= 热更新检测逻辑 =================
document.addEventListener('DOMContentLoaded', function() {
    // 每次会话仅检测一次，避免每次刷新都弹窗
    if (!sessionStorage.getItem('updateChecked')) {
        setTimeout(checkForUpdates, 2000); // 延迟2秒检测，不影响页面首屏加载
    }
});

let updateDataCache = null;

function checkForUpdates() {
    fetch('updater.php?action=check')
        .then(res => res.json())
        .then(data => {
            sessionStorage.setItem('updateChecked', 'true');
            if (data.status === 'success' && data.has_update) {
                updateDataCache = data.info;
                showUpdateModal(data.info);
            }
        })
        .catch(err => console.error("Update check failed:", err));
}

function showUpdateModal(info) {
    document.getElementById('newVersionNumber').innerText = '最新版本: ' + info.version;
    // 将换行符转为 <br>
    document.getElementById('updateLog').innerHTML = info.changelog.replace(/\n/g, '<br>'); 
    document.getElementById('updateModal').classList.add('show');
}

window.closeUpdateModal = function() {
    document.getElementById('updateModal').classList.remove('show');
}

window.startUpdate = function() {
    if (!updateDataCache) return;
    
    // UI 切换为更新中状态
    const btn = document.getElementById('btnDoUpdate');
    const ignoreBtn = document.querySelector('.btn-ignore');
    const progressBox = document.getElementById('updateProgressBox');
    const progressText = document.getElementById('updateProgressText');
    const progressFill = document.getElementById('updateProgressFill');

    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 更新中...';
    ignoreBtn.style.display = 'none';
    progressBox.style.display = 'block';

    // 发起更新请求
    let formData = new FormData();
    formData.append('download_url', updateDataCache.download_url);
    formData.append('version', updateDataCache.version);

    progressFill.style.width = '30%';
    progressText.innerText = '正在下载并解压更新包，请勿关闭页面...';

    fetch('updater.php?action=update', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'success') {
            progressFill.style.width = '100%';
            progressText.innerText = '更新成功！正在重启...';
            progressText.style.color = '#10b981';
            setTimeout(() => window.location.reload(true), 1500); // 刷新页面以应用更新
        } else {
            throw new Error(data.message || '更新失败');
        }
    })
    .catch(err => {
        progressFill.style.background = '#ef4444';
        progressText.innerText = '更新出错: ' + err.message;
        progressText.style.color = '#ef4444';
        btn.disabled = false;
        btn.innerText = '重试';
        ignoreBtn.style.display = 'block';
    });
}