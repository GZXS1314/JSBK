document.addEventListener('DOMContentLoaded', () => {

    const isUserLogin = window.siteData.isUserLogin;
    const currentUserId = window.siteData.currentUserId;
    const csrfToken = window.siteData.csrfToken;
    const enableChatroom = window.siteData.enableChatroom;

    // ==========================================
    //       1. 文章列表与分页逻辑 (极速版)
    // ==========================================
    const state = { page: 1, limit: 6, totalPages: 1, category: 'all', keyword: '', isLoading: false, isMobile: window.innerWidth <= 1024 };
    const container = document.getElementById('articleContainer');
    const pagination = document.getElementById('pagination');

    function loadArticles(isReset = false) {
        if (state.isLoading) return;
        
        // 【性能核弹】：如果是第一次加载首页，直接读取 PHP 直塞的数据，0网络请求！
        if (!isReset && state.page === 1 && state.category === 'all' && state.keyword === '' && window.siteData.initialArticles) {
            state.totalPages = window.siteData.initialTotalPages;
            container.innerHTML = '';
            renderHTML(window.siteData.initialArticles);
            renderPagination(state.totalPages, 1);
            // 消费完毕后置空，后续点击其他分类依然走正常的 AJAX
            window.siteData.initialArticles = null;
            return;
        }

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

    if(container) loadArticles(false); // 第一次加载传 false，触发直塞数据

    // ==========================================
    //       2. 聊天室逻辑 (保持不变)
    // ==========================================
    if (enableChatroom) {
        const chatContainer = document.getElementById('chatMessages');
        const chatInput = document.getElementById('chatInput');
        
        const emojiPicker = document.getElementById('pcEmojiPicker');
        const emojiBtn = document.querySelector('.emoji-btn');
        const emojis = ['😀','😁','😂','🤣','😃','😄','😅','😆','😉','😊','😋','😎','😍','😘','🥰','😗','😙','😚','🙂','🤗','🤩','🤔','🤨','😐','😑','😶','🙄','😏','😣','😥','😮','😯','😪','😫','😴','😌','😛','😜','😝','🤤','😒','😓','😔','😕','🙃','🤑','😲','☹️','🙁','😖','😞','😟','😤','😢','😭','😦','😧','😨','😩','🤯','😬','😰','😱','🥵','🥶','😳','🤪','😵','😡','😠','🤬','😷','🤒','🤕','🤢','🤮','🤧','😇','🤠','🤡','🥳','🥴','🥺','🤥','🤫','🤭','🧐','🤓','😈','👿','👹','👺','💀','👻','👽','🤖','💩','😺','😸','😹','😻','😼','😽','🙀','😿','😾','🙈','🙉','🙊','👍','👎','👊','✊','🤛','🤜','🤞','✌️','🤟','🤘','👌','🤏','👈','👉','👆','👇','☝️','✋','🤚','🖐','🖖','👋','🤙','💪','🖕','✍️','🙏'];

        if (emojiPicker) {
            emojiPicker.innerHTML = emojis.map(e => `<div class="emoji-item">${e}</div>`).join('');
            
            emojiPicker.addEventListener('click', (e) => {
                if(e.target.classList.contains('emoji-item')) {
                    const emoji = e.target.innerText;
                    if (chatInput.selectionStart || chatInput.selectionStart == '0') {
                        var startPos = chatInput.selectionStart;
                        var endPos = chatInput.selectionEnd;
                        chatInput.value = chatInput.value.substring(0, startPos) + emoji + chatInput.value.substring(endPos, chatInput.value.length);
                        chatInput.selectionStart = startPos + emoji.length;
                        chatInput.selectionEnd = startPos + emoji.length;
                    } else {
                        chatInput.value += emoji;
                    }
                    
                    emojiPicker.classList.remove('active');
                    chatInput.focus();
                }
            });
        }

        window.togglePcEmoji = (e) => {
            if(e) e.stopPropagation(); 
            if(emojiPicker) {
                const isActive = emojiPicker.classList.contains('active');
                document.querySelectorAll('.emoji-picker').forEach(el => el.classList.remove('active'));
                
                if (!isActive) {
                    emojiPicker.classList.add('active');
                } else {
                    emojiPicker.classList.remove('active');
                }
            }
        };

        document.addEventListener('click', (e) => {
            if (emojiPicker && emojiPicker.classList.contains('active')) {
                if (!emojiPicker.contains(e.target) && (!emojiBtn || !emojiBtn.contains(e.target))) {
                    emojiPicker.classList.remove('active');
                }
            }
        });

        let pollingInterval = null;
        let lastMsgCount = 0;

        const loadChatMessages = () => {
            fetch('api/chatroom.php?action=get_messages')
                .then(res => res.json())
                .then(res => {
                    if (!res.success) return;
                    const isMuted = res.is_muted;
                    window.siteData.chatroomMuted = isMuted; 
                    
                    const chatInputDOM = document.getElementById('chatInput');
                    const chatSendBtnDOM = document.querySelector('.chat-send');
                    const emojiBtnDOM = document.querySelector('.emoji-btn');

                    if (chatInputDOM && chatSendBtnDOM) {
                        if (isMuted) {
                            chatInputDOM.disabled = true;
                            chatInputDOM.placeholder = "当前聊天室已全体禁言";
                            chatSendBtnDOM.disabled = true;
                            chatSendBtnDOM.style.opacity = "0.5";
                            chatSendBtnDOM.style.cursor = "not-allowed";
                            if(emojiBtnDOM) { emojiBtnDOM.disabled = true; emojiBtnDOM.style.opacity = "0.5"; }
                        } else {
                            chatInputDOM.disabled = !window.siteData.isUserLogin;
                            chatInputDOM.placeholder = window.siteData.isUserLogin ? "说点什么..." : "请先登录";
                            chatSendBtnDOM.disabled = false;
                            chatSendBtnDOM.style.opacity = "1";
                            chatSendBtnDOM.style.cursor = "pointer";
                            if(emojiBtnDOM) { emojiBtnDOM.disabled = false; emojiBtnDOM.style.opacity = "1"; }
                        }
                    }
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
            if(emojiPicker) emojiPicker.classList.remove('active');

            const formData = new FormData();
            formData.append('message', msg);
            if(csrfToken) formData.append('csrf_token', csrfToken);

            fetch('api/index.php?action=send_message', { method: 'POST', body: formData })
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
    //       3. 首页幻灯片切换逻辑 (保持不变)
    // ==========================================
    const sliderTrack = document.getElementById('sliderTrack');
    if (sliderTrack) {
        const slides = sliderTrack.querySelectorAll('.slider-item');
        const dots = document.querySelectorAll('.slider-dots .dot');
        const prevBtn = document.querySelector('.prev-btn');
        const nextBtn = document.querySelector('.next-btn');
        let currentSlide = 0;
        let slideInterval;

        window.goToSlide = (index) => {
            if (slides.length <= 1) return;
            currentSlide = (index + slides.length) % slides.length;
            sliderTrack.style.transform = `translateX(-${currentSlide * 100}%)`;
            dots.forEach((dot, i) => dot.classList.toggle('active', i === currentSlide));
        };

        const nextSlide = () => goToSlide(currentSlide + 1);
        const prevSlide = () => goToSlide(currentSlide - 1);

        if (prevBtn) prevBtn.addEventListener('click', () => { prevSlide(); resetInterval(); });
        if (nextBtn) nextBtn.addEventListener('click', () => { nextSlide(); resetInterval(); });

        const startInterval = () => {
            if (slides.length > 1) {
                slideInterval = setInterval(nextSlide, 4000); 
            }
        };

        const resetInterval = () => {
            clearInterval(slideInterval);
            startInterval();
        };

        startInterval();
    }
    
    // ==========================================
    //       4. 文章详情弹窗与积分系统 
    // ==========================================
    const modal = document.getElementById('articleModal');
    
    window.payToRead = (id) => {
        if (!isUserLogin) {
            if(typeof openAuthModal === 'function') openAuthModal('login');
            return;
        }
        if (!confirm('解锁文章将会扣除对应积分，确认继续吗？')) return;
        
        const btn = document.getElementById('payViewBtn');
        const originalText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> 处理中...';

        const formData = new FormData();
        formData.append('action', 'pay_view');
        formData.append('id', id);

        fetch('api/index.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    openArticle(id);
                } else {
                    alert(data.msg || '解锁失败，可能是积分不足');
                    btn.disabled = false;
                    btn.innerHTML = originalText;
                }
            }).catch(e => {
                alert('网络错误，请重试');
                btn.disabled = false;
                btn.innerHTML = originalText;
            });
    };

    window.payToDownload = (id) => {
        if (!isUserLogin) {
            if(typeof openAuthModal === 'function') openAuthModal('login');
            return;
        }
        if (!confirm('兑换此资源将扣除对应积分，确认继续吗？')) return;

        const formData = new FormData();
        formData.append('action', 'pay_resource');
        formData.append('id', id);

        fetch('api/index.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.success && data.link) {
                    alert('兑换成功！您的积分已扣除。');
                    window.open(data.link, '_blank');
                    openArticle(id); 
                } else {
                    alert(data.msg || '兑换失败，可能是积分不足');
                }
            }).catch(e => { alert('网络错误，请重试'); });
    };

    window.openArticle = (id, pwd = '') => { 
        if(!modal) return;
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';

        const modalBody = document.getElementById('modalBody');
        modalBody.innerHTML = '<div style="text-align:center;padding:100px;color:#999;width:100%;"><i class="fa-solid fa-spinner fa-spin fa-2x"></i><br><br>加载中...</div>';
        
        fetch(`api/index.php?action=get_article&id=${id}&pwd=${encodeURIComponent(pwd)}`).then(res => res.json()).then(data => {
            if (data.error) { modalBody.innerHTML = `<p style="text-align:center;padding:20px;">${data.error}</p>`; return; }
            
            // --- 密码锁定界面渲染 ---
            if (data.require_password) {
                modalBody.innerHTML = `
                    <div style="width:100%; height:100%; display:flex; align-items:center; justify-content:center; background: #f8fafc; position:relative; overflow:hidden;">
                        <div style="position:absolute; top:-20%; left:-10%; width:50%; height:50%; background:radial-gradient(circle, rgba(99,102,241,0.08) 0%, transparent 70%); border-radius:50%;"></div>
                        <div style="position:absolute; bottom:-20%; right:-10%; width:50%; height:50%; background:radial-gradient(circle, rgba(236,72,153,0.08) 0%, transparent 70%); border-radius:50%;"></div>

                        <div class="pwd-lock-box" style="position:relative; z-index:1; background: rgba(255,255,255,0.7); backdrop-filter: blur(24px); -webkit-backdrop-filter: blur(24px); padding: 48px 40px; border-radius: 28px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.08), inset 0 1px 1px rgba(255,255,255,0.8); text-align: center; width: 85%; max-width: 360px; border: 1px solid rgba(255,255,255,0.6); transform: translateY(0); animation: pwdFloatUp 0.6s cubic-bezier(0.16, 1, 0.3, 1);">
                            
                            <div style="width: 88px; height: 88px; background: linear-gradient(135deg, #ffffff, #f1f5f9); border-radius: 28px; display: flex; align-items: center; justify-content: center; margin: 0 auto 28px; box-shadow: inset 0 2px 4px rgba(255,255,255,1), 0 10px 20px rgba(0,0,0,0.05); transform: rotate(-5deg);">
                                <i class="fa-solid fa-lock" style="font-size: 36px; color: #334155; background: linear-gradient(135deg, #334155, #0f172a); -webkit-background-clip: text; -webkit-text-fill-color: transparent;"></i>
                            </div>
                            
                            <h3 style="margin: 0 0 10px 0; color: #0f172a; font-size: 24px; font-weight: 800; letter-spacing: 0.5px;">私密文章</h3>
                            <p style="font-size: 14px; color: #64748b; margin: 0 0 32px 0;">请输入访问密码以解锁完整内容</p>
                            
                            <div style="display: flex; flex-direction: column; gap: 16px;">
                                <div style="position: relative;">
                                    <i class="fa-solid fa-key" style="position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 14px;"></i>
                                    <input type="password" id="artPwdInput" placeholder="输入访问密码" style="width: 100%; padding: 14px 16px 14px 44px; border: 2px solid #e2e8f0; border-radius: 14px; outline: none; font-size: 15px; box-sizing: border-box; background: rgba(255,255,255,0.9); transition: all 0.3s ease; color: #0f172a; font-weight: 600;" onfocus="this.style.borderColor='#3b82f6'; this.style.boxShadow='0 0 0 4px rgba(59,130,246,0.1)'; this.style.background='#fff';" onblur="this.style.borderColor='#e2e8f0'; this.style.boxShadow='none'; this.style.background='rgba(255,255,255,0.9)';">
                                </div>
                                <button onclick="submitArtPwd(${id})" style="background: linear-gradient(135deg, #1e293b, #0f172a); color: #fff; border: none; padding: 14px; border-radius: 14px; cursor: pointer; font-size: 15px; font-weight: 600; letter-spacing: 1px; transition: all 0.3s ease; box-shadow: 0 6px 16px rgba(15,23,42,0.2);" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 10px 20px rgba(15,23,42,0.3)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 6px 16px rgba(15,23,42,0.2)';">
                                    确认解锁 <i class="fa-solid fa-arrow-right-long" style="margin-left: 6px; font-size: 12px;"></i>
                                </button>
                            </div>
                            <div style="margin-top: 28px; font-size: 12px; color: #cbd5e1; font-weight: 700; letter-spacing: 1.5px; text-transform: uppercase;">JS·Blog Secure</div>
                        </div>
                        <style>
                            @keyframes pwdFloatUp {
                                0% { opacity: 0; transform: translateY(30px) scale(0.95); }
                                100% { opacity: 1; transform: translateY(0) scale(1); }
                            }
                        </style>
                    </div>
                `;
                
                setTimeout(() => {
                    const pwdInput = document.getElementById('artPwdInput');
                    if (pwdInput) {
                        pwdInput.focus();
                        if (pwd !== '') {
                            pwdInput.style.borderColor = '#f87171';
                            pwdInput.style.backgroundColor = '#fef2f2';
                            pwdInput.style.color = '#ef4444';
                            pwdInput.value = '';
                            pwdInput.placeholder = '密码错误，请重试';
                            const box = document.querySelector('.pwd-lock-box');
                            if(box) {
                                box.animate([
                                    { transform: 'translateX(0)' }, { transform: 'translateX(-8px)' },
                                    { transform: 'translateX(8px)' }, { transform: 'translateX(-8px)' },
                                    { transform: 'translateX(8px)' }, { transform: 'translateX(0)' }
                                ], { duration: 400, easing: 'ease-in-out' });
                            }
                        }
                        pwdInput.addEventListener('keypress', (e) => {
                            if (e.key === 'Enter') submitArtPwd(id);
                        });
                    }
                }, 50);
                return; 
            }

            // --- 积分锁定阅读界面渲染 ---
            if (data.require_view_points) {
                modalBody.innerHTML = `
                    <div style="width:100%; height:100%; display:flex; align-items:center; justify-content:center; background: #f8fafc; position:relative; overflow:hidden;">
                        <div style="position:absolute; top:-20%; left:-10%; width:50%; height:50%; background:radial-gradient(circle, rgba(245,158,11,0.08) 0%, transparent 70%); border-radius:50%;"></div>
                        <div style="position:absolute; bottom:-20%; right:-10%; width:50%; height:50%; background:radial-gradient(circle, rgba(236,72,153,0.08) 0%, transparent 70%); border-radius:50%;"></div>

                        <div class="pwd-lock-box" style="position:relative; z-index:1; background: rgba(255,255,255,0.7); backdrop-filter: blur(24px); -webkit-backdrop-filter: blur(24px); padding: 48px 40px; border-radius: 28px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.08), inset 0 1px 1px rgba(255,255,255,0.8); text-align: center; width: 85%; max-width: 360px; border: 1px solid rgba(255,255,255,0.6); transform: translateY(0); animation: pwdFloatUp 0.6s cubic-bezier(0.16, 1, 0.3, 1);">
                            
                            <div style="width: 88px; height: 88px; background: linear-gradient(135deg, #fffbeb, #fef3c7); border-radius: 28px; display: flex; align-items: center; justify-content: center; margin: 0 auto 28px; box-shadow: inset 0 2px 4px rgba(255,255,255,1), 0 10px 20px rgba(0,0,0,0.05); transform: rotate(5deg);">
                                <i class="fa-solid fa-gem" style="font-size: 36px; color: #d97706; background: linear-gradient(135deg, #d97706, #92400e); -webkit-background-clip: text; -webkit-text-fill-color: transparent;"></i>
                            </div>
                            
                            <h3 style="margin: 0 0 10px 0; color: #0f172a; font-size: 24px; font-weight: 800; letter-spacing: 0.5px;">付费阅读文章</h3>
                            <p style="font-size: 14px; color: #64748b; margin: 0 0 24px 0;">此内容需支付 <b style="color:#d97706; font-size:16px;">${data.view_points}</b> 积分才能解锁</p>
                            
                            <div style="background: rgba(0,0,0,0.04); padding: 12px; border-radius: 12px; margin-bottom: 30px; font-size: 13px; color: #64748b; display:flex; justify-content:space-between; align-items:center;">
                                <span>我的余额:</span>
                                <span style="color:#0f172a; font-weight:800; font-size:15px;">${data.user_points || 0} <i class="fa-solid fa-coins" style="color:#fbbf24; margin-left:4px;"></i></span>
                            </div>
                            
                            <button id="payViewBtn" onclick="payToRead(${id})" style="width:100%; background: linear-gradient(135deg, #d97706, #b45309); color: #fff; border: none; padding: 16px; border-radius: 14px; cursor: pointer; font-size: 15px; font-weight: 700; letter-spacing: 1px; transition: all 0.3s ease; box-shadow: 0 8px 20px rgba(217,119,6,0.3);" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 12px 24px rgba(217,119,6,0.4)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 8px 20px rgba(217,119,6,0.3)';">
                                确认支付并解锁
                            </button>
                        </div>
                        <style>
                            @keyframes pwdFloatUp {
                                0% { opacity: 0; transform: translateY(30px) scale(0.95); }
                                100% { opacity: 1; transform: translateY(0) scale(1); }
                            }
                        </style>
                    </div>`;
                return;
            }

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
                    imgDisplay = `
                        <div class="xhs-blur-bg" style="background-image: url('${finalImg}');"></div>
                        <img src="${finalImg}" alt="cover" style="position:relative; z-index:1; width:100%; height:100%; object-fit:cover;">
                    `;
                } else {
                    imgDisplay = `<div style="width:100%; height:100%; display:flex; align-items:center; justify-content:center; background: #000; color:#444; position:relative; z-index:1;"><i class="fa-regular fa-image" style="font-size:48px;"></i></div>`;
                }
            }

            let resourceHtml = '';
            if (data.resource_data) {
                let resData = null;
                try { resData = typeof data.resource_data === 'string' ? JSON.parse(data.resource_data) : data.resource_data; } catch(e) {}
                
                if (resData && resData.name) {
                    const needPay = resData.need_pay; 
                    const points = resData.points || 0;
                    const isExternal = resData.link && resData.link.startsWith('http');

                    if (needPay) {
                        resourceHtml = `
                        <div class="xhs-resource-card">
                            <div class="res-icon" style="background:#fffbeb; color:#d97706;">
                                <i class="fa-solid fa-lock"></i>
                            </div>
                            <div class="res-info">
                                <div class="res-name" title="${resData.name}">${resData.name}</div>
                                <div class="res-type" style="color:#d97706; font-weight:600;">消耗 ${points} 积分兑换</div>
                            </div>
                            <button onclick="payToDownload(${id})" class="res-btn" style="background:linear-gradient(135deg, #d97706, #b45309); border:none; cursor:pointer;">
                                <i class="fa-solid fa-gem"></i> 兑换资源
                            </button>
                        </div>`;
                    } else if (resData.link) {
                        resourceHtml = `
                        <div class="xhs-resource-card">
                            <div class="res-icon">
                                <i class="fa-solid fa-folder-closed"></i>
                            </div>
                            <div class="res-info">
                                <div class="res-name" title="${resData.name}">${resData.name}</div>
                                <div class="res-type">${isExternal ? '外部链接' : '本地资源'} ${points > 0 ? '<span style="color:#10b981; font-weight:bold; margin-left:4px;">(已解锁)</span>' : ''}</div>
                            </div>
                            <a href="${resData.link}" target="_blank" class="res-btn">
                                <i class="fa-solid fa-cloud-arrow-down"></i> 下载
                            </a>
                        </div>`;
                    }
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
                            
                            ${resourceHtml}

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
    //       5. 互动功能函数 (保持不变)
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
                if (data.points_awarded && data.points_awarded > 0) {
                    alert(`点赞成功！积分 +${data.points_awarded}`);
                }
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
                
                if (data.points_awarded && data.points_awarded > 0) {
                    alert(`评论成功！积分 +${data.points_awarded}`);
                }
            } else { alert(data.msg || '评论失败'); }
        }).catch(() => {
            sendBtn.disabled = false; sendBtn.innerText = originalText; sendBtn.style.opacity = "1";
            alert("网络请求失败，请稍后重试");
        });
    };

    window.shareArticle = (id, title) => {
        const shareUrl = window.location.origin + window.location.pathname + '?id=' + id;
        
        const claimSharePoints = () => {
            if (isUserLogin) {
                const fd = new FormData();
                fd.append('article_id', id);
                if(csrfToken) fd.append('csrf_token', csrfToken);
                fetch(`api/index.php?action=share`, { method: 'POST', body: fd })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success && data.points_awarded && data.points_awarded > 0) {
                            alert(`分享成功！积分 +${data.points_awarded}`);
                        }
                    }).catch(e => console.error(e));
            }
        };

        if (navigator.share) {
            navigator.share({ title: title, text: '快来看看这篇文章：' + title, url: shareUrl })
            .then(() => { claimSharePoints(); })
            .catch(console.error);
        } else {
            copyToClipboard(shareUrl).then(() => { 
                alert('链接已复制到剪贴板，快去转发给朋友吧！'); 
                claimSharePoints();
            }).catch(() => { alert('复制失败，请手动复制当前网址。'); });
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

    window.submitArtPwd = (id) => {
        const pwdInput = document.getElementById('artPwdInput');
        if(pwdInput && pwdInput.value.trim() !== '') {
            openArticle(id, pwdInput.value.trim());
        }
    };
});