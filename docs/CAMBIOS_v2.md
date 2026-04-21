# Cambios del Portal SM — v1 → v2
**Fecha:** Abril 2026
**Fuente:** SM_Portal_Modulos2.pdf vs plan original

---

## Resumen ejecutivo

La nueva versión simplifica drásticamente el portal eliminando dos módulos complejos
(Inventory y la contabilidad avanzada con IA) y delegando esa funcionalidad a
QuickBooks Online. También reestructura los módulos de Assessments, Repositories y
Process Maps con nuevas funcionalidades.

---

## 1. MÓDULOS ELIMINADOS

### ❌ Inventory — ELIMINADO COMPLETAMENTE
- **Antes:** Módulo completo con productos (SKU, stock mínimo), movimientos de
  inventario y asientos contables automáticos.
- **Ahora:** Eliminado. QuickBooks Online gestiona el inventario completo.
- **Impacto en BD:** Eliminar tablas `inventory_items` e `inventory_movements`.
- **Impacto en backend:** Eliminar `InventoryItemController`, `InventoryMovement`
  model, `InventoryPage` y todas las rutas asociadas.

---

## 2. MÓDULOS SIMPLIFICADOS

### ⚠️ Accounting — SIMPLIFICAR SIGNIFICATIVAMENTE

| | Antes (v1) | Ahora (v2) |
|---|---|---|
| Plan de cuentas US-GAAP | ✅ Completo | ❌ Eliminado |
| Asientos de doble partida | ✅ Con aprobación IA | ❌ Eliminado |
| OCR + IA de facturas | ✅ Pipeline completo | ❌ Eliminado |
| Conciliación bancaria | ✅ Con matching automático | ❌ Eliminado |
| Integración POS (Square, Stripe...) | ✅ 5 proveedores | ❌ Eliminado |
| Repositorio de facturas/extractos | ✅ | ✅ Se queda |
| Dashboard con link a QBO | ❌ No existía | ✅ Nuevo |
| Resumen visual de documentos cargados | ❌ No existía | ✅ Nuevo |

**Lo que queda del módulo Accounting:**
- Sección de carga de facturas y extractos bancarios (solo almacenamiento)
- Acceso directo / integración básica a QuickBooks Online
- Resumen visual de documentos cargados

**Impacto en BD:** Eliminar `chart_of_accounts`, `journal_entries`,
`journal_entry_lines`, `bank_transactions`, `pos_connections`.
Simplificar `financial_documents` (solo almacenamiento, sin procesamiento IA).

**Impacto en backend:** Eliminar `OcrService`, `OpenAIService.extractTransactions()`,
`AccountingService`, `BankReconciliationService`, `PosConnectionService`,
jobs `ProcessFinancialDocument`. Eliminar colas `ai-processing`.

---

## 3. MÓDULOS CON CAMBIOS FUNCIONALES

### 🔄 Process Maps — AMPLIADO

| | Antes (v1) | Ahora (v2) |
|---|---|---|
| Niveles de proceso | Proceso → Subproceso | Proceso → Subproceso → **Sub-subproceso** |
| Walkthrough paso a paso | ❌ No existía | ✅ Nuevo |
| Botón "Ver Manual" | ❌ No existía | ✅ Nuevo |
| Tipos de documentos | Sin especificar | MP, FOR, MN, IN, AN, PO, PR, CR |
| Edición por cliente | Sin cambio | Solo SM puede editar — cliente solo ve |

**Impacto en BD:** Agregar tabla `sub_sub_processes` (tercer nivel).
Actualizar `process_documents` con los tipos documentales nuevos.

### 🔄 Repositories — REESTRUCTURADO

El repositorio ahora tiene 3 tabs definidas claramente:

| Tab | Contenido |
|-----|-----------|
| **Company Setup** | Documentos iniciales del cliente: legales, RRHH, certificados, marketing, SOPs |
| **Process Documents** | Todos los manuales y formatos cargados en el mapa de procesos, en árbol por categoría → proceso → subproceso |
| **Records by Process** | Registros llenados y subidos al repositorio, organizados por proceso. Incluye botón "Añadir registro" |

**Antes:** Una sola vista con secciones (setup / process).
**Ahora:** 3 tabs con propósito y estructura distintos.

