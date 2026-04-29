<?php
defined( 'ABSPATH' ) || exit;

class Nextech_Filter_Widget extends WP_Widget {

    public function __construct() {
        parent::__construct(
            'nextech_filter_widget',
            'Nextech — Filtro de Productos',
            [ 'description' => 'Filtro de productos personalizado (REST + Vanilla JS). Úsalo en reemplazo de Husky.' ]
        );
    }

    public function widget( $args, $instance ): void {
        if ( ! is_shop() && ! is_product_category() && ! is_product_tag() ) return;

        echo $args['before_widget'];
        ?>
        <div id="nextech-filter" class="nextech-filter-sidebar" data-loading="false">

            <!-- ── Filtros activos (chips) ────────────────────────────────── -->
            <div id="nxf-active-filters" class="nxf-active-filters" hidden></div>

            <!-- ── Precio ─────────────────────────────────────────────────── -->
            <div class="nxf-accordion" id="nxf-precio-section" data-open="true">
                <button class="nxf-accordion-header" type="button" aria-expanded="true">
                    <span>Precio (CLP)</span>
                    <svg class="nxf-chevron" viewBox="0 0 10 6" aria-hidden="true">
                        <path d="M0 0l5 6 5-6z"/>
                    </svg>
                </button>
                <div class="nxf-accordion-body">
                    <div class="nxf-price-row">
                        <input type="number" id="nxf-min-precio" data-filter="min_precio"
                               class="nxf-price-input" placeholder="Mín" min="0" step="1000" />
                        <span class="nxf-price-sep">—</span>
                        <input type="number" id="nxf-max-precio" data-filter="max_precio"
                               class="nxf-price-input" placeholder="Máx" min="0" step="1000" />
                    </div>
                </div>
            </div>

            <!-- ── Marcas (oculto hasta que JS confirme que hay marcas) ──────── -->
            <div class="nxf-accordion" id="nxf-marcas-section" data-open="true" hidden>
                <button class="nxf-accordion-header" type="button" aria-expanded="true">
                    <span>Marca</span>
                    <svg class="nxf-chevron" viewBox="0 0 10 6" aria-hidden="true">
                        <path d="M0 0l5 6 5-6z"/>
                    </svg>
                </button>
                <div class="nxf-accordion-body">
                    <input type="search" class="nxf-search-input" id="nxf-marcas-search"
                           placeholder="Buscar marca…" aria-label="Buscar marca" autocomplete="off" />
                    <div class="nxf-term-list" id="nxf-marcas-list"></div>
                </div>
            </div>

            <!-- ── Categorías (oculto hasta que JS confirme que hay subcats) ─ -->
            <div class="nxf-accordion" id="nxf-categorias-section" data-open="true" hidden>
                <button class="nxf-accordion-header" type="button" aria-expanded="true">
                    <span>Categoría</span>
                    <svg class="nxf-chevron" viewBox="0 0 10 6" aria-hidden="true">
                        <path d="M0 0l5 6 5-6z"/>
                    </svg>
                </button>
                <div class="nxf-accordion-body">
                    <div class="nxf-term-list" id="nxf-categorias-list"></div>
                </div>
            </div>

            <!-- ── Limpiar filtros ────────────────────────────────────────── -->
            <button id="nxf-reset" class="nxf-reset-btn" type="button" hidden>
                Limpiar filtros
            </button>

        </div><!-- #nextech-filter -->
        <?php
        echo $args['after_widget'];
    }

    public function form( $instance ): void {
        echo '<p>No requiere configuración adicional.</p>';
    }
}
