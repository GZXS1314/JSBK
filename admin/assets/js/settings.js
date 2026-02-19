/**
 * ====================================================================
 * 项目名称：Settings Script
 * 文件名称：settings.js
 *  * @author:      jiang shuo
 * @update:      2026-1-1
 * 描述：后台设置页面的交互逻辑（Tab切换、表单提交、动态元素等）
 * ====================================================================
 */

/**
 * 切换设置页面的 Tab 选项卡
 * @param {string} id - 目标内容区域的 ID
 */
function switchTab(id) {
    // 移除所有激活状态
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
    
    // 激活当前点击的按钮 (通过 event.currentTarget 获取)
    if(event && event.currentTarget) {
        event.currentTarget.classList.add('active');
    }
    
    // 显示对应的内容
    const targetContent = document.getElementById(id);
    if(targetContent) {
        targetContent.classList.add('active');
    }
}

/**
 * 根据背景类型选择下拉框，切换显示的输入区域
 */
function toggleBgInputs() {
    const bgTypeSelect = document.getElementById('bgType');
    if (!bgTypeSelect) return;

    const type = bgTypeSelect.value;
    
    // 隐藏所有背景选项
    document.querySelectorAll('.bg-option').forEach(el => el.style.display = 'none');
    
    // 显示选中的类型
    // 注意：CSS Grid 布局仅用于 gradient，其他用 block
    const targetEl = document.getElementById('bg-' + type);
    if(targetEl) {
        targetEl.style.display = (type === 'gradient') ? 'grid' : 'block';
    }
}

/**
 * 动态添加友情链接输入行
 */
function addLink() {
    const container = document.getElementById('fl-container');
    if (!container) return;

    const div = document.createElement('div');
    div.className = 'fl-item';
    div.style.cssText = 'display:flex; gap:10px; margin-bottom:10px; animation: fadeIn 0.3s;';
    
    div.innerHTML = `
        <input type="text" name="fl_name[]" class="form-control" placeholder="网站名称" style="flex:1">
        <input type="text" name="fl_url[]" class="form-control" placeholder="URL" style="flex:2">
        <button type="button" class="btn btn-danger-ghost" onclick="removeLink(this)" style="padding:0 10px;">
            <i class="fas fa-times"></i>
        </button>
    `;
    container.appendChild(div);
}

/**
 * 删除友情链接行
 */
function removeLink(btn) {
    if(confirm('确定删除此链接吗？')) {
        btn.parentElement.remove();
    }
}

/**
 * AJAX 异步保存设置
 */
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
    .then(res => {
        if(res.success) {
            showToast(res.message, 'success');
        } else {
            showToast(res.message, 'error');
        }
    })
    .catch(err => {
        console.error(err);
        showToast('保存失败: 网络错误或服务器异常', 'error');
    })
    .finally(() => {
        allBtns.forEach(b => {
            b.innerHTML = b.dataset.original;
            b.disabled = false;
        });
    });
}

/**
 * [新增] AJAX 清空 Redis 缓存
 */
function clearCache(btn) {
    if (!confirm('确定要清空所有 Redis 缓存吗？')) return;

    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 清理中...';
    btn.disabled = true;

    // 构建表单数据，仅发送 action
    const formData = new FormData();
    formData.append('action', 'clear_cache');

    fetch('settings.php', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData
    })
    .then(r => r.json())
    .then(res => {
        if(res.success) {
            showToast(res.message, 'success');
        } else {
            showToast(res.message, 'error');
        }
    })
    .catch(err => {
        console.error(err);
        showToast('请求失败，请检查网络', 'error');
    })
    .finally(() => {
        btn.innerHTML = originalText;
        btn.disabled = false;
    });
}

/**
 * 显示 Toast 提示框
 */
function showToast(msg, type) {
    const t = document.getElementById('toast');
    if (!t) return;

    t.className = `toast ${type} active`;
    const iconClass = type === 'success' ? 'fas fa-check-circle' : 'fas fa-exclamation-triangle';
    
    t.querySelector('span').innerText = msg;
    t.querySelector('i').className = iconClass;

    setTimeout(() => {
        t.classList.remove('active');
    }, 3000);
}

// ================= 初始化事件监听 =================
document.addEventListener('DOMContentLoaded', function() {
    toggleBgInputs();

    const picker = document.getElementById('bgPicker');
    const text = document.getElementById('bgText');
    
    if(picker && text) {
        picker.addEventListener('input', e => text.value = e.target.value);
        text.addEventListener('input', e => picker.value = e.target.value);
    }
});
