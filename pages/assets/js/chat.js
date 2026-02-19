document.addEventListener('DOMContentLoaded', function() {
    const container = document.getElementById('chatContainer');
    const input = document.getElementById('chatInput');
    const sendBtn = document.getElementById('sendBtn');
    const emojiPanel = document.getElementById('emojiPanel');
    
    // 从 HTML 传入的配置中获取变量
    const currentUserId = chatConfig.currentUserId;
    const isLogin = chatConfig.isLogin;
    
    let lastMsgId = 0;
    let isSending = false;

    // Emoji 列表
    const emojis = ["😀","😁","😂","🤣","😃","😄","😅","😆","😉","😊","😋","😎","😍","😘","🥰","😗","😙","😚","🙂","🤗","🤩","🤔","🤨","😐","😑","😶","🙄","😏","😣","😥","😮","🤐","😯","😪","😫","😴","😌","😛","😜","😝","🤤","😒","😓","😔","😕","🙃","🤑","😲","☹️","🙁","😖","😞","😟","😤","😢","😭","😦","😧","V","😨","😩","🤯","😬","😰","😱","🥵","🥶","😳","🤪","😵","😡","😠","🤬","😷","🤒","🤕","🤢","🤮","🤧","😇","🤠","🤡","🥳","🥴","🥺","🤥","🤫","🤭","🧐","🤓","😈","👿","👹","👺","💀","👻","👽","🤖","💩","😺","😸","😹","😻","😼","😽","🙀","😿","😾"];

    emojis.forEach(e => {
        const span = document.createElement('div');
        span.className = 'emoji-item';
        span.innerText = e;
        span.onclick = () => { 
            input.value += e; 
            input.focus();
        };
        emojiPanel.appendChild(span);
    });

    window.toggleEmoji = function() {
        emojiPanel.classList.toggle('open');
        if(emojiPanel.classList.contains('open')) {
            setTimeout(scrollToBottom, 300);
        }
    }
    
    if (input) {
        input.addEventListener('focus', () => { 
            emojiPanel.classList.remove('open'); 
        });
        input.addEventListener('keypress', (e) => { 
            if(e.key === 'Enter') {
                e.preventDefault(); // 防止回车换行
                sendMsg(); 
            }
        });
    }

    function scrollToBottom() { 
        if (container) {
            container.scrollTop = container.scrollHeight; 
        }
    }

    function loadMessages() {
        fetch('api/chatroom.php?action=get_messages')
            .then(res => res.json())
            .then(res => {
                if(!res.success) return;

                if (res.is_muted) {
                    input.disabled = true;
                    input.placeholder = "全员禁言中...";
                    if(sendBtn) sendBtn.disabled = true;
                } else {
                    if (isLogin) { 
                        input.disabled = false; 
                        input.placeholder = "发消息...";
                        if(sendBtn) sendBtn.disabled = false;
                    }
                }

                const data = res.data;
                if(data.length === 0) {
                    container.innerHTML = '<div style="text-align:center; color:#ccc; font-size:12px; margin-top:50px;">暂无消息</div>';
                    return;
                }
                
                const newestId = data[data.length - 1].id;
                if (newestId > lastMsgId) {
                    container.innerHTML = ''; // 清空现有消息
                    data.forEach(msg => {
                        const isSelf = parseInt(msg.user_id) === currentUserId;
                        const div = document.createElement('div');
                        div.className = `chat-msg ${isSelf ? 'self' : ''}`;
                        
                        let displayNick = msg.nickname || "该用户已注销";
                        let displayAvatar = msg.avatar || "https://placehold.co/100";
                        if (!msg.nickname) {
                            displayAvatar = "https://ui-avatars.com/api/?name=🚫&background=f0f0f0&color=999&font-size=0.5";
                        }

                        div.innerHTML = `<img src="${displayAvatar}" class="chat-avatar"><div class="msg-content"><div class="chat-name">${displayNick}</div><div class="chat-bubble">${msg.message}</div></div>`;
                        container.appendChild(div);
                    });
                    scrollToBottom();
                    lastMsgId = newestId;
                }
            })
            .catch(err => console.error("Error loading messages:", err));
    }

    window.sendMsg = function() {
        if(!isLogin) { 
            if(confirm('需要登录才能发言，去登录？')) {
                window.location.href = 'user/login.php';
            }
            return; 
        }
        const msg = input.value.trim();
        if(!msg || isSending) return;
        
        isSending = true; 
        sendBtn.disabled = true;
        
        const formData = new FormData();
        formData.append('message', msg);
        
        fetch('api/chatroom.php?action=send_message', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(res => {
                if(res.success) { 
                    input.value = ''; 
                    loadMessages(); 
                    input.focus(); 
                } else { 
                    alert(res.msg || '发送失败'); 
                }
            })
            .catch(() => { 
                alert('网络错误'); 
            })
            .finally(() => {
                isSending = false; 
                // 只有在非禁言状态下才恢复按钮
                if (!input.placeholder.includes('禁言')) {
                   sendBtn.disabled = false;
                }
            });
    }

    // 初始化加载并设置定时刷新
    loadMessages();
    setInterval(loadMessages, 3000);
});
