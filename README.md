# NexTech

Proyecto de Taller de Programación Aplicada

---

## Descripción

NexTech es una tienda de e-commerce para venta de equipos y componentes tecnológicos (PCs Gamer, periféricos y accesorios), construida sobre WordPress + WooCommerce con tres plugins desarrollados a medida:

*Nextech Product Filter* expone un endpoint REST propio (/nextech/v1) y reemplaza los plugins de filtrado nativos (Husky/YITH) por una solución en Vanilla JS con paginación y ordenamiento propios. Al activarse crea índices compuestos en wp_postmeta y wc_product_meta_lookup para optimizar consultas de stock y precio en catálogos de más de 1 000 productos. Los resultados se almacenan en transients de WordPress y se invalidan automáticamente cuando cambia el stock o la taxonomía.

*PC Gamer Configurator* agrega un flujo de configuración de PCs por componentes directamente en la página de producto. Guía al usuario paso a paso (CPU → Placa → RAM → Almacenamiento → Fuente → Gabinete → Refrigeración → Accesorios) mediante carruseles con selección por checkbox. Los componentes seleccionados se agregan al carrito como productos simples independientes junto al PC base, con precios personalizables por categoría y sincronizables desde WooCommerce vía AJAX. Incluye un motor de compatibilidad que valida socket, tipo de RAM, form factor, wattaje y soporte de radiadores entre componentes, almacenando las especificaciones en post meta (_pcgamer_socket, _pcgamer_ram_type, etc.).

*Nextech Cart Cotización* genera cotizaciones en PDF desde el carrito sin dependencias externas, con numeración automática y diseño con marca RS Tech.

---

## Tecnologías utilizadas

| Capa | Tecnología |
|------|-----------|
| CMS | WordPress 6.x |
| E-commerce | WooCommerce 7+ |
| Tema | Flatsome |
| Lenguaje backend | PHP 8.0+ |
| Frontend | Vanilla JS, CSS |
| Base de datos | MySQL |
| API | WordPress REST API |
| Entorno local | Local by Flywheel |
| Pasarelas de pago | Mercado Pago, Transbank Webpay Plus |
| SEO | Rank Math |
| CDN / Seguridad | Cloudflare |


## Estructura del equipo

| Nombre | Rol |
|---|---|
| Alex Caica Zamora | Scrum Master / Development Team |
| Renato Ortega Ramos | Development Team |
| Ángel Prado Correa | Development Team |
| Manuel Reyes Bustos | Product Owner |

## Tablero Kanban

[Ver tablero Kanban](https://caica-ortega-prado.atlassian.net/jira/software/projects/SCRUM/boards/1)

