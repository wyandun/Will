# Informe de Cambios — Revisión Pre-Sprint 3
## SM Portal — Strategic Mates

**Fecha:** 15 de abril de 2026, 15:00 hs (GMT-3)
**Preparado por:** Aquiles Dev
**Proyecto:** SM Portal — Strategic Mates
**Entregado a:** Strategic Mates

---

## Introducción

Este documento describe los ajustes y correcciones aplicados al sistema SM Portal antes del inicio del Sprint 3. Cada cambio tiene como objetivo garantizar que el sistema respete correctamente las reglas de negocio definidas en el documento oficial del proyecto, y que el acceso a la información esté protegido según los roles y permisos establecidos.

Los cambios se agrupan en tres áreas: el servidor (backend), la interfaz de usuario (frontend) y la base de datos.

---

## Resumen Ejecutivo

Se corrigieron **12 puntos** identificados durante la revisión interna de calidad:

- 3 correcciones de seguridad y permisos de acceso (servidor)
- 3 correcciones de interfaz y experiencia de usuario (frontend)
- 6 mejoras en la estructura y consistencia de la base de datos

Ningún cambio altera la funcionalidad visible para el usuario final. Todos los ajustes son preventivos o correctivos sobre comportamientos que podrían haber generado errores en el Sprint 3.

---

## Area 1: Servidor (Backend)

### Cambio 1 — Protección de la asignación de Business Bishops

**Qué se cambió:**
Se creó un mecanismo formal de permisos para controlar quién puede asignar y desasignar un Business Bishop (BB) a una empresa.

**Por qué se cambió:**
El documento de negocio establece que el BB es el "patrocinador inversor" de una empresa, con acceso limitado únicamente a la contabilidad y contratos de la empresa que patrocina. Para mantener esa integridad, es fundamental que un administrador de franquicia (`admin_sm`) solo pueda gestionar asignaciones de BB dentro de las empresas que pertenecen a su propia franquicia, sin poder afectar empresas de otras franquicias.

Antes de este cambio, esa restricción no estaba implementada formalmente y podría haber permitido operaciones fuera del alcance permitido.

**Qué problema resolvía:**
Un administrador de franquicia podría, sin esta corrección, intentar asignar o desasignar un BB en una empresa que no le corresponde. Ahora el sistema lo impide automáticamente.

---

### Cambio 2 — Validación al modificar los datos de una empresa

**Qué se cambió:**
Se agregó una verificación al formulario de edición de empresa que impide que un administrador de franquicia (`admin_sm`) pueda reasignar una empresa a una franquicia diferente a la suya.

**Por qué se cambió:**
El documento de negocio define claramente que el rol `admin_sm` tiene alcance limitado a su propia franquicia. Permitir que un administrador cambie la franquicia a la que pertenece una empresa sería una violación directa de esa regla de negocio.

**Qué problema resolvía:**
Sin esta validación, era técnicamente posible que un administrador de franquicia "transfiriera" una empresa a otra franquicia, alterando la estructura jerárquica del sistema (Strategic Mates → Franquicia → Empresa).

---

### Cambio 3 — Corrección de comparaciones de identidad en permisos

**Qué se cambió:**
Se corrigió la forma en que el sistema compara los identificadores de franquicia, empresa y sub-franquicia en el modelo de usuario.

**Por qué se cambió:**
Internamente, los identificadores numéricos (IDs) a veces llegaban al sistema como texto en lugar de como número. Esto podía hacer que una comparación del tipo "¿este usuario pertenece a esta franquicia?" fallara incorrectamente, incluso cuando el ID era el mismo.

**Qué problema resolvía:**
Podría haber producido falsos negativos en los controles de acceso: un usuario con los permisos correctos podría haber sido bloqueado por error, o viceversa.

---

## Area 2: Interfaz de Usuario (Frontend)

### Cambio 4 — Corrección en la carga del listado de franquicias

