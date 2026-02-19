document.addEventListener('DOMContentLoaded', () => {

    const isUserLogin = window.siteData.isUserLogin;
    const currentUserId = window.siteData.currentUserId;
    const csrfToken = window.siteData.csrfToken;
    const enableChatroom = window.siteData.enableChatroom;

    // ==========================================
    //       核心文章列表逻辑
    // ==========================================
    const state = { page: 1, limit: 6, totalPages: 1, category: 'all', keyword: '', isLoading: false, isMobile: window.innerWidth <= 1024 };
    const container = document.getElementById('articleContainer');
    const pagination = document.getElementById('pagination');

    function loadArticles(isReset = false) {
        if (state.isLoading) return;
        state.isLoading = true;
        container.innerHTML = '<div style="text-align:center; padding:40px; color:#999;"><i class="fa-solid fa-spinner fa-spin"></i> 加载中...</div>';

        const apiUrl = `api/index.php?action=get_list&page=${state.page}&category=${encodeURIComponent(state.category)}&keyword=${encodeURIComponent(state.keyword)}`;
        fetch(apiUrl).then(res => res.json()).then(data => {
            state.isLoading = false;
            state.totalPages = data.total_pages;
            container.innerHTML = '';
            renderHTML(data.articles);
            renderPagination(data.total_pages, data.current_page);
            if (state.isMobile && !isReset) {
                const offsetTop = document.querySelector('.main-grid').offsetTop - 80;
                window.scrollTo({ top: offsetTop, behavior: 'smooth' });
            }
        }).catch(err => {
            console.error(err);
            state.isLoading = false;
            container.innerHTML = '<div style="width:100%; padding:20px; text-align:center; color:#999;">加载失败，请刷新重试</div>';
        });
    }

    function renderHTML(list) {
        if (list.length === 0 && state.page === 1) {
            container.innerHTML = '<div style="width:100%; padding:40px; text-align:center; color:#999; grid-column: 1 / -1;">暂无相关文章</div>';
            return;
        }
        const formatNum = (num) => num > 999 ? (num / 1000).toFixed(1) + 'k' : num;
        const htmlStr = list.map(art => {
            const imgUrl = art.cover_image ? art.cover_image : 'https://placehold.co/600x400?text=No+Image';
            return `
            <div class="glass-card article-card" onclick="openArticle(${art.id})">
                <div class="ac-thumb"><img src="${imgUrl}" loading="lazy" alt="${art.title}"></div>
                <div class="ac-info">
                    <div class="ac-meta"><span class="ac-cat-tag">${art.category}</span><span>${art.date}</span></div>
                    <div class="ac-title">${art.title}</div>
                    <p class="ac-desc">${art.summary}</p>
                    <div class="ac-footer">
                        <div class="ac-read-btn">阅读全文 <i class="fa-solid fa-arrow-right"></i></div>
                        <div class="ac-stats">
                            <span class="stat-item"><i class="fa-regular fa-eye"></i> ${formatNum(art.views)}</span>
                            <span class="stat-item"><i class="fa-regular fa-heart"></i> ${formatNum(art.likes)}</span>
                            <span class="stat-item"><i class="fa-regular fa-comment"></i> ${formatNum(art.comments_count)}</span>
                        </div>
                    </div>
                </div>
            </div>`;
        }).join('');
        container.insertAdjacentHTML('beforeend', htmlStr);
    }

    function renderPagination(total, current) {
        if (!pagination || total <= 1) { if(pagination) pagination.innerHTML = ''; return; }
        let html = `<div class="pg-btn ${current === 1 ? 'disabled' : ''}" onclick="${current > 1 ? `changePage(${current - 1})` : ''}"><i class="fa-solid fa-chevron-left"></i></div>`;
        if (window.innerWidth <= 768) {
            html += `<div style="font-weight:600; color:#555; padding:0 10px; font-size:14px;">${current} <span style="opacity:0.4; margin:0 3px;">/</span> ${total}</div>`;
        } else {
            const showRange = [...new Set([1, total, ...Array.from({length: 5}, (_, i) => current - 2 + i).filter(n => n > 1 && n < total)])].sort((a,b) => a-b);
            let lastNum = 0;
            showRange.forEach(num => {
                if (lastNum > 0 && num - lastNum > 1) html += `<div class="pg-dots">...</div>`;
                html += `<div class="pg-btn ${num === current ? 'active' : ''}" onclick="changePage(${num})">${num}</div>`;
                lastNum = num;
            });
            html += `<div class="pg-jump-wrap"><input type="number" class="pg-input" id="jumpInput" min="1" max="${total}" placeholder="Go" onkeypress="handleJumpEnter(event, ${total})"></div>`;
        }
        html += `<div class="pg-btn ${current === total ? 'disabled' : ''}" onclick="${current < total ? `changePage(${current + 1})` : ''}"><i class="fa-solid fa-chevron-right"></i></div>`;
        pagination.innerHTML = html;
        pagination.style.display = 'flex';
    }

    window.handleJumpEnter = (e, total) => { if (e.key === 'Enter') { let val = parseInt(e.target.value); if (val >= 1 && val <= total) changePage(val); else alert('页码无效'); } };
    window.filterCategory = (cat) => { document.querySelectorAll('.cat-item').forEach(el => el.classList.remove('active')); event.target.classList.add('active'); state.category = cat; state.page = 1; loadArticles(true); };
    window.searchArticles = (keyword) => { document.getElementById('searchInput').value = keyword; state.keyword = keyword; state.page = 1; loadArticles(true); };
    window.changePage = (page) => { if (page < 1 || page > state.totalPages) return; state.page = page; loadArticles(true); if (!state.isMobile) { const offsetTop = document.querySelector('.main-grid').offsetTop - 80; window.scrollTo({ top: offsetTop, behavior: 'smooth' }); } };
    
    // 监听搜索框输入
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        let debounceTimer;
        searchInput.addEventListener('keyup', (e) => {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => {
                searchArticles(e.target.value);
            }, 300); // 300ms 防抖
        });
    }

    window.addEventListener('resize', () => {
        const isNowMobile = window.innerWidth <= 1024;
        if (isNowMobile !== state.isMobile) {
            state.isMobile = isNowMobile;
            state.page = 1; loadArticles(true);
        } else if (pagination && state.totalPages > 0) {
            renderPagination(state.totalPages, state.page);
        }
    });

    if(container) loadArticles(true);

    // --- 聊天室逻辑 ---
    if (enableChatroom) {
        const chatMessages = document.getElementById('chatMessages');
        const chatInput = document.getElementById('chatInput');
        const pcPicker = document.getElementById('pcEmojiPicker');
        let lastMsgId = 0;
        const emojis = ["😀", "😁", "😂", "🤣", "😃", "😄", "😅", "😆", "😉", "😊", "😋", "😎", "😍", "😘", "🥰", "😗", "😙", "😚", "🙂", "🤗", "🤩", "🤔", "🤨", "😐", "😑", "😶", "🙄", "😏", "😣", "😥", "😮", "🤐", "😯", "😪", "😫", "😴", "😌", "😛", "😜", "😝", "🤤", "😒", "😓", "😔", "😕", "🙃", "🤑", "😲", "☹️", "🙁", "😖", "😞", "😟", "😤", "😢", "😭", "😦", "😧", "V", "😨", "😩", "🤯", "😬", "😰", "😱", "🥵", "🥶", "😳", "🤪", "😵", "😡", "😠", "🤬", "😷", "🤒", "🤕", "🤢", "🤮", "🤧", "😇", "🤠", "🤡", "🥳", "🥴", "🥺", "🤥", "🤫", "🤭", "🧐", "🤓", "😈", "👿", "👹", "👺", "💀", "👻", "👽", "🤖", "💩", "😺", "😸", "😹", "😻", "😼", "😽", "🙀", "😿", "😾"];
        if (pcPicker) {
            emojis.forEach(e => {
                const span = document.createElement('span');
                span.className = 'emoji-item';
                span.innerText = e;
                span.onclick = () => { chatInput.value += e; pcPicker.classList.remove('active'); chatInput.focus(); };
                pcPicker.appendChild(span);
            });
        }
        window.togglePcEmoji = () => { if(pcPicker) pcPicker.classList.toggle('active'); };
        document.addEventListener('click', (e) => { const btn = document.querySelector('.emoji-btn'); if (pcPicker && !pcPicker.contains(e.target) && !btn.contains(e.target)) pcPicker.classList.remove('active'); });
        
        function loadMessages() {
            fetch('api/chatroom.php?action=get_messages').then(res => res.json()).then(res => {
                if (res.success) {
                    const input = document.getElementById('chatInput'); const btn = document.querySelector('.chat-send');
                    if (res.is_muted) { input.disabled = true; input.placeholder = "全员禁言中..."; if (btn) btn.disabled = true; } 
                    else if (isUserLogin) { input.disabled = false; input.placeholder = "说点什么..."; if (btn) btn.disabled = false; }
                    const data = res.data;
                    if (data.length === 0) { chatMessages.innerHTML = '<div style="text-align:center; color:#999; font-size:12px; margin-top:50px;">暂无消息，快来抢沙发</div>'; return; }
                    const newestId = data[data.length - 1].id;
                    if (newestId > lastMsgId) {
                        chatMessages.innerHTML = '';
                        data.forEach(msg => {
                            const isSelf = parseInt(msg.user_id) === currentUserId;
                            const div = document.createElement('div');
                            div.className = `chat-msg ${isSelf ? 'self' : ''}`;
                            let avatarHtml = !msg.nickname ? `<div class="chat-avatar" style="background:#e5e7eb; display:flex; align-items:center; justify-content:center; color:#9ca3af; font-size:14px;"><i class="fa-solid fa-user-slash"></i></div>` : `<img src="${msg.avatar || 'assets/images/default-avatar.png'}" class="chat-avatar" onerror="this.src='https://placehold.co/100x100/e5e7eb/9ca3af?text=Err'">`;
                            div.innerHTML = `${avatarHtml}<div><div class="chat-name">${msg.nickname || '该用户已注销'}</div><div class="chat-bubble">${msg.message}</div></div>`;
                            chatMessages.appendChild(div);
                        });
                        chatMessages.scrollTop = chatMessages.scrollHeight; lastMsgId = newestId;
                    }
                }
            });
        }
        window.sendChat = () => {
            if (!isUserLogin) return alert('请先登录');
            const msg = chatInput.value.trim(); if (!msg) return;
            const formData = new FormData(); formData.append('message', msg);
            fetch('api/chatroom.php?action=send_message', { method: 'POST', body: formData }).then(res => res.json()).then(res => { if (res.success) { chatInput.value = ''; loadMessages(); } else { alert(res.msg); } });
        };
        if(chatInput) chatInput.addEventListener('keypress', (e) => { if (e.key === 'Enter') sendChat(); });
        if(chatMessages) { loadMessages(); setInterval(loadMessages, 3000); }
    }

    // --- 幻灯片逻辑 ---
    const sliderTrack = document.getElementById('sliderTrack');
    const prevBtn = document.querySelector('.prev-btn');
    const nextBtn = document.querySelector('.next-btn');
    const dots = document.querySelectorAll('.dot');
    let currentSlide = 0; const totalSlides = dots.length;
    function updateSlider() { if (sliderTrack && totalSlides > 0) { sliderTrack.style.transform = `translateX(-${currentSlide * 100}%)`; dots.forEach((dot, index) => { dot.classList.toggle('active', index === currentSlide); }); } }
    window.goToSlide = (index) => { currentSlide = index; updateSlider(); };
    if (nextBtn && prevBtn && totalSlides > 0) {
        nextBtn.addEventListener('click', () => { currentSlide = (currentSlide + 1) % totalSlides; updateSlider(); });
        prevBtn.addEventListener('click', () => { currentSlide = (currentSlide - 1 + totalSlides) % totalSlides; updateSlider(); });
        setInterval(() => { currentSlide = (currentSlide + 1) % totalSlides; updateSlider(); }, 5000);
    }

    // ==========================================
    //       文章详情弹窗
    // ==========================================
    const modal = document.getElementById('articleModal');
    
    window.openArticle = (id) => {
        if(!modal) return;
        modal.classList.add('active');
        const modalBody = document.getElementById('modalBody');
        const modalHeaderTitle = document.getElementById('modalHeaderTitle');
        modalBody.innerHTML = '<div style="text-align:center;padding:50px;color:#999;"><i class="fa-solid fa-spinner fa-spin fa-2x"></i><br><br>加载中...</div>';
        if (modalHeaderTitle) modalHeaderTitle.innerText = '加载中...';
        modalBody.scrollTop = 0;
        fetch(`api/index.php?action=get_article&id=${id}`).then(res => res.json()).then(data => {
            if (data.error) { modalBody.innerHTML = `<p style="text-align:center;padding:20px;">${data.error}</p>`; if (modalHeaderTitle) modalHeaderTitle.innerText = '错误'; return; }
            if (modalHeaderTitle) modalHeaderTitle.innerText = data.title;
            const createdDate = data.created_at ? data.created_at.substring(0, 10) : '未知日期';
            modalBody.innerHTML = `
                <h1 class="modal-title">${data.title}</h1>
                <div style="color:#999; margin-bottom:20px; font-size:13px; display:flex; align-items:center;">
                    <span class="ac-cat-tag" style="margin-right:10px;">${data.category}</span>
                    <span><i class="fa-regular fa-clock"></i> ${createdDate}</span>
                    <span style="margin-left:15px"><i class="fa-solid fa-eye"></i> ${data.views}</span>
                </div>
                ${data.cover_image ? `<img src="${data.cover_image}" style="width:100%; height:auto; display:block; border-radius:12px; margin-bottom:20px;">` : ''}
                <div class="article-content" id="articleContentArea" style="line-height:1.8; font-size:16px; color:#333;">${data.content}</div>
                <div class="action-bar"><div class="action-btn ${data.is_liked ? 'liked' : ''}" onclick="likeArticle(${data.id}, this)"><i class="${data.is_liked ? 'fa-solid fa-heart fa-bounce' : 'fa-regular fa-heart'}"></i> <span>${data.likes}</span> 点赞</div></div>
                <div class="comment-section">
                    <h3><i class="fa-regular fa-comments"></i> 评论</h3>
                    <div class="comment-list" id="commentList-${data.id}">${data.comments.length > 0 ? data.comments.map(c => `<div class="comment-item"><strong><i class="fa-regular fa-user"></i> ${c.username}</strong> <span style="color:#aaa;font-size:12px">${c.created_at}</span><p style="margin-top:5px; color:#555;">${c.content}</p></div>`).join('') : '<div style="color:#aaa; font-size:13px; padding:10px 0;">暂无评论，快来抢沙发~</div>'}</div>
                    <div style="margin-top:15px; display:flex; gap:10px;">
                        <input type="text" class="comment-input" id="input-${data.id}" placeholder="${isUserLogin ? '写下你的评论...' : '请先登录后评论'}">
                        <button onclick="postComment(${data.id}, event)" style="background:#000; color:#fff; border:none; padding:0 20px; border-radius:8px; cursor:pointer; height:42px; white-space:nowrap;">发送</button>
                    </div>
                </div>`;
            setTimeout(initCodeBlocks, 50);
        }).catch(err => { console.error(err); modalBody.innerHTML = '<p style="text-align:center;padding:20px;">加载失败，请检查网络或控制台错误。</p>'; });
    };

    window.closeModal = () => { if(modal) modal.classList.remove('active'); };
    if(modal) modal.addEventListener('click', (e) => { if (e.target === modal) closeModal(); });

    window.likeArticle = (id, btn) => {
        if (!isUserLogin) { if (confirm("点赞需要先登录，去登录？")) openAuthModal('login'); return; }
        fetch(`api/index.php?action=like&id=${id}`).then(res => res.json()).then(data => {
            if (data.success) {
                btn.classList.toggle('liked', data.liked);
                btn.querySelector('i').className = `fa-${data.liked ? 'solid' : 'regular'} fa-heart ${data.liked ? 'fa-bounce' : ''}`;
                btn.querySelector('span').innerText = data.new_likes;
            } else { alert(data.msg || '操作失败'); }
        });
    };

    window.postComment = (id, event) => {
        if (!isUserLogin) { if (confirm("评论需要先登录，去登录？")) openAuthModal('login'); return; }
        const input = document.getElementById(`input-${id}`); const content = input.value.trim();
        if (!content) { alert("请输入评论内容"); return; }
        if (content.length > 500) { alert("评论内容太长了（最多500字）"); return; }
        const sendBtn = event.target; const originalText = sendBtn.innerText;
        sendBtn.disabled = true; sendBtn.innerText = "发送中..."; sendBtn.style.opacity = "0.6";
        const formData = new FormData(); formData.append('article_id', id); formData.append('content', content); formData.append('csrf_token', csrfToken);
        fetch(`api/index.php?action=comment`, { method: 'POST', body: formData }).then(res => res.json()).then(data => {
            sendBtn.disabled = false; sendBtn.innerText = originalText; sendBtn.style.opacity = "1";
            if (data.success) {
                const list = document.getElementById(`commentList-${id}`);
                if (list.innerText.includes('暂无评论')) list.innerHTML = '';
                const newItem = document.createElement('div'); newItem.className = 'comment-item';
                const safeContent = content.replace(/</g, "&lt;").replace(/>/g, "&gt;");
                newItem.innerHTML = `<strong><i class="fa-regular fa-user"></i> ${window.siteData.currentUserName}</strong> <span style="color:#aaa;font-size:12px">刚刚</span><p style="margin-top:5px; color:#555;">${safeContent}</p>`;
                list.prepend(newItem); input.value = '';
            } else { alert(data.msg || '评论失败'); }
        }).catch(() => {
            sendBtn.disabled = false; sendBtn.innerText = originalText; sendBtn.style.opacity = "1";
            alert("网络请求失败，请稍后重试");
        });
    };

    function copyToClipboard(text) { return navigator.clipboard ? navigator.clipboard.writeText(text) : new Promise((res, rej) => { try { const ta = document.createElement("textarea"); ta.value = text; ta.style.position = "fixed"; ta.style.left = "-9999px"; document.body.appendChild(ta); ta.select(); document.execCommand('copy') ? res() : rej(); document.body.removeChild(ta); } catch (err) { rej(err); } }); }
    function initCodeBlocks() {
        document.querySelectorAll('.article-content pre').forEach(pre => {
            if (pre.querySelector('.copy-code-btn')) return;
            const btn = document.createElement('button'); btn.className = 'copy-code-btn'; btn.innerHTML = '<i class="fa-regular fa-copy"></i> 复制';
            btn.onclick = (e) => {
                e.stopPropagation(); const codeText = pre.querySelector('code').innerText;
                copyToClipboard(codeText).then(() => { btn.innerHTML = '<i class="fa-solid fa-check"></i> 已复制'; btn.style.background = 'rgba(40, 167, 69, 0.6)'; setTimeout(() => { btn.innerHTML = '<i class="fa-regular fa-copy"></i> 复制'; btn.style.background = ''; }, 2000); }).catch(() => { btn.innerHTML = '<i class="fa-solid fa-xmark"></i> 失败'; btn.style.background = 'rgba(220, 53, 69, 0.6)'; setTimeout(() => { btn.innerHTML = '<i class="fa-regular fa-copy"></i> 复制'; btn.style.background = ''; }, 2000); });
            };
            pre.appendChild(btn);
        });
        if (window.Prism) Prism.highlightAllUnder(document.getElementById('articleContentArea'));
    }
});
