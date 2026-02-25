document.addEventListener('DOMContentLoaded', function() {

    // --- 轮播图逻辑 ---
    let currentSlide = 0;
    const slides = document.querySelectorAll('.hero-slide');
    const dots = document.querySelectorAll('.slider-dot');
    const totalSlides = slides.length;
    let slideInterval;

    // 将函数绑定到全局 window 对象，以便 HTML 中的 onclick 可以调用
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

    // --- 灯箱 ---
    const lightbox = document.getElementById('lightbox');
    const lbImage = document.getElementById('lbImage');
    
    window.openLightbox = function(src) {
        if (!lightbox || !lbImage) return;
        lbImage.src = src;
        lightbox.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
    
    window.closeLightbox = function() {
        if (!lightbox) return;
        lightbox.classList.remove('active');
        document.body.style.overflow = '';
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
            // 只在内容宽度超出容器时才滚动
            if (scrollContainer.scrollWidth > scrollContainer.clientWidth) {
                evt.preventDefault();
                scrollContainer.scrollLeft += evt.deltaY;
            }
        });
    }
    
    window.scrollCategory = function(direction) {
        if (!scrollContainer) return;
        const scrollAmount = 200; // 每次滚动的像素值
        if (direction === 'left') {
            scrollContainer.scrollBy({ left: -scrollAmount, behavior: 'smooth' });
        } else {
            scrollContainer.scrollBy({ left: scrollAmount, behavior: 'smooth' });
        }
    }

    // --- 画廊筛选 ---
    window.filterGallery = function(category, btn) {
        // 切换激活按钮的样式
        document.querySelectorAll('.folder-card').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        
        const items = document.querySelectorAll('#galleryGrid .art-card');
        items.forEach(item => {
            // 判断是否显示该项
            if(category === 'all' || item.classList.contains(category)) {
                // 使用 CSS 动画实现平滑出现
                item.style.display = 'block';
                // 短暂延迟后应用动画效果
                setTimeout(() => {
                    item.style.opacity = '1';
                    item.style.transform = 'translateY(0)';
                }, 10);
            } else {
                // 平滑隐藏
                item.style.opacity = '0';
                item.style.transform = 'translateY(15px)';
                // 动画结束后再隐藏
                setTimeout(() => {
                    item.style.display = 'none';
                }, 300); // 这里的延迟应与 CSS transition 时间匹配
            }
        });
    }
});
