/**
 * BKCS 后台设置页核心 JS (精简版)
 * 仅保留系统级设置的 Tab 切换、等级列表处理和 AJAX 保存逻辑
 */

function switchTab(id) {
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
    if(event && event.currentTarget) event.currentTarget.classList.add('active');
    const targetContent = document.getElementById(id);
    if(targetContent) targetContent.classList.add('active');
}

function toggleSocialLoginInputs() {
    const modeSelect = document.getElementById('socialLoginMode');
    if (!modeSelect) return;
    const mode = modeSelect.value;
    document.querySelectorAll('.social-config-block').forEach(el => el.style.display = 'none');
    const targetEl = document.getElementById('social-config-' + mode);
    if(targetEl) {
        targetEl.style.display = 'block';
        targetEl.style.animation = 'fadeIn 0.3s';
    }
}

// 用户等级动态行管理
function addLevel() {
    const container = document.getElementById('levels-container');
    if (!container) return;
    const div = document.createElement('div');
    div.className = 'level-item';
    div.style.cssText = 'display:flex; gap:10px; margin-bottom:10px; align-items: center; animation: fadeIn 0.3s;';
    div.innerHTML = `
        <div class="form-group" style="margin-bottom:0; flex:1">
            <input type="number" name="level_num[]" class="form-control" placeholder="例: 1" required>
        </div>
        <div class="form-group" style="margin-bottom:0; flex:1">
            <input type="number" name="level_points[]" class="form-control" placeholder="例: 100" required>
        </div>
        <div class="form-group" style="margin-bottom:0; flex:2">
            <input type="text" name="level_name[]" class="form-control" placeholder="例: 头衔" required>
        </div>
        <div>
            <button type="button" class="btn btn-danger-ghost" onclick="removeLevel(this)" style="padding:0 10px; height: 38px;"><i class="fas fa-times"></i></button>
        </div>
    `;
    container.appendChild(div);
}

function removeLevel(btn) {
    if(confirm('确定删除此等级配置吗？')) btn.closest('.level-item').remove();
}

// 统一保存逻辑
function saveSettings(btn) {
    const allBtns = document.querySelectorAll('.btn-save-desktop, .mobile-save-bar .btn');
    allBtns.forEach(b => {
        b.dataset.original = b.innerHTML;
        b.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 保存中...';
        b.disabled = true;
    });

    const form = document.getElementById('settingsForm');
    const formData = new FormData(form);

    fetch('settings.php', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData
    })
    .then(r => r.json())
    .then(res => { showToast(res.message, res.success ? 'success' : 'error'); })
    .catch(err => { showToast('保存失败: 网络错误或服务器异常', 'error'); })
    .finally(() => {
        allBtns.forEach(b => {
            b.innerHTML = b.dataset.original;
            b.disabled = false;
        });
    });
}

// 清理缓存
function clearCache(btn) {
    if (!confirm('确定要清空所有 Redis 缓存吗？这可能会造成短时间加载变慢。')) return;

    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 清理中...';
    btn.disabled = true;

    const formData = new FormData();
    formData.append('action', 'clear_cache');

    fetch('settings.php', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData
    })
    .then(r => r.json())
    .then(res => { showToast(res.message, res.success ? 'success' : 'error'); })
    .catch(err => { showToast('请求失败，请检查网络', 'error'); })
    .finally(() => {
        btn.innerHTML = originalText;
        btn.disabled = false;
    });
}

function showToast(msg, type) {
    const t = document.getElementById('toast');
    if (!t) return;
    t.className = `toast ${type} active`;
    const iconClass = type === 'success' ? 'fas fa-check-circle' : 'fas fa-exclamation-triangle';
    t.querySelector('span').innerText = msg;
    t.querySelector('i').className = iconClass;
    setTimeout(() => t.classList.remove('active'), 3000);
}

document.addEventListener('DOMContentLoaded', function() {
    toggleSocialLoginInputs(); 
});