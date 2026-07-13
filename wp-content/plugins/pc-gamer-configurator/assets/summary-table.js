document.addEventListener('DOMContentLoaded', function () {
    const checkboxes = document.querySelectorAll('input[name="pcgamer_extra[]"]');
    const summaryTable = document.getElementById('pcgamer-summary-table');
    const summaryTotal = document.getElementById('pcgamer-summary-total');
    const basePrice = parseFloat(document.getElementById('pcgamer-total-price').dataset.basePrice || 0);

    // ── Toggle dropdown del resumen ───────────────────────────────────────
    const summaryToggle   = document.getElementById('pcgamer-summary-toggle');
    const summaryDropdown = document.getElementById('pcgamer-summary-table-container');
    if (summaryToggle && summaryDropdown) {
        summaryToggle.addEventListener('click', function () {
            const isOpen = summaryDropdown.classList.toggle('active');
            summaryToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        });
    }

    if (!checkboxes.length || !summaryTable || !summaryTotal) return;

    const selectedProducts = {};
    const categoryLabels = {};

    function formatPrice(number) {
        return new Intl.NumberFormat('es-CL', {
            style: 'currency',
            currency: 'CLP',
            minimumFractionDigits: 0
        }).format(number);
    }

    document.querySelectorAll('.pcgamer-carousel-wrapper').forEach(wrapper => {
        const category = wrapper.dataset.category;
        const label = wrapper.dataset.categoryLabel;
        if (category && label) {
            categoryLabels[category] = label;
            selectedProducts[category] = [];
        }
    });

    function isOptionalCategory(category) {
        return ['Accesorios PC Armado', 'Monitores'].some(c => category.includes(c));
    }

    function updateSummaryTable() {
        const tableBody = summaryTable.querySelector('tbody');
        let total = basePrice;

        let baseRowHTML = '';
        const existingBaseRow = tableBody.querySelector('.base-product-row');

        if (existingBaseRow) {
            const clone = existingBaseRow.cloneNode(true);
            const basePriceCell = clone.querySelector('td:last-child');
            if (basePriceCell) {
                basePriceCell.classList.add('pcgamer-base-price');
            }
            baseRowHTML = clone.outerHTML;
        }

        tableBody.innerHTML = baseRowHTML;

        for (const category in selectedProducts) {
            if (selectedProducts[category] && selectedProducts[category].length > 0) {
                const existingRows = tableBody.querySelectorAll(`tr[data-category="${category}"]`);
                existingRows.forEach(row => row.remove());

                selectedProducts[category].forEach(product => {
                    const row = document.createElement('tr');
                    row.setAttribute('data-category', category);
                    row.setAttribute('data-product-id', product.id);

                    const categoryCell = document.createElement('td');
                    categoryCell.textContent = categoryLabels[category] || category;
                    row.appendChild(categoryCell);

                    const nameCell = document.createElement('td');
                    nameCell.textContent = product.name;
                    row.appendChild(nameCell);

                    const priceCell = document.createElement('td');
                    priceCell.textContent = formatPrice(product.price);
                    row.appendChild(priceCell);

                    tableBody.appendChild(row);
                    total += product.price;
                });
            }
        }

        summaryTotal.textContent = formatPrice(total);

        const totalDisplay = document.getElementById('pcgamer-total-price');
        if (totalDisplay) {
            totalDisplay.innerText = formatPrice(total);
        }
    }

    function refreshSelections() {
        for (const category in selectedProducts) {
            selectedProducts[category] = [];
        }

        document.querySelectorAll('.pcgamer-carousel-wrapper').forEach(wrapper => {
            const category = wrapper.dataset.category;
            if (!category) return;

            const checkboxesInCategory = wrapper.querySelectorAll('input[name="pcgamer_extra[]"]');

            if (isOptionalCategory(category)) {
                checkboxesInCategory.forEach(checkbox => {
                    if (checkbox.checked) {
                        const productData = {
                            id: checkbox.value,
                            name: checkbox.dataset.productName || 'Producto seleccionado',
                            price: parseFloat(checkbox.dataset.price || 0)
                        };

                        if (!selectedProducts[category]) {
                            selectedProducts[category] = [];
                        }

                        if (!selectedProducts[category].some(item => item.id === productData.id)) {
                            selectedProducts[category].push(productData);
                        }
                    }
                });
            } else {
                const checked = wrapper.querySelector('input[name="pcgamer_extra[]"]:checked');
                if (checked) {
                    const productData = {
                        id: checked.value,
                        name: checked.dataset.productName || 'Producto seleccionado',
                        price: parseFloat(checked.dataset.price || 0)
                    };
                    selectedProducts[category] = [productData];
                }
            }
        });

        updateSummaryTable();
    }

    function initializeSelections() {
        document.querySelectorAll('.pcgamer-carousel-wrapper').forEach(wrapper => {
            const category = wrapper.dataset.category;

            if (!isOptionalCategory(category)) {
                const checkboxes = wrapper.querySelectorAll('input[type="checkbox"]');
                let hasChecked = false;

                checkboxes.forEach((checkbox, index) => {
                    if (checkbox.checked) {
                        if (hasChecked) {
                            checkbox.checked = false;
                            const item = checkbox.closest('.upgrade-item');
                            if (item) item.classList.remove('selected');
                        } else {
                            hasChecked = true;
                            checkbox.setAttribute('data-was-checked', 'true');
                        }
                    }
                });

                if (!hasChecked && checkboxes.length > 0) {
                    checkboxes[0].checked = true;
                    const item = checkboxes[0].closest('.upgrade-item');
                    if (item) item.classList.add('selected');
                    checkboxes[0].setAttribute('data-was-checked', 'true');
                }
            }
        });

        refreshSelections();
    }

    checkboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function (e) {
            const wrapper = checkbox.closest('.pcgamer-carousel-wrapper');
            if (!wrapper) return;

            const category = wrapper.dataset.category;
            if (!category) return;

            if (!isOptionalCategory(category)) {
                const otherCheckboxes = wrapper.querySelectorAll('input[type="checkbox"]');
                otherCheckboxes.forEach(box => {
                    if (box !== checkbox && box.checked) {
                        box.checked = false;
                        const boxItem = box.closest('.upgrade-item');
                        if (boxItem) boxItem.classList.remove('selected');
                    }
                });

                if (!checkbox.checked) {
                    checkbox.checked = true;
                }
            }

            checkbox.setAttribute('data-was-checked', checkbox.checked ? 'true' : 'false');
            setTimeout(refreshSelections, 10);
        });
    });

    document.querySelectorAll('.select-button').forEach(button => {
        button.addEventListener('click', function () {
            setTimeout(refreshSelections, 10);
        });
    });

    window.updateSummaryTable = refreshSelections;

    initializeSelections();
});
