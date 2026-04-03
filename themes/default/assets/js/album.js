document.addEventListener('DOMContentLoaded', function() {

    // --- 轮播图逻辑 ---
    let currentSlide = 0;
    const slides = document.querySelectorAll('.hero-slide');
    const dots = document.querySelectorAll('.slider-dot');
    const totalSlides = slides.length;
    let slideInterval;

    window.showSlide = function(index) {
        if (!slides.length) return;
        slides.forEach(s => s.classList.remove('active'));
        dots.forEach(d => d.classList.remove('active'));
        
        slides[index].classList.add('active');
        if(dots[index]) dots[index].classList.add('active');
        currentSlide = index;
    }

    window.nextSlide = function(e) {
        if(e) e.stopPropagation();
        if (!slides.length) return;
        let next = (currentSlide + 1) % totalSlides;
        showSlide(next);
        resetTimer();
    }

    window.prevSlide = function(e) {
        if(e) e.stopPropagation();
        if (!slides.length) return;
        let prev = (currentSlide - 1 + totalSlides) % totalSlides;
        showSlide(prev);
        resetTimer();
    }

    window.goToSlide = function(index, e) {
        if(e) e.stopPropagation();
        showSlide(index);
        resetTimer();
    }

    function resetTimer() {
        clearInterval(slideInterval);
        slideInterval = setInterval(() => nextSlide(), 5000); 
    }

    if (totalSlides > 1) {
        resetTimer();
    }

    // --- 灯箱逻辑 ---
    const lightbox = document.getElementById('lightbox');
    const lbImage = document.getElementById('lbImage');
    const lbVideo = document.getElementById('lbVideo');
    
    window.openLightbox = function(src, isVideo = false, poster = '') {
        if (!lightbox) return;
        
        if (typeof isVideo !== 'boolean') {
            isVideo = src.match(/\.(mp4|webm|mov)(\?.*)?$/i) !== null;
        }

        if (isVideo) {
            lbImage.style.display = 'none';
            lbImage.src = '';
            lbVideo.style.display = 'block';
            lbVideo.poster = poster; 
            lbVideo.src = src;
            lbVideo.play();
        } else {
            lbVideo.style.display = 'none';
            lbVideo.pause();
            lbVideo.src = '';
            lbVideo.poster = '';
            lbImage.style.display = 'block';
            lbImage.src = src;
        }

        lightbox.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
    
    window.closeLightbox = function() {
        if (!lightbox) return;
        lightbox.classList.remove('active');
        document.body.style.overflow = '';
        if(lbVideo) {
            lbVideo.pause();
            lbVideo.src = '';
            lbVideo.poster = '';
        }
    }
    
    if (lightbox) {
        lightbox.addEventListener('click', (e) => {
            if(e.target === lightbox) closeLightbox();
        });
    }

    // --- 类别横向滚动 ---
    const scrollContainer = document.getElementById('categoryScroll');
    if (scrollContainer) {
        scrollContainer.addEventListener('wheel', (evt) => {
            if (scrollContainer.scrollWidth > scrollContainer.clientWidth) {
                evt.preventDefault();
                scrollContainer.scrollLeft += evt.deltaY;
            }
        });
    }
    
    window.scrollCategory = function(direction) {
        if (!scrollContainer) return;
        const scrollAmount = 200;
        if (direction === 'left') {
            scrollContainer.scrollBy({ left: -scrollAmount, behavior: 'smooth' });
        } else {
            scrollContainer.scrollBy({ left: scrollAmount, behavior: 'smooth' });
        }
    }

    // =========================================================
    // 【核心性能优化】前端 Virtual DOM 分页与极速过滤
    // =========================================================
    const galleryGrid = document.getElementById('galleryGrid');
    const loadMoreBtn = document.getElementById('loadMoreBtn');
    
    let currentPage = 1;
    const pageSize = 16; // 每页渲染 16 张图，兼顾首屏速度与瀑布流排版
    let currentFilterList = window.galleryAllData || [];

    // 渲染函数
    function renderPhotos(isAppend = false) {
        if (!galleryGrid) return;
        
        const start = (currentPage - 1) * pageSize;
        const end = start + pageSize;
        const itemsToRender = currentFilterList.slice(start, end);
        
        let htmlStr = itemsToRender.map(p => {
            const badgeHtml = p.album_name ? `<div class="art-badge">${p.album_name}</div>` : '';
            const isVidStr = p.is_video ? 'true' : 'false';
            
            let mediaHtml = '';
            if (p.is_video) {
                const posterStr = p.video_cover ? `poster="${p.video_cover}"` : '';
                mediaHtml = `
                    <video src="${p.image_url}" ${posterStr} class="art-img" loop muted playsinline onmouseover="this.play()" onmouseout="this.pause(); this.currentTime = 0;"></video>
                    <div class="video-indicator-front"><i class="fa-solid fa-play"></i></div>`;
            } else {
                mediaHtml = `<img src="${p.thumb_url}" class="art-img" loading="lazy" alt="${p.title}">`;
            }
            
            return `
            <div class="art-card" style="animation: fadeIn 0.4s ease forwards;" onclick="openLightbox('${p.image_url}', ${isVidStr}, '${p.video_cover}')">
                ${badgeHtml}
                ${mediaHtml}
                <div class="art-info-panel">
                    <div class="art-text">
                        <h3>${p.title}</h3>
                        <p>${p.device}</p>
                    </div>
                    <div class="art-action"><i class="fa-solid fa-arrow-right"></i></div>
                </div>
            </div>`;
        }).join('');

        if (itemsToRender.length === 0 && !isAppend) {
            htmlStr = '<div style="text-align:center; width:100%; grid-column:1/-1; padding:50px; color:#999;">该相册暂无照片</div>';
        }

        if (isAppend) {
            galleryGrid.insertAdjacentHTML('beforeend', htmlStr);
        } else {
            galleryGrid.innerHTML = htmlStr;
        }

        // 控制 Load More 按钮显示状态
        if (loadMoreBtn) {
            if (end >= currentFilterList.length) {
                loadMoreBtn.style.display = 'none';
            } else {
                loadMoreBtn.style.display = 'inline-block';
            }
        }
        
        // 更新左侧统计数字
        const countSpan = document.getElementById('totalPhotoCount');
        if (countSpan) countSpan.innerText = currentFilterList.length;
    }

    // 绑定“加载更多”按钮
    window.loadMorePhotos = function() {
        loadMoreBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> 加载中...';
        loadMoreBtn.disabled = true;
        
        setTimeout(() => {
            currentPage++;
            renderPhotos(true);
            loadMoreBtn.innerHTML = 'LOAD MORE';
            loadMoreBtn.disabled = false;
        }, 300); // 人为增加 300ms 延迟，提升视觉上的按需加载反馈感
    };

    // 极速画廊筛选
    window.filterGallery = function(category, btn) {
        // 更新激活按钮 UI
        document.querySelectorAll('.folder-card').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        
        // 数组高速过滤
        if (category === 'all') {
            currentFilterList = window.galleryAllData;
        } else {
            const albumId = parseInt(category.replace('album-', ''));
            currentFilterList = window.galleryAllData.filter(p => parseInt(p.album_id) === albumId);
        }
        
        // 重置页码并渲染
        currentPage = 1;
        galleryGrid.style.opacity = '0';
        
        setTimeout(() => {
            renderPhotos(false);
            galleryGrid.style.opacity = '1';
        }, 200); // 配合 CSS opacity 过渡实现平滑刷新
    }

    // 初始化渲染首屏
    if (window.galleryAllData) {
        renderPhotos(false);
    }
});