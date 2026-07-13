/**
 * iOS & iPhone enhancements for PC Gamer Configurator
 * Optimized version: removes inline styles, uses CSS-only approach
 *
 * Changes:
 * - Removed all item.style assignments (width, fontSize, padding, etc.)
 * - Now purely adds classes for CSS to handle
 * - Simplified touch handling moved to mobile-carousel.js
 */
(function() {
    // Run on DOM loaded
    document.addEventListener('DOMContentLoaded', function() {
        // Detect iOS devices
        const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream;

        if (isIOS) {
            // Mark document for iOS-specific CSS (kept for potential future CSS hooks)
            document.documentElement.classList.add('ios-device');

            // Mark carousel items with class instead of inline styles
            markCarouselItemsForIOS();

            // Re-initialize on orientation change
            window.addEventListener('orientationchange', function() {
                setTimeout(markCarouselItemsForIOS, 500);
            });
        }
    });

    function markCarouselItemsForIOS() {
        // Simply mark items with a class - let CSS handle all styling
        document.querySelectorAll('.pcgamer-carousel-wrapper').forEach(function(wrapper) {
            const items = wrapper.querySelectorAll('.upgrade-item');
            items.forEach(function(item) {
                item.classList.add('ios-optimized');
            });
        });

        // Make carousel navigation buttons touch-friendly (size only, no inline styles for dimensions)
        document.querySelectorAll('.pcgamer-carousel-nav').forEach(function(btn) {
            btn.classList.add('ios-touch-friendly');
        });
    }
})();