**Qué se cambió:**
Se corrigió la función que carga la lista de franquicias disponibles al momento de registrar o editar una empresa. Ahora devuelve los datos en el formato esperado, consistente con el resto del sistema.

**Por qué se cambió:**
El formulario de empresa tiene un campo para seleccionar a qué franquicia pertenece. Ese campo se llenaba con datos del servidor, pero la función que traía esos datos tenía un formato diferente al del resto de la aplicación, lo que podía causar que el campo apareciera vacío.

**Qué problema resolvía:**
Al crear o editar una empresa, el selector de franquicia podría haber aparecido vacío aunque existieran franquicias disponibles.

---

### Cambio 5 — Protección de rutas de administración

**Qué se cambió:**
Se aplicaron guardias de rol en las secciones de administración de la interfaz. Las páginas de Franquicias, Empresas y Usuarios ahora solo son accesibles para los roles `superadmin` y `admin_sm`.

**Por qué se cambió:**
El documento de negocio establece que la gestión de franquicias y empresas está restringida a los administradores del sistema. Roles como `sb_owner`, `sb_employee`, `bb` o los relacionados con sub-franquicias no deben tener acceso a esas secciones.

**Qué problema resolvía:**
Sin estas protecciones, cualquier usuario autenticado podría haber navegado directamente a esas URLs y visualizado o intentado operar sobre información que no le corresponde.

---

### Cambio 6 — Optimización en la lectura del perfil del usuario autenticado

**Qué se cambió:**
Se corrigió la forma en que el formulario de empresa lee la información del usuario actualmente conectado (su rol y sus datos personales).

**Por qué se cambió:**
La forma anterior de leer esa información hacía que el componente se recargara innecesariamente ante cualquier cambio en el estado global de la aplicación, incluso cuando el cambio no tenía nada que ver con el usuario. Esto podía causar parpadeos o comportamientos inesperados en la interfaz.

**Qué problema resolvía:**
Posibles re-renderizados innecesarios en el formulario de empresa que podrían causar pérdida de datos ingresados por el usuario si el formulario se recargaba en un momento inoportuno.

---

## Area 3: Base de Datos

### Cambio 7 — Prevención de "likes" duplicados en el Feed

**Qué se cambió:**
Se agregó una restricción en la base de datos para que un usuario no pueda dar "me gusta" más de una vez a la misma publicación del Feed.

**Por qué se cambió:**
Aunque la lógica de la aplicación ya intentaba prevenir esta situación, la base de datos no tenía una restricción formal que lo garantizara. Una restricción a nivel de base de datos es la forma más segura de evitar datos inconsistentes.

**Qué problema resolvía:**
Sin esta restricción, en condiciones específicas (por ejemplo, doble clic rápido o peticiones simultáneas), un usuario podría haber registrado múltiples "me gusta" en la misma publicación.

---

### Cambio 8 — Vinculación formal entre contactos de assessment y decisiones

**Qué se cambió:**
Se agregó una referencia formal entre la tabla de contactos de evaluación (`assessment_contacts`) y la tabla de decisiones (`assessment_decisions`).

**Por qué se cambió:**
El sistema de evaluaciones (Módulo 03 — Assessments Públicos) registra tanto los contactos de personas evaluadas como las decisiones tomadas sobre esas evaluaciones. Sin esta vinculación formal, la relación entre ambos registros existía solo a nivel de código, sin protección en la base de datos.

**Qué problema resolvía:**
Posibilidad de que existieran registros de contacto apuntando a decisiones que ya no existen, generando datos huérfanos o inconsistentes.

---

### Cambio 9 — Soporte para seguimiento histórico por año

**Qué se cambió:**
Se agregó un campo `year` (año) a la tabla de seguimiento de empresas (`client_trackings`), y se estableció una restricción para que no puedan existir dos registros de seguimiento para la misma empresa, en el mismo mes y año, para el mismo ítem de catálogo.

