document.addEventListener('DOMContentLoaded', () => {

    const isUserLogin = window.siteData.isUserLogin;
    const currentUserId = window.siteData.currentUserId;
    const csrfToken = window.siteData.csrfToken;
    const enableChatroom = window.siteData.enableChatroom;

    // ==========================================
    //       1. 文章列表与分页逻辑
    // ==========================================
    const state = { page: 1, limit: 6, totalPages: 1, category: 'all', keyword: '', isLoading: false, isMobile: window.innerWidth <= 1024 };
    const container = document.getElementById('articleContainer');
    const pagination = document.getElementById('pagination');

    function loadArticles(isReset = false) {
        if (state.isLoading) return;
        state.isLoading = true;
        container.innerHTML = '<div style="text-align:center; padding:40px; color:#999; grid-column:1/-1;"><i class="fa-solid fa-spinner fa-spin"></i> 加载中...</div>';

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
            container.innerHTML = '<div style="width:100%; padding:20px; text-align:center; color:#999; grid-column:1/-1;">加载失败，请刷新重试</div>';
        });
    }

    function renderHTML(list) {
        if (list.length === 0 && state.page === 1) {
            container.innerHTML = '<div style="width:100%; padding:40px; text-align:center; color:#999; grid-column: 1 / -1;">暂无相关文章</div>';
            return;
        }
        const formatNum = (num) => num > 999 ? (num / 1000).toFixed(1) + 'k' : num;
        const htmlStr = list.map(art => {
            const imgUrl = art.cover_image ? art.cover_image : 'https://placehold.co/600x800?text=No+Image';
            return `
            <div class="article-card" onclick="openArticle(${art.id})">
                <div class="ac-thumb"><img src="${imgUrl}" loading="lazy" alt="${art.title}"></div>
                <div class="ac-info">
                    <div class="ac-title">${art.title}</div>
                    <div class="ac-desc">${art.summary}</div>
                    <div class="ac-bottom">
                        <span class="ac-tag">${art.category}</span>
                        <div class="ac-stats">
                            <span class="stat-item"><i class="fa-regular fa-eye"></i> ${formatNum(art.views)}</span>
                            <span class="stat-item"><i class="fa-regular fa-heart"></i> ${formatNum(art.likes)}</span>
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
    
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        let debounceTimer;
        searchInput.addEventListener('keyup', (e) => {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => { searchArticles(e.target.value); }, 300);
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

    // ==========================================
    //       2. 聊天室逻辑 (已优化表情功能)
    // ==========================================
    if (enableChatroom) {
        const chatContainer = document.getElementById('chatMessages');
        const chatInput = document.getElementById('chatInput');
        
        // --- [新增] 表情功能核心逻辑 ---
        const emojiPicker = document.getElementById('pcEmojiPicker');
        const emojiBtn = document.querySelector('.emoji-btn');
        const emojis = ['😀','😁','😂','🤣','😃','😄','😅','😆','😉','😊','😋','😎','😍','😘','🥰','😗','😙','😚','🙂','🤗','🤩','🤔','🤨','😐','😑','😶','🙄','😏','😣','😥','😮','😯','😪','😫','😴','😌','😛','😜','😝','🤤','😒','😓','😔','😕','🙃','🤑','😲','☹️','🙁','😖','😞','😟','😤','😢','😭','😦','😧','😨','😩','🤯','😬','😰','😱','🥵','🥶','😳','🤪','😵','😡','😠','🤬','😷','🤒','🤕','🤢','🤮','🤧','😇','🤠','🤡','🥳','🥴','🥺','🤥','🤫','🤭','🧐','🤓','😈','👿','👹','👺','💀','👻','👽','🤖','💩','😺','😸','😹','😻','😼','😽','🙀','😿','😾','🙈','🙉','🙊','👍','👎','👊','✊','🤛','🤜','🤞','✌️','🤟','🤘','👌','🤏','👈','👉','👆','👇','☝️','✋','🤚','🖐','🖖','👋','🤙','💪','🖕','✍️','🙏'];

        // 1. 渲染表情列表
        if (emojiPicker) {
            emojiPicker.innerHTML = emojis.map(e => `<div class="emoji-item">${e}</div>`).join('');
            
            // 2. 表情点击事件代理
            emojiPicker.addEventListener('click', (e) => {
                if(e.target.classList.contains('emoji-item')) {
                    const emoji = e.target.innerText;
                    // 在光标位置插入表情 (如果支持) 或 直接追加
                    if (chatInput.selectionStart || chatInput.selectionStart == '0') {
                        var startPos = chatInput.selectionStart;
                        var endPos = chatInput.selectionEnd;
                        chatInput.value = chatInput.value.substring(0, startPos) + emoji + chatInput.value.substring(endPos, chatInput.value.length);
                        chatInput.selectionStart = startPos + emoji.length;
                        chatInput.selectionEnd = startPos + emoji.length;
                    } else {
                        chatInput.value += emoji;
                    }
                    
                    emojiPicker.classList.remove('active'); // 选完后自动关闭
                    chatInput.focus(); // 聚焦回输入框
                }
            });
        }

        // 3. 全局切换显示函数 (挂载到window以便HTML onclick调用)
        window.togglePcEmoji = (e) => {
            // 阻止冒泡，防止触发 document 的关闭事件
            if(e) e.stopPropagation(); 
            if(emojiPicker) {
                const isActive = emojiPicker.classList.contains('active');
                // 先关闭所有其他可能弹出的层
                document.querySelectorAll('.emoji-picker').forEach(el => el.classList.remove('active'));
                
                if (!isActive) {
                    emojiPicker.classList.add('active');
                } else {
                    emojiPicker.classList.remove('active');
                }
            }
        };

        // 4. 点击页面其他空白区域关闭表情面板
        document.addEventListener('click', (e) => {
            if (emojiPicker && emojiPicker.classList.contains('active')) {
                // 如果点击的既不是表情面板内部，也不是触发按钮，则关闭
                if (!emojiPicker.contains(e.target) && (!emojiBtn || !emojiBtn.contains(e.target))) {
                    emojiPicker.classList.remove('active');
                }
            }
        });

        // --- 以下为原有的消息加载与发送逻辑 ---
        let pollingInterval = null;
        let lastMsgCount = 0;

        const loadChatMessages = () => {
            fetch('api/chatroom.php?action=get_messages')
                .then(res => res.json())
                .then(res => {
                    if (!res.success) return;
                    const messages = res.data || [];
                    if (messages.length === lastMsgCount && lastMsgCount !== 0) return;
                    lastMsgCount = messages.length;
                    if (messages.length === 0) {
                        chatContainer.innerHTML = '<div style="text-align:center; color:#999; font-size:12px; margin-top:50px;">暂无消息，来做第一个发言的人吧~</div>';
                        return;
                    }
                    const html = messages.map(msg => {
                        const isMe = parseInt(msg.user_id) === parseInt(currentUserId);
                        const avatar = msg.avatar || `https://api.dicebear.com/7.x/avataaars/svg?seed=${encodeURIComponent(msg.nickname || 'User')}`;
                        return `
                        <div class="chat-row ${isMe ? 'chat-right' : 'chat-left'}" style="display:flex; margin-bottom:15px; flex-direction:${isMe?'row-reverse':'row'}; align-items:flex-start; gap:10px;">
                            <div class="chat-avatar" style="width:36px; height:36px; flex-shrink:0;">
                                <img src="${avatar}" style="width:100%; height:100%; border-radius:50%; object-fit:cover;">
                            </div>
                            <div class="chat-bubble-wrap" style="max-width:75%; display:flex; flex-direction:column; align-items:${isMe?'flex-end':'flex-start'};">
                                ${!isMe ? `<div class="chat-nick" style="font-size:12px; color:#888; margin-bottom:2px;">${msg.nickname}</div>` : ''}
                                <div class="chat-bubble" style="background:${isMe?'#333':'#f1f2f6'}; color:${isMe?'#fff':'#333'}; padding:8px 12px; border-radius:12px; font-size:14px; line-height:1.5; word-break:break-all;">
                                    ${msg.message}
                                </div>
                            </div>
                        </div>`;
                    }).join('');
                    chatContainer.innerHTML = html;
                    chatContainer.scrollTop = chatContainer.scrollHeight;
                })
                .catch(err => console.error("Chat Error:", err));
        };

        window.sendChat = () => {
            if (!isUserLogin) { 
                if(confirm("请先登录")) { if(typeof openAuthModal === 'function') openAuthModal('login'); } 
                return; 
            }
            const msg = chatInput.value.trim();
            if (!msg) return;
            chatInput.value = '';
            // 发送后关闭表情面板
            if(emojiPicker) emojiPicker.classList.remove('active');

            const formData = new FormData();
            formData.append('message', msg);
            if(csrfToken) formData.append('csrf_token', csrfToken);

            fetch('api/chatroom.php?action=send_message', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => { if (data.success) { loadChatMessages(); } else { alert(data.msg || "发送失败"); chatInput.value = msg; } })
            .catch(() => { alert("网络错误"); chatInput.value = msg; });
        };

        if(chatInput) {
            chatInput.addEventListener('keypress', (e) => { if (e.key === 'Enter') sendChat(); });
        }
        loadChatMessages();
        pollingInterval = setInterval(loadChatMessages, 3000);
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) clearInterval(pollingInterval);
            else { loadChatMessages(); pollingInterval = setInterval(loadChatMessages, 3000); }
        });
    }

    // ==========================================
    //       3. 首页幻灯片切换逻辑
    // ==========================================
    const sliderTrack = document.getElementById('sliderTrack');
    if (sliderTrack) {
        const slides = sliderTrack.querySelectorAll('.slider-item');
        const dots = document.querySelectorAll('.slider-dots .dot');
        const prevBtn = document.querySelector('.prev-btn');
        const nextBtn = document.querySelector('.next-btn');
        let currentSlide = 0;
        let slideInterval;

        // 全局绑定，供 HTML 中的 dots 点击调用
        window.goToSlide = (index) => {
            if (slides.length <= 1) return;
            currentSlide = (index + slides.length) % slides.length;
            sliderTrack.style.transform = `translateX(-${currentSlide * 100}%)`;
            dots.forEach((dot, i) => dot.classList.toggle('active', i === currentSlide));
        };

        const nextSlide = () => goToSlide(currentSlide + 1);
        const prevSlide = () => goToSlide(currentSlide - 1);

        // 绑定左右按钮点击事件
        if (prevBtn) prevBtn.addEventListener('click', () => { prevSlide(); resetInterval(); });
        if (nextBtn) nextBtn.addEventListener('click', () => { nextSlide(); resetInterval(); });

        const startInterval = () => {
            if (slides.length > 1) {
                slideInterval = setInterval(nextSlide, 4000); // 4秒自动切换
            }
        };

        const resetInterval = () => {
            clearInterval(slideInterval);
            startInterval();
        };

        // 启动自动轮播
        startInterval();
    }
    
    // ==========================================
    //       4. 文章详情弹窗
    // ==========================================
    const modal = document.getElementById('articleModal');
    
    window.openArticle = (id) => {
        if(!modal) return;
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';

        const modalBody = document.getElementById('modalBody');
        modalBody.innerHTML = '<div style="text-align:center;padding:100px;color:#999;width:100%;"><i class="fa-solid fa-spinner fa-spin fa-2x"></i><br><br>加载中...</div>';
        
        fetch(`api/index.php?action=get_article&id=${id}`).then(res => res.json()).then(data => {
            if (data.error) { modalBody.innerHTML = `<p style="text-align:center;padding:20px;">${data.error}</p>`; return; }
            
            const createdDate = data.created_at ? data.created_at.substring(0, 10) : '未知日期';

            // --- 构建媒体显示 ---
            let imgDisplay = '';
            let mediaTypeClass = 'is-image'; 
            
            if (data.media_type === 'video') {
                mediaTypeClass = 'is-video'; 
                let md = {};
                try { md = JSON.parse(data.media_data || '{}'); } catch(e){}
                const vUrl = md.video || '';
                const cUrl = md.cover || data.cover_image || '';
                // 视频保持 contain 否则裁剪太严重
                imgDisplay = `<video controls autoplay poster="${cUrl}"><source src="${vUrl}" type="video/mp4">不支持视频</video>`;
            } else if (data.media_type === 'images' && data.media_data && data.media_data !== '[]') {
                let imgs = [];
                try { imgs = JSON.parse(data.media_data); } catch(e){}
                if (imgs.length > 1) {
                    window.xhsImgs = imgs;
                    window.xhsCurrent = 0;
                    window.xhsSlide = (dir) => {
                        const slideImgs = document.querySelectorAll('.xhs-slide-img');
                        const slideDots = document.querySelectorAll('.xhs-dot');
                        if(slideImgs[window.xhsCurrent]) slideImgs[window.xhsCurrent].style.opacity = 0;
                        if(slideDots[window.xhsCurrent]) slideDots[window.xhsCurrent].style.background = 'rgba(255,255,255,0.4)';
                        window.xhsCurrent = (window.xhsCurrent + dir + imgs.length) % imgs.length;
                        if(slideImgs[window.xhsCurrent]) slideImgs[window.xhsCurrent].style.opacity = 1;
                        if(slideDots[window.xhsCurrent]) slideDots[window.xhsCurrent].style.background = '#fff'; 
                        const blurBg = document.getElementById('xhsBlurBg');
                        if (blurBg) blurBg.style.backgroundImage = `url('${imgs[window.xhsCurrent]}')`;
                    };
                    // 【修复核心】：这里将 object-fit: contain 替换成了 object-fit: cover 填满容器
                    imgDisplay = `
                        <div class="xhs-blur-bg" id="xhsBlurBg" style="background-image: url('${imgs[0]}');"></div>
                        <div style="position:relative; width:100%; height:100%; overflow:hidden; z-index:1;">
                            ${imgs.map((src, i) => `<img src="${src}" class="xhs-slide-img" style="position:absolute; width:100%; height:100%; object-fit:cover; transition:opacity 0.3s ease; opacity:${i===0?1:0}; top:0; left:0;">`).join('')}
                            <button onclick="xhsSlide(-1)" style="position:absolute; left:15px; top:50%; transform:translateY(-50%); background:rgba(0,0,0,0.3); color:#fff; border:none; width:36px; height:36px; border-radius:50%; cursor:pointer; z-index:10; display:flex; align-items:center; justify-content:center; backdrop-filter:blur(4px); transition:0.2s;"><i class="fa-solid fa-chevron-left"></i></button>
                            <button onclick="xhsSlide(1)" style="position:absolute; right:15px; top:50%; transform:translateY(-50%); background:rgba(0,0,0,0.3); color:#fff; border:none; width:36px; height:36px; border-radius:50%; cursor:pointer; z-index:10; display:flex; align-items:center; justify-content:center; backdrop-filter:blur(4px); transition:0.2s;"><i class="fa-solid fa-chevron-right"></i></button>
                            <div style="position:absolute; bottom:20px; left:50%; transform:translateX(-50%); display:flex; gap:6px; z-index:10;">
                                ${imgs.map((_, i) => `<div class="xhs-dot" style="width:6px; height:6px; border-radius:50%; background:${i===0?'#fff':'rgba(255,255,255,0.4)'}; transition:0.3s;"></div>`).join('')}
                            </div>
                        </div>`;
                } else if (imgs.length === 1) {
                    // 【修复核心】：这里将 object-fit: contain 替换成了 object-fit: cover
                    imgDisplay = `
                        <div class="xhs-blur-bg" style="background-image: url('${imgs[0]}');"></div>
                        <img src="${imgs[0]}" style="position:relative; z-index:1; width:100%; height:100%; object-fit:cover;">
                    `;
                }
            }
            
            if (!imgDisplay) {
                let finalImg = data.cover_image;
                if (!finalImg) {
                    const imgMatch = data.content.match(/<img[^>]+src="([^">]+)"/);
                    if (imgMatch) finalImg = imgMatch[1];
                }
                if (finalImg) {
                    // 【修复核心】：这里将 object-fit: contain 替换成了 object-fit: cover
                    imgDisplay = `
                        <div class="xhs-blur-bg" style="background-image: url('${finalImg}');"></div>
                        <img src="${finalImg}" alt="cover" style="position:relative; z-index:1; width:100%; height:100%; object-fit:cover;">
                    `;
                } else {
                    imgDisplay = `<div style="width:100%; height:100%; display:flex; align-items:center; justify-content:center; background: #000; color:#444; position:relative; z-index:1;"><i class="fa-regular fa-image" style="font-size:48px;"></i></div>`;
                }
            }

            modalBody.innerHTML = `
                <div class="xhs-container">
                    <div class="xhs-left ${mediaTypeClass}">
                        ${imgDisplay}
                    </div>
                    
                    <div class="xhs-right">
                        <div class="xhs-content-scroll">
                            <h1 class="xhs-title">${data.title}</h1>
                            <div class="xhs-article-content" id="articleContentArea">${data.content}</div>
                            <div class="xhs-meta">
                                <span>发布于 ${createdDate}</span>
                                <span><i class="fa-regular fa-eye"></i> ${data.views}</span>
                            </div>

                            <div class="xhs-comments-area">
                                <div class="xhs-comments-count">共 ${data.comments ? data.comments.length : 0} 条评论</div>
                                <div class="comment-list" id="commentList-${data.id}">
                                    ${data.comments && data.comments.length > 0 ? data.comments.map(c => `
                                    <div class="xhs-comment-item">
                                        <div class="xhs-comment-avatar">
                                            <img src="${c.avatar || 'https://api.dicebear.com/7.x/avataaars/svg?seed=' + encodeURIComponent(c.username)}" style="width:100%;height:100%;object-fit:cover;">
                                        </div>
                                        <div class="xhs-comment-body">
                                            <div class="xhs-comment-name">${c.username}</div>
                                            <div class="xhs-comment-text">${c.content}</div>
                                            <div class="xhs-comment-time">${c.created_at}</div>
                                        </div>
                                    </div>`).join('') : '<div style="color:#aaa; font-size:13px; text-align:center; padding:20px 0;">快来抢占沙发~</div>'}
                                </div>
                            </div>
                        </div>

                        <div class="xhs-bottom-bar">
                            <div class="xhs-input-wrap">
                                <i class="fa-solid fa-pen pencil-icon"></i>
                                <input type="text" class="comment-input" id="input-${data.id}" onclick="checkLogin(event)" placeholder="${isUserLogin ? '说点什么...' : '请先登录'}">
                                <button onclick="postComment(${data.id}, event)" class="xhs-send-btn">发送</button>
                            </div>
                            <div class="xhs-interactions">
                                <div class="action-btn ${data.is_liked ? 'liked' : ''}" onclick="likeArticle(${data.id}, this)">
                                    <i class="${data.is_liked ? 'fa-solid fa-heart fa-bounce' : 'fa-regular fa-heart'}"></i>
                                    <span>${data.likes || ''}</span>
                                </div>
                                <div class="action-btn" onclick="shareArticle(${data.id}, '${data.title.replace(/'/g, "\\'")}')">
                                    <i class="fa-solid fa-share-nodes"></i> <span class="action-text">分享</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>`;
            
            const mobileContainer = document.querySelector('.xhs-container');
            if (mobileContainer && window.innerWidth <= 768) {
                mobileContainer.scrollTop = 0;
            }
            
            setTimeout(initCodeBlocks, 50);
        }).catch(err => { console.error(err); modalBody.innerHTML = '<p style="text-align:center;padding:20px;">加载失败，请检查网络或控制台错误。</p>'; });
    };

    window.closeModal = () => { 
        if(modal) {
            modal.classList.remove('active'); 
            const video = modal.querySelector('video');
            if (video) {
                video.pause();
                video.currentTime = 0;
            }
            setTimeout(() => {
                const modalBody = document.getElementById('modalBody');
                if (modalBody) modalBody.innerHTML = '';
            }, 300);
        }
        document.body.style.overflow = ''; 
    };
    
    if(modal) modal.addEventListener('click', (e) => { if (e.target === modal) closeModal(); });

    // ==========================================
    //       5. 互动功能函数
    // ==========================================
    window.checkLogin = (e) => {
        if (!isUserLogin) {
            e.preventDefault();
            e.target.blur();
            if(typeof openAuthModal === 'function') openAuthModal('login');
            else alert('请先登录');
        }
    };

    window.likeArticle = (id, btn) => {
        if (!isUserLogin) { 
            if(typeof openAuthModal === 'function') openAuthModal('login'); else alert('请先登录');
            return; 
        }
        fetch(`api/index.php?action=like&id=${id}`).then(res => res.json()).then(data => {
            if (data.success) {
                btn.classList.toggle('liked', data.liked);
                btn.querySelector('i').className = `fa-${data.liked ? 'solid' : 'regular'} fa-heart ${data.liked ? 'fa-bounce' : ''}`;
                btn.querySelector('span').innerText = data.new_likes;
            } else { alert(data.msg || '操作失败'); }
        });
    };

    window.postComment = (id, event) => {
        if (!isUserLogin) { 
            if(typeof openAuthModal === 'function') openAuthModal('login'); else alert('请先登录');
            return; 
        }
        const input = document.getElementById(`input-${id}`); const content = input.value.trim();
        if (!content) { alert("请输入评论内容"); return; }
        if (content.length > 500) { alert("评论太长了（最多500字）"); return; }
        const sendBtn = event.target; const originalText = sendBtn.innerText;
        sendBtn.disabled = true; sendBtn.innerText = "发送中..."; sendBtn.style.opacity = "0.6";
        const formData = new FormData(); formData.append('article_id', id); formData.append('content', content); formData.append('csrf_token', csrfToken);
        fetch(`api/index.php?action=comment`, { method: 'POST', body: formData }).then(res => res.json()).then(data => {
            sendBtn.disabled = false; sendBtn.innerText = originalText; sendBtn.style.opacity = "1";
            if (data.success) {
                const list = document.getElementById(`commentList-${id}`);
                if (list.innerText.includes('快来抢占沙发')) list.innerHTML = '';
                
                const newItem = document.createElement('div');
                const safeContent = content.replace(/</g, "&lt;").replace(/>/g, "&gt;");
                newItem.className = 'xhs-comment-item';
                newItem.innerHTML = `
                    <div class="xhs-comment-avatar">
                        <img src="${window.siteData.currentUserAvatar}" style="width:100%;height:100%;object-fit:cover;border-radius:50%;">
                    </div>
                    <div class="xhs-comment-body">
                        <div class="xhs-comment-name">${window.siteData.currentUserName}</div>
                        <div class="xhs-comment-text">${safeContent}</div>
                        <div class="xhs-comment-time">刚刚</div>
                    </div>
                `;
                list.prepend(newItem); input.value = '';
            } else { alert(data.msg || '评论失败'); }
        }).catch(() => {
            sendBtn.disabled = false; sendBtn.innerText = originalText; sendBtn.style.opacity = "1";
            alert("网络请求失败，请稍后重试");
        });
    };

    window.shareArticle = (id, title) => {
        const shareUrl = window.location.origin + window.location.pathname + '?id=' + id;
        if (navigator.share) {
            navigator.share({ title: title, text: '快来看看这篇文章：' + title, url: shareUrl }).catch(console.error);
        } else {
            copyToClipboard(shareUrl).then(() => { alert('链接已复制到剪贴板，快去转发给朋友吧！'); }).catch(() => { alert('复制失败，请手动复制当前网址。'); });
        }
    };

    function copyToClipboard(text) { return navigator.clipboard ? navigator.clipboard.writeText(text) : new Promise((res, rej) => { try { const ta = document.createElement("textarea"); ta.value = text; ta.style.position = "fixed"; ta.style.left = "-9999px"; document.body.appendChild(ta); ta.select(); document.execCommand('copy') ? res() : rej(); document.body.removeChild(ta); } catch (err) { rej(err); } }); }
    
    function initCodeBlocks() {
        document.querySelectorAll('.xhs-article-content pre').forEach(pre => {
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

    const urlParams = new URLSearchParams(window.location.search);
    const sharedArticleId = urlParams.get('id');
    if (sharedArticleId) {
        setTimeout(() => {
            openArticle(sharedArticleId);
            history.replaceState(null, '', window.location.pathname);
        }, 300);
    }
});
