/**
 * ============================================================================
 * Admin Header Logic
 * ============================================================================
 * @description: 后台全局交互逻辑 (侧边栏、在线更新、安全检测、通知中心)
 * @author:      jiang shuo
 * @update:      2026-3-3
 */

// ============================================================================
// 1. 基础布局交互 (侧边栏)
// ============================================================================
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
});

// ============================================================================
// 2. 伪静态与安全检测中心 (铃铛通知)
// ============================================================================
document.addEventListener('DOMContentLoaded', function() {
    const notiDropdown = document.getElementById('notiDropdown');
    
    // --- 通知中心下拉菜单控制 ---
    window.toggleNotification = function(e) {
        e.stopPropagation();
        if(notiDropdown) notiDropdown.classList.toggle('show');
    };

    // 点击页面其他地方关闭通知下拉框
    document.addEventListener('click', function(e) {
        if (notiDropdown && !notiDropdown.contains(e.target) && e.target.id !== 'bellBtn') {
            notiDropdown.classList.remove('show');
        }
    });

    // --- 伪静态安全规则检测核心逻辑 ---
    window.checkSecurityRules = function(isManual = false) {
        const notiSecurity = document.getElementById('notiSecurity');
        const bellBadge = document.getElementById('bellBadge');
        
        if (!notiSecurity || !bellBadge) return;

        if (!isManual && localStorage.getItem('security_notice_dismissed_v106')) {
            updateNotificationUI();
            return; 
        }
        
        // 【核心修复】：加上时间戳和 cache: 'no-store'，强制每次都去问服务器，不读本地缓存！
        const checkUrl = '../pages/about.php?_t=' + new Date().getTime();
        
        fetch(checkUrl, { 
            method: 'GET',
            cache: 'no-store' 
        })
            .then(res => {
                if (res.status === 200) {
                    notiSecurity.style.display = 'block';
                    bellBadge.style.display = 'block'; 
                    if (isManual) alert('检测失败：物理文件仍可直接访问，请检查 Nginx 规则是否保存并重启！');
                } else if (res.status === 403 || res.status === 404 || res.status === 405) {
                    // 只要是被拦截的 HTTP 状态码，都算作安全！
                    notiSecurity.style.display = 'none';
                    if (isManual) {
                        alert('太棒了！安全防护规则已生效，您的系统坚如磐石！');
                        window.dismissSecurityNotice(); 
                    }
                }
                updateNotificationUI();
            })
            .catch(err => {
                // 如果跨域或者被硬性防火墙切断，也会走到这里，同样算作安全
                notiSecurity.style.display = 'none';
                if (isManual) {
                    alert('太棒了！安全防护规则已生效！');
                    window.dismissSecurityNotice();
                }
                updateNotificationUI();
            });
    };

    // 用户点击忽略
    window.dismissSecurityNotice = function() {
        const notiSecurity = document.getElementById('notiSecurity');
        localStorage.setItem('security_notice_dismissed_v106', 'true');
        if(notiSecurity) notiSecurity.style.display = 'none';
        updateNotificationUI();
    };

    // 更新界面状态（如果没有任何通知，显示“暂无通知”，并熄灭红点）
    function updateNotificationUI() {
        const notiSecurity = document.getElementById('notiSecurity');
        const notiEmpty = document.getElementById('notiEmpty');
        const bellBadge = document.getElementById('bellBadge');
        
        if(!notiSecurity || !notiEmpty || !bellBadge) return;

        // 判断当前是否有可见的通知条目
        let hasNotification = (notiSecurity.offsetHeight > 0); 
        
        if (hasNotification) {
            notiEmpty.style.display = 'none';
            bellBadge.style.display = 'block';
        } else {
            notiEmpty.style.display = 'block';
            bellBadge.style.display = 'none';
        }
    }

    // 页面加载后延迟 1 秒静默检测，不卡顿渲染
    setTimeout(() => window.checkSecurityRules(false), 1000);
});

// ============================================================================
// 3. OTA 热更新检测逻辑 (版本升级)
// ============================================================================
document.addEventListener('DOMContentLoaded', function() {
    // 每次会话仅检测一次，避免每次刷新都向 COS 发送请求
    if (!sessionStorage.getItem('updateChecked')) {
        setTimeout(checkForUpdates, 2000); // 延迟2秒检测，不影响页面首屏加载
    }
});

function checkForUpdates() {
    fetch('updater.php?action=check')
        .then(res => res.json())
        .then(data => {
            sessionStorage.setItem('updateChecked', 'true');
            if (data.status === 'success' && data.has_update) {
                showUpdateModal(data.info);
            }
        })
        .catch(err => console.error("Update check failed:", err));
}

function showUpdateModal(info) {
    document.getElementById('newVersionNumber').innerText = '最新版本: ' + info.version;
    // 将换行符转为 <br>
    document.getElementById('updateLog').innerHTML = info.changelog.replace(/\n/g, '<br>'); 
    
    // 直接将数据绑定在按钮的 HTML 属性上，彻底杜绝变量丢失
    const btn = document.getElementById('btnDoUpdate');
    btn.dataset.version = info.version;
    btn.dataset.downloadUrl = info.download_url;

    document.getElementById('updateModal').classList.add('show');
}

window.closeUpdateModal = function() {
    document.getElementById('updateModal').classList.remove('show');
}

window.startUpdate = function() {
    const btn = document.getElementById('btnDoUpdate');
    
    // 从按钮本身读取数据
    const targetVersion = btn.dataset.version;
    const targetUrl = btn.dataset.downloadUrl;

    if (!targetVersion || !targetUrl) {
        alert('更新数据加载异常，请刷新页面重试！');
        return;
    }
    
    // UI 切换为更新中状态
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
    formData.append('download_url', targetUrl);
    formData.append('version', targetVersion);

    progressFill.style.width = '30%';
    progressText.innerText = '正在下载并解压更新包，过程较长请耐心等待...';

    fetch('updater.php?action=update', {
        method: 'POST',
        body: formData
    })
    .then(res => res.text()) // 先作为文本接收，防止 PHP 致命错误导致 JSON 解析崩溃
    .then(text => {
        try {
            return JSON.parse(text);
        } catch(e) {
            // 如果解析 JSON 失败，回显服务器错误信息
            throw new Error('服务器端异常: ' + text.substring(0, 100) + '...');
        }
    })
    .then(data => {
        if (data.status === 'success') {
            progressFill.style.width = '100%';
            progressText.innerText = '更新成功！正在重启...';
            progressText.style.color = '#10b981';
            
            // 更新成功后，清除安全检测的“忽略”标记，因为更新可能引入了新的规则需求
            localStorage.removeItem('security_notice_dismissed_v106');
            
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
        btn.innerHTML = '<i class="fas fa-rotate-right"></i> 重试';
        ignoreBtn.style.display = 'block';
    });
}