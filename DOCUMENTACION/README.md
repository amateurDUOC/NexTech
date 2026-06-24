# NexTech

Proyecto de Taller de Programación Aplicada con desarrollo multisistemas en plataforma WordPress y módulo construído con React Vite + Spring Boot

---

## Descripción

**RS Tech Ltda** es una tienda de e-commerce para venta de equipos y componentes tecnológicos (PCs Gamer, periféricos y accesorios), construida sobre WordPress + WooCommerce con tres plugins desarrollados a medida:

*PC Gamer Configurator* agrega un flujo de configuración de PCs por componentes directamente en la página de producto. Guía al usuario paso a paso (CPU → Placa → RAM → Almacenamiento → Fuente → Gabinete → Refrigeración → Accesorios) mediante carruseles con selección por checkbox. Los componentes seleccionados se agregan al carrito como productos simples independientes junto al PC base, con precios personalizables por categoría y sincronizables desde WooCommerce vía AJAX. Incluye un motor de compatibilidad que valida socket, tipo de RAM, form factor, wattaje y soporte de radiadores entre componentes, almacenando las especificaciones en post meta (_pcgamer_socket, _pcgamer_ram_type, etc.).

*NexTech Product Filter* expone un endpoint REST propio (/nextech/v1) y reemplaza los plugins de filtrado nativos (Husky/YITH) por una solución en Vanilla JS con paginación y ordenamiento propios. Al activarse crea índices compuestos en wp_postmeta y wc_product_meta_lookup para optimizar consultas de stock y precio en catálogos de más de 1 000 productos. Los resultados se almacenan en transients de WordPress y se invalidan automáticamente cuando cambia el stock o la taxonomía.

*NexTech Cart Cotización* genera cotizaciones en PDF desde el carrito sin dependencias externas, con numeración automática y diseño con marca RS Tech.

*NexTech Seguimiento y Control de Facturación* Módulo completo diseñado para la gestión y el control de la facturación de manera interna de **RS Tech Ltda**. Expone endpoints para autenticación, gestión de facturas, sincronización con API Rest Lioren (DTEs) y carga de documentos de respaldo.

---

## Tecnologías Utilizadas WordPress

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

## Tecnologías Utilizadas Facturación

| Tecnología | Uso |
|---|---|
| Java 17 | Lenguaje principal |
| Spring Boot 3.3.5 | Framework web y configuración |
| Spring Security + JWT | Autenticación con tokens JWT (jjwt 0.12.6) |
| Spring Data JPA | ORM y acceso a base de datos |
| PostgreSQL | Base de datos relacional (Neon cloud) |
| Spring WebFlux | WebClient para llamadas a Lioren API |
| Lombok | Reducción de boilerplate |
| Maven | Gestión de dependencias y build |

---

## Estructura del equipo

| Nombre | Rol |
|---|---|
| Alex Caica Zamora | Scrum Master / Development Team |
| Renato Ortega Ramos | Development Team |
| Ángel Prado Correa | Development Team |
| Manuel Reyes Bustos | Product Owner |

## Tablero Kanban

https://caica-ortega-prado.atlassian.net/jira/software/projects/SCRUM/list?jql=project%20%3D%20SCRUM%20ORDER%20BY%20created%20ASC

