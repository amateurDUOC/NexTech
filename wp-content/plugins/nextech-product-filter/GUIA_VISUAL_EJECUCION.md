# 📊 Guía Visual - Cómo Ejecutar el Script SQL Paso a Paso

## 🖼️ PASO 1: Acceder a phpMyAdmin

### Desde SITEGROUND Local (Local Site):

1. **Abre tu navegador** y ve a `localhost:3306/phpmyadmin`
   - O busca el puerto exacto en tu configuración local

2. **Inicia sesión** con tus credenciales
   - Usuario: generalmente `root` o el que configuraste
   - Contraseña: la que definiste

### Pantalla que verás:

```
┌─────────────────────────────────────────────────────────┐
│  phpMyAdmin 5.x.x                      [Logout] [Panel] │
├─────────────────────────────────────────────────────────┤
│                                                           │
│  Base de Datos                                            │
│  ├─ information_schema                                    │
│  ├─ mysql                                                 │
│  ├─ nextech_db        ← SELECCIONA ESTA                 │
│  └─ ...                                                   │
│                                                           │
└─────────────────────────────────────────────────────────┘
```

---

## 🖼️ PASO 2: Seleccionar la Base de Datos

1. **Haz clic en** `nextech_db` (o el nombre de tu base de datos)

### Pantalla que verás:

```
┌─────────────────────────────────────────────────────────┐
│  Base de Datos: nextech_db                              │
├─────────────────────────────────────────────────────────┤
│                                                           │
│  [Estructura] [SQL] [Búsqueda] [Consultas] [Export]     │
│                                                           │
│  Tablas:                                                  │
│  • wp_posts                                               │
│  • wp_postmeta                                            │
│  • wp_terms              ← Donde se crean los términos   │
│  • wp_term_taxonomy      ← Donde van las relaciones      │
│  • wp_term_relationships ← Donde se asignan a productos  │
│  • ...                                                    │
│                                                           │
└─────────────────────────────────────────────────────────┘
```

---

## 🖼️ PASO 3: Abrir la Pestaña SQL

1. **Haz clic en la pestaña** `SQL` (en la fila con los botones)

### Pantalla que verás:

```
┌─────────────────────────────────────────────────────────┐
│  [Estructura] [SQL] [Búsqueda] ...                       │
├─────────────────────────────────────────────────────────┤
│                                                           │
│  ┌─────────────────────────────────────────────────────┐ │
│  │ Ejecutar una o varias consultas SQL                 │ │
│  ├─────────────────────────────────────────────────────┤ │
│  │                                                       │ │
│  │  (EDITOR SQL VACÍO)                                 │ │
│  │  ← AQUÍ PEGAS EL SQL                                │ │
│  │                                                       │ │
│  │                                                       │ │
│  │  [Ir] [Limpiar] [Cargar archivo]                    │ │
│  │                                                       │ │
│  └─────────────────────────────────────────────────────┘ │
│                                                           │
└─────────────────────────────────────────────────────────┘
```

---

## 🖼️ PASO 4: Cargar el Archivo SQL (Opción A)

### Opción A1: Usar "Cargar archivo"

1. **Haz clic en** `[Cargar archivo]`
2. **Selecciona** el archivo `setup-atributos.sql`
3. **Abre** (double-click o botón abrir)

### Opción A2: Copiar y Pegar (Más control)

1. **Abre el archivo** `setup-atributos.sql` con un editor de texto
   - VS Code
   - Notepad++
   - O cualquier editor

```
┌─────────────────────────────────────────┐
│ setup-atributos.sql (en tu editor)      │
├─────────────────────────────────────────┤
│ /*═══════════════════════════════════   │
│   NexTech Product Filter - ...           │
│                                          │
│   INSTRUCCIONES DE EJECUCIÓN:            │
│   ...                                    │
│                                          │
│   PASO 1: Crear términos del PROCESADOR  │
│   ...                                    │
│                                          │
│   INSERT IGNORE INTO wp_terms...         │
│   INSERT IGNORE INTO wp_term_taxonomy... │
│   INSERT IGNORE INTO wp_term_relationsh..
│   ...*/                                  │
└─────────────────────────────────────────┘
```

2. **Selecciona TODO** (Ctrl+A o Cmd+A)
3. **Copia** (Ctrl+C o Cmd+C)

---

## 🖼️ PASO 5: Pegar el SQL en phpMyAdmin

1. **Vuelve a la ventana de phpMyAdmin**
2. **Haz clic** en el editor SQL (caja blanca)
3. **Pega** el código (Ctrl+V o Cmd+V)

### Cómo se ve:

