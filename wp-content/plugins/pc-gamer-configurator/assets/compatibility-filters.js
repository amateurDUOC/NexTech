/**
 * PC Gamer Configurator - Compatibility Filters + Step Locking
 * v0.9.0
 *
 * Responsabilidades:
 * 1. Filtro en cascada: al seleccionar un componente, filtra las categorías que dependen de él.
 * 2. Validación de build: verifica compatibilidad cruzada de todos los componentes seleccionados.
 * 3. Control de pasos: bloquea cada paso hasta que el anterior esté completado, guiando al usuario.
 */

(function () {
    'use strict';

    // ── CONFIGURACIÓN ────────────────────────────────────────────────────────

    /**
     * Orden de pasos requeridos. La clave debe coincidir EXACTAMENTE con el
     * atributo data-category del HTML (= clave en $plugin_categories en PHP).
     */
    const STEP_ORDER = [
        'Procesadores PC Armado',
        'Placas PC Armado',
        'Memoria RAM PC Armado',
        'Almacenamiento PC Armado',
        'Fuente de Poder PC Armado',
        'Gabinetes PC Armado',
        'refrigeracion',
    ];

    const STEP_LABELS = {
        'Procesadores PC Armado':    'Procesador',
        'Placas PC Armado':          'Placa Madre',
        'Memoria RAM PC Armado':     'Memoria RAM',
        'Almacenamiento PC Armado':  'Almacenamiento',
        'Fuente de Poder PC Armado': 'Fuente de Poder',
        'Gabinetes PC Armado':       'Gabinete',
        'refrigeracion':             'Refrigeración',
    };

    /**
     * Define qué categorías dependen de cuáles y cómo se verifica compatibilidad.
     * KEY = categoría dependiente; 'dependencies' = categorías que la restringen.
     */
    const dependencyMap = {
        'Placas PC Armado': {
            dependencies: ['Procesadores PC Armado'],
            check_via: 'socket',
        },
        'Memoria RAM PC Armado': {
            dependencies: ['Procesadores PC Armado'],
            check_via: 'ram_type',
        },
        'Gabinetes PC Armado': {
            dependencies: ['Placas PC Armado'],
            check_via: 'form_factor',
        },
        'refrigeracion': {
            dependencies: ['Procesadores PC Armado'],
            check_via: 'socket',
        },
        'Almacenamiento PC Armado': {
            dependencies: [],
            check_via: null,
        },
        'Fuente de Poder PC Armado': {
            dependencies: [],
            check_via: null,
        },
    };

    // ── ESTADO ───────────────────────────────────────────────────────────────

    /** { 'Procesadores PC Armado': 123, 'Placas PC Armado': 456, ... } */
    let selectedComponents = {};
    let ajaxNonce  = '';
    let ajaxUrl    = '/wp-admin/admin-ajax.php';

    // ── INIT ─────────────────────────────────────────────────────────────────

    document.addEventListener('DOMContentLoaded', function () {
        if (typeof pcgamerAjax !== 'undefined') {
            ajaxNonce = pcgamerAjax.nonce;
            ajaxUrl   = pcgamerAjax.ajaxUrl;
        }

        initStepLocking();

        document.querySelectorAll('input[name="pcgamer_extra[]"]').forEach(cb => {
            cb.addEventListener('change', handleComponentSelection);
        });

        // Botón "↺ Borrar selección" — limpia TODO de una vez
        document.addEventListener('click', function(e) {
            if (!e.target.closest('#pcgamer-reset-all')) return;
            e.stopPropagation();

            // Desmarcar todos los checkboxes del configurador
            document.querySelectorAll('input[name="pcgamer_extra[]"]').forEach(cb => {
                cb.checked = false;
            });

            // Limpiar estado interno
            selectedComponents = {};

            // Restaurar todos los carruseles
            STEP_ORDER.forEach(slug => restoreDependentCarousels(slug));

            // Re-bloquear todos los pasos excepto el primero
            for (let i = 1; i < STEP_ORDER.length; i++) {
                relockStep(STEP_ORDER[i], i);
            }

            // Limpiar mensajes de validación
            const validationContainer = document.querySelector('.pcgamer-validation-messages');
            if (validationContainer) validationContainer.innerHTML = '';
        });
    });

    // ── CONTROL DE PASOS ─────────────────────────────────────────────────────

    function getDropdownForCategory(slug) {
        const wrapper = document.querySelector(`.pcgamer-carousel-wrapper[data-category="${slug}"]`);
        return wrapper ? wrapper.closest('.pcgamer-category-dropdown') : null;
    }

    function initStepLocking() {
        STEP_ORDER.forEach((slug, index) => {
            if (index === 0) return; // El primer paso siempre disponible

            const dropdown = getDropdownForCategory(slug);
            if (!dropdown) return;

            applyLock(dropdown, slug, index);
        });
    }

    function applyLock(dropdown, slug, stepIndex) {
        dropdown.dataset.locked = 'true';
        dropdown.classList.add('pcgamer-step-locked');
        dropdown.classList.remove('active');

        // Badge de bloqueo en el header
        const header = dropdown.querySelector('.pcgamer-dropdown-header');
        if (header && !header.querySelector('.pcgamer-lock-badge')) {
            const badge = document.createElement('span');
            badge.className = 'pcgamer-lock-badge';
            badge.innerHTML = ' 🔒';
            badge.style.cssText = 'font-size:14px;opacity:0.6;';
            header.querySelector('h3').appendChild(badge);
        }

        // Interceptar click en pasos bloqueados (fase capture, antes de carousel-dropdown.js)
        header.addEventListener('click', function onLockedClick(e) {
            if (dropdown.dataset.locked !== 'true') {
                header.removeEventListener('click', onLockedClick, true);
                return;
            }
            e.stopImmediatePropagation();
            const prevLabel = STEP_LABELS[STEP_ORDER[stepIndex - 1]] || STEP_ORDER[stepIndex - 1];
            showToast(dropdown, `🔒 Primero completa: ${prevLabel}`);
        }, true);
    }

    /**
     * Re-bloquea un paso que estaba desbloqueado, limpiando su selección.
     * Se usa cuando el usuario limpia un paso anterior.
     */
    function relockStep(slug, stepIndex) {
        const dropdown = getDropdownForCategory(slug);
        if (!dropdown || dropdown.dataset.locked === 'true') return;

        // Desmarcar checkbox seleccionado en este paso sin disparar change (evitar cascada)
        const wrapper = document.querySelector(`.pcgamer-carousel-wrapper[data-category="${slug}"]`);
        if (wrapper) {
            wrapper.querySelectorAll('input[type="checkbox"]:checked').forEach(cb => {
                cb.checked = false;
            });
        }
        delete selectedComponents[slug];
        restoreDependentCarousels(slug);

        applyLock(dropdown, slug, stepIndex);
    }

    function unlockStep(slug) {
        const dropdown = getDropdownForCategory(slug);
        if (!dropdown || dropdown.dataset.locked !== 'true') return;

        dropdown.dataset.locked = 'false';
        dropdown.classList.remove('pcgamer-step-locked');

        // Quitar badge de candado
        const badge = dropdown.querySelector('.pcgamer-lock-badge');
        if (badge) badge.remove();

        // Auto-abrir el siguiente paso y scroll hacia él
        dropdown.classList.add('active');
        const carousel = dropdown.querySelector('.pcgamer-carousel-container');
        if (carousel && typeof window.pcgamerInitCarousel === 'function') {
            setTimeout(() => window.pcgamerInitCarousel(carousel), 120);
        }
        setTimeout(() => dropdown.scrollIntoView({ behavior: 'smooth', block: 'start' }), 150);
    }

    function showToast(dropdown, message) {
        let toast = dropdown.querySelector('.pcgamer-toast');
        if (!toast) {
            toast = document.createElement('div');
            toast.className = 'pcgamer-toast';
            toast.style.cssText = [
                'padding:10px 16px',
                'background:#fff3cd',
                'color:#856404',
                'font-size:14px',
                'border-left:3px solid #ffc107',
                'margin:4px 0',
                'border-radius:0 4px 4px 0',
                'transition:opacity 0.3s',
            ].join(';');
            dropdown.querySelector('.pcgamer-dropdown-header').insertAdjacentElement('afterend', toast);
        }
        toast.textContent = message;
        toast.style.opacity = '1';
        clearTimeout(toast._timer);
        toast._timer = setTimeout(() => {
            toast.style.opacity = '0';
            setTimeout(() => toast.remove(), 320);
        }, 2500);
    }

    // ── SELECCIÓN DE COMPONENTES ─────────────────────────────────────────────

    function handleComponentSelection(event) {
        const checkbox        = event.target;
        const carouselWrapper = checkbox.closest('.pcgamer-carousel-wrapper');
        if (!carouselWrapper) return;

        const selectedCategory = carouselWrapper.getAttribute('data-category');
        const selectedId       = parseInt(checkbox.value);

        if (!checkbox.checked) {
            delete selectedComponents[selectedCategory];
            restoreDependentCarousels(selectedCategory);

            // Re-bloquear todos los pasos que dependían de este
            const stepIndex = STEP_ORDER.indexOf(selectedCategory);
            if (stepIndex !== -1) {
                for (let i = stepIndex + 1; i < STEP_ORDER.length; i++) {
                    relockStep(STEP_ORDER[i], i);
                }
            }
            return;
        }

        selectedComponents[selectedCategory] = selectedId;

        // Filtrar categorías dependientes
        getCategoriesthatDependOn(selectedCategory).forEach(targetCategory => {
            const dep = dependencyMap[targetCategory];
            if (!dep) return;
            fetchCompatibleProducts(selectedId, targetCategory, dep.check_via)
                .then(response => {
                    if (response && response.success) {
                        updateCarouselProducts(targetCategory, response.data.compatible_products);
                    }
                })
                .catch(err => console.error('[PCGamer] Error AJAX compatibilidad:', err));
        });

        // Desbloquear el siguiente paso si corresponde
        const stepIndex = STEP_ORDER.indexOf(selectedCategory);
        if (stepIndex !== -1 && stepIndex < STEP_ORDER.length - 1) {
            unlockStep(STEP_ORDER[stepIndex + 1]);
        }

        // Validar build completa
        validateCompleteBuild();
    }

    // ── HELPERS DE COMPATIBILIDAD ────────────────────────────────────────────

    function getCategoriesthatDependOn(sourceCategory) {
        return Object.entries(dependencyMap)
            .filter(([, dep]) => dep.dependencies.includes(sourceCategory))
            .map(([cat]) => cat);
    }

    function fetchCompatibleProducts(componentId, targetCategory, compatibilityType) {
        // Enviar los IDs que realmente están en el carrusel de esta categoría,
        // para que el backend filtre sobre esos productos y no sobre toda la categoría.
        const wrapper = document.querySelector(`.pcgamer-carousel-wrapper[data-category="${targetCategory}"]`);
        const carouselIds = wrapper
            ? Array.from(wrapper.querySelectorAll('input[type="checkbox"]')).map(cb => parseInt(cb.value, 10))
            : [];

        const data = new FormData();
        data.append('action',             'pcgamer_get_compatible_products');
        data.append('component_id',       componentId);
        data.append('target_category',    targetCategory);
        data.append('compatibility_type', compatibilityType || '');
        data.append('nonce',              ajaxNonce);
        data.append('carousel_ids',       JSON.stringify(carouselIds));

        return fetch(ajaxUrl, { method: 'POST', body: data }).then(r => r.json());
    }

    /**
     * Muestra/oculta tarjetas en un carrusel según la lista de IDs compatibles.
     *
     * Comportamiento de degradación elegante:
     * - Si compatibleIds tiene elementos → filtra normalmente (oculta incompatibles).
     * - Si compatibleIds está vacío → los datos de compatibilidad son incompletos;
     *   se muestran TODOS los productos con un aviso amarillo para no bloquear al usuario.
     */
    function updateCarouselProducts(categorySlug, compatibleIds) {
        const wrapper = document.querySelector(`.pcgamer-carousel-wrapper[data-category="${categorySlug}"]`);
        if (!wrapper) return;

        const hasCompatibilityData = compatibleIds && compatibleIds.length > 0;

        // Sin datos de compatibilidad → mostrar todo con aviso y salir
        if (!hasCompatibilityData) {
            wrapper.querySelectorAll('.upgrade-item').forEach(item => {
                item.style.display = '';
                item.style.opacity = '1';
                const cb = item.querySelector('input[type="checkbox"]');
                if (cb) cb.disabled = false;
                item.classList.remove('pcgamer-incompatible');
            });
            showCompatibilityWarning(
                wrapper,
                '⚠ Datos de compatibilidad incompletos — se muestran todas las opciones. Verifica antes de agregar al carrito.',
                'warn'
            );
            return;
        }

        // Con datos → filtrar normalmente
        let visible = 0;
        wrapper.querySelectorAll('.upgrade-item').forEach(item => {
            const cb       = item.querySelector('input[type="checkbox"]');
            if (!cb) return;
            const isCompat = compatibleIds.includes(parseInt(cb.value));

            if (isCompat) {
                item.style.display = '';
                item.style.opacity = '1';
                cb.disabled        = false;
                item.classList.remove('pcgamer-incompatible');
                visible++;
            } else {
                item.style.display = 'none';
                cb.disabled        = true;
                if (cb.checked) {
                    cb.checked = false;
                    cb.dispatchEvent(new Event('change', { bubbles: true }));
                }
                item.classList.add('pcgamer-incompatible');
            }
        });

        if (visible === 0) {
            // Hay datos pero ningún producto del carrusel coincide → datos desincronizados.
            // Mostrar todos con aviso en lugar de bloquear completamente.
            wrapper.querySelectorAll('.upgrade-item').forEach(item => {
                item.style.display = '';
                item.style.opacity = '0.7';
                const cb = item.querySelector('input[type="checkbox"]');
                if (cb) cb.disabled = false;
                item.classList.remove('pcgamer-incompatible');
            });
            showCompatibilityWarning(
                wrapper,
                '⚠ Los productos configurados para este paso no coinciden con los resultados de compatibilidad. Verifica la configuración.',
                'error'
            );
        } else {
            removeCompatibilityWarning(wrapper);
        }
    }

    /** Restaura todos los items visibles en las categorías que dependen de la categoría deseleccionada */
    function restoreDependentCarousels(sourceCategory) {
        getCategoriesthatDependOn(sourceCategory).forEach(targetCategory => {
            const wrapper = document.querySelector(`.pcgamer-carousel-wrapper[data-category="${targetCategory}"]`);
            if (!wrapper) return;
            wrapper.querySelectorAll('.upgrade-item').forEach(item => {
                item.style.display = '';
                item.style.opacity = '1';
                const cb = item.querySelector('input[type="checkbox"]');
                if (cb) cb.disabled = false;
                item.classList.remove('pcgamer-incompatible');
            });
            removeCompatibilityWarning(wrapper);
        });
    }

    function showCompatibilityWarning(wrapper, message, type) {
        let el = wrapper.parentElement.querySelector('.compatibility-warning');
        if (!el) {
            el = document.createElement('div');
            el.className = 'compatibility-warning';
            wrapper.parentElement.insertBefore(el, wrapper);
        }
        const styles = {
            warn:  'padding:8px 12px;background:#fff3cd;color:#856404;font-size:13px;border-radius:4px;margin:4px 0;border-left:3px solid #ffc107;',
            error: 'padding:8px 12px;background:#f8d7da;color:#721c24;font-size:13px;border-radius:4px;margin:4px 0;border-left:3px solid #dc3545;',
        };
        el.style.cssText = styles[type] || styles.warn;
        el.textContent   = message;
        el.style.display = 'block';
    }

    function removeCompatibilityWarning(wrapper) {
        const el = wrapper.parentElement && wrapper.parentElement.querySelector('.compatibility-warning');
        if (el) el.style.display = 'none';
    }

    // ── VALIDACIÓN COMPLETA ──────────────────────────────────────────────────

    function validateCompleteBuild() {
        if (Object.keys(selectedComponents).length < 2) return;

        const data = new FormData();
        data.append('action', 'pcgamer_validate_build');
        data.append('nonce',  ajaxNonce);
        // Enviar como JSON para preservar las claves de categoría en PHP
        data.append('selected_items', JSON.stringify(selectedComponents));

        fetch(ajaxUrl, { method: 'POST', body: data })
            .then(r => r.json())
            .then(response => {
                if (response && response.success) {
                    displayValidationResults(response.data);
                }
            })
            .catch(err => console.error('[PCGamer] Error validando build:', err));
    }

    function displayValidationResults(validation) {
        const container = document.querySelector('.pcgamer-validation-messages');
        if (!container) return;

        container.innerHTML = '';

        if (validation.valid) {
            container.innerHTML = '<div class="pcgamer-validation-ok" style="padding:8px 12px;background:#d4edda;color:#155724;border-radius:4px;font-size:13px;">✓ Configuración compatible</div>';
            return;
        }

        if (validation.errors && validation.errors.length) {
            container.innerHTML = validation.errors
                .map(err => `<div class="pcgamer-validation-error" style="padding:8px 12px;background:#f8d7da;color:#721c24;border-radius:4px;font-size:13px;margin-bottom:4px;">⚠ ${err}</div>`)
                .join('');
        }

        if (validation.warnings && validation.warnings.length) {
            container.innerHTML += validation.warnings
                .map(w => `<div class="pcgamer-validation-warn" style="padding:8px 12px;background:#fff3cd;color:#856404;border-radius:4px;font-size:13px;margin-bottom:4px;">ℹ ${w}</div>`)
                .join('');
        }
    }

    // ── API PÚBLICA ──────────────────────────────────────────────────────────

    window.PCGamerGetSelectedComponents = () => ({ ...selectedComponents });

    window.PCGamerResetSelections = () => {
        selectedComponents = {};
        document.querySelectorAll('input[name="pcgamer_extra[]"]').forEach(cb => {
            cb.checked = false;
        });
    };

})();
