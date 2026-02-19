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