**Por qué se cambió:**
El módulo de Tracking (Módulo 12) registra el avance de una empresa a lo largo del tiempo. Sin el campo de año, no era posible distinguir entre el mes de enero del año actual y el mismo mes de años anteriores, lo que impediría llevar un historial correcto a largo plazo.

**Qué problema resolvía:**
Sin el año, al ingresar datos de seguimiento en el segundo año de operación de una empresa, el sistema habría generado conflictos o sobrescritura de datos históricos.

---

### Cambio 10 — Mejora de rendimiento en búsquedas de sub-franquicias

**Qué se cambió:**
Se agregó un índice de búsqueda sobre el campo `sub_franchise_id` en la tabla de usuarios.

**Por qué se cambió:**
Un índice (similar a un índice al final de un libro) permite que la base de datos encuentre registros mucho más rápido. Este índice estaba faltante, lo que significa que cada consulta que filtrara usuarios por sub-franquicia requería recorrer todos los usuarios de la tabla.

**Qué problema resolvía:**
A medida que crezca la cantidad de usuarios en el sistema, las consultas relacionadas con sub-franquicias serán progresivamente más lentas sin este índice. Se agregó de forma preventiva antes de que el volumen de datos sea significativo.

---

### Cambio 11 — Restricción de unicidad en repositorios de documentos por empresa

**Qué se cambió:**
Se agregó una restricción en la base de datos para evitar que una misma empresa tenga dos repositorios de documentos duplicados al mismo nivel.

**Por qué se cambió:**
El Módulo 08 — Repositorio de Documentos organiza los archivos en niveles jerárquicos por empresa. Si dos repositorios del mismo nivel pudieran coexistir para la misma empresa, la estructura de carpetas del repositorio quedaría inconsistente.

**Qué problema resolvía:**
Creación accidental de carpetas duplicadas en el repositorio de documentos de una empresa, lo que dificultaría la navegación y podría generar confusión sobre dónde están almacenados los documentos.

---

### Cambio 12 — Acceso directo a los mapas de procesos desde la empresa

**Qué se cambió:**
Se agregaron dos relaciones directas en el modelo de empresa para acceder fácilmente al mapa de procesos tipo "franquiciadora" y al mapa tipo "franquiciada".

**Por qué se cambió:**
El documento de negocio establece explícitamente que:

> "Cada empresa tiene exactamente 2 mapas de procesos: uno de tipo 'franquiciadora' y uno de tipo 'franquiciada'. Ambos son creados automáticamente cuando se registra la empresa mediante el proceso de Close Deal."

Para que el sistema pueda acceder a estos mapas de forma eficiente y consistente desde cualquier parte de la aplicación, era necesario definir esta relación formalmente en el modelo de datos.

**Qué problema resolvía:**
Sin estas relaciones definidas, acceder a los mapas de una empresa requería escribir consultas personalizadas en cada lugar del código donde se necesitaran, aumentando el riesgo de errores y duplicación de lógica.

---

## Impacto en el Cliente

Todos los cambios descritos son internos al sistema. El usuario final no percibe diferencias visuales, pero se beneficia de:

- Mayor seguridad: cada usuario solo puede acceder y modificar lo que le corresponde segun su rol
- Mayor consistencia: los datos del sistema mantienen su integridad a lo largo del tiempo
- Mayor rendimiento: las consultas frecuentes se ejecutan más rápido gracias a los índices agregados
- Mayor estabilidad: se eliminaron condiciones que podrían haber generado errores en el Sprint 3

---

## Proximos Pasos

Con estas correcciones aplicadas, el sistema está listo para comenzar el Sprint 3. Los módulos que se trabajarán en el próximo sprint podrán construirse sobre una base sólida y sin las inconsistencias identificadas en esta revisión.

---

*Documento generado por Aquiles Dev — 15 de abril de 2026*