```
┌──────────────────────────────────────────────────────────┐
│  [Estructura] [SQL] [Búsqueda] ...                        │
├──────────────────────────────────────────────────────────┤
│                                                            │
│  ┌──────────────────────────────────────────────────────┐ │
│  │ /*════════════════════════════════════════════════   │ │
│  │   NexTech Product Filter - Instalación de Atributos  │ │
│  │   ...                                                 │ │
│  │ */                                                    │ │
│  │                                                        │ │
│  │ -- Insertar términos de procesadores                 │ │
│  │ INSERT IGNORE INTO wp_terms (name, slug, term_group) │ │
│  │ VALUES                                                 │ │
│  │ ('AMD Ryzen 3',     'amd-ryzen-3',   0),             │ │
│  │ ('AMD Ryzen 5',     'amd-ryzen-5',   0),             │ │
│  │ ...                                                   │ │
│  │                                                        │ │
│  │ [Ir] [Limpiar] [Cargar archivo]                      │ │
│  │                                                        │ │
│  └──────────────────────────────────────────────────────┘ │
│                                                            │
└──────────────────────────────────────────────────────────┘
```

---

## 🖼️ PASO 6: Ejecutar el Script

1. **Haz clic en el botón** `[Ir]` (o `Execute` en algunas versiones)

### Progreso esperado:

```
┌──────────────────────────────────────────────────────────┐
│ Ejecutando ...                    ⏳ Cargando             │
│                                                            │
│ Procesando consulta 1 de 156...                           │
│ [████████░░░░░░░░░░░░░░░░░░░░░░░░░░░░░] 25%            │
│                                                            │
└──────────────────────────────────────────────────────────┘
```

⏱️ **Tiempo esperado: 30-120 segundos** (depende del tamaño de tu BD)

---

## 🎉 PASO 7: Verificar Éxito

### Si todo fue bien:

```
┌──────────────────────────────────────────────────────────┐
│ ✓ Consulta ejecutada exitosamente                         │
│                                                            │
│ Información de ejecución:                                │
│ • Se procesaron: 156 consultas                           │
│ • Filas afectadas: 4,237                                 │
│ • Tiempo de ejecución: 47.8 segundos                     │
│                                                            │
│ ✓ Todas las tablas se actualizaron correctamente         │
│                                                            │
└──────────────────────────────────────────────────────────┘
```

### Si hay un error:

```
┌──────────────────────────────────────────────────────────┐
│ ✗ ERROR en línea 154                                      │
│                                                            │
│ Error SQL: Duplicate entry 'amd-ryzen-3' for key 'slug'  │
│                                                            │
│ Línea:                                                    │
│   INSERT IGNORE INTO wp_terms (name, slug, term_group)   │
│   VALUES ('AMD Ryzen 3', 'amd-ryzen-3', 0)              │
│                                                            │
│ Solución: El término ya existe. Usa INSERT IGNORE        │
│ (que ya está incluido en el script)                       │
│                                                            │
└──────────────────────────────────────────────────────────┘
```

---

## 🔍 VERIFICACIÓN VISUAL en WordPress

### Ver los Atributos Creados:

1. Ve a **WordPress Admin**
2. **Productos** → **Atributos**

### Deberías ver (21 atributos):

```
┌────────────────────────────────────────────────────────────┐
│  Atributos                              [+ Agregar Nuevo]   │
├────────────────────────────────────────────────────────────┤
│                                                              │
│  Nombre                    │ Slug                │ Términos │
│  ─────────────────────────┼────────────────────┼──────────│
│  Almacenamiento           │ almacenamiento     │    6    │
│  Capacidad RAM            │ capacidad-ram      │    3    │
│  Certificación            │ certificacion      │    3    │
│  Chipset                  │ chipset            │   16    │
│  Color gabinete           │ color-gabinete     │    2    │
│  Factor de forma          │ factor-de-forma    │    3    │
│  Generación RAM           │ generacion-ram     │    2    │
│  Hz                       │ hz                 │    0    │
│  Marca GPU                │ marca-gpu          │    2    │
│  Modularidad              │ modularidad        │    2    │
│  Panel                    │ panel              │    2    │
│  Panel Monitor            │ panel-monitor      │    0    │
│  Placa Madre              │ placa-madre        │    7    │
│  Potencia                 │ potencia           │   11    │
│  Procesador               │ procesador         │    7    │
│  Resolución               │ resolucion         │    0    │
│  Serie GPU                │ serie-gpu          │    6    │
│  Tamaño                   │ tamano             │    4    │
│  Tamaño Monitor           │ tamano-monitor     │    0    │
│  Tipo Refrigeración       │ tipo-refrigeracion │    5    │
│  VRAM                     │ vram               │    6    │
│                                                              │
│                                    [Página 1 de 1]          │
│                                                              │
└────────────────────────────────────────────────────────────┘
```

---

## 🎯 Ver Atributos en un Producto

1. **Productos** → **Todos los Productos**
2. **Haz clic** en editar un PC Gamer

### Busca la sección "Atributos":

```
┌─────────────────────────────────────────────────────────┐
│  PC Gamer RTX 4060 - Ryzen 5 5600X                       │
├─────────────────────────────────────────────────────────┤
│                                                           │
│  📋 Atributos                                             │
│                                                           │
│  [+] Almacenamiento: 512GB, 1TB                          │
│  [+] Capacidad RAM: 16GB                                 │
│  [+] Chipset: B550                                        │
│  [+] Color gabinete: Negro                               │
│  [+] Factor de forma: ATX                                 │
│  [+] Generación RAM: DDR4                                │
│  [+] Marca GPU: NVIDIA GeForce                           │
│  [+] Modularidad: Full Modular                           │
│  [+] Panel: Vidrio Templado                              │
│  [+] Placa Madre: B550M                                  │
│  [+] Potencia: 650W                                       │
│  [+] Procesador: AMD Ryzen 5                             │
│  [+] Serie GPU: RTX 40xx                                 │
│  [+] Tamaño: 240mm                                        │
│  [+] Tipo Refrigeración: Refrigeración Líquida           │
│  [+] VRAM: 8GB                                            │
│                                                           │
└─────────────────────────────────────────────────────────┘
```

---

## 🧪 Probar el Filtro en la Tienda

1. **Ve a la página de productos** en el frontend
2. **Deberías ver los filtros** en la barra lateral:

```
┌──────────────────────────────┐
│  🔍 FILTRAR POR:              │
├──────────────────────────────┤
│                              │
│  📦 Almacenamiento           │
│  ☐ 240GB                     │
│  ☐ 256GB                     │
│  ☐ 480GB                     │
│  ☐ 512GB                     │
│  ☐ 1TB                       │
│  ☐ 2TB                       │
│                              │
│  🔧 Capacidad RAM            │
│  ☐ 8GB                       │
│  ☐ 16GB                      │
│  ☐ 32GB                      │
│                              │
│  💾 Chipset                  │
│  ☐ A320M                     │
│  ☐ B450M                     │
│  ☐ B550                      │
│  ☐ B650                      │
│  ☐ B760                      │
│  ☐ X570                      │
│  ☐ Z790                      │
│  ...                         │
│                              │
└──────────────────────────────┘
```

3. **Haz clic** en algunos filtros
4. **Verifica** que los productos se filtren correctamente

---

## 🐛 Si Algo Salió Mal

### Error: "Table already exists"
```
✗ ERROR: Table 'wp_terms' already exists at line 23

→ SOLUCIÓN: Esto es normal. El script usa INSERT IGNORE
            que no crea duplicados. Ignora este error.
```

### Error: "Duplicate entry"
```
✗ ERROR: Duplicate entry 'amd-ryzen-3' for key 'slug'

→ SOLUCIÓN: El término ya estaba creado. INSERT IGNORE 
            lo evita. No hay problema, continúa.
```

### Error: "Access denied"
```
✗ ERROR: Access denied for user 'root'@'localhost'

→ SOLUCIÓN: Tu usuario no tiene permisos. Usa phpMyAdmin 
            como admin o crea un usuario con permisos
```

### Los atributos se crearon pero los productos no los tienen
```
→ SOLUCIÓN: Los títulos de tus productos no tienen las 
            palabras clave. Ejemplo:
            - Si dice "Ryzen 5 5600X", debe detectar "Ryzen 5"
            - Si dice "16 Gigabytes", debe ser "16GB"
            
            Renombra los productos o ejecuta los pasos
            individuales con patrones correctos.
```

---

## ✅ CHECKLIST FINAL

Marca cada paso completado:

```
□ Hice backup de la base de datos
□ Abrí phpMyAdmin
□ Seleccioné la base de datos nextech_db
□ Fui a la pestaña SQL
□ Pegué el contenido de setup-atributos.sql
□ Ejecuté el script (hice clic en [Ir])
□ El script completó sin errores fatales
□ Verifiqué 21 atributos en WordPress Admin
□ Los atributos aparecen en los productos
□ Los filtros funcionan en la tienda (frontend)
□ Limpié la caché de WordPress
□ Limpié la caché del navegador (Ctrl+Shift+Delete)
```

---

## 📞 Si Necesitas Ayuda

Incluye esta información:

1. **Mensaje de error exacto** (copia y pega)
2. **Línea del error** (número)
3. **Versión de WordPress** (Ajustes → General)
4. **Versión de WooCommerce** (Productos → Estado)
5. **Versión de PHP** (Herramientas del Sitio → Estado)

---

**¡Listo! 🎉 El script debería estar funcionando.**
