(function () {
    const isMobile = window.innerWidth <= 768 ||
        navigator.userAgent.match(/Android/i) ||
        navigator.userAgent.match(/iPhone|iPad|iPod/i);

    if (!isMobile) return;

    document.addEventListener('DOMContentLoaded', function () {
        setTimeout(initMobileCarousels, 300);

        window.addEventListener('orientationchange', function () {
            setTimeout(initMobileCarousels, 500);
        });

        let resizeTimer;
        window.addEventListener('resize', function () {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(initMobileCarousels, 250);
        });
    });

    function initMobileCarousels() {
        document.querySelectorAll('.pcgamer-carousel-container').forEach(function (container) {
            initSingleProductCarousel(container);
        });
    }

    function initSingleProductCarousel(container) {
        const track = container.querySelector('.pcgamer-carousel');
        let items = track.querySelectorAll('.upgrade-item');
        const prevBtn = container.querySelector('.prev');
        const nextBtn = container.querySelector('.next');

        if (!track || items.length === 0) return;

        cleanupCarousel(track, prevBtn, nextBtn);

        // 📱 Diferenciar ancho entre iPhone y Android
        const isiPhone = /iPhone|iPad|iPod/i.test(navigator.userAgent);
        const isAndroid = /Android/i.test(navigator.userAgent);
        const itemWidth = isiPhone ? 218 : (isAndroid ? 150 : 218);

        // 🔁 Eliminar cualquier dummy previamente agregado
        track.querySelectorAll('.upgrade-item.dummy').forEach(d => d.remove());

        // 👉 Añadir dummy si hay más de 1 producto
        if (isMobile && items.length > 1) {
            const dummy = document.createElement('div');
            dummy.className = 'upgrade-item dummy';
            dummy.style.width = `${itemWidth}px`;
            dummy.style.minWidth = `${itemWidth}px`;
            dummy.style.maxWidth = `${itemWidth}px`;
            dummy.style.visibility = 'hidden';
            dummy.style.pointerEvents = 'none';
            track.appendChild(dummy);
        }

        items = track.querySelectorAll('.upgrade-item');

        items.forEach(item => {
            item.classList.add('mobile-optimized');
            item.style.width = `${itemWidth}px`;
            item.style.minWidth = `${itemWidth}px`;
            item.style.maxWidth = `${itemWidth}px`;
            item.style.margin = '0';
            item.style.height = 'auto';
            item.style.display = 'flex';
            item.style.flexDirection = 'column';
            item.style.justifyContent = 'space-between';
            item.style.padding = '15px';
            item.style.boxSizing = 'border-box';

            const img = item.querySelector('img');
            if (img) {
                img.style.maxHeight = '150px';
                img.style.objectFit = 'contain';
                img.style.margin = '0 auto 15px';
            }

            const title = item.querySelector('p strong');
            if (title) {
                title.style.fontSize = '12px';
                title.style.lineHeight = '1.2';
                title.style.display = '-webkit-box';
                title.style.webkitLineClamp = '4';
                title.style.webkitBoxOrient = 'vertical';
                title.style.overflow = 'hidden';
                title.style.maxHeight = 'none';
                title.style.minHeight = '4.8em';
            }

            const description = item.querySelector('p');
            if (description) {
                description.style.fontSize = '11px';
                description.style.lineHeight = '1.3';
                description.style.webkitLineClamp = '4';
                description.style.margin = '6px 0';
                description.style.display = '-webkit-box';
                description.style.webkitBoxOrient = 'vertical';
                description.style.overflow = 'hidden';
                description.style.textOverflow = 'ellipsis';
            }

            const button = item.querySelector('.select-button');
            if (button) {
                button.style.marginTop = 'auto';

                const isMobile = /Android|iPhone|iPad|iPod/i.test(navigator.userAgent) || window.innerWidth <= 768;
                button.style.padding = isMobile ? '1px' : '12px 15px';
                button.style.minHeight = '44px';

                if (isMobile) {
                    button.style.display = 'flex';
                    button.style.alignItems = 'center';
                    button.style.justifyContent = 'center';
                    button.style.textAlign = 'center';
                }
            }
        });

        const totalItems = items.length;
        track.style.width = `${itemWidth * totalItems}px`;
        track.style.gap = '0px';

        let currentIndex = 0;
        track._initialized = true;
        track._currentIndex = currentIndex;
        track._totalItems = totalItems;
        track._itemWidth = itemWidth;

        updateButtonVisibility();

        if (prevBtn) {
            prevBtn._clickHandler = handlePrevClick;
            prevBtn.addEventListener('click', handlePrevClick);
        }

        if (nextBtn) {
            nextBtn._clickHandler = handleNextClick;
            nextBtn.addEventListener('click', handleNextClick);
        }

        function handlePrevClick(e) {
            e.preventDefault();
            e.stopPropagation();
            if (currentIndex > 0) {
                currentIndex--;
                updateCarouselPosition();
                updateButtonVisibility();
            }
        }

        function handleNextClick(e) {
            e.preventDefault();
            e.stopPropagation();
            const visibleItemsCount = Math.floor(container.clientWidth / itemWidth);
            if (currentIndex < totalItems - visibleItemsCount) {
                currentIndex++;
                updateCarouselPosition();
                updateButtonVisibility();
            }
        }

        function updateCarouselPosition() {
            const maxTranslate = (itemWidth * totalItems) - container.clientWidth;
            const desiredTranslate = currentIndex * itemWidth;
            track.style.transform = `translateX(-${Math.min(desiredTranslate, maxTranslate)}px)`;
            track._currentIndex = currentIndex;
        }

        function updateButtonVisibility() {
            const visibleItemsCount = Math.floor(container.clientWidth / itemWidth);
            if (totalItems > visibleItemsCount) {
                if (prevBtn) prevBtn.style.visibility = currentIndex > 0 ? 'visible' : 'hidden';
                if (nextBtn) nextBtn.style.visibility = currentIndex < (totalItems - visibleItemsCount) ? 'visible' : 'hidden';
            } else {
                if (prevBtn) prevBtn.style.visibility = 'hidden';
                if (nextBtn) nextBtn.style.visibility = 'hidden';
            }
        }

        setupTouchSwipe(track, itemWidth, totalItems);
    }

    function setupTouchSwipe(track, itemWidth, totalItems) {
        let startX, startY;
        let isDragging = false;
        let currentTranslate = 0;
        let startTranslate = 0;
        let currentIndex = 0;

        if (track._touchHandlersSet) {
            track.removeEventListener('touchstart', track._touchStartHandler);
            track.removeEventListener('touchmove', track._touchMoveHandler);
            track.removeEventListener('touchend', track._touchEndHandler);
        }

        track._touchStartHandler = handleTouchStart;
        track._touchMoveHandler = handleTouchMove;
        track._touchEndHandler = handleTouchEnd;
        track._touchHandlersSet = true;

        track.addEventListener('touchstart', handleTouchStart, { passive: true });
        track.addEventListener('touchmove', handleTouchMove, { passive: false });
        track.addEventListener('touchend', handleTouchEnd, { passive: true });

        function handleTouchStart(e) {
            if (!e.touches || e.touches.length === 0) return;
            startX = e.touches[0].clientX;
            startY = e.touches[0].clientY;
            isDragging = true;

            const style = window.getComputedStyle(track);
            const transform = style.transform || style.webkitTransform;
            currentTranslate = transform !== 'none' ? new WebKitCSSMatrix(transform).m41 : 0;
            startTranslate = currentTranslate;

            track.style.transition = 'none';
            currentIndex = track._currentIndex || 0;
        }

        function handleTouchMove(e) {
            if (!isDragging || !e.touches || e.touches.length === 0) return;
            const touchX = e.touches[0].clientX;
            const touchY = e.touches[0].clientY;
            const diffX = touchX - startX;
            const diffY = Math.abs(touchY - startY);

            if (diffY > Math.abs(diffX) * 1.2) return;

            if (Math.abs(diffX) > 5) {
                e.preventDefault();
                const newTranslate = startTranslate + diffX;

                if ((currentIndex === 0 && diffX > 0) || (currentIndex === totalItems - 1 && diffX < 0)) {
                    currentTranslate = startTranslate + (diffX * 0.2);
                } else {
                    currentTranslate = newTranslate;
                }

                track.style.transform = `translateX(${currentTranslate}px)`;
            }
        }

        function handleTouchEnd(e) {
            if (!isDragging) return;
            isDragging = false;
            track.style.transition = 'transform 0.3s ease';

            const touchEndX = e.changedTouches[0].clientX;
            const diffX = touchEndX - startX;
            const threshold = itemWidth * 0.2;

            if (Math.abs(diffX) > threshold) {
                if (diffX > 0 && currentIndex > 0) {
                    currentIndex--;
                } else if (diffX < 0 && currentIndex < totalItems - 1) {
                    currentIndex++;
                }
            }

            const maxTranslate = (itemWidth * totalItems) - track.closest('.pcgamer-carousel-container').clientWidth;
            const desiredTranslate = currentIndex * itemWidth;
            track.style.transform = `translateX(-${Math.min(desiredTranslate, maxTranslate)}px)`;
            track._currentIndex = currentIndex;

            const container = track.closest('.pcgamer-carousel-container');
            if (container) {
                const prevBtn = container.querySelector('.prev');
                const nextBtn = container.querySelector('.next');
                const visibleItems = Math.floor(container.clientWidth / itemWidth);
                if (prevBtn) prevBtn.style.visibility = currentIndex > 0 ? 'visible' : 'hidden';
                if (nextBtn) nextBtn.style.visibility = currentIndex < (totalItems - visibleItems) ? 'visible' : 'hidden';
            }
        }
    }

    function cleanupCarousel(track, prevBtn, nextBtn) {
        if (track._touchHandlersSet) {
            track.removeEventListener('touchstart', track._touchStartHandler);
            track.removeEventListener('touchmove', track._touchMoveHandler);
            track.removeEventListener('touchend', track._touchEndHandler);
            track._touchHandlersSet = false;
        }

        if (prevBtn && prevBtn._clickHandler) {
            prevBtn.removeEventListener('click', prevBtn._clickHandler);
        }

        if (nextBtn && nextBtn._clickHandler) {
            nextBtn.removeEventListener('click', nextBtn._clickHandler);
        }

        track.style.transform = 'translateX(0)';
        track.style.transition = 'transform 0.3s ease';
        track._initialized = false;
    }

    window.pcgamerMobileCarousel = {
        init: initMobileCarousels,
        refresh: function () {
            document.querySelectorAll('.pcgamer-carousel-container').forEach(function (container) {
                cleanupCarousel(
                    container.querySelector('.pcgamer-carousel'),
                    container.querySelector('.prev'),
                    container.querySelector('.next')
                );
                initSingleProductCarousel(container);
            });
        }
    };
})();