document.addEventListener('DOMContentLoaded', function () {
    const wrappers = document.querySelectorAll('.pcgamer-carousel-wrapper');
    const isMobile = window.innerWidth <= 768 || 
                     navigator.userAgent.match(/Android/i) || 
                     navigator.userAgent.match(/iPhone|iPad|iPod/i);

    // Apply mobile-specific text sizing
    if (isMobile) {
        // Reduce product titles by 30% for mobile
        const productTitles = document.querySelectorAll('.upgrade-item p strong');
        productTitles.forEach(title => {
            title.style.fontSize = '12px'; // 30% smaller
            title.style.lineHeight = '1.2';
            title.style.display = '-webkit-box';
            title.style.webkitLineClamp = '4';
            title.style.webkitBoxOrient = 'vertical';
            title.style.overflow = 'hidden';
            title.style.maxHeight = 'none';
            title.style.minHeight = '4.8em'; // 4 lines of text
        });
        
        // Adjust normal text as well
        const productDescriptions = document.querySelectorAll('.upgrade-item p:not(:has(strong))');
        productDescriptions.forEach(desc => {
            desc.style.fontSize = '11px';
            desc.style.lineHeight = '1.3';
            desc.style.webkitLineClamp = '4';
        });
        
        // Increase price text by 25%
        const productPrices = document.querySelectorAll('.upgrade-price, .upgrade-price .woocommerce-Price-amount, .upgrade-price .amount, .upgrade-price bdi');
        productPrices.forEach(price => {
            price.style.fontSize = '125%'; // 25% larger
        });
        
        // Optimize tap targets by increasing their size
        document.querySelectorAll('.select-button').forEach(button => {
            button.style.padding = '10px 8px';
            button.style.minHeight = '36px';
        });
        
        // Optimize product items for touch
        const items = document.querySelectorAll('.upgrade-item');
        items.forEach(item => {
            // Improve touch handling
            item.classList.add('mobile-optimized');
            
            // Prevent accidental scrolling when trying to tap items
            item.addEventListener('touchstart', function(e) {
                if (e.target.closest('.select-button')) return;
                
                // Only prevent default if touching the item itself
                if (e.target === item || item.contains(e.target)) {
                    e.preventDefault();
                }
            }, { passive: false });
            
            // Enhanced tap behavior
            item.addEventListener('touchend', function(e) {
                if (e.target.closest('.select-button')) return;
                
                // Only process the tap if it's on the item itself
                if (e.target === item || item.contains(e.target)) {
                    e.preventDefault();
                    
                    // Find and trigger the select button
                    const selectBtn = item.querySelector('.select-button');
                    if (selectBtn) {
                        selectBtn.click();
                    }
                }
            }, { passive: false });
        });
        
        // Fix layout issues by forcing consistent heights
        enforceConsistentHeights();
    }

    wrappers.forEach(wrapper => {
        const categorySlug = wrapper.dataset.category;

        // Si el paso está bloqueado (PHP lo marca con data-locked="true") no auto-seleccionar nada.
        // compatibility-filters.js lo desbloqueará cuando corresponda.
        const parentDropdown = wrapper.closest('.pcgamer-category-dropdown');
        const isLocked = parentDropdown && parentDropdown.dataset.locked === 'true';

        // Process initial state for pre-selected checkboxes
        const isOptional = ['accesorios', 'monitores'].some(optional =>
            categorySlug.toLowerCase().includes(optional)
        );

        if (!isOptional && !isLocked) {
            // For required categories that are not locked, ensure only one checkbox is selected
            const checkboxes = wrapper.querySelectorAll('input[type="checkbox"]');
            let hasChecked = false;

            checkboxes.forEach(box => {
                if (box.checked) {
                    hasChecked = true;
                    // Apply selected class to parent item
                    updateItemSelection(box);
                }
            });

            // If none are checked, check the first one
            if (!hasChecked && checkboxes.length > 0) {
                checkboxes[0].checked = true;
                updateItemSelection(checkboxes[0]);
            }
        } else if (!isLocked) {
            // For optional categories that are not locked, apply selected class to checked items
            const checkboxes = wrapper.querySelectorAll('input[type="checkbox"]');
            checkboxes.forEach(box => {
                if (box.checked) {
                    updateItemSelection(box);
                }
            });
        }

        // Add click handler for select buttons
        const selectButtons = wrapper.querySelectorAll('.select-button');
        selectButtons.forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                const checkbox = this.previousElementSibling;
                if (checkbox && checkbox.type === 'checkbox') {
                    const categorySlug = checkbox.closest('.pcgamer-carousel-wrapper').dataset.category;
                    const isOptional = ['accesorios', 'monitores'].some(optional =>
                        categorySlug.toLowerCase().includes(optional)
                    );
                    
                    if (isOptional) {
                        // Toggle checkbox for optional categories
                        checkbox.checked = !checkbox.checked;
                    } else {
                        // Always check for required categories
                        checkbox.checked = true;
                        
                        // For required categories, uncheck all other checkboxes immediately
                        const otherCheckboxes = checkbox.closest('.pcgamer-carousel-wrapper').querySelectorAll('input[type="checkbox"]');
                        otherCheckboxes.forEach(box => {
                            if (box !== checkbox && box.checked) {
                                box.checked = false;
                                updateItemSelection(box); // Update visual state
                            }
                        });
                    }
                    
                    // Dispatch change event
                    checkbox.dispatchEvent(new Event('change', { bubbles: true }));
                    
                    // Ensure immediate update of the summary table
                    if (typeof window.updateSummaryTable === 'function') {
                        setTimeout(window.updateSummaryTable, 10);
                    } else if (typeof window.updateTotal === 'function') {
                        setTimeout(window.updateTotal, 10);
                    }
                }
            });
        });

        // Handle regular checkbox changes
        wrapper.addEventListener('change', function (e) {
            if (e.target.type === 'checkbox') {
                // Update selection visuals
                updateItemSelection(e.target);
                
                const isOptional = ['accesorios', 'monitores'].some(optional =>
                    categorySlug.toLowerCase().includes(optional)
                );
                
                // ✅ Permitir múltiples selecciones en categorías opcionales
                if (isOptional) {
                    // Ensure summary table is updated immediately
                    if (typeof window.updateSummaryTable === 'function') {
                        window.updateSummaryTable();
                    }
                    return;
                }

                // ❌ En todas las demás, limitar a solo uno
                const checkboxes = wrapper.querySelectorAll('input[type="checkbox"]');
                checkboxes.forEach(box => {
                    if (box !== e.target) {
                        box.checked = false;
                        // Update UI for other boxes
                        updateItemSelection(box);
                    }
                });
                
                // For required categories, ensure the checkbox stays checked
                // This ensures the summary table doesn't lose the item
                if (!e.target.checked) {
                    e.target.checked = true;
                    updateItemSelection(e.target);
                }
                
                // Immediately refresh the summary table
                if (typeof window.updateSummaryTable === 'function') {
                    window.updateSummaryTable();
                } else if (typeof window.updateTotal === 'function') {
                    window.updateTotal();
                }
            }
        });
        
        // Add click handler to product items (both mobile and desktop)
        const items = wrapper.querySelectorAll('.upgrade-item');
        
        items.forEach(item => {
            // Only disable hover effects on mobile
            if (isMobile) {
                item.addEventListener('mouseenter', function(e) {
                    e.preventDefault();
                    return false;
                });
            }

            // Add click handler for all devices
            item.addEventListener('click', function(e) {
                // Only process clicks outside of the select button
                if (!e.target.classList.contains('select-button')) {
                    // Prevent default behaviors
                    e.preventDefault();
                    e.stopPropagation();
                    
                    // Find the select button and simulate a click
                    const selectButton = item.querySelector('.select-button');
                    if (selectButton) {
                        selectButton.click();
                    }
                }
            });
        });
    });
    
    // Function to update the visual state of an item based on checkbox
    function updateItemSelection(checkbox) {
        const itemElement = checkbox.closest('.upgrade-item');
        if (itemElement) {
            const btn = itemElement.querySelector('.select-button');

            if (checkbox.checked) {
                itemElement.classList.add('selected');
                if (btn) btn.textContent = 'Seleccionado';
                // Ensure we don't apply any transform that might scale the element
                if (window.innerWidth <= 768) itemElement.style.transform = 'none';
            } else {
                itemElement.classList.remove('selected');
                if (btn) btn.textContent = 'Seleccionar';
                if (window.innerWidth <= 768) itemElement.style.transform = 'none';
            }
        }
    }
    
    // Function to handle mobile optimizations
    function enforceConsistentHeights() {
        // Process each category separately
        document.querySelectorAll('.pcgamer-carousel-wrapper').forEach(wrapper => {
            const items = wrapper.querySelectorAll('.upgrade-item');
            if (!items.length) return;
            
            // Reset heights first
            items.forEach(item => {
                const image = item.querySelector('img');
                const title = item.querySelector('p');
                const price = item.querySelector('.upgrade-price');
                
                if (image) image.style.height = '';
                if (title) title.style.height = '';
                if (price) price.style.height = '';
            });
            
            // Set timeout to ensure DOM is fully rendered
            setTimeout(() => {
                // Find the tallest elements and standardize heights
                let maxImageHeight = 0;
                let maxTitleHeight = 0;
                let maxPriceHeight = 0;
                
                items.forEach(item => {
                    const image = item.querySelector('img');
                    const title = item.querySelector('p');
                    const price = item.querySelector('.upgrade-price');
                    
                    if (image) maxImageHeight = Math.max(maxImageHeight, image.offsetHeight);
                    if (title) maxTitleHeight = Math.max(maxTitleHeight, title.scrollHeight);
                    if (price) maxPriceHeight = Math.max(maxPriceHeight, price.offsetHeight);
                });
                
                // Apply standardized heights
                items.forEach(item => {
                    const image = item.querySelector('img');
                    const title = item.querySelector('p');
                    const price = item.querySelector('.upgrade-price');
                    
                    if (image && maxImageHeight > 0) image.style.height = `${maxImageHeight}px`;
                    if (title && maxTitleHeight > 0) {
                        // Allow more height for titles to prevent truncation - up to 4 lines
                        title.style.minHeight = '4.8em'; // 4 lines at 1.2 line height
                        title.style.maxHeight = 'none';
                        title.style.webkitLineClamp = '4';
                    }
                    if (price && maxPriceHeight > 0) price.style.height = `${maxPriceHeight}px`;
                });
            }, 200);
        });
    }
    
    // Update the total price calculation on page load to account for default selections
    if (typeof window.updateSummaryTable === 'function') {
        window.updateSummaryTable();
    } else if (typeof window.updateTotal === 'function') {
        window.updateTotal();
    }
    
    // Also handle window resizing to apply/remove styles when switching between desktop and mobile
    window.addEventListener('resize', function() {
        const newIsMobile = window.innerWidth <= 768;
        
        if (newIsMobile) {
            enforceConsistentHeights();
            
            // Re-apply mobile text scaling
            document.querySelectorAll('.upgrade-item p strong').forEach(title => {
                title.style.fontSize = '13px';
            });
            
            document.querySelectorAll('.upgrade-price, .upgrade-price .woocommerce-Price-amount').forEach(price => {
                price.style.fontSize = '15px';
            });
        }
    });
    
    // Also handle orientation change explicitly
    window.addEventListener('orientationchange', function() {
        if (isMobile) {
            // Allow layout to settle after orientation change
            setTimeout(enforceConsistentHeights, 500);
        }
    });
});