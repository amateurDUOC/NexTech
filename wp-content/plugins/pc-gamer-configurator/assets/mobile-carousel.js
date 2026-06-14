(function () {
    // Detección de móvil mejorada
    const isMobile = () => window.innerWidth <= 768 ||
        /Android|iPhone|iPad|iPod/i.test(navigator.userAgent);

    if (!isMobile()) return;

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

        // Obtener ancho real del item desde CSS
        const firstItem = track.querySelector('.upgrade-item');
        let itemWidth = firstItem ? firstItem.getBoundingClientRect().width : 150;

        if (!itemWidth || itemWidth === 0) {
            itemWidth = 150;
        }

        // Eliminar cualquier dummy previamente agregado
        track.querySelectorAll('.upgrade-item.dummy').forEach(d => d.remove());

        // Añadir dummy si hay más de 1 producto
        if (isMobile && items.length > 1) {
            const dummy = document.createElement('div');
            dummy.className = 'upgrade-item dummy';
            dummy.style.visibility = 'hidden';
            dummy.style.pointerEvents = 'none';
            track.appendChild(dummy);
        }

        items = track.querySelectorAll('.upgrade-item');

        // Recalcular itemWidth después de que el DOM esté actualizado
        const actualFirstItem = track.querySelector('.upgrade-item');
        if (actualFirstItem) {
            itemWidth = actualFirstItem.getBoundingClientRect().width;
        }

        items.forEach(item => {
            item.classList.add('mobile-optimized');
        });

        const totalItems = items.length;
        const actualItemsCount = Array.from(items).filter(item => !item.classList.contains('dummy')).length;

        // CORREGIDO: Leer el gap del CSS computed styles, no forzar 0px
        const computedGap = parseInt(window.getComputedStyle(track).gap) || 16;

        // CORREGIDO: Calcular ancho considerando gaps: (itemWidth * totalItems) + (gaps entre items)
        const totalGapWidth = computedGap * (totalItems - 1);
        track.style.width = `${(itemWidth * totalItems) + totalGapWidth}px`;
        // NO SOBRESCRIBIR EL GAP: track.style.gap = '0px'; ← REMOVIDO

        let currentIndex = 0;
        track._initialized = true;
        track._currentIndex = currentIndex;
        track._totalItems = totalItems;
        track._itemWidth = itemWidth;
        track._computedGap = computedGap;

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
            const visibleItemsCount = Math.floor(container.clientWidth / (itemWidth + computedGap));
            if (currentIndex < totalItems - visibleItemsCount) {
                currentIndex++;
                updateCarouselPosition();
                updateButtonVisibility();
            }
        }

        function updateCarouselPosition() {
            const maxTranslate = ((itemWidth + computedGap) * totalItems) - container.clientWidth;
            const desiredTranslate = (itemWidth + computedGap) * currentIndex;
            track.style.transform = `translateX(-${Math.min(desiredTranslate, maxTranslate)}px)`;
            track._currentIndex = currentIndex;
        }

        function updateButtonVisibility() {
            const itemsToCount = Array.from(items).filter(item => !item.classList.contains('dummy')).length;
            const visibleItemsCount = Math.floor(container.clientWidth / (itemWidth + computedGap));
            if (itemsToCount > visibleItemsCount) {
                if (prevBtn) prevBtn.style.visibility = currentIndex > 0 ? 'visible' : 'hidden';
                if (nextBtn) nextBtn.style.visibility = currentIndex < (itemsToCount - visibleItemsCount) ? 'visible' : 'hidden';
            } else {
                if (prevBtn) prevBtn.style.visibility = 'hidden';
                if (nextBtn) nextBtn.style.visibility = 'hidden';
            }
        }

        setupTouchSwipe(track, itemWidth, computedGap, totalItems, container, actualItemsCount);
    }

    function setupTouchSwipe(track, itemWidth, computedGap, totalItems, container, actualItemsCount = totalItems) {
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
            const itemWithGap = itemWidth + computedGap;
            const threshold = itemWithGap * 0.2;

            if (Math.abs(diffX) > threshold) {
                if (diffX > 0 && currentIndex > 0) {
                    currentIndex--;
                } else if (diffX < 0 && currentIndex < totalItems - 1) {
                    currentIndex++;
                }
            }

            const maxTranslate = (itemWithGap * totalItems) - container.clientWidth;
            const desiredTranslate = itemWithGap * currentIndex;
            track.style.transform = `translateX(-${Math.min(desiredTranslate, maxTranslate)}px)`;
            track._currentIndex = currentIndex;

            const prevBtn = container.querySelector('.prev');
            const nextBtn = container.querySelector('.next');
            const visibleItems = Math.floor(container.clientWidth / itemWithGap);
            if (prevBtn) prevBtn.style.visibility = currentIndex > 0 ? 'visible' : 'hidden';
            if (nextBtn) nextBtn.style.visibility = currentIndex < (actualItemsCount - visibleItems) ? 'visible' : 'hidden';
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
