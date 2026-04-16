# Informe de Cambios — Segunda Revisión de Calidad — Pre-Demo
## SM Portal — Strategic Mates

**Fecha:** 15 de abril de 2026, 21:00 hs (GMT-3)
**Preparado por:** Aquiles Dev
**Proyecto:** SM Portal — Strategic Mates
**Entregado a:** Strategic Mates

---

## Introducción

Este documento describe las correcciones aplicadas al sistema SM Portal como parte de la segunda revisión de calidad previa a la demostración. Cada cambio tiene como objetivo garantizar que el sistema se comporte de forma correcta, coherente y libre de errores visibles durante la presentación al cliente.

Los cambios se agrupan en dos áreas: el servidor (backend) y la interfaz de usuario (frontend).

---

## Resumen Ejecutivo

Se corrigieron **8 puntos** identificados durante la segunda revisión interna de calidad:

- 7 correcciones en el servidor (backend)
- 1 corrección en la interfaz de usuario (frontend)

Varios de estos cambios corrigen problemas que habrían sido directamente visibles durante una demostración: campos en blanco, formularios bloqueados, y mensajes de error técnicos sin explicación.

---

## Area 1: Servidor (Backend)

### Cambio 1 — Campos ciudad y correo electrónico ahora visibles al editar una empresa

**Qué se cambió:**
Los campos `ciudad` y `correo electrónico` de una empresa ahora se incluyen correctamente en la información que el servidor envía al formulario de edición. Además, la columna "Ubicación" de la tabla de empresas ahora muestra la ciudad guardada en lugar de mostrar "—".

**Por qué se cambió:**
El sistema debe mantener la información completa de cada empresa cliente (Small Business) para que los administradores de franquicia puedan gestionarla correctamente. Al abrir el formulario de edición de una empresa, el servidor no estaba enviando esos campos al formulario, por lo que aparecían siempre en blanco aunque la información estuviera correctamente guardada en la base de datos.

**Qué problema resolvía:**
Durante cualquier demostración del sistema, al intentar editar una empresa, los campos de ciudad y correo aparecían vacíos, y la columna de ubicación en la tabla mostraba "—" en lugar del valor real. Esto generaba una impresión incorrecta sobre la integridad de los datos del sistema.

---

### Cambio 2 — Respuesta completa al crear una empresa por ruta directa

**Qué se cambió:**
Al crear una empresa utilizando el flujo directo (sin pasar por el proceso de Close Deal), la respuesta del servidor ahora incluye el nombre de la franquicia asociada, de la misma manera que lo hace el flujo de Close Deal.

**Por qué se cambió:**
La interfaz de usuario esperaba recibir el nombre de la franquicia como parte de la respuesta de creación, para poder mostrar la nueva empresa correctamente en la tabla. Al crearse por la vía directa, ese dato no estaba siendo devuelto, lo que generaba una inconsistencia en la información mostrada.

**Qué problema resolvía:**
Al crear una empresa por la ruta directa, la tabla mostraba un valor vacío en la columna de franquicia hasta que el usuario recargaba la página. Esto podía generar confusión sobre si la operación se completó correctamente.

---

### Cambio 3 — Cierre de sesión corregido para entornos multi-pestaña

**Qué se cambió:**
Al cerrar sesión, el sistema ahora invalida únicamente la sesión actual del usuario, en lugar de cerrar todas sus sesiones activas al mismo tiempo.

**Por qué se cambió:**
El sistema fue diseñado para soportar sesiones simultáneas, dado que los equipos de trabajo pueden usar el portal desde múltiples dispositivos o pestañas al mismo tiempo. Al cerrar sesión en una pestaña, el comportamiento anterior cerraba también todas las demás sesiones activas del mismo usuario, contradiciendo ese diseño intencional.

**Qué problema resolvía:**
Si un usuario tenía el portal abierto en dos pestañas y cerraba sesión en una, la otra pestaña perdía acceso de forma silenciosa e inesperada. Esto podía provocar errores confusos durante el uso cotidiano del sistema.

---

### Cambio 4 — Protección ante asignaciones simultáneas de Business Bishop

**Qué se cambió:**
La asignación de un Business Bishop (BB) a una empresa ahora es una operación atómica y segura. Si dos personas intentan asignar un BB a la misma empresa al mismo tiempo, el sistema garantiza que solo una operación tenga éxito y la otra recibe un mensaje claro de error en lugar de un fallo técnico.

**Por qué se cambió:**
El documento de negocio establece que cada empresa tiene exactamente un Business Bishop patrocinador:

> "Business Bishop (investor sponsor, 1 per SB)" — cada Small Business tiene exactamente un Business Bishop.

Sin esta protección, dos operaciones simultáneas podían romper esa regla y generar un error técnico interno en lugar de un mensaje amigable y comprensible para el usuario.

**Qué problema resolvía:**
En condiciones de uso concurrente, la pantalla del usuario podría haber mostrado un error 500 (error interno del servidor) sin ninguna explicación. Ahora, en ese caso, el sistema responde con un mensaje de validación claro.

