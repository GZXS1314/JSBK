<style>
    /* --- 弹窗独立变量 --- */
    #authOverlay {
        --modal-width: 720px;
        --modal-bg: #fff;
        --modal-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.35);
        --modal-radius: 24px;
        --highlight: #000;
        --text-sub: #666;
        --transition: cubic-bezier(0.4, 0, 0.2, 1); 
    }

    .auth-overlay {
        position: fixed; top: 0; left: 0; width: 100%; height: 100%;
        background: rgba(0, 0, 0, 0.5);
        backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px);
        z-index: 10000; 
        display: none; align-items: center; justify-content: center;
        opacity: 0; transition: opacity 0.3s ease;
        font-family: -apple-system, BlinkMacSystemFont, "PingFang SC", sans-serif;
        padding: 20px;
    }
    .auth-overlay.active { display: flex; opacity: 1; }

    .auth-card {
        width: var(--modal-width);
        max-width: 100%;
        min-height: 400px; 
        background: var(--modal-bg);
        border-radius: var(--modal-radius);
        box-shadow: var(--modal-shadow);
        display: flex; overflow: hidden;
        transform: scale(0.95) translateY(20px);
        transition: all 0.4s var(--transition), opacity 0.4s ease;
        opacity: 0;
    }
    .auth-overlay.active .auth-card { transform: scale(1) translateY(0); opacity: 1; }

    .auth-side {
        width: 40%; background: #000; position: relative;
        display: flex; flex-direction: column; justify-content: center; padding: 40px;
        color: #fff; overflow: hidden;
    }
    .auth-side-bg { position: absolute; inset: 0; opacity: 0.4; background: radial-gradient(circle at 30% 30%, #555, #000); }
    .auth-side::after {
        content: ''; position: absolute; top: -50%; left: -50%; width: 200%; height: 200%;
        background: conic-gradient(from 180deg at 50% 50%, #000 0deg, #333 180deg, #000 360deg);
        animation: rotateBg 20s linear infinite; opacity: 0.2; mix-blend-mode: screen; pointer-events: none;
    }
    @keyframes rotateBg { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
    .side-content { position: relative; z-index: 2; }
    .side-title { font-size: 36px; font-weight: 900; margin-bottom: 15px; line-height: 1; letter-spacing: -1px; }
    .side-desc { font-size: 14px; opacity: 0.7; line-height: 1.6; font-weight: 500; }

    .auth-main { flex: 1; position: relative; display: flex; flex-direction: column; justify-content: center; padding: 40px; }
    .close-btn { position: absolute; top: 20px; right: 20px; width: 32px; height: 32px; border-radius: 50%; background: #f7f7f9; color: #999; display: flex; align-items: center; justify-content: center; cursor: pointer; z-index: 10; font-size: 16px; transition: 0.2s; }
    .close-btn:hover { background: #eee; color: #000; }

    .auth-tabs { display: flex; margin-bottom: 30px; border-bottom: 2px solid #f0f0f0; align-self: flex-start; width: 100%; }
    .tab-btn { margin-right: 30px; padding-bottom: 10px; font-size: 16px; font-weight: 700; color: var(--text-sub); cursor: pointer; position: relative; transition: 0.3s; margin-bottom: -2px; }
    .tab-btn.active { color: var(--highlight); }
    .tab-btn.active::after { content: ''; position: absolute; bottom: 0; left: 0; width: 100%; height: 2px; background: var(--highlight); }

    .forms-wrapper { position: relative; width: 100%; }
    .auth-form { display: none; }
    .auth-form.active { display: block; animation: slideUpFade 0.4s var(--transition); }
    @keyframes slideUpFade { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }

    .input-box { margin-bottom: 12px; }
    .glass-input { width: 100%; padding: 14px 16px; background: #f7f7f9; border: 2px solid transparent; border-radius: 12px; font-size: 15px; color: #333; outline: none; transition: 0.3s; box-sizing: border-box; font-weight: 500; }
    .glass-input:focus { background: #fff; border-color: #000; }
    
    /* 找回密码专用链接样式 */
    .forgot-trigger { display: block; text-align: right; font-size: 12px; color: #999; margin-top: -5px; margin-bottom: 15px; cursor: pointer; }
    .forgot-trigger:hover { color: #000; text-decoration: underline; }

    .code-row { display: flex; gap: 10px; }
    .captcha-img-box { width: 110px; height: 50px; border-radius: 12px; overflow: hidden; cursor: pointer; flex-shrink: 0; border: 2px solid #eee; box-sizing: border-box; background: #f7f7f9; }
    .captcha-img-box img { width: 100%; height: 100%; object-fit: cover; display: block; }
    .verify-btn { width: 110px; height: 50px; border-radius: 12px; background: #333; color: #fff; font-size: 13px; border: none; cursor: pointer; flex-shrink: 0; transition: 0.2s; font-weight: 600; }
    .submit-btn { width: 100%; padding: 16px; border-radius: 12px; background: var(--highlight); color: #fff; font-size: 16px; font-weight: 700; border: none; cursor: pointer; margin-top: 15px; transition: 0.3s; }
    .submit-btn:hover { transform: translateY(-2px); box-shadow: 0 10px 20px -10px rgba(0,0,0,0.5); }

    .status-alert { font-size: 14px; padding: 12px; border-radius: 12px; text-align: center; margin-bottom: 20px; display: none; font-weight: 600; animation: shake 0.4s ease-in-out; }
    @keyframes shake { 0%, 100% { transform: translateX(0); } 25% { transform: translateX(-5px); } 75% { transform: translateX(5px); } }
    .status-alert.error { background: #fff2f0; color: #ff4d4f; border: 1px solid #ffccc7; }
    .status-alert.success { background: #f6ffed; color: #52c41a; border: 1px solid #b7eb8f; animation: none; }

    @media (max-width: 768px) {
        .auth-card { flex-direction: column; width: 100%; min-height: auto; }
        .auth-side { width: 100%; padding: 30px 25px; height: auto; align-items: center; text-align: center; }
        .auth-main { padding: 30px 25px; }
        .auth-tabs { justify-content: center; }
    }
</style>

<div class="auth-overlay" id="authOverlay">
    <div class="auth-card" id="authCard">
        <div class="auth-side">
            <div class="auth-side-bg"></div>
            <div class="side-content">
                <div class="side-title">Join<br>Us.</div>
                <div class="side-desc">登录以解锁完整体验，<br>与我们一起探索创意世界。</div>
            </div>
        </div>

        <div class="auth-main">
            <div class="close-btn" onclick="closeAuth()"><i class="fa-solid fa-xmark"></i></div>

            <div class="auth-tabs">
                <div class="tab-btn active" id="tabLogin" onclick="switchTab('login')">登录</div>
                <div class="tab-btn" id="tabRegister" onclick="switchTab('register')">注册帐号</div>
                <div class="tab-btn" id="tabForgot" style="display:none">找回密码</div>
            </div>

            <div class="status-alert" id="authAlert"></div>

            <div class="forms-wrapper">
                <!-- 登录表单 -->
                <form id="modalLoginForm" class="auth-form active" onsubmit="handleLogin(event)">
                    <input type="hidden" name="action" value="login">
                    <input type="hidden" name="ajax" value="1">
                    <div class="input-box"><input type="text" name="username" class="glass-input" placeholder="用户名 / 电子邮箱" required></div>
                    <div class="input-box"><input type="password" name="password" class="glass-input" placeholder="密码" required></div>
                    <span class="forgot-trigger" onclick="switchTab('forgot')">忘记密码？</span>
                    <div class="input-box code-row">
                        <input type="text" name="captcha" class="glass-input" placeholder="验证码" required autocomplete="off">
                        <div class="captcha-img-box">
                            <!-- 🔥 核心修复：src 留空，防止页面加载时产生竞争 Session -->
                            <img src="" class="captcha-img" onclick="refreshCaptchas()" alt="点击加载">
                        </div>
                    </div>
                    <button type="submit" class="submit-btn" id="loginBtn">立即登录</button>
                </form>

                <!-- 注册表单 -->
                <form id="modalRegForm" class="auth-form" onsubmit="handleRegister(event)">
                    <input type="hidden" name="action" value="register"><input type="hidden" name="ajax" value="1">
                    <div class="input-box"><input type="text" name="username" class="glass-input" placeholder="设置用户名 (唯一ID)" required></div>
                    <div class="input-box"><input type="email" name="email" id="mEmail" class="glass-input" placeholder="电子邮箱" required></div>
                    <div class="input-box"><input type="password" name="password" class="glass-input" placeholder="设置登录密码" required></div>
                    <div class="input-box code-row">
                        <input type="text" id="mCaptcha" class="glass-input" placeholder="图形码" autocomplete="off">
                        <div class="captcha-img-box">
                            <!-- 🔥 核心修复：src 留空 -->
                            <img src="" class="captcha-img" id="mCaptchaImg" onclick="refreshCaptchas()" alt="点击加载">
                        </div>
                    </div>
                    <div class="input-box code-row">
                        <input type="text" name="email_code" class="glass-input" placeholder="邮箱验证码" required>
                        <button type="button" class="verify-btn" id="mSendBtn" onclick="sendModalEmail('register')">获取</button>
                    </div>
                    <button type="submit" class="submit-btn" id="regBtn">完成注册</button>
                </form>

                <!-- 找回密码表单 -->
                <form id="modalForgotForm" class="auth-form" onsubmit="handleResetPassword(event)">
                    <input type="hidden" name="action" value="reset_password"><input type="hidden" name="ajax" value="1">
                    <div class="input-box"><input type="email" name="email" id="fEmail" class="glass-input" placeholder="绑定的电子邮箱" required></div>
                    <div class="input-box"><input type="password" name="new_password" class="glass-input" placeholder="设置新密码" required></div>
                    <div class="input-box code-row">
                        <input type="text" id="fCaptcha" class="glass-input" placeholder="图形码" autocomplete="off">
                        <div class="captcha-img-box">
                            <!-- 🔥 核心修复：src 留空 -->
                            <img src="" class="captcha-img" id="fCaptchaImg" onclick="refreshCaptchas()" alt="点击加载">
                        </div>
                    </div>
                    <div class="input-box code-row">
                        <input type="text" name="email_code" class="glass-input" placeholder="邮箱验证码" required>
                        <button type="button" class="verify-btn" id="fSendBtn" onclick="sendModalEmail('forgot')">获取</button>
                    </div>
                    <button type="submit" class="submit-btn" id="resetBtn">重置密码并登录</button>
                    <div style="text-align:center; margin-top:15px;"><span class="forgot-trigger" onclick="switchTab('login')">想起密码了？去登录</span></div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    const AUTH_API_PATH = '/api/'; 
    const AUTH_USER_PATH = '/user/';
    const authOverlay = document.getElementById('authOverlay');
    const authAlert = document.getElementById('authAlert');
    
    // 打开弹窗
    function openAuthModal(type = 'login') { 
        authOverlay.classList.add('active'); 
        // 🔥 修复：打开时只切换标签，不在这里刷新所有验证码
        // 验证码刷新逻辑移交给了 switchTab
        switchTab(type); 
    }

    function closeAuth() { 
        authOverlay.classList.remove('active'); 
        // 关闭时清空，防止下次打开闪烁
        setTimeout(() => {
            document.querySelectorAll('.captcha-img').forEach(img => img.src = '');
        }, 300);
    }
    
    authOverlay.addEventListener('click', (e) => { 
        if(e.target === authOverlay) closeAuth(); 
    });

    // 刷新单张图片 (点击图片时用)
    function refreshCaptcha(img) {
        img.src = AUTH_API_PATH + 'captcha.php?t=' + new Date().getTime() + Math.random();
    }

    // 🔥 核心修复：切换标签时，只刷新当前标签下的验证码
    function switchTab(type) {
        authAlert.style.display = 'none';
        
        // 1. 切换按钮状态
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        
        // 2. 切换表单显示
        document.querySelectorAll('.auth-form').forEach(f => f.classList.remove('active'));
        
        let activeFormId = '';
        if (type === 'login') { 
            document.getElementById('tabLogin').classList.add('active'); 
            activeFormId = 'modalLoginForm';
        } else if (type === 'register') { 
            document.getElementById('tabRegister').classList.add('active'); 
            activeFormId = 'modalRegForm';
        } else if (type === 'forgot') { 
            activeFormId = 'modalForgotForm';
        }

        const activeForm = document.getElementById(activeFormId);
        if (activeForm) {
            activeForm.classList.add('active');
            
            // 🔥🔥🔥 关键：只找到当前表单里的图片进行刷新！
            // 其他表单的图片保持不动（或者是空的），防止 Session 覆盖
            const img = activeForm.querySelector('.captcha-img');
            if (img) {
                refreshCaptcha(img);
            }
        }
    }

    function showMsg(text, type) { 
        authAlert.innerText = text; 
        authAlert.className = 'status-alert ' + type; 
        authAlert.style.display = 'block'; 
    }

    // --- 登录逻辑 ---
    function handleLogin(e) {
        e.preventDefault(); 
        const btn = document.getElementById('loginBtn'); 
        btn.disabled = true;
        
        fetch(AUTH_USER_PATH + 'login.php', { method: 'POST', body: new FormData(e.target) })
            .then(r => r.json()).then(d => {
                if(d.success) { 
                    showMsg('登录成功！', 'success'); 
                    setTimeout(() => window.location.reload(), 800); 
                } else { 
                    showMsg(d.msg, 'error'); 
                    btn.disabled = false; 
                    
                    // 🔥 失败后，只刷新当前登录框的验证码
                    const img = document.querySelector('#modalLoginForm .captcha-img');
                    if(img) refreshCaptcha(img);
                    
                    e.target.querySelector('input[name="captcha"]').value = ''; 
                }
            });
    }

    // --- 注册获取验证码 ---
    function sendModalEmail(type) {
        const email = document.getElementById(type === 'register' ? 'mEmail' : 'fEmail').value;
        const captcha = document.getElementById(type === 'register' ? 'mCaptcha' : 'fCaptcha').value;
        const btn = document.getElementById(type === 'register' ? 'mSendBtn' : 'fSendBtn');
        
        if(!email || !captcha) return showMsg('请填写邮箱和图形码', 'error');
        
        btn.disabled = true; btn.innerText = '发送中...';
        const fd = new FormData(); 
        fd.append('email', email); 
        fd.append('captcha', captcha);
        
        const action = type === 'register' ? 'send_email_code' : 'send_reset_code';
        fetch(AUTH_API_PATH + 'index.php?action=' + action, { method: 'POST', body: fd })
            .then(r => r.json()).then(d => {
                if(d.success) {
                    showMsg('验证码已发送', 'success'); 
                    let s = 60;
                    const t = setInterval(() => { 
                        btn.innerText = s-- + 's'; 
                        if(s<0) { clearInterval(t); btn.disabled=false; btn.innerText='获取'; }
                    }, 1000);
                } else { 
                    showMsg(d.msg, 'error'); 
                    btn.disabled=false; 
                    btn.innerText='获取'; 
                    
                    // 🔥 失败后刷新当前验证码
                    const formId = type === 'register' ? 'modalRegForm' : 'modalForgotForm';
                    const img = document.querySelector('#' + formId + ' .captcha-img');
                    if(img) refreshCaptcha(img);
                }
            });
    }

    function handleRegister(e) {
        e.preventDefault(); const btn = document.getElementById('regBtn'); btn.disabled = true;
        fetch(AUTH_USER_PATH + 'login.php', { method: 'POST', body: new FormData(e.target) })
            .then(r => r.json()).then(d => {
                if(d.success) { 
                    showMsg('注册成功！正在登录...', 'success'); 
                    setTimeout(() => window.location.reload(), 1000); 
                } else { 
                    showMsg(d.msg, 'error'); 
                    btn.disabled = false; 
                }
            });
    }

    function handleResetPassword(e) {
        e.preventDefault(); const btn = document.getElementById('resetBtn'); btn.disabled = true;
        fetch(AUTH_USER_PATH + 'login.php', { method: 'POST', body: new FormData(e.target) })
            .then(r => r.json()).then(d => {
                if(d.success) { 
                    showMsg('密码已重置并登录！', 'success'); 
                    setTimeout(() => window.location.reload(), 1000); 
                } else { 
                    showMsg(d.msg, 'error'); 
                    btn.disabled = false; 
                }
            });
    }
</script>