**Impacto en BD:** Actualizar `repository_documents` para soportar el tipo "record"
y la vinculación a proceso/subproceso para la tab 3.

### 🔄 Assessments — REDISEÑADO

| | Antes (v1) | Ahora (v2) |
|---|---|---|
| Assessment 1 | 63 preguntas, 9 dimensiones (A-I) | 4 etapas: Madurez → Franquicia → Simulador BB → Resultados |
| Assessment 2 | Formulario de aplicación BB | ❌ Ya no existe como Assessment 2 |
| Assessment 3 | No existía | ✅ Evaluación complementaria (en revisión) |
| Simulador BB | No existía | ✅ Integrado en Assessment 1, proyección a 5 años |
| PDF de resultados | ❌ No existía | ✅ Pendiente de implementar |

**Detalle Assessment 1 nuevo:**
1. **Madurez operativa:** 7 dimensiones + legal + involucramiento
2. **Alineación franquicia:** evaluación de fit al programa
3. **Simulador Business Bishop:** proyección financiera a 5 años
4. **Resultados:** diagnóstico personalizado con PDF descargable

**Impacto en BD:** Rediseñar `assessment_contacts` para las 4 etapas.
Eliminar la estructura de Assessment 2 (BB application form).
Crear lógica del Simulador BB.

### 🔄 Tracking — GANTT CONFIRMADO

- Antes: tablero kanban.
- Ahora: incluye **vista Gantt** explícitamente.
- Sin otros cambios de fondo.

### 🔄 Applications — NUEVA FUNCIONALIDAD

- Antes: gestión básica de evaluaciones enviadas.
- Ahora: se agrega integración con API externa para obtener
  información actualizada del sector del cliente automáticamente.
- Acceso: solo superadmin.

---

## 4. MÓDULOS SIN CAMBIOS

| Módulo | Estado |
|--------|--------|
| Home / Dashboard | Sin cambios |
| Calendar | Sin cambios |
| Feed | Sin cambios |
| Contracts | Sin cambios |
| Catalog | Sin cambios |
| Franchises | Sin cambios |

---

## 5. IMPACTO EN TABLAS DE BASE DE DATOS

### Tablas ELIMINADAS (7)
- `inventory_items`
- `inventory_movements`
- `chart_of_accounts`
- `journal_entries`
- `journal_entry_lines`
- `bank_transactions`
- `pos_connections`

### Tablas SIMPLIFICADAS (1)
- `financial_documents` — solo almacenamiento, sin campos de procesamiento IA

### Tablas NUEVAS (1)
- `sub_sub_processes` — tercer nivel en el árbol de procesos

### Tablas MODIFICADAS (2)
- `process_documents` — nuevos tipos: MP, FOR, MN, IN, AN, PO, PR, CR
- `repository_documents` — nuevo tipo "record", vinculación a proceso/subproceso
- `assessment_contacts` — rediseño para 4 etapas del Assessment 1

**Schema total: 28 tablas v1 → ~22 tablas v2**

---

## 6. IMPACTO EN SPRINTS DEL BACKLOG

| Sprint | Impacto |
|--------|---------|
| Sprint 1 | Sin cambios |
| Sprint 2 | Sin cambios |
| Sprint 3 | Assessments requiere rediseño completo |
| Sprint 4 | Process Maps agrega sub-subprocesos y walkthrough |
| Sprint 5 | Sprint liberado: eliminar todo Accounting avanzado e Inventory |
| Sprint 6 | Tracking agrega Gantt (ya estaba previsto) |

**Sprint 5 queda significativamente más liviano** — se puede adelantar trabajo
de otros sprints o reducir el timeline total del proyecto.

---

## 7. SERVICIOS / INTEGRACIONES ELIMINADAS

| Servicio | Antes | Ahora |
|----------|-------|-------|
| Tesseract OCR | Requerido | Eliminado |
| OpenAI extractTransactions() | Requerido | Eliminado |
| Cola `ai-processing` Redis | Requerida | Eliminada |
| Integraciones POS (Square, Stripe, Shopify, Clover, WooCommerce) | Planificadas | Eliminadas |
| QuickBooks Online | No planificado | Integración básica nueva |

---

*Documento generado en base a SM_Portal_Modulos2.pdf — Abril 2026*
