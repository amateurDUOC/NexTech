/**
 * iOS & iPhone enhancements for PC Gamer Configurator
 * This script helps make the desktop version better on iOS devices
 */
(function() {
    // Run on DOM loaded
    document.addEventListener('DOMContentLoaded', function() {
        // Detect iOS devices
        const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream;
        
        if (isIOS) {
            // Mark document for iOS-specific CSS
            document.documentElement.classList.add('ios-device');
            
            // Force 3-item layout for categories
            optimizeForIOS();
            
            // Re-initialize on orientation change
            window.addEventListener('orientationchange', function() {
                // Allow layout to settle after orientation change
                setTimeout(optimizeForIOS, 500);
            });
        }
    });
    
    function optimizeForIOS() {
        // Optimize carousel items for iOS
        document.querySelectorAll('.pcgamer-carousel-wrapper').forEach(function(wrapper) {
            const track = wrapper.querySelector('.pcgamer-carousel');
            const items = track.querySelectorAll('.upgrade-item');
            
            // Skip if we don't have enough items
            if (items.length < 3) return;
            
            // Calculate container width
            const containerWidth = track.parentElement.clientWidth - 80; // Subtract button space
            
            // Calculate item width for 3 per view
            const itemWidth = Math.floor((containerWidth / 3) - 15); // 15px spacing between items
            
            // Apply width to all items
            items.forEach(function(item) {
                item.style.width = itemWidth + 'px';
                item.style.minWidth = itemWidth + 'px';
                item.style.maxWidth = itemWidth + 'px';
                
                // Make text smaller to fit
                const title = item.querySelector('p strong');
                if (title) title.style.fontSize = '14px';
                
                // Optimize image height
                const img = item.querySelector('img');
                if (img) img.style.maxHeight = '120px';
                
                // Optimize select buttons
                const selectBtn = item.querySelector('.select-button');
                if (selectBtn) {
                    selectBtn.style.padding = '12px 10px';
                    selectBtn.style.fontSize = '14px';
                }
            });
            
            // Add iOS-specific touch handling
            if (!track._iosHandlersAdded) {
                // Simple swipe detection for desktop iOS
                let startX, startY, moveX, moveY;
                
                track.addEventListener('touchstart', function(e) {
                    startX = e.touches[0].clientX;
                    startY = e.touches[0].clientY;
                }, { passive: true });
                
                track.addEventListener('touchmove', function(e) {
                    moveX = e.touches[0].clientX;
                    moveY = e.touches[0].clientY;
                    
                    // If horizontal movement is greater than vertical
                    if (Math.abs(moveX - startX) > Math.abs(moveY - startY)) {
                        e.preventDefault(); // Prevent page scrolling
                    }
                }, { passive: false });
                
                track._iosHandlersAdded = true;
            }
        });
        
        // Make carousel navigation buttons larger for easier tapping
        document.querySelectorAll('.pcgamer-carousel-nav').forEach(function(btn) {
            btn.style.width = '44px';
            btn.style.height = '44px';
            btn.style.fontSize = '20px';
        });
    }
})();
