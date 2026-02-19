/**
 * ============================================================================
 * Love Space Manager Logic
 * ============================================================================
 * @description: 情侣空间管理交互逻辑 (弹窗、上传Tab切换)
 * @author:      jiang shuo
 * @update:      2026-1-1
 */

document.addEventListener('DOMContentLoaded', function() {

    // ------------------------------------------------------------------------
    // 1. Modal Logic (弹窗逻辑)
    // ------------------------------------------------------------------------

    /**
     * 打开弹窗
     * @param {string} modalId 弹窗ID
     */
    window.openModal = function(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
        }
    };

    /**
     * 关闭弹窗
     * @param {Event|null} event 触发事件
     */
    window.closeModal = function(event) {
        // 如果传递了事件，检查是否点击的是遮罩层
        if (event && event.target.classList.contains('modal-overlay')) {
            event.target.classList.remove('active');
            document.body.style.overflow = '';
        } 
        // 如果没有事件（如点击取消按钮），关闭所有活跃弹窗
        else if (!event) {
            document.querySelectorAll('.modal-overlay.active').forEach(modal => {
                modal.classList.remove('active');
            });
            document.body.style.overflow = '';
        }
    };

    // 绑定所有关闭按钮的事件
    document.querySelectorAll('[data-close]').forEach(btn => {
        btn.addEventListener('click', () => window.closeModal());
    });


    // ------------------------------------------------------------------------
    // 2. Upload Tab Switching (上传方式切换)
    // ------------------------------------------------------------------------

    /**
     * 切换上传 Tab (本地/网络)
     * @param {string} type 'local' 或 'net'
     * @param {HTMLElement} btn 点击的 Tab 元素
     */
    window.switchUploadTab = function(type, btn) {
        // 找到当前弹窗或卡片的上下文
        const context = btn.closest('.modal-body, .card');
        if (!context) return;

        // 切换 Tab 样式
        context.querySelectorAll('.upload-tab').forEach(t => t.classList.remove('active'));
        btn.classList.add('active');

        // 切换面板显示
        context.querySelectorAll('.upload-pane').forEach(p => p.classList.remove('active'));
        const targetPane = context.querySelector('#pane-' + type);
        if(targetPane) targetPane.classList.add('active');
    };

});
