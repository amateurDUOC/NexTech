document.addEventListener('DOMContentLoaded', function() {
    // Enhanced mobile detection
    const isMobile = window.innerWidth <= 768 || 
                     navigator.userAgent.match(/Android/i) || 
                     navigator.userAgent.match(/iPhone|iPad|iPod/i);
    
    // Dropdown functionality
    const dropdowns = document.querySelectorAll('.pcgamer-dropdown-header');
    
    dropdowns.forEach(dropdown => {
        dropdown.addEventListener('click', function() {
            const parent = this.parentElement;
            
            // Close all other dropdowns first
            document.querySelectorAll('.pcgamer-category-dropdown').forEach(item => {
                if (item !== parent) {
                    item.classList.remove('active');
                }
            });
            
            // Toggle current dropdown
            parent.classList.toggle('active');
            
            // Initialize carousel when dropdown is opened
            if (parent.classList.contains('active')) {
                const carouselContainer = parent.querySelector('.pcgamer-carousel-container');
                if (carouselContainer) {
                    setTimeout(() => {
                        initializeCarousel(carouselContainer);
                    }, 100); // Small delay to allow CSS transition to complete
                }
            }
        });
    });
    
    // Open the first dropdown by default
    if (dropdowns.length > 0) {
        const firstDropdownParent = dropdowns[0].parentElement;
        firstDropdownParent.classList.add('active');
        
        const firstCarousel = firstDropdownParent.querySelector('.pcgamer-carousel-container');
        if (firstCarousel) {
            setTimeout(() => {
                initializeCarousel(firstCarousel);
            }, 100);
        }
    }
    
    // Function to initialize carousel with improved mobile support
    function initializeCarousel(carouselContainer) {
        const track = carouselContainer.querySelector('.pcgamer-carousel');
        const items = track.querySelectorAll('.upgrade-item');
        
        if (items.length === 0) return;
        
        const prevBtn = carouselContainer.querySelector('.prev');
        const nextBtn = carouselContainer.querySelector('.next');
        
        // Don't reinitialize if already done
        if (track.dataset.initialized === 'true') {
            return;
        }
        
        // Enhanced item width calculation
        const itemStyle = window.getComputedStyle(items[0]);
        let itemWidth = items[0].offsetWidth + 
                       parseInt(itemStyle.marginLeft || 0) + 
                       parseInt(itemStyle.marginRight || 0);
        
        // Get the gap between items
        const gap = parseInt(window.getComputedStyle(track).gap || 0);
        itemWidth += gap; // Add gap to item width calculation
        
        // Calculate container dimensions considering padding
        const containerWidth = carouselContainer.clientWidth - 
                              parseInt(window.getComputedStyle(carouselContainer).paddingLeft) - 
                              parseInt(window.getComputedStyle(carouselContainer).paddingRight);
        
        // Calculate visible items with better precision for mobile
        let visibleItems;
        
        if (window.innerWidth <= 480) {
            visibleItems = Math.max(2, Math.floor(containerWidth / itemWidth));
        } else if (window.innerWidth <= 768) {
            visibleItems = Math.max(2, Math.floor(containerWidth / itemWidth));
        } else {
            visibleItems = Math.floor(containerWidth / itemWidth) || 1;
        }
        
        // Ensure last item is fully visible by adjusting the maximum index
        let currentIndex = 0;
        const maxIndex = Math.max(0, Math.ceil(items.length - visibleItems));
        
        // Explicitly store values in dataset
        track.dataset.initialized = 'true';
        track.dataset.visibleItems = visibleItems;
        track.dataset.itemWidth = itemWidth;
        track.dataset.currentIndex = currentIndex;
        track.dataset.maxIndex = maxIndex;
        
        // Force arrows to be visible on mobile
        if (isMobile) {
            // Always show navigation arrows on mobile if there are enough items
            prevBtn.style.visibility = items.length <= visibleItems ? 'hidden' : 'visible';
            nextBtn.style.visibility = items.length <= visibleItems ? 'hidden' : 'visible';
            
            // Hide prev button only if we're at the beginning
            if (currentIndex === 0) {
                prevBtn.style.visibility = 'hidden';
            }
            
            // Add more pronounced active states for buttons
            [prevBtn, nextBtn].forEach(btn => {
                btn.style.cursor = 'pointer';
                btn.addEventListener('touchstart', function() {
                    this.style.opacity = '1';
                    this.style.transform = 'translateY(-50%) scale(1.1)';
                });
                
                btn.addEventListener('touchend', function() {
                    this.style.opacity = '0.9';
                    this.style.transform = 'translateY(-50%)';
                });
            });
        } else {
            // Default behavior for desktop
            prevBtn.style.visibility = currentIndex === 0 ? 'hidden' : 'visible';
            nextBtn.style.visibility = items.length <= visibleItems ? 'hidden' : 'visible';
        }
        
        // Navigation buttons with improved mobile touch handling
        nextBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            if (currentIndex < maxIndex) {
                currentIndex++;
                track.dataset.currentIndex = currentIndex;
                updateCarousel();
            }
        });
        
        prevBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            if (currentIndex > 0) {
                currentIndex--;
                track.dataset.currentIndex = currentIndex;
                updateCarousel();
            }
        });
        
        // Improved carousel update function
        function updateCarousel() {
            // Use transform for better performance
            track.style.transform = `translateX(-${currentIndex * itemWidth}px)`;
            
            // Update navigation visibility
            prevBtn.style.visibility = currentIndex <= 0 ? 'hidden' : 'visible';
            nextBtn.style.visibility = currentIndex >= maxIndex ? 'hidden' : 'visible';
        }
        
        // Completely revamped touch handling for mobile
        if (isMobile) {
            // Remove any existing event listeners first to avoid duplicates
            track.removeEventListener('touchstart', handleTouchStart);
            track.removeEventListener('touchmove', handleTouchMove);
            track.removeEventListener('touchend', handleTouchEnd);
            
            // Variables for tracking touch
            let startX = 0;
            let startY = 0;
            let currentX = 0;
            let currentY = 0;
            let startTranslate = 0;
            let isTouching = false;
            let isScrollingVertically = false;
            
            // Attach new optimized touch listeners
            track.addEventListener('touchstart', handleTouchStart, { passive: true });
            track.addEventListener('touchmove', handleTouchMove, { passive: false });
            track.addEventListener('touchend', handleTouchEnd, { passive: true });
            
            function handleTouchStart(e) {
                if (!e.touches || e.touches.length === 0) return;
                
                const touch = e.touches[0];
                startX = touch.clientX;
                startY = touch.clientY;
                currentX = startX;
                currentY = startY;
                isTouching = true;
                isScrollingVertically = false;
                
                // Get the current transform value
                const style = window.getComputedStyle(track);
                const matrix = new DOMMatrix(style.transform);
                startTranslate = matrix.m41; // translateX value
                
                // Remove transition while dragging for responsive feel
                track.style.transition = 'none';
            }
            
            function handleTouchMove(e) {
                if (!isTouching || !e.touches || e.touches.length === 0) return;
                
                const touch = e.touches[0];
                currentX = touch.clientX;
                currentY = touch.clientY;
                
                // Calculate movement distance
                const deltaX = startX - currentX;
                const deltaY = Math.abs(startY - currentY);
                
                // Detect vertical scrolling
                if (deltaY > Math.abs(deltaX) * 1.2) {
                    isScrollingVertically = true;
                    return;
                }
                
                // If scrolling horizontally, prevent page scrolling
                if (Math.abs(deltaX) > 10 && !isScrollingVertically) {
                    e.preventDefault();
                    
                    // Apply resistance at edges
                    let moveX = deltaX;
                    if ((currentIndex === 0 && deltaX < 0) || 
                        (currentIndex >= maxIndex && deltaX > 0)) {
                        moveX = deltaX * 0.3;
                    }
                    
                    // Apply the transform with smooth movement
                    track.style.transform = `translateX(${startTranslate - moveX}px)`;
                }
            }
            
            function handleTouchEnd(e) {
                if (!isTouching) return;
                isTouching = false;
                
                // Restore transition for smooth snapping
                track.style.transition = '';
                
                // If we're scrolling vertically, don't change slides
                if (isScrollingVertically) return;
                
                // Calculate swipe distance
                const deltaX = startX - currentX;
                const absX = Math.abs(deltaX);
                
                // Swipe detection threshold: 50px or 30% of item width
                const threshold = Math.min(50, itemWidth * 0.3);
                
                if (absX >= threshold) {
                    // Swipe right -> previous slide
                    if (deltaX < 0 && currentIndex > 0) {
                        currentIndex--;
                    }
                    // Swipe left -> next slide
                    else if (deltaX > 0 && currentIndex < maxIndex) {
                        currentIndex++;
                    }
                }
                
                // Update track position and navigation
                track.dataset.currentIndex = currentIndex;
                updateCarousel();
            }
        }
        
        // Initial update to ensure correct positioning
        updateCarousel();
    }
    
    // Handle window resize
    let resizeTimer;
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function() {
            document.querySelectorAll('.pcgamer-category-dropdown.active .pcgamer-carousel-container').forEach(carousel => {
                // Reset carousel
                const track = carousel.querySelector('.pcgamer-carousel');
                track.dataset.initialized = 'false';
                track.style.transform = 'translateX(0)';
                
                // Reinitialize
                initializeCarousel(carousel);
            });
        }, 250);
    });
    
    // Ensure dropdowns close completely on mobile
    function ensureDropdownsClose() {
        // Add click listener to document to close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            // Only if we're on mobile
            if (window.innerWidth <= 768) {
                const isDropdown = event.target.closest('.pcgamer-category-dropdown');
                if (!isDropdown) {
                    document.querySelectorAll('.pcgamer-category-dropdown').forEach(dropdown => {
                        dropdown.classList.remove('active');
                    });
                }
            }
        });
    }
    
    ensureDropdownsClose();
});
