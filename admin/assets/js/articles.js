/**
 * ============================================================================
 * Articles Manager Logic
 * ============================================================================
 * @description: 文章管理交互逻辑 (编辑器、AI、批量操作、多媒体上传、笔记卡片、资源下载)
 */

let editor = null;
const modal = document.getElementById('postModal');
const form = document.getElementById('postForm');

document.addEventListener('DOMContentLoaded', function() {});

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
        let currentStage = 0; 
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
 * 2. 多媒体 (图文/视频) 切换与增加逻辑
 * ----------------------------------------------------------------------------
 */
function toggleMediaMode() {
    const mode = document.getElementById('artMediaType').value;
    if(mode === 'video') {
        document.getElementById('mode-images').style.display = 'none';
        document.getElementById('mode-video').style.display = 'block';
    } else {
        document.getElementById('mode-images').style.display = 'block';
        document.getElementById('mode-video').style.display = 'none';
    }
}

function addImageInput(url = '') {
    const container = document.getElementById('image-list-container');
    const index = container.children.length;
    
    const uid = Date.now().toString(36) + Math.random().toString(36).substr(2, 5);
    const fileInputId = 'img_file_' + uid;
    const thumbId = 'img_thumb_' + uid;
    
    const defaultSvg = "data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='100' height='100'><rect width='100' height='100' fill='%23f1f5f9'/><text x='50%' y='50%' font-family='sans-serif' font-size='12' fill='%2394a3b8' text-anchor='middle' dominant-baseline='middle'>暂无图片</text></svg>";
    const imgSrc = url ? url : defaultSvg;
    
    const div = document.createElement('div');
    div.className = 'image-list-item';
    
    div.innerHTML = `
        <div class="image-item-header">
            <span class="image-item-title">图片 ${index + 1} ${index === 0 ? '<span style="color:#ec4899;font-size:12px;margin-left:4px;">(封面图)</span>' : ''}</span>
            <button type="button" class="image-item-delete" onclick="this.closest('.image-list-item').remove()" title="移除此图"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="image-item-body">
            <div class="image-item-preview">
                <img id="${thumbId}" src="${imgSrc}" onerror="this.src='${defaultSvg}'">
            </div>
            <div class="image-item-controls">
                <input type="file" name="image_files[]" id="${fileInputId}" accept="image/*" style="display:none;" onchange="previewLocalImage(this, '${thumbId}')">
                
                <label for="${fileInputId}" class="upload-btn">
                    <i class="fa-solid fa-cloud-arrow-up"></i> 点击选择本地图片
                </label>
                
                <input type="text" name="image_urls[]" class="form-control" placeholder="或填写网络图片 URL..." value="${url}" oninput="document.getElementById('${thumbId}').src = this.value || '${defaultSvg}'">
            </div>
        </div>
    `;
    container.appendChild(div);
}

