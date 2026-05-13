# ⚡ QUICK START - Ejecutar en 5 Minutos

## 🚀 La Forma Más Rápida de Hacerlo

### PASO 1: Backup (1 minuto)
```
1. Abre phpMyAdmin
2. Selecciona tu base de datos
3. Click en "Exportar"
4. Descarga el backup (click en Ir)
```

### PASO 2: Ejecutar Script (2 minutos)
```
1. En phpMyAdmin, ve a la pestaña SQL
2. Abre el archivo setup-atributos.sql
3. Copia TODO el contenido
4. Pégalo en la ventana SQL
5. Click en [Ir] o [Execute]
6. Espera a que termine
```

### PASO 3: Verificar (1 minuto)
```
1. Ve a WordPress Admin
2. Productos → Atributos
3. Deberías ver 21 atributos
4. Listo ✅
```

### PASO 4: Prueba (1 minuto)
```
1. Edita un producto
2. Desplázate hasta "Atributos"
3. Deberías ver los atributos asignados
4. Ve a la tienda (frontend)
5. Prueba los filtros
```

---

## ⏰ Timeline Esperado

| Acción | Tiempo |
|--------|--------|
| Backup | 1-2 min |
| Ejecución SQL | 30-120 seg |
| Verificación | 1-2 min |
| Pruebas | 1-2 min |
| **TOTAL** | **5-7 min** |

---

## 🎯 Checklist Rápido

- [ ] Hice backup
- [ ] Ejecuté setup-atributos.sql
- [ ] Ver 21 atributos en WordPress
- [ ] Los productos tienen los atributos
- [ ] Los filtros funcionan

---

## ❌ Si Algo Falla

### Error "Duplicate entry"
→ Normal, ignóralo. El script usa `INSERT IGNORE`

### Error "Access denied"
→ Usa usuario admin en phpMyAdmin

### Atributos creados pero sin productos
→ Los títulos no coinciden. Edita títulos de productos

### Los filtros no aparecen en la tienda
→ Limpia caché (Ctrl+Shift+Del en navegador)

---

## 📚 Para Más Ayuda

- Lee `INSTRUCCIONES_SETUP.md` para instrucciones completas
- Mira `GUIA_VISUAL_EJECUCION.md` para ver screenshots
- Revisa `RESUMEN_CAMBIOS.md` para entender qué cambió

---

## 🔑 Puntos Clave

1. ✅ **Ejecuta TODO de una vez** (más fácil)
2. ✅ **El error "Duplicate" es normal** (el script lo maneja)
3. ✅ **Espera a que termine** (no interrumpas)
4. ✅ **Verifica los atributos en WordPress** (no en BD directamente)
5. ✅ **Limpia caché después** (Ctrl+Shift+Delete)

---

**¿Necesitas ayuda? Lee las otras guías. 📖**
