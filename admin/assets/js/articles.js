/**
 * ============================================================================
 * Articles Manager Logic
 * ============================================================================
 * @description: 文章管理交互逻辑 (编辑器、AI、批量操作)
 * @author:      jiang shuo
 * @update:      2026-1-1
 */

let editor = null;
const modal = document.getElementById('postModal');
const form = document.getElementById('postForm');

document.addEventListener('DOMContentLoaded', function() {
    // 初始化事件
});

/**
 * ----------------------------------------------------------------------------
 * 1. AI 写作功能模块
 * ----------------------------------------------------------------------------
 */
function openAiModal() {
    document.getElementById('aiModal').classList.add('active');
    setTimeout(() => document.getElementById('aiTopic').focus(), 100);
}

function closeAiModal() {
    document.getElementById('aiModal').classList.remove('active');
}

async function startAiGenerate() {
    const topic = document.getElementById('aiTopic').value.trim();
    if (!topic) return alert('请输入主题！');
    if (!editor) return alert('编辑器未初始化');

    const btn = document.getElementById('btnStartAi');
    const originalText = btn.innerHTML;
    
    const titleInput = document.getElementById('artTitle');
    const summaryInput = document.getElementById('artSummary');
    
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i> 正在连接大脑...';
    
    titleInput.value = '';
    summaryInput.value = '';

    try {
        const response = await fetch('../api/ai_generate.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ topic: topic })
        });

        if (!response.ok) throw new Error('网络请求失败: ' + response.statusText);

        const reader = response.body.getReader();
        const decoder = new TextDecoder();
        
        closeAiModal();

        const SEPARATOR = "===PART_SPLIT_MARKER==="; 
        let currentStage = 0; // 0=Title, 1=Summary, 2=Content
        let tempBuffer = ""; 
        let textQueue = []; 
        let fullHtml = "";    
        let isStreamDone = false;
        let isSearchingForStart = false; 

        const renderTimer = setInterval(() => {
            if (textQueue.length > 0) {
                const chunkSize = textQueue.length > 50 ? 5 : (textQueue.length > 20 ? 2 : 1);
                const chunk = textQueue.splice(0, chunkSize).join('');
                fullHtml += chunk;
                editor.setHtml(fullHtml);
            } else if (isStreamDone && currentStage === 2) {
                clearInterval(renderTimer);
                document.getElementById('content-textarea').value = fullHtml;
                btn.disabled = false;
                btn.innerHTML = originalText;
            }
        }, 30);

        while (true) {
            const { done, value } = await reader.read();
            if (done) { isStreamDone = true; break; }
            if (!value) continue;

            const chunk = decoder.decode(value, { stream: true });
            
            if (chunk.includes('[ERROR]')) {
                alert('生成出错: ' + chunk.replace('[ERROR]', ''));
                clearInterval(renderTimer);
                btn.disabled = false;
                btn.innerHTML = originalText;
                break;
            }

            if (currentStage < 2) {
                tempBuffer += chunk;
                const sepIndex = tempBuffer.indexOf(SEPARATOR);
                if (sepIndex !== -1) {
                    const finalContent = tempBuffer.substring(0, sepIndex).trim();
                    let nextPartStart = tempBuffer.substring(sepIndex + SEPARATOR.length);
                    
                    if (currentStage === 0) titleInput.value = finalContent;
                    else summaryInput.value = finalContent;
                    
                    currentStage++;
                    
                    if (currentStage === 2) {
                        isSearchingForStart = true;
                        if (nextPartStart.trim().length > 0) {
                            isSearchingForStart = false;
                            textQueue.push(...nextPartStart.trimStart().split(''));
                        }
                        tempBuffer = ""; 
                    } else {
                        tempBuffer = nextPartStart;
                        if (currentStage === 1) summaryInput.value = tempBuffer;
                    }
                } else {
                    const limit = (currentStage === 0) ? 100 : 800;
                    if (tempBuffer.length > limit) {
                        const forcedContent = tempBuffer.substring(0, limit);
                        const remaining = tempBuffer.substring(limit);
                        if (currentStage === 0) titleInput.value = forcedContent;
                        else summaryInput.value = forcedContent;
                        currentStage++;
                        if (currentStage === 2) {
                            isSearchingForStart = false; 
                            textQueue.push(...remaining.split(''));
                        }
                        tempBuffer = "";
                    } else {
                        if (currentStage === 0) titleInput.value = tempBuffer;
                        else summaryInput.value = tempBuffer;
                    }
                }
            } else {
                if (isSearchingForStart) {
                    if (chunk.trim().length === 0) { continue; } 
                    else {
                        const validStart = chunk.trimStart();
                        textQueue.push(...validStart.split(''));
                        isSearchingForStart = false; 
                    }
                } else {
                    textQueue.push(...chunk.split(''));
                }
            }
        }

    } catch (err) {
        console.error(err);
        alert('错误：' + err.message);
        btn.disabled = false;
        btn.innerHTML = originalText;
    }
}

/**
 * ----------------------------------------------------------------------------
 * 2. 封面预览功能
 * ----------------------------------------------------------------------------
 */
function previewCover(input) {
    const box = document.getElementById('cover-preview-box');
    const img = document.getElementById('cover-preview-img');
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            img.src = e.target.result;
            box.style.display = 'block';
        }
        reader.readAsDataURL(input.files[0]);
    }
}

