/**
 * ============================================================================
 * Categories Manager Logic
 * ============================================================================
 * @description: 分类管理页面的交互逻辑
 * @author:      jiang shuo
 * @update:      2026-1-1
 */

document.addEventListener('DOMContentLoaded', function() {
    
    /**
     * 删除确认逻辑
     * 通过事件委托处理，避免内联 onclick
     */
    const deleteButtons = document.querySelectorAll('.btn-delete');
    
    deleteButtons.forEach(btn => {
        btn.addEventListener('click', function(e) {
            const name = this.getAttribute('data-name');
            if (!confirm(`确定要删除“${name}”这个分类吗？\n删除后该分类下的文章可能会失去关联。`)) {
                e.preventDefault();
            }
        });
    });

    /**
     * 表单验证 (可选)
     * 防止提交空名称
     */
    const addForm = document.querySelector('.add-form-grid');
    if(addForm) {
        addForm.addEventListener('submit', function(e) {
            const nameInput = document.getElementById('category-name');
            if(!nameInput.value.trim()) {
                e.preventDefault();
                alert('分类名称不能为空');
                nameInput.focus();
            }
        });
    }

});