function previewLocalImage(input, thumbId) {
    if (input.files && input.files[0]) {
        const file = input.files[0];
        if (!file.type.startsWith('image/')) {
            alert('请选择图片文件！');
            input.value = ''; 
            return;
        }
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById(thumbId).src = e.target.result;
        }
        reader.readAsDataURL(file);
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
 * 4. 编辑器与弹窗逻辑
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
        document.getElementById('artTags').value = '';
        document.getElementById('artSummary').value = '';
        
        // [新增] 重置资源字段
        document.getElementById('resName').value = '';
        document.getElementById('resLink').value = '';
        document.getElementById('resFile').value = '';
        document.getElementById('artPassword').value = ''; // [新增] 清空密码
        
        // [新增] 重置文件名显示文字
        const fileNameSpan = document.getElementById('resFileName');
        if(fileNameSpan) {
            fileNameSpan.innerText = '点击选择文件...';
            fileNameSpan.style.color = '#666';
        }
        document.querySelector('input[name="res_type"][value="link"]').click();
        
        document.getElementById('artMediaType').value = 'images';
        toggleMediaMode();
        document.getElementById('image-list-container').innerHTML = '';
        addImageInput(); 
        document.getElementById('vCoverUrl').value = '';
        document.getElementById('vUrl').value = '';
        
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
                    document.getElementById('artTags').value = d.tags;
                    document.getElementById('artRec').checked = (d.is_recommended == 1);
                    document.getElementById('artHide').checked = (d.is_hidden == 1);
                    
                    // [新增] 回填资源和密码字段
                    let resData = { name: '', link: '' };
                    try {
                        if (d.resource_data) resData = JSON.parse(d.resource_data);
                    } catch(e) {}
                    
                    document.getElementById('resName').value = resData.name || '';
                    document.getElementById('resLink').value = resData.link || '';
                    document.getElementById('resFile').value = ''; // 文件上传控件无法设置值
                    document.getElementById('artPassword').value = d.password || ''; // [新增] 回填密码
                    
                    // 如果有链接，默认显示出来
                    document.querySelector('input[name="res_type"][value="link"]').click();
                    
                    document.getElementById('artMediaType').value = d.media_type || 'images';
                    toggleMediaMode();
                    
                    document.getElementById('image-list-container').innerHTML = '';
                    if (d.media_type === 'video') {
                        let md = {};
                        try { md = JSON.parse(d.media_data || '{}') } catch(e){}
                        document.getElementById('vCoverUrl').value = md.cover || d.cover_image || '';
                        document.getElementById('vUrl').value = md.video || '';
                    } else {
                        let md = [];
                        try { md = JSON.parse(d.media_data || '[]') } catch(e){}
                        if (md.length > 0) {
                            md.forEach(url => addImageInput(url));
                        } else if (d.cover_image) {
                            addImageInput(d.cover_image); 
                        } else {
                            addImageInput();
                        }
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

/**
 * ----------------------------------------------------------------------------
 * 5. 小红书风格：笔记卡片生成器逻辑
 * ----------------------------------------------------------------------------
 */
const noteBgTemplates = [
    // --- 原有基础模板 ---
    { name: '纯净白', type: 'color', value: '#ffffff' },
    { name: '暗夜黑', type: 'color', value: '#1e293b', fontColor: '#ffffff' },
    { name: '晚霞紫', type: 'gradient', value: ['#fbc2eb', '#a6c1ee'] },
    { name: '蜜桃粉', type: 'gradient', value: ['#ff9a9e', '#fecfef'] },
    { name: '手账网格', type: 'pattern_grid', value: { bg: '#faf9f5', line: '#e2dfd5' } },
    { name: '可爱波点', type: 'pattern_dots', value: { bg: '#fff0f5', dot: '#ffb6c1' } },
    
    // --- 新增 20 个绝美模板 ---
    { name: '燕麦奶茶', type: 'color', value: '#F5F0E6' },
    { name: '灰豆绿', type: 'color', value: '#D9E4DD' },
    { name: '茱萸粉', type: 'color', value: '#F2D8D8' },
    { name: '克莱因蓝', type: 'color', value: '#002FA7', fontColor: '#ffffff' },
    { name: '复古牛皮', type: 'color', value: '#D2B48C' },
    { name: '日落黄昏', type: 'gradient', value: ['#ff7e5f', '#feb47b'], fontColor: '#ffffff' },
    { name: '极光之森', type: 'gradient', value: ['#43e97b', '#38f9d7'], fontColor: '#1e293b' },
    { name: '赛博霓虹', type: 'gradient', value: ['#f83600', '#f9d423'], fontColor: '#ffffff' },
    { name: '深海浩瀚', type: 'gradient', value: ['#2b5876', '#4e4376'], fontColor: '#ffffff' },
    { name: '微醺香槟', type: 'gradient', value: ['#eaddcf', '#f0e6e6'] },
    { name: '初春樱花', type: 'gradient', value: ['#ffecd2', '#fcb69f'] },
    { name: '星际迷航', type: 'gradient', value: ['#141E30', '#243B55'], fontColor: '#ffffff' },
    { name: '冰川时代', type: 'gradient', value: ['#e0c3fc', '#8ec5fc'] },
    { name: '夏日橘子', type: 'gradient', value: ['#f6d365', '#fda085'], fontColor: '#ffffff' },

    { name: '大理石纹', type: 'image', value: 'https://images.unsplash.com/photo-1518331647614-7a1f04cd34cf?q=80&w=1080&auto=format&fit=crop', fontColor: '#333333' },
    { name: '宣纸纹理', type: 'image', value: 'https://images.unsplash.com/photo-1603513492128-ba7bc9b3e143?q=80&w=1080&auto=format&fit=crop', fontColor: '#333333' },
    { name: '自然绿叶', type: 'image', value: 'https://images.unsplash.com/photo-1518531933037-91b2f5f229cc?q=80&w=1080&auto=format&fit=crop', fontColor: '#ffffff' },
    { name: '落日晚霞', type: 'image', value: 'https://images.unsplash.com/photo-1509803874385-db7c23652552?q=80&w=1080&auto=format&fit=crop', fontColor: '#ffffff' },
    { name: '抽象流体', type: 'image', value: 'https://images.unsplash.com/photo-1541701494587-cb58502866ab?q=80&w=1080&auto=format&fit=crop', fontColor: '#ffffff' },
    { name: '极简静物', type: 'image', value: 'https://images.unsplash.com/photo-1490481651871-ab68de25d43d?q=80&w=1080&auto=format&fit=crop', fontColor: '#333333' }
];

let currentNoteBg = noteBgTemplates[0];

function updateFontSizeLabel() {
    const size = document.getElementById('noteTextSize').value;
    document.getElementById('noteTextSizeVal').innerText = size + 'px';
}

function updateBgBlurLabel() {
    const blur = document.getElementById('noteBgBlur').value;
    document.getElementById('noteBgBlurVal').innerText = blur + 'px';
}

function initNoteBgs() {
    const container = document.getElementById('noteBgOptions');
    container.innerHTML = '';
    noteBgTemplates.forEach((bg, idx) => {
        const div = document.createElement('div');
        div.className = 'note-bg-item' + (idx === 0 ? ' active' : '');
        
        if (bg.type === 'color') {
            div.style.background = bg.value;
        } else if (bg.type === 'gradient') {
            div.style.background = `linear-gradient(135deg, ${bg.value[0]}, ${bg.value[1]})`;
        } else if (bg.type === 'pattern_grid') {
            div.style.backgroundColor = bg.value.bg;
            div.style.backgroundImage = `linear-gradient(${bg.value.line} 1px, transparent 1px), linear-gradient(90deg, ${bg.value.line} 1px, transparent 1px)`;
            div.style.backgroundSize = '10px 10px';
        } else if (bg.type === 'pattern_dots') {
            div.style.backgroundColor = bg.value.bg;
            div.style.backgroundImage = `radial-gradient(${bg.value.dot} 2px, transparent 2px)`;
            div.style.backgroundSize = '14px 14px';
        } else if (bg.type === 'image') {
            div.style.backgroundImage = `url(${bg.value})`;
            div.style.backgroundSize = 'cover';
            div.style.backgroundPosition = 'center';
            div.style.color = '#fff';
            div.style.textShadow = '0 1px 2px rgba(0,0,0,0.5)';
        }
        
        if (bg.value === '#1e293b') div.style.color = '#fff'; 

        div.innerText = bg.name;
        
        div.onclick = () => {
            document.querySelectorAll('.note-bg-item').forEach(el => el.classList.remove('active'));
            div.classList.add('active');
            currentNoteBg = bg;
            
            if(bg.fontColor) {
                document.getElementById('noteTextColor').value = bg.fontColor;
            } else if(bg.value !== '#1e293b') {
                document.getElementById('noteTextColor').value = '#333333';
            }
            
            drawNoteCard();
        };
        container.appendChild(div);
    });
}

function openNoteModal() {
    document.getElementById('noteModal').classList.add('active');
    initNoteBgs();
    
    const textInput = document.getElementById('noteTextInput');
    if(!textInput.value) {
        textInput.value = "如何看待生活？\n\n生活就像一盒巧克力，\n你永远不知道下一块会是什么味道。";
    }
    
    setTimeout(drawNoteCard, 100); 
}

function closeNoteModal() {
    document.getElementById('noteModal').classList.remove('active');
}

function drawNoteCard() {
    const canvas = document.getElementById('noteCanvas');
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    
    const text = document.getElementById('noteTextInput').value;
    const textColor = document.getElementById('noteTextColor').value;
    document.getElementById('noteTextColorVal').innerText = textColor;

    const blurInput = document.getElementById('noteBgBlur');
    const blurVal = blurInput ? parseInt(blurInput.value) : 0;

    const cw = 1080;
    const ch = 1440;
    
    ctx.clearRect(0, 0, cw, ch);
    
    const finalizeDraw = () => {
        ctx.filter = 'none'; 
        renderNoteText(ctx, text, textColor, cw, ch);
    };

    ctx.filter = `blur(${blurVal}px)`;
    
    const expand = blurVal * 2; 
    
    if (currentNoteBg.type === 'color') {
        ctx.fillStyle = currentNoteBg.value;
        ctx.fillRect(-expand, -expand, cw + expand*2, ch + expand*2);
        finalizeDraw();
    } 
    else if (currentNoteBg.type === 'gradient') {
        const grad = ctx.createLinearGradient(0, 0, cw, ch);
        grad.addColorStop(0, currentNoteBg.value[0]);
        grad.addColorStop(1, currentNoteBg.value[1]);
        ctx.fillStyle = grad;
        ctx.fillRect(-expand, -expand, cw + expand*2, ch + expand*2);
        finalizeDraw();
    } 
    else if (currentNoteBg.type === 'pattern_grid') {
        ctx.fillStyle = currentNoteBg.value.bg;
        ctx.fillRect(-expand, -expand, cw + expand*2, ch + expand*2);
        ctx.strokeStyle = currentNoteBg.value.line;
        ctx.lineWidth = 2;
        for(let i = -expand; i < cw + expand; i += 60) { ctx.beginPath(); ctx.moveTo(i, -expand); ctx.lineTo(i, ch + expand); ctx.stroke(); }
        for(let j = -expand; j < ch + expand; j += 60) { ctx.beginPath(); ctx.moveTo(-expand, j); ctx.lineTo(cw + expand, j); ctx.stroke(); }
        finalizeDraw();
    } 
    else if (currentNoteBg.type === 'pattern_dots') {
        ctx.fillStyle = currentNoteBg.value.bg;
        ctx.fillRect(-expand, -expand, cw + expand*2, ch + expand*2);
        ctx.fillStyle = currentNoteBg.value.dot;
        let row = 0;
        for(let j = 30 - expand; j < ch + 60 + expand; j += 60) {
            let offsetX = (row % 2 === 0) ? 30 : 60;
            for(let i = offsetX - expand; i < cw + 60 + expand; i += 60) {
                ctx.beginPath(); ctx.arc(i, j, 6, 0, Math.PI*2); ctx.fill();
            }
            row++;
        }
        finalizeDraw();
    }
    else if (currentNoteBg.type === 'image') {
        const img = new Image();
        img.crossOrigin = "Anonymous"; 
        img.onload = () => {
            const imgRatio = img.width / img.height;
            const canvasRatio = cw / ch;
            let drawW = cw, drawH = ch, offsetX = 0, offsetY = 0;
            if (imgRatio > canvasRatio) {
                drawW = ch * imgRatio;
                offsetX = (cw - drawW) / 2;
            } else {
                drawH = cw / imgRatio;
                offsetY = (ch - drawH) / 2;
            }
            ctx.drawImage(img, offsetX - expand, offsetY - expand, drawW + expand*2, drawH + expand*2);
            finalizeDraw();
        };
        img.src = currentNoteBg.value;
    }
}

function renderNoteText(ctx, text, textColor, cw, ch) {
    ctx.fillStyle = textColor;
    ctx.textBaseline = 'top';
    
    const fontSizeInput = document.getElementById('noteTextSize');
    const fontSize = fontSizeInput ? parseInt(fontSizeInput.value) : 56;
    
    const lineHeight = fontSize * 1.6;
    ctx.font = `bold ${fontSize}px "PingFang SC", "Microsoft YaHei", sans-serif`;
    
    const paddingX = 100;
    const maxWidth = cw - paddingX * 2;
    let y = 150;

    const paragraphs = text.split('\n');
    
    paragraphs.forEach(p => {
        if (p === '') { y += lineHeight; return; }
        let line = '';
        for (let i = 0; i < p.length; i++) {
            const char = p[i];
            const testLine = line + char;
            const metrics = ctx.measureText(testLine);
            if (metrics.width > maxWidth && i > 0) {
                ctx.fillText(line, paddingX, y);
                line = char;
                y += lineHeight;
            } else {
                line = testLine;
            }
        }
        ctx.fillText(line, paddingX, y);
        y += lineHeight;
    });
    
    ctx.font = `32px sans-serif`;
    ctx.fillStyle = "rgba(0,0,0,0.2)";
    if(textColor === '#ffffff') ctx.fillStyle = "rgba(255,255,255,0.4)";
    const authorName = document.getElementById('artTitle').value || 'JS·Blog';
    ctx.fillText("@" + authorName, cw - paddingX - ctx.measureText("@" + authorName).width, ch - 80);
}

async function generateAndInsertNote() {
    const canvas = document.getElementById('noteCanvas');
    const btn = document.getElementById('btnGenerateNote');
    const originalText = btn.innerHTML;
    
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> 正在生成上传...';
    
    try {
        const base64Data = canvas.toDataURL('image/png');
        
        const formData = new URLSearchParams();
        formData.append('action', 'upload_base64');
        formData.append('image', base64Data);

        const response = await fetch('articles.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: formData.toString()
        });
        
        const res = await response.json();
        
        if (res.success) {
            document.getElementById('artMediaType').value = 'images';
            toggleMediaMode();
            addImageInput(res.url);
            closeNoteModal();
            alert('卡片生成成功，已添加至文章图片列表中！');
        } else {
            throw new Error(res.msg || '上传失败');
        }
    } catch (err) {
        console.error(err);
        alert('生成错误：' + err.message);
    } finally {
        btn.disabled = false;
        btn.innerHTML = originalText;
    }
}

function uploadCustomNoteBg(input) {
    if (input.files && input.files[0]) {
        const file = input.files[0];
        
        if (!file.type.startsWith('image/')) {
            alert('请选择有效的图片文件！');
            input.value = ''; 
            return;
        }
        
        const reader = new FileReader();
        reader.onload = function(e) {
            document.querySelectorAll('.note-bg-item').forEach(el => el.classList.remove('active'));
            currentNoteBg = {
                name: '自定义',
                type: 'image',
                value: e.target.result
            };
            drawNoteCard();
            input.value = '';
        };
        reader.readAsDataURL(file);
    }
}

// 绑定全局
window.openAiModal = openAiModal;
window.closeAiModal = closeAiModal;
window.startAiGenerate = startAiGenerate;
window.toggleSelect = toggleSelect;
window.toggleAllCards = toggleAllCards;
window.submitBatch = submitBatch;
window.openMoveModal = openMoveModal;
window.confirmMove = confirmMove;
window.openModal = openModal;
window.editArticle = editArticle;
window.closeModal = closeModal;
window.toggleMediaMode = toggleMediaMode;
window.addImageInput = addImageInput;
window.previewLocalImage = previewLocalImage;
window.openNoteModal = openNoteModal;
window.closeNoteModal = closeNoteModal;
window.drawNoteCard = drawNoteCard;
window.generateAndInsertNote = generateAndInsertNote;
window.updateFontSizeLabel = updateFontSizeLabel;
window.updateBgBlurLabel = updateBgBlurLabel;
window.uploadCustomNoteBg = uploadCustomNoteBg;