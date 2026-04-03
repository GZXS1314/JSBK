/**
 * BKCS 音乐播放器核心逻辑 (极速并发优化版)
 */
document.addEventListener('DOMContentLoaded', function() {
    const audio = document.getElementById('audio-player');
    const lyricsList = document.getElementById('lyrics-list');
    const playBtn = document.getElementById('play-btn');
    const albumArt = document.getElementById('album-art');
    const bgLayer = document.getElementById('bg-layer');
    const playlistArea = document.getElementById('playlist-area');
    const playlistOverlay = document.getElementById('playlist-overlay');
    const playlistToggleBtn = document.getElementById('playlist-toggle-btn');
    const closePlaylistBtn = document.getElementById('close-playlist-btn');

    const PROXY_URL = '/api/proxy.php';
    const DEFAULT_IMG = "https://p2.music.126.net/6y-UleORITEDbvrOLV0Q8A==/5639395138885805.jpg";
    
    let playlist = [];
    let currentIndex = 0;
    let isPlaying = false;
    let lyricData = [];
    let currentLoadToken = 0; 
    
    // 【核心优化】：缓存歌词 DOM 节点和遮罩高度，避免高频读取 DOM
    let cachedLyricNodes = [];
    let cachedMaskHeight = 200;
    let currentActiveIdx = -1;

    if (!document.querySelector("link[rel~='icon']")) {
        const link = document.createElement('link'); link.rel = 'icon'; link.href = 'data:;base64,iVBORw0KGgo='; document.head.appendChild(link);
    }

    async function fetchApi(endpoint, params = {}) {
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), 60000);
        try {
            const query = new URLSearchParams(params).toString();
            const fullPath = endpoint + (query ? '?' + query : '');
            const res = await fetch(`${PROXY_URL}?path=${encodeURIComponent(fullPath)}`, {
                method: 'GET', headers: { 'Accept': 'application/json' }, signal: controller.signal
            });
            clearTimeout(timeoutId);
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            const json = await res.json();
            if (json.code === 500 || json.code === 403) throw new Error(json.msg || '服务器错误');
            return json;
        } catch (e) {
            clearTimeout(timeoutId);
            throw e;
        }
    }

    function getArtist(t) {
        const data = t.song ? t.song : t;
        const ar = data.ar || data.artists || data.singer || data.author;
        if (Array.isArray(ar)) {
            const names = ar.map(a => typeof a === 'object' ? (a.name || a.singer || a.author || '') : a).filter(Boolean);
            if (names.length > 0) return names.join(' / ');
        }
        if (typeof ar === 'string' && ar.trim() !== '') return ar;
        return data.artist || data.author || data.singer || "未知歌手";
    }

    function getPic(t) { 
        const data = t.song ? t.song : t;
        let pic = (data.al?.picUrl || data.album?.picUrl || data.picUrl || data.cover || data.pic) || DEFAULT_IMG; 
        return pic.replace('http://', 'https://'); 
    }

    function setLyricMessage(msg) {
        if (!lyricsList) return;
        lyricsList.innerHTML = `<li class="lyric-line active">${msg}</li>`;
        cachedLyricNodes = []; // 清空缓存节点
        const maskH = document.querySelector('.lyrics-mask')?.offsetHeight || 200;
        lyricsList.style.transform = `translateY(${(maskH / 2) - 20}px)`;
    }

    function updateUI() {
        if (playBtn) playBtn.innerHTML = isPlaying ? '<i class="fas fa-pause"></i>' : '<i class="fas fa-play"></i>';
        if (albumArt) isPlaying ? albumArt.classList.add('playing') : albumArt.classList.remove('playing');
        document.querySelectorAll('.track-item').forEach(el => el.classList.remove('active'));
        const activeItem = document.getElementById(`item-${currentIndex}`);
        if(activeItem) activeItem.classList.add('active');
    }

    async function loadSong(index, shouldPlay) {
        if (!playlist[index]) return;
        
        currentIndex = index;
        const track = playlist[index];
        const myToken = ++currentLoadToken; 

        audio.pause();
        audio.removeAttribute('src');
        isPlaying = false;
        lyricData = [];
        cachedLyricNodes = [];
        currentActiveIdx = -1;
        updateUI();

        document.getElementById('track-name').textContent = track.name || '未知曲目';
        document.getElementById('track-artist').textContent = getArtist(track);
        const pic = getPic(track);
        if (albumArt) albumArt.src = pic + "?param=500y500";
        if (bgLayer) bgLayer.style.backgroundImage = `url(${pic}?param=800y800)`;
        
        setLyricMessage('音源解析中...');

        try {
            // 【核心优化：并发请求】同时去获取歌曲 URL 和 歌词，时间缩短一半！
            const [sJson, lJson] = await Promise.all([
                fetchApi('/song', { id: track.id }).catch(e => ({ error: e })),
                fetchApi('/lyric', { id: track.id }).catch(e => ({ error: e }))
            ]);

            if (myToken !== currentLoadToken) return;

            // 1. 处理音频
            if (sJson.error) {
                setLyricMessage('❌ 音源获取失败');
            } else {
                let songUrl = sJson.data?.[0]?.url || sJson.data?.url || sJson.url;
                if (!songUrl || songUrl === "null") {
                    setLyricMessage('⚠️ 无版权或需 VIP 会员');
                    return;
                }
                audio.src = songUrl.replace('http://', 'https://');
                if (shouldPlay) {
                    const playPromise = audio.play();
                    if (playPromise !== undefined) {
                        playPromise.then(() => {
                            if (myToken === currentLoadToken) { isPlaying = true; updateUI(); }
                        }).catch(e => {
                            if (myToken === currentLoadToken) setLyricMessage('播放受限（需交互）');
                        });
                    }
                }
            }

            // 2. 处理歌词
            if (lJson.error) {
                setLyricMessage('歌词加载失败');
            } else {
                let lrcString = lJson.lrc?.lyric || lJson.data?.lrc?.lyric || lJson.lyric || "";
                if (lJson.nolyric || lJson.uncollected) setLyricMessage('纯音乐，请欣赏');
                else if (lrcString) parseLyrics(lrcString);
                else setLyricMessage('暂无歌词');
            }

        } catch (err) {
            if (myToken === currentLoadToken) setLyricMessage('❌ 致命加载错误');
        }
    }

    function parseLyrics(lrc) {
        lyricData = [];
        const lines = lrc.split('\n');
        const pattern = /\[(\d{2}):(\d{2})\.(\d{2,3})\]/;

        lines.forEach(line => {
            const match = pattern.exec(line);
            if (match) {
                const m = parseInt(match[1]);
                const s = parseInt(match[2]);
                const ms = parseInt(match[3]);
                const time = m * 60 + s + (ms > 99 ? ms / 1000 : ms / 100);
                const text = line.replace(pattern, '').trim();
                if (text) lyricData.push({ time, text });
            }
        });

        lyricData.sort((a, b) => a.time - b.time);
        
        if (lyricData.length > 0) {
            lyricsList.innerHTML = lyricData.map(l => `<li class="lyric-line">${l.text}</li>`).join('');
            // 【核心优化】：解析完立刻缓存 DOM 节点！
            cachedLyricNodes = Array.from(lyricsList.querySelectorAll('.lyric-line'));
            const mask = document.querySelector('.lyrics-mask');
            cachedMaskHeight = mask ? mask.offsetHeight : 200;
        } else {
            setLyricMessage('歌词格式暂不支持');
        }
    }

    // 【核心优化】：消除 DOM 灾难的歌词滚动监听
    audio.addEventListener('timeupdate', () => {
        if (!lyricData.length || !cachedLyricNodes.length) return;
        const currentTime = audio.currentTime;
        
        // 使用高效查找
        let newIdx = -1;
        for (let i = 0; i < lyricData.length; i++) {
            if (currentTime >= lyricData[i].time && (!lyricData[i+1] || currentTime < lyricData[i+1].time)) {
                newIdx = i; break;
            }
        }

        // 仅在当前歌词行改变时，才去触碰 DOM！
        if (newIdx !== -1 && newIdx !== currentActiveIdx) {
            if (currentActiveIdx !== -1 && cachedLyricNodes[currentActiveIdx]) {
                cachedLyricNodes[currentActiveIdx].classList.remove('active');
            }
            if (cachedLyricNodes[newIdx]) {
                cachedLyricNodes[newIdx].classList.add('active');
                const offset = (cachedMaskHeight / 2) - cachedLyricNodes[newIdx].offsetTop - (cachedLyricNodes[newIdx].offsetHeight / 2);
                lyricsList.style.transform = `translateY(${offset}px)`;
                currentActiveIdx = newIdx;
            }
        }
    });

    async function init() {
        if (typeof PLAYLIST_ID === 'undefined' || !PLAYLIST_ID) {
            document.getElementById('loading-text').innerText = "未配置歌单ID"; return;
        }
        try {
            const json = await fetchApi('/playlist', { id: PLAYLIST_ID });
            const tracks = json.data?.playlist?.tracks || json.playlist?.tracks || [];
            if (tracks.length === 0) throw new Error("歌单为空");

            playlist = tracks;
            document.getElementById('p-count').textContent = `共 ${playlist.length} 首`;
            
            const itemsHtml = playlist.map((t, i) => `
                <div class="track-item" id="item-${i}" onclick="window.playSong(${i}, true)">
                    <div class="track-num">${i + 1}</div>
                    <img src="${getPic(t)}?param=50y50" class="track-thumb" onerror="this.src='${DEFAULT_IMG}'">
                    <div class="track-details">
                        <div class="item-title">${t.name || '未知曲目'}</div>
                        <div class="item-artist">${getArtist(t)}</div>
                    </div>
                </div>
            `).join('');
            
            document.getElementById('p-items').innerHTML = itemsHtml;
            document.getElementById('loading-layer').style.display = 'none';
            loadSong(0, false);
        } catch (e) {
            document.getElementById('loading-text').innerText = "获取歌单失败: " + e.message;
        }
    }

    window.playSong = (index, shouldPlay) => {
        loadSong(index, shouldPlay);
        if (window.innerWidth <= 1024) toggleMobilePlaylist();
    };

    if (playBtn) playBtn.onclick = () => {
        if (!audio.src) return;
        if (isPlaying) audio.pause(); else audio.play();
        isPlaying = !isPlaying;
        updateUI();
    };

    document.getElementById('next-btn').onclick = () => window.playSong((currentIndex + 1) % playlist.length, true);
    document.getElementById('prev-btn').onclick = () => window.playSong((currentIndex - 1 + playlist.length) % playlist.length, true);
    audio.onended = () => document.getElementById('next-btn').click();

    function toggleMobilePlaylist() {
        if (!playlistArea || !playlistOverlay) return;
        playlistArea.classList.toggle('show');
        playlistOverlay.classList.toggle('show');
    }
    
    if (playlistToggleBtn) playlistToggleBtn.onclick = toggleMobilePlaylist;
    if (closePlaylistBtn) closePlaylistBtn.onclick = toggleMobilePlaylist;
    if (playlistOverlay) playlistOverlay.onclick = toggleMobilePlaylist;

    init();
});