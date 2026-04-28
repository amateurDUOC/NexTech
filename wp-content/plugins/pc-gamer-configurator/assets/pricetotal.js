document.addEventListener('DOMContentLoaded', function () {
    const checkboxes = document.querySelectorAll('input[name="pcgamer_extra[]"]');
    const totalDisplay = document.getElementById('pcgamer-total-price');
    const summaryTotal = document.getElementById('pcgamer-summary-total');

    if (!totalDisplay || checkboxes.length === 0) return;

    const basePrice = parseFloat(totalDisplay.dataset.basePrice);

    function formatPrice(number) {
        return new Intl.NumberFormat('es-CL', {
            style: 'currency',
            currency: 'CLP',
            minimumFractionDigits: 0
        }).format(number);
    }

    window.updateTotal = function() {
        let total = basePrice;

        checkboxes.forEach(checkbox => {
            if (checkbox.checked) {
                const extraPrice = parseFloat(checkbox.dataset.price);
                if (!isNaN(extraPrice)) {
                    total += extraPrice;
                }
            }
        });

        totalDisplay.innerText = formatPrice(total);
        
        // Update summary total if it exists
        if (summaryTotal) {
            summaryTotal.innerText = formatPrice(total);
        }
    };

    checkboxes.forEach(checkbox => {
        checkbox.addEventListener('change', updateTotal);
    });

    // Initialize on load to account for default selected checkboxes
    updateTotal();
});