---

### Cambio 5 — Coherencia en la verificación de permisos de franquicia

**Qué se cambió:**
La verificación de acceso a una franquicia ahora utiliza el mismo patrón de comparación segura que el resto del sistema.

**Por qué se cambió:**
Se detectó una inconsistencia interna en la forma en que se comparaban los identificadores de franquicia dentro del módulo de permisos. Esta inconsistencia era similar a la ya corregida en la política de empresas durante la primera revisión (Cambio 3 del documento anterior), y podía producir resultados incorrectos en los controles de acceso.

**Qué problema resolvía:**
Un administrador de franquicia con los permisos correctos podría haber recibido un error de acceso denegado al intentar operar sobre su propia franquicia.

---

### Cambio 6 — Mensaje claro cuando un administrador no tiene franquicia asignada

**Qué se cambió:**
Si un administrador de franquicia (`admin_sm`) intenta crear o editar una empresa pero su cuenta no tiene una franquicia asignada, ahora recibe un mensaje claro: "Tu cuenta no tiene una franquicia asignada. Contacta al superadmin." Antes, esta situación producía un bloqueo silencioso sin ninguna explicación.

**Por qué se cambió:**
El documento de negocio establece que el rol `admin_sm` siempre debe estar vinculado a una franquicia específica, ya que su alcance está limitado a las empresas de esa franquicia. Si una cuenta de administrador está mal configurada, el sistema debe comunicarlo de forma clara para que pueda resolverse.

**Qué problema resolvía:**
Un administrador con una cuenta mal configurada recibía un error de validación sin ninguna explicación, sin poder entender qué salió mal ni cómo resolverlo.

---

### Cambio 7 — Información de franquicia disponible desde el inicio de sesión

**Qué se cambió:**
La respuesta que el servidor devuelve al iniciar sesión ahora incluye el identificador de franquicia del usuario (`sm_franchise_id`), es decir, a qué franquicia pertenece ese administrador.

**Por qué se cambió:**
El documento de negocio establece que un administrador de franquicia solo puede operar dentro de su propia franquicia. Para que el formulario de creación de empresa pueda seleccionar automáticamente la franquicia correcta desde el inicio, el sistema necesita conocer esa vinculación desde el momento en que el usuario inicia sesión.

**Qué problema resolvía:**
Este dato faltante era la causa raíz del bloqueo descrito en el Cambio 8 (Frontend). Sin este identificador disponible, el formulario de empresa no podía pre-seleccionar la franquicia del administrador.

---

## Area 2: Interfaz de Usuario (Frontend)

### Cambio 8 — Formulario de empresa funcional para administradores de franquicia

**Qué se cambió:**
Cuando un administrador de franquicia (`admin_sm`) abre el formulario para crear una empresa, la franquicia correspondiente ahora se selecciona automáticamente. El administrador ya no necesita elegirla manualmente, y el formulario puede enviarse sin problemas.

**Por qué se cambió:**
El documento de negocio establece que el rol `admin_sm` tiene alcance limitado a su propia franquicia y debe poder registrar empresas dentro de ella sin fricciones adicionales:

> "admin_sm: Their SM franchise only" — un administrador de franquicia opera exclusivamente dentro de su franquicia.

El formulario intentaba leer la franquicia del usuario desde el estado local de la aplicación, pero ese dato no estaba disponible en ese momento. Como resultado, el selector de franquicia quedaba bloqueado en "Seleccionar franquicia" y el formulario no podía enviarse.

**Qué problema resolvía:**
El rol `admin_sm` tenía el formulario de creación de empresa completamente inutilizable: el campo de franquicia permanecía bloqueado y el botón de guardar no funcionaba. Este problema habría sido inmediatamente visible en cualquier demostración del sistema.

---

## Impacto en el Cliente

Varios de los cambios descritos corrigen comportamientos que habrían sido directamente visibles durante una demostración. El usuario del sistema se beneficia de:

- **Mayor visibilidad de datos:** los campos de ciudad y correo ahora se muestran correctamente en la tabla y en los formularios de edición
- **Mayor usabilidad:** el rol `admin_sm` puede crear empresas sin bloqueos ni pasos manuales innecesarios
- **Mensajes de error comprensibles:** en lugar de errores técnicos, el sistema ahora explica con claridad qué ocurrió y cómo resolverlo
- **Mayor estabilidad:** se eliminaron condiciones de uso concurrente que podían generar errores inesperados
- **Mayor consistencia:** los datos mostrados son los mismos sin importar el flujo utilizado para crear una empresa

---

## Proximos Pasos

Con estas correcciones aplicadas, el sistema está listo para la demostración. Los módulos ya desarrollados se comportan de manera correcta y coherente para todos los roles del sistema, y los datos se muestran con fidelidad en la interfaz.

---

*Documento generado por Aquiles Dev — 15 de abril de 2026*