function previewUrl(url) {
    const box = document.getElementById('cover-preview-box');
    const img = document.getElementById('cover-preview-img');
    if(url && url.length > 5) {
        img.src = url;
        box.style.display = 'block';
    } else {
        if(document.getElementById('coverFile').files.length === 0) {
            box.style.display = 'none';
        }
    }
}

/**
 * ----------------------------------------------------------------------------
 * 3. 批量操作逻辑
 * ----------------------------------------------------------------------------
 */
function toggleSelect(card) {
    card.classList.toggle('selected');
    const checkbox = card.querySelector('input[type="checkbox"]');
    checkbox.checked = card.classList.contains('selected');
}

function toggleAllCards() {
    const allCards = document.querySelectorAll('.art-card');
    const allCheckboxes = document.querySelectorAll('input[name="ids[]"]');
    const isAllSelected = Array.from(allCheckboxes).every(cb => cb.checked);
    allCards.forEach((card, index) => {
        const cb = allCheckboxes[index];
        if (isAllSelected) {
            card.classList.remove('selected');
            cb.checked = false;
        } else {
            card.classList.add('selected');
            cb.checked = true;
        }
    });
}

function getSelectedIds() {
    const checkboxes = document.querySelectorAll('input[name="ids[]"]:checked');
    return Array.from(checkboxes).map(cb => cb.value);
}

function submitBatch(type) {
    const ids = getSelectedIds();
    if (ids.length === 0) return alert('请先点击卡片选择文章');
    if (type === 'delete') {
        if (!confirm(`确定删除选中的 ${ids.length} 篇文章？`)) return;
    }
    document.getElementById('batchType').value = type;
    document.getElementById('batchForm').submit();
}

function openMoveModal() {
    const ids = getSelectedIds();
    if (ids.length === 0) return alert('请先点击卡片选择文章');
    document.getElementById('moveModal').classList.add('active');
}

function confirmMove() {
    const target = document.getElementById('moveSelect').value;
    document.getElementById('targetCategory').value = target;
    submitBatch('move');
}

/**
 * ----------------------------------------------------------------------------
 * 4. 编辑器相关逻辑 (WangEditor)
 * ----------------------------------------------------------------------------
 */
function initEditor() {
    const { createEditor, createToolbar } = window.wangEditor;
    if (editor) {
        editor.destroy();
        editor = null;
    }

    document.getElementById('editor-toolbar').innerHTML = '';

    const editorConfig = {
        placeholder: '开始创作...',
        onChange(editor) {
            document.getElementById('content-textarea').value = editor.getHtml();
        },
        MENU_CONF: {
            uploadImage: {
                server: 'upload.php',
                fieldName: 'wangeditor-uploaded-image',
                maxFileSize: 5 * 1024 * 1024,
                // 显示上传错误信息
                onError(file, err, res) {
                    alert('图片上传失败: ' + (res && res.message ? res.message : err.message));
                }
            }
        }
    };
    
    editor = createEditor({
        selector: '#editor-container',
        html: '',
        config: editorConfig,
        mode: 'default'
    });
    
    const toolbar = createToolbar({
        editor,
        selector: '#editor-toolbar',
        config: {},
        mode: 'default'
    });
}

function openModal() {
    modal.classList.add('active');
    setTimeout(() => {
        initEditor(); 
        document.getElementById('artId').value = 0;
        document.getElementById('artTitle').value = ''; 
        document.getElementById('artRec').checked = false;
        document.getElementById('artHide').checked = false;
        document.getElementById('artCover').value = '';
        document.getElementById('artTags').value = '';
        document.getElementById('artSummary').value = '';
        document.getElementById('coverFile').value = '';
        document.getElementById('cover-preview-box').style.display = 'none';
        
        form.reset(); 
        if(editor) editor.setHtml(''); 
    }, 100);
}

function editArticle(id) {
    modal.classList.add('active');
    fetch(`articles.php?action=get_detail&id=${id}`)
        .then(r => r.json())
        .then(res => {
            if(res.success) {
                const d = res.data;
                setTimeout(() => {
                    initEditor();
                    document.getElementById('artId').value = d.id;
                    document.getElementById('artTitle').value = d.title;
                    document.getElementById('artCategory').value = d.category;
                    document.getElementById('artSummary').value = d.summary;
                    document.getElementById('artCover').value = d.cover_image;
                    document.getElementById('artTags').value = d.tags;
                    document.getElementById('artRec').checked = (d.is_recommended == 1);
                    document.getElementById('artHide').checked = (d.is_hidden == 1);
                    document.getElementById('coverFile').value = '';
                    
                    if(d.cover_image) {
                        previewUrl(d.cover_image);
                    } else {
                        document.getElementById('cover-preview-box').style.display = 'none';
                    }
                    if(editor) editor.setHtml(d.content); 
                }, 100);
            } else {
                alert('获取文章详情失败');
                closeModal();
            }
        });
}

function closeModal() { 
    modal.classList.remove('active'); 
    setTimeout(() => {
        if (editor) {
            editor.destroy();
            editor = null;
        }
        document.getElementById('editor-toolbar').innerHTML = '';
    }, 300);
}

window.openAiModal = openAiModal;
window.closeAiModal = closeAiModal;
window.startAiGenerate = startAiGenerate;
window.previewCover = previewCover;
window.previewUrl = previewUrl;
window.toggleSelect = toggleSelect;
window.toggleAllCards = toggleAllCards;
window.submitBatch = submitBatch;
window.openMoveModal = openMoveModal;
window.confirmMove = confirmMove;
window.openModal = openModal;
window.editArticle = editArticle;
window.closeModal = closeModal;