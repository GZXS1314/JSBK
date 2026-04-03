document.addEventListener('DOMContentLoaded', function() {
    const { startDate, isLogin, userAvatar, isLetterEnabled, csrfToken, initialWishes } = loveConfig;

    // --- 计时器 ---
    const start = new Date(`${startDate} 00:00:00`).getTime();
    function timer() {
        const now = new Date().getTime();
        const diff = now - start;
        if (diff > 0) {
            document.getElementById('d-days').innerText = Math.floor(diff / 86400000);
            document.getElementById('d-hours').innerText = Math.floor((diff % 86400000) / 3600000).toString().padStart(2, '0');
            document.getElementById('d-mins').innerText = Math.floor((diff % 3600000) / 60000).toString().padStart(2, '0');
            document.getElementById('d-secs').innerText = Math.floor((diff % 60000) / 1000).toString().padStart(2, '0');
        }
    }
    setInterval(timer, 1000);
    timer();

    // --- 滚动提示 ---
    document.getElementById('scrollHint').addEventListener('click', () => {
        document.getElementById('contentSec').scrollIntoView({ behavior: 'smooth' });
    });

    // --- 节点动画 ---
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) entry.target.classList.add('visible');
        });
    }, { threshold: 0.15, rootMargin: "0px 0px -50px 0px" });
    document.querySelectorAll('.tl-node').forEach(el => observer.observe(el));

    // --- 【核心修复】真实弹幕系统 ---
    let wishes = initialWishes || [];
    const dmContainer = document.getElementById('danmakuLayer');

    function spawn() {
        if (!wishes.length) return;
        const data = wishes[Math.floor(Math.random() * wishes.length)];
        const el = document.createElement('div');
        el.className = 'dm-item';
        // XSS 过滤
        const safeText = data.content.replace(/</g, "&lt;").replace(/>/g, "&gt;");
        el.innerHTML = `<img src="${data.avatar}" class="dm-av"> <span>${safeText}</span>`;
        
        el.style.top = (Math.random() * 60 + 10) + '%'; 
        const duration = Math.random() * 8 + 12; // 12-20s
        el.style.transition = `transform ${duration}s linear`;
        el.style.left = '100%';
        
        dmContainer.appendChild(el);
    
        requestAnimationFrame(() => {
            el.offsetWidth; // 触发回流
            const totalDistance = dmContainer.offsetWidth + el.offsetWidth + 50;
            el.style.transform = `translateX(-${totalDistance}px) translateZ(0)`;
        });
    
        setTimeout(() => el.remove(), duration * 1000);
    }
    
    // 初始化弹幕
    setTimeout(spawn, 500);
    setTimeout(spawn, 1500);
    setInterval(spawn, 4000);

    // --- 【核心修复】发送真实的祝福请求 ---
    function sendWish() {
        if (!isLogin) {
            if (typeof openAuthModal === 'function') openAuthModal('login');
            else alert('请先登录以送上祝福');
            return;
        }
        const input = document.getElementById('wishInput');
        const content = input.value.trim();
        if (!content) return alert('请写下一句祝福吧~');
        
        const btn = document.getElementById('sendWishBtn');
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';

        const fd = new FormData();
        fd.append('content', content);
        if(csrfToken) fd.append('csrf_token', csrfToken);

        fetch('/api/love.php?action=send_wish', { method: 'POST', body: fd })
            .then(res => res.json())
            .then(data => {
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-paper-plane"></i>';
                if (data.success) {
                    // 推入本地数组以供循环显示
                    wishes.push({ nickname: 'Me', avatar: userAvatar, content: content });
                    
                    // 立即生成一条高亮弹幕
                    const el = document.createElement('div');
                    el.className = 'dm-item';
                    el.style.border = '1px solid #ff7675';
                    el.innerHTML = `<img src="${userAvatar}" class="dm-av"> <span>${content.replace(/</g, "&lt;").replace(/>/g, "&gt;")}</span>`;
                    el.style.top = '20%';
                    el.style.left = '100%';
                    el.style.transition = `transform 15s linear`;
                    dmContainer.appendChild(el);
                    requestAnimationFrame(() => {
                         el.offsetWidth;
                         const dist = dmContainer.offsetWidth + el.offsetWidth + 50;
                         el.style.transform = `translateX(-${dist}px) translateZ(0)`;
                    });
                    
                    input.value = '';
                    alert('祝福已送达，存入时光机 ❤️');
                } else {
                    alert(data.msg || '发送失败');
                }
            }).catch(e => {
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-paper-plane"></i>';
                alert('网络错误，请重试');
            });
    }
    
    document.getElementById('sendWishBtn').addEventListener('click', sendWish);
    document.getElementById('wishInput').addEventListener('keypress', (e) => {
        if (e.key === 'Enter') sendWish();
    });

    // --- 灯箱 ---
    const lightbox = document.getElementById('lightbox');
    const lightboxImg = document.getElementById('lightboxImg');
    function openLightbox(url) {
        let cleanUrl = url.split('?')[0]; 
        lightboxImg.src = cleanUrl;
        lightbox.classList.add('active');
    }
    lightbox.addEventListener('click', () => lightbox.classList.remove('active'));
    document.querySelectorAll('.img-item').forEach(img => {
        img.addEventListener('click', (e) => {
            e.stopPropagation();
            openLightbox(e.target.dataset.src || e.target.src);
        });
    });

    // --- 情书 ---
    const letterModal = document.getElementById('letterModal');
    const bgm = document.getElementById('loveBgm');

    document.getElementById('openLetterBtn').addEventListener('click', () => {
        if (!isLetterEnabled) return alert('对方还没有准备好情书哦~');
        letterModal.classList.add('active');
    });

    document.getElementById('openEnvelopeBtn').addEventListener('click', (e) => {
        e.stopPropagation();
        if (letterModal.classList.contains('opened')) return;
        letterModal.classList.add('opening');
        setTimeout(() => {
            letterModal.classList.add('opened');
            if (bgm) { bgm.volume = 0; bgm.play(); let vol=0; setInterval(()=>{vol=Math.min(1,vol+0.1);bgm.volume=vol},100); }
        }, 800);
    });

    document.getElementById('closeLetterBtn').addEventListener('click', (e) => {
        e.stopPropagation();
        letterModal.classList.remove('active', 'opening', 'opened');
        if (bgm) { bgm.pause(); bgm.currentTime = 0; }
    });
});