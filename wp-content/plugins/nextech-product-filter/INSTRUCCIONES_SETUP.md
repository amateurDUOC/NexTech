# 📋 NexTech Product Filter - Guía de Instalación de Atributos

## ⚠️ IMPORTANTE - HACER BACKUP PRIMERO

Antes de ejecutar cualquier script SQL, **SIEMPRE** realiza una copia de seguridad completa de tu base de datos:

```bash
# Si tienes acceso a phpMyAdmin:
1. Accede a phpMyAdmin desde tu dashboard local
2. Selecciona la base de datos "nextech_db" (o la que uses)
3. Click en "Exportar"
4. Descarga el backup completo
```

---

## 🚀 Cómo Ejecutar el Script

### Opción 1: Ejecutar TODO de una vez (Recomendado para principiantes)

1. Abre **phpMyAdmin** en tu navegador
2. Selecciona la base de datos de tu WordPress
3. Haz clic en la pestaña **"SQL"**
4. Abre el archivo `setup-atributos.sql` desde tu computadora
5. Copia TODO el contenido y pégalo en la ventana SQL
6. Haz clic en **"Continuar"** o **"Execute"**
7. Espera a que se complete (puede tomar algunos minutos)

### Opción 2: Ejecutar PASO por PASO (Para mayor control)

Si prefieres ejecutar paso a paso para ver qué sucede:

1. Abre el archivo `setup-atributos.sql` con un editor de texto (VS Code, Notepad++, etc)
2. En phpMyAdmin, copia y pega **SOLO** el contenido del PASO que deseas ejecutar
3. Ejemplo - Para ejecutar PASO 1:
   - Copia desde `/* PASO 1: Crear términos del PROCESADOR */`
   - Hasta la siguiente línea `/* PASO 2: Crear términos del ALMACENAMIENTO */`
4. Pega en phpMyAdmin y ejecuta
5. Verifica que no haya errores
6. Repite para cada paso

---

## 📦 Estructura del Script

El archivo está organizado en **11 PASOS**:

| PASO | DESCRIPCIÓN | Qué Hace |
|------|-------------|----------|
| **0** | Información | Solo lectura, no ejecuta nada |
| **1** | Procesador | AMD Ryzen, Intel Core i5/i7/i9 |
| **2** | Almacenamiento | 240GB, 256GB, 480GB, 512GB, 1TB, 2TB |
| **3** | Placa Madre | A520M, B550M, B650, B760M, H610M, X870 |
| **4** | Chipset | A320M, B450M, B550, B650, B760, X570, Z790, etc |
| **5** | RAM | 8GB, 16GB, 32GB + DDR4, DDR5 |
| **6** | Refrigeración | Líquida, Aire, Ventiladores, Pasta térmica |
| **7** | Fuentes de Poder | 450W-1650W + Certificación 80 Plus + Modularidad |
| **8** | Tarjeta de Video | NVIDIA, AMD + RTX/GTX/RX + VRAM |
| **9** | Gabinete | Factor de forma (ATX/MATX/EATX), Panel, Color |
| **10** | Limpiar Duplicados | Identifica y muestra términos duplicados |
| **11** | Caché & Verificación | Limpia caché y verifica integridad |

---

## ✅ Cómo Verificar que Funcionó

Después de ejecutar el script:

1. **Ve a WordPress Admin**
   - Productos → Atributos
   - Debería mostrar 21 atributos creados

2. **Edita un Producto**
   - Products → Todos los productos
   - Abre cualquier PC Gamer
   - Desplázate hasta "Atributos"
   - Debería mostrar los atributos asignados (Procesador, RAM, Almacenamiento, etc)

3. **Comprueba el Frontend**
   - Ve a la página de la tienda en el frontend
   - Debería ver los filtros de atributos funcionando

4. **Ejecuta las Verificaciones del PASO 11**
   - En phpMyAdmin, copia las queries de verificación al final del script
   - Te mostrará el resumen de atributos creados

---

## 🐛 Solución de Problemas

### Error: "Duplicate entry"
**Causas**: Intentaste crear un atributo que ya existe
**Solución**: Usa `INSERT IGNORE` (el script ya lo hace) o elimina los atributos duplicados

### Error: "Access denied"
**Causas**: Tu usuario de phpMyAdmin no tiene permisos
**Solución**: Usa el usuario root o uno con permisos de escritura

### Los atributos se crearon pero no aparecen en los productos
**Causas**: Los slugs en los títulos no coinciden
**Solución**: Revisa que los títulos de tus productos contengan las palabras clave (ej: "Ryzen 5", "16GB", "DDR4")

### Error: "Syntax error"
**Causas**: Pegaste solo parte del código incorrectamente
**Solución**: Copia y pega el código completo del paso, incluyendo todos los comentarios

---

## 📝 Atributos Creados

### Lista Completa:

```
✓ Almacenamiento (almacenamiento)
✓ Capacidad RAM (capacidad-ram)
✓ Certificación (certificacion)
✓ Chipset (chipset)
✓ Color gabinete (color-gabinete)
✓ Factor de forma (factor-de-forma)
✓ Generación RAM (generacion-ram)
✓ Hz (hz)
✓ Marca GPU (marca-gpu)
✓ Modularidad (modularidad)
✓ Panel (panel)
✓ Panel Monitor (panel-monitor)
✓ Placa Madre (placa-madre)
✓ Potencia (potencia)
✓ Procesador (procesador)
✓ Resolución (resolucion)
✓ Serie GPU (serie-gpu)
✓ Tamaño (tamano)
✓ Tamaño Monitor (tamano-monitor)
✓ Tipo Refrigeración (tipo-refrigeracion)
✓ VRAM (vram)
```

---

## 🔄 Pasos POSTERIORES a la Ejecución

1. **Vacía la caché**
   - WordPress: Admin → Herramientas → Limpiar caché
   - WooCommerce: Productos → Atributos → (Herramientas de regeneración)

2. **Prueba el filtro**
   - Ve a la página de tienda en el frontend
   - Prueba seleccionar filtros (Procesador, RAM, etc)
   - Verifica que los productos se filtren correctamente

3. **Comprueba el rendimiento**
   - Abre un producto
   - Verifica que los atributos sean visibles en la variación (si es variable)

---

## 📞 Soporte

Si encuentras errores:

1. Copia el **mensaje de error exacto**
2. Verifica la **versión de WordPress** y **WooCommerce**
3. Consulta los logs en: `/wp-content/debug.log`
4. Revisa que los **slugs en los títulos** coincidan con los del script

---

## 🗂️ Archivos del Plugin

```
nextech-product-filter/
├── setup-atributos.sql              ← ARCHIVO PRINCIPAL (ejecutar este)
├── setup-atributos-BACKUP.sql       ← Backup del original
├── setup-atributos-REORGANIZADO.sql ← Versión reorganizada
├── INSTRUCCIONES_SETUP.md           ← Este archivo
└── ...otros archivos del plugin
```

---

**¡Éxito! 🎉**

Si todo funcionó correctamente, los atributos deberían estar asignados a tus productos y funcionando en el filtro.
