document.addEventListener('DOMContentLoaded', function() {
    let link = document.querySelector("link[rel~='icon']");
    if (!link) {
        link = document.createElement('link');
        link.rel = 'icon';
        link.href = 'data:;base64,iVBORw0KGgo=';
        document.head.appendChild(link);
    }

    const mainNav = document.getElementById('mainNav');
    let navTimer;

    function showNav() {
        if (!mainNav) return;
        mainNav.classList.remove('nav-idle');
        clearTimeout(navTimer);
        navTimer = setTimeout(() => { mainNav.classList.add('nav-idle'); }, 2000);
    }

    if (mainNav) {
        ['mousemove', 'mousedown', 'touchstart', 'keypress'].forEach(evt => {
            window.addEventListener(evt, showNav);
        });
        showNav();
    }

    const menuBtn = document.getElementById('menuBtn');
    const mobileOverlay = document.getElementById('mobileOverlay');
    const mobileSidebar = document.getElementById('mobileSidebar');

    window.toggleMenu = function() { 
        if(mobileOverlay) mobileOverlay.classList.toggle('active'); 
        if(mobileSidebar) mobileSidebar.classList.toggle('active'); 
    }
    if(menuBtn) menuBtn.onclick = window.toggleMenu;
    if(mobileOverlay) mobileOverlay.onclick = window.toggleMenu;

    const PROXY_URL = '/proxy.php'; 
    const DEFAULT_IMG = "https://p2.music.126.net/6y-UleORITEDbvrOLV0Q8A==/5639395138885805.jpg";

    const audio = document.getElementById('audio-player');
    const lyricsList = document.getElementById('lyrics-list');
    const playBtn = document.getElementById('play-btn');
    const albumArt = document.getElementById('album-art');
    const bgLayer = document.getElementById('bg-layer');

    const playlistArea = document.getElementById('playlist-area');
    const playlistOverlay = document.getElementById('playlist-overlay');
    const playlistToggleBtn = document.getElementById('playlist-toggle-btn');
    const closePlaylistBtn = document.getElementById('close-playlist-btn');

    let playlist = [], currentIndex = 0, isPlaying = false, lyricData = [];

    function toggleMobilePlaylist(e) {
        if(e) e.stopPropagation(); 
        if(window.innerWidth <= 1024) {
            playlistArea.classList.toggle('show');
            playlistOverlay.classList.toggle('show');
        }
    }
    
    if (playlistToggleBtn) playlistToggleBtn.addEventListener('click', toggleMobilePlaylist);
    if (closePlaylistBtn) closePlaylistBtn.addEventListener('click', toggleMobilePlaylist);
    if (playlistOverlay) playlistOverlay.addEventListener('click', toggleMobilePlaylist);

    function getArtist(t) {
        const ar = t.ar || t.artists;
        if (Array.isArray(ar)) return ar.map(a => typeof a === 'object' ? a.name : a).join(' / ');
        return typeof ar === 'string' ? ar : (t.artist || "未知歌手");
    }

    function getPic(t) { return (t.al?.picUrl || t.album?.picUrl || t.picUrl) || DEFAULT_IMG; }

    async function fetchApi(endpoint, data) {
        try {
            const res = await fetch(`${PROXY_URL}?path=${endpoint}`, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(data)
            });
            if (!res.ok) throw new Error(`HTTP error! status: ${res.status}`);
            return await res.json();
        } catch (e) { throw e; }
    }

    async function init() {
        if (typeof PLAYLIST_ID === 'undefined' || !PLAYLIST_ID) {
            document.getElementById('loading-text').innerText = "错误：未配置歌单ID";
            return;
        }
        try {
            const json = await fetchApi('/playlist', {id: PLAYLIST_ID});
            const rawTracks = json.data?.playlist?.tracks || json.playlist?.tracks || json.data || [];
            playlist = Array.isArray(rawTracks) ? rawTracks : [];
            
            if (playlist.length === 0) throw new Error("API返回空歌单");

            document.getElementById('p-count').textContent = `共 ${playlist.length} 首`;
            const items = document.getElementById('p-items');
            items.innerHTML = playlist.map((t, i) => `
                <div class="track-item" id="item-${i}" onclick="window.playSong(${i}, true)">
                    <div class="track-num" style="width:20px;opacity:0.5">${i+1}</div>
                    <img src="${getPic(t)}?param=50y50" class="track-thumb" onerror="this.src='${DEFAULT_IMG}'">
                    <div class="track-details" style="overflow:hidden">
                        <div class="item-title">${t.name}</div>
                        <div class="item-artist">${getArtist(t)}</div>
                    </div>
                </div>
            `).join('');
            
            document.getElementById('loading-layer').style.display = 'none';
            loadSong(0, false);
        } catch (e) { document.getElementById('loading-text').innerText = "加载失败"; }
    }

    // 【封装动态居中逻辑，取代以前写死的90px】
    function setLyricMessage(msg) {
        if (lyricsList) {
            lyricsList.innerHTML = `<li class="lyric-line active">${msg}</li>`;
            const maskElement = document.querySelector('.lyrics-mask');
            const maskH = maskElement ? maskElement.offsetHeight : 200;
            // 居中单行文本
            lyricsList.style.transform = `translateY(${(maskH / 2) - 20}px)`;
        }
    }

    async function loadSong(index, auto) {
        if (!playlist[index]) return;
        currentIndex = index;
        const t = playlist[index];
        
        document.querySelectorAll('.track-item').forEach(el => el.classList.remove('active'));
        const activeItem = document.getElementById(`item-${index}`);
        if(activeItem) activeItem.classList.add('active');
        
        document.getElementById('track-name').textContent = t.name;
        document.getElementById('track-artist').textContent = getArtist(t);
        const pic = getPic(t);
        if (albumArt) albumArt.src = pic + "?param=500y500";
        if (bgLayer) bgLayer.style.backgroundImage = `url(${pic}?param=800y800)`;
        
        lyricData = [];
        setLyricMessage('载入中...');
        
        try {
            const sJson = await fetchApi('/song', {id: t.id});
            const songUrl = sJson.data?.url || sJson.url || sJson.data?.[0]?.url;
            
            if(!songUrl) {
                setLyricMessage('无版权或VIP歌曲');
                return;
            }
            
            audio.src = songUrl;
            if (auto) audio.play().then(() => { isPlaying = true; updateUI(); }).catch(e => {});
            
            try {
                const lJson = await fetchApi('/lyric', {id: t.id});
                let lrcString = "";
                if (lJson.lrc && lJson.lrc.lyric) lrcString = lJson.lrc.lyric;
                else if (lJson.data && lJson.data.lrc && lJson.data.lrc.lyric) lrcString = lJson.data.lrc.lyric;
                else if (lJson.lyric) lrcString = lJson.lyric;

                if (lJson.nolyric || lJson.uncollected) {
                    setLyricMessage('纯音乐');
                    return;
                }

                if (lrcString && lrcString.trim().length > 0) {
                    lyricData = [];
                    const lines = lrcString.split('\n');

                    for (let line of lines) {
                        line = line.trim();
                        if (!line) continue;
                        const closeBracketIndex = line.indexOf(']');
                        if (closeBracketIndex > -1) {
                            const timePart = line.substring(0, closeBracketIndex).replace('[', '');
                            let textPart = line.substring(closeBracketIndex + 1).trim();
                            const timeParts = timePart.split(':');
                            if (timeParts.length >= 2) {
                                const min = parseFloat(timeParts[0]);
                                const sec = parseFloat(timeParts[1]);
                                if (!isNaN(min) && !isNaN(sec)) {
                                    textPart = textPart.replace(/<.*?>/g, '').trim();
                                    if (textPart) {
                                        lyricData.push({ time: min * 60 + sec, text: textPart });
                                    }
                                }
                            }
                        }
                    }

                    lyricData.sort((a, b) => a.time - b.time);

                    if (lyricsList) {
                        if (lyricData.length > 0) {
                            lyricsList.innerHTML = lyricData.map(l => `<li class="lyric-line">${l.text}</li>`).join('');
                        } else {
                            const cleanLines = lines.map(l => l.replace(/<.*?>/g, '').trim()).filter(l => l);
                            lyricsList.innerHTML = cleanLines.map(l => `<li class="lyric-line">${l}</li>`).join('');
                        }
                        
                        // 初次加载完毕，把第一句推到居中位置
                        const maskElement = document.querySelector('.lyrics-mask');
                        const maskH = maskElement ? maskElement.offsetHeight : 200;
                        lyricsList.style.transform = `translateY(${(maskH / 2) - 20}px)`;
                    }
                } else { 
                    setLyricMessage('暂无歌词');
                }
            } catch (lError) {
                setLyricMessage('歌词加载失败');
            }

        } catch (e) { }
    }

    audio.addEventListener('timeupdate', () => {
        if (!lyricData.length || !lyricsList) return;
        const time = audio.currentTime;
        const idx = lyricData.findIndex((l, i) => time >= l.time && (!lyricData[i+1] || time < lyricData[i+1].time));
        
        if (idx !== -1) {
            const lines = lyricsList.querySelectorAll('.lyric-line');
            const currentActive = lyricsList.querySelector('.lyric-line.active');
            
            if (lines[idx] && !lines[idx].classList.contains('active')) {
                if (currentActive) currentActive.classList.remove('active');
                
                const activeLine = lines[idx];
                activeLine.classList.add('active'); 
                
                // 【核心修复：动态计算居中位置】
                // 彻底解决手机端因高度不足、歌词换行导致的截断问题
                const maskElement = document.querySelector('.lyrics-mask');
                const maskH = maskElement ? maskElement.offsetHeight : 200;
                
                // 完美居中公式：遮罩层高度的一半 - (当前行距离顶部的offsetTop + 当前行自身高度的一半)
                const scrollY = (maskH / 2) - (activeLine.offsetTop + (activeLine.offsetHeight / 2));
                
                lyricsList.style.transform = `translateY(${scrollY}px)`; 
            }
        }
    });

    function updateUI() {
        if (playBtn) playBtn.innerHTML = isPlaying ? '<i class="fas fa-pause"></i>' : '<i class="fas fa-play"></i>';
        if (albumArt) isPlaying ? albumArt.classList.add('playing') : albumArt.classList.remove('playing');
    }

    if (playBtn) playBtn.onclick = () => { if(isPlaying) audio.pause(); else audio.play(); isPlaying = !isPlaying; updateUI(); };
    const nextBtn = document.getElementById('next-btn');
    const prevBtn = document.getElementById('prev-btn');
    
    window.playSong = async function(index, auto) {
        await loadSong(index, auto);
        if (window.innerWidth <= 1024 && playlistArea && playlistArea.classList.contains('show')) {
            toggleMobilePlaylist();
        }
    };
    
    if (nextBtn) nextBtn.onclick = () => window.playSong((currentIndex + 1) % playlist.length, true);
    if (prevBtn) prevBtn.onclick = () => window.playSong((currentIndex - 1 + playlist.length) % playlist.length, true);
    if (audio) audio.onended = () => { if (nextBtn) nextBtn.click(); };

    init();
});