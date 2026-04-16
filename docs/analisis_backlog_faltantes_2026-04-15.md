# Análisis de Backlog — Historias Faltantes en Linear

**Proyecto:** SM Portal — Strategic Mates  
**Fecha:** 15 de abril de 2026, 22:00 hs (GMT-3)  
**Preparado por:** Aquiles Dev  
**Destinatario:** Equipo Strategic Mates

---

## Introducción

Este documento presenta el resultado de una revisión del backlog actual en Linear para el proyecto SM Portal. Durante el análisis del documento de negocio del proyecto, se identificaron **17 historias de usuario que no tienen representación en el backlog**, pero que son necesarias para cumplir con los requisitos funcionales documentados por el cliente.

Cada historia está justificada con citas textuales del documento de negocio y acompañada de una evaluación del impacto de no implementarla. El objetivo es que este análisis sirva como base para una sesión de refinamiento de backlog con el equipo, donde se prioricen y agreguen estas historias a Linear antes de iniciar los sprints correspondientes.

Las 17 historias están agrupadas por sprint sugerido y ordenadas según su dependencia técnica y valor para el negocio.

---

## Tabla Resumen

| # | Historia | Título corto | Sprint | Prioridad |
|---|----------|--------------|--------|-----------|
| A | Invitación por enlace para nuevos usuarios | Invitación por email | Sprint 2 | Alta |
| B | Recuperación de contraseña | Reset de contraseña | Sprint 2 | Alta |
| C | Seed formal de los 7 roles | Seed de roles | Sprint 2 | Alta |
| D | Vista global del superadmin | Panel global superadmin | Sprint 2 | Media |
| E | Close Deal atómico con todo | Close Deal completo | Sprint 3 | Alta |
| F | Catálogo formal de decisiones de assessment | Catálogo de decisiones | Sprint 3 | Media |
| G | Vincular assessment con empresa creada | Traza assessment → empresa | Sprint 3 | Media |
| H | Campo type en mapas de proceso BPMN | Tipo de mapa BPMN | Sprint 4 | Alta |
| I | Sub_franchise_owner ve mapa franquiciada | Vista mapa sub-franquicia | Sprint 4 | Alta |
| J | 3 firmantes explícitos en contratos | Firmantes en contratos | Sprint 4 | Alta |
| K | Scope del BB a su empresa únicamente | Acceso BB restringido | Sprint 5 | Alta |
| L | Integración POS | Conexión POS automática | Sprint 5 | Media |
| M | sb_employee con módulos limitados | Permisos por módulo | Sprint 5 | Alta |
| N | sub_franchise_admin gestiona su equipo | Gestión sub-franquicia | Sprint 6 | Media |
| O | Crear sub-franquicias desde empresa SB | Crear sub-franquicias | Sprint 6 | Media |
| P | Panel global de métricas para superadmin | Métricas globales | Sprint 6 | Baja |
| Q | Preferencias de notificación | Notificaciones por evento | Sprint 6 | Baja |

---

## Detalle de las 17 Historias

---

### Historia A — Invitación por enlace para nuevos usuarios

**Historia de usuario:**
Como admin_sm o superadmin, quiero invitar a un nuevo usuario por enlace enviado a su email para que él mismo configure su contraseña al primer acceso, en lugar de que yo le asigne una manualmente.

**Sprint sugerido:** Sprint 2

**Justificación:**
El flujo actual obliga al administrador a crear la cuenta del nuevo usuario y asignarle una contraseña de forma manual, lo que genera dos problemas: el administrador conoce la contraseña del usuario (problema de seguridad) y el usuario no tiene un acceso inicial propio al sistema. Un flujo de invitación por enlace resuelve ambos problemas: el administrador envía el email, el usuario activa su cuenta y establece su propia contraseña. Este flujo es estándar en cualquier plataforma SaaS B2B y es especialmente importante aquí dado que los usuarios del sistema incluyen empresas clientes (SBs) e inversores (BBs) que deben tener autonomía sobre sus credenciales desde el primer día.

**Cita del documento:**
> "Falta: flujo de invitación por link para nuevos usuarios (hoy el admin crea la cuenta manualmente con contraseña)."

**Qué pasa si no se agrega:**
Los administradores continuarán creando contraseñas manuales para cada usuario, lo que representa un riesgo de seguridad directo (el admin conoce la clave del usuario) y una experiencia de incorporación deficiente para los clientes del portal. En un entorno B2B con múltiples actores (admins, owners, employees, BBs), esto no escala.

**Prioridad:** Alta  
Es un requisito de seguridad básico y afecta a todos los roles del sistema desde el inicio de la operación.

---

### Historia B — Recuperación de contraseña

**Historia de usuario:**
Como usuario, quiero recuperar mi contraseña solicitando un enlace de reseteo a mi email para no quedar bloqueado si olvido mis credenciales.

**Sprint sugerido:** Sprint 2

**Justificación:**
La recuperación de contraseña es una funcionalidad esencial en cualquier sistema con autenticación. Sin ella, cualquier usuario que olvide su contraseña queda bloqueado permanentemente hasta que un administrador intervenga de forma manual. En el contexto de SM Portal, donde conviven múltiples roles con diferentes niveles de acceso técnico, es esperable que los usuarios no administradores (sb_employee, bb, sub_franchise_owner) no tengan forma de recuperar acceso por sus propios medios.

**Cita del documento:**
> "Falta: recuperación de contraseña por email."

**Qué pasa si no se agrega:**
Cualquier usuario que pierda el acceso a su cuenta dependerá de la intervención manual del administrador para recuperarla. Esto genera carga operativa innecesaria sobre los admins y puede dejar a clientes sin acceso al portal por tiempo indeterminado, afectando la imagen del servicio.

**Prioridad:** Alta  
Es una funcionalidad mínima esperada en cualquier sistema de autenticación; su ausencia genera dependencia operativa y riesgo de abandono del sistema por parte de usuarios.

---

### Historia C — Seed formal de los 7 roles

**Historia de usuario:**
Como desarrollador del sistema, quiero que al ejecutar las migraciones queden creados los 7 roles exactos con Spatie Permissions para que todas las historias posteriores puedan asignarlos correctamente.

**Sprint sugerido:** Sprint 2

**Justificación:**
El sistema de permisos de SM Portal se basa en 7 roles exactos que determinan qué puede ver y hacer cada actor en la plataforma. Si estos roles no están formalmente inicializados en la base de datos desde el principio, cualquier lógica de permisos que se construya sobre ellos es frágil: puede funcionar en un entorno pero no en otro, o romperse al reinicializar la base de datos. Esta historia es técnica pero tiene impacto directo en el negocio: sin los roles correctamente sembrados, la diferenciación de acceso entre superadmin, admin_sm, sb_owner, sb_employee, bb, sub_franchise_owner y sub_franchise_admin no existe de forma confiable en ningún entorno.

**Cita del documento:**
> "Falta: definición formal de permisos para roles BB y sub_franchise en el código."

**Qué pasa si no se agrega:**
Las funcionalidades de control de acceso (qué ve cada rol) no tienen una base confiable sobre la cual construirse. Esto puede generar errores silenciosos donde un usuario accede a información que no le corresponde, o donde las restricciones no aplican en entornos nuevos o de producción.

**Prioridad:** Alta  
Es una dependencia técnica directa de todas las historias relacionadas con acceso por rol, que son la mayoría de las funcionalidades del sistema.

---

### Historia D — Vista global del superadmin

**Historia de usuario:**
Como superadmin, quiero ver el listado completo de todas las franquicias SM y sus empresas hijas para supervisar la operación global de la plataforma.

**Sprint sugerido:** Sprint 2

**Justificación:**
El rol de superadmin tiene acceso total al sistema y representa al equipo central de Strategic Mates. Sin una vista consolidada que muestre todas las franquicias SM activas y las empresas SB que cada una tiene a cargo, el equipo central no tiene forma de supervisar el estado general de la operación. Esta visibilidad es fundamental para tomar decisiones estratégicas, detectar problemas y auditar la actividad de la plataforma.

**Cita del documento:**
> "Strategic Mates (Superadmin): acceso total, crea franquicias SM, admins, gestiona catálogo."  
> "admin_sm: Their SM franchise only."

**Qué pasa si no se agrega:**
El superadmin no tiene una forma centralizada de ver la operación global. Cada vez que necesite revisar el estado de la plataforma deberá acceder a los datos directamente en la base de datos o navegar manualmente por cada franquicia, lo que no es viable en producción.

**Prioridad:** Media  
Es importante para la gestión operativa de Strategic Mates, pero no bloquea las funcionalidades de los clientes SB. Se puede incorporar en Sprint 2 aprovechando la base de autenticación ya existente.

---

### Historia E — Close Deal atómico con todo

**Historia de usuario:**
Como admin_sm, quiero que al ejecutar el Close Deal desde un assessment se cree automáticamente la empresa, el usuario sb_owner, el vínculo con el Business Bishop y los 2 mapas de proceso en una sola transacción, para que el onboarding del cliente no dependa de pasos manuales.

**Sprint sugerido:** Sprint 3

**Justificación:**
El Close Deal es el momento más crítico del flujo de negocio de Strategic Mates: es cuando un prospecto se convierte en cliente activo. Según el documento, este evento debe desencadenar de forma automática y simultánea la creación de múltiples entidades en el sistema. Si alguno de estos pasos falla o debe hacerse manualmente, el cliente puede quedar en un estado inconsistente: con empresa creada pero sin usuario, o con usuario pero sin mapas de proceso. La atomicidad de esta operación (que todo suceda junto o nada) es una garantía de integridad del negocio.

**Cita del documento:**
> "Cuando deciden proceder con un negocio, cierran el trato, se hace el pago, se verifica: en ese momento el sistema crea automáticamente el portal del SB, su usuario owner, vincula al BB asignado y genera los dos mapas de procesos (franquiciadora y franquiciada)."  
> "Falta: flujo de 'cerrar trato' que cree automáticamente el portal del SB, el user owner, vincule al BB y genere los 2 mapas de procesos."

**Qué pasa si no se agrega:**
El onboarding de cada nuevo cliente requeriría intervención manual en múltiples pasos, lo que aumenta el riesgo de errores, inconsistencias en la base de datos y demoras en la activación del cliente. En escala, esto hace que el sistema no sea viable como producto SaaS.

**Prioridad:** Alta  
Es el flujo central del negocio y su ausencia hace que el onboarding de clientes dependa enteramente de operaciones manuales propensas a error.

---

### Historia F — Catálogo formal de decisiones de assessment

**Historia de usuario:**
Como admin_sm, quiero registrar la decisión de un assessment eligiendo de un catálogo formal de decisiones para mantener trazabilidad consistente y poder generar reportes sobre los resultados de las postulaciones.

**Sprint sugerido:** Sprint 3

**Justificación:**
Actualmente la decisión sobre un assessment se guarda como texto libre (un campo varchar sin valores predefinidos). Esto impide hacer análisis consistentes: si un admin escribe "Aprobado", otro escribe "aprobado" y un tercero escribe "Sí", el sistema no puede agruparlos como la misma decisión. Un catálogo formal garantiza que todas las decisiones sean comparables, filtrables y reportables, lo que es valioso tanto para la operación diaria como para el análisis estratégico de Strategic Mates.

**Cita del documento:**
> "Falta: tabla assessment_decisions como catálogo formal (hoy la decisión es un campo varchar libre)."

**Qué pasa si no se agrega:**
Los datos de decisiones de assessments serán inconsistentes e incomparables entre diferentes admins. Cualquier intento de analizar cuántos prospectos fueron aprobados, rechazados o puestos en espera requerirá limpieza manual de datos, y los reportes generados serán poco confiables.

**Prioridad:** Media  
No bloquea el flujo operativo inmediato, pero la falta de este catálogo genera deuda de calidad de datos que se vuelve más difícil de corregir cuanto más tiempo pasa.

---

### Historia G — Vincular assessment con empresa creada

**Historia de usuario:**
Como admin_sm, quiero ver en el detalle de un assessment el vínculo con la empresa convertida cuando el Close Deal ya fue ejecutado, para rastrear de dónde provino cada cliente y auditar el proceso de conversión.

**Sprint sugerido:** Sprint 3

**Justificación:**
Una vez que se ejecuta el Close Deal y se crea la empresa del cliente, la relación entre la postulación original (assessment) y la empresa resultante queda perdida si no hay un campo que la registre. Esto impide responder preguntas básicas del negocio como "¿qué empresa salió de este assessment?" o "¿cuánto tiempo pasó entre la postulación y la conversión?". Esta trazabilidad es fundamental para la auditabilidad del proceso comercial de Strategic Mates.

**Cita del documento:**
> "Falta: campo converted_company_id en assessment_contacts para vincular la postulación con la company creada."

**Qué pasa si no se agrega:**
La conexión entre el origen de un cliente (su postulación) y su estado actual en el portal se pierde. No es posible auditar el proceso comercial, calcular tasas de conversión reales ni rastrear el historial completo de un cliente desde su primera interacción con Strategic Mates.

**Prioridad:** Media  
Es importante para la trazabilidad del negocio y complementa directamente el flujo de Close Deal (Historia E). Se recomienda implementar en el mismo sprint.

---

### Historia H — Campo type en mapas de proceso BPMN

**Historia de usuario:**
Como sistema, quiero que cada mapa de proceso BPMN tenga un campo type con valor "franquiciadora" o "franquiciada" para diferenciar los 2 mapas obligatorios que debe tener cada empresa y aplicar correctamente las reglas de visibilidad por rol.

**Sprint sugerido:** Sprint 4

**Justificación:**
Una de las reglas de negocio más específicas de Strategic Mates es que cada empresa SB debe tener exactamente dos mapas de proceso: uno que representa cómo opera la empresa como franquiciadora (visible para el SB) y otro que representa cómo debe operar una sub-franquicia (visible para los sub_franchise_owner). Sin este campo, el sistema no puede distinguir entre los dos mapas y no puede aplicar las reglas de visibilidad correctas para cada rol. Actualmente todos los mapas son tratados de la misma forma.

**Cita del documento:**
> "Cada empresa tiene dos mapas: el mapa 'franquiciadora' que ve el SB [...] y el mapa 'franquiciada' que ven sus sub-franquicias."  
> "Falta: campo type en process_maps (franquiciadora vs franquiciada) — hoy todos los mapas son iguales."

**Qué pasa si no se agrega:**
El sistema no puede diferenciar qué mapa debe ver cada rol. Los sub_franchise_owner podrían ver el mapa incorrecto, y el módulo de Mapas de Proceso no cumple con la regla de negocio central de Strategic Mates que distingue entre operación propia y operación de sub-franquicia.

**Prioridad:** Alta  
Es un requisito estructural del módulo de Mapas de Proceso y una regla de negocio explícita del documento. Sin este campo, el módulo no puede funcionar correctamente para ningún rol.

---

### Historia I — Sub_franchise_owner ve mapa franquiciada

**Historia de usuario:**
Como sub_franchise_owner, quiero ver únicamente el mapa de proceso de tipo "franquiciada" de la empresa SB a la que pertenezco para seguir la operación estandarizada que debo replicar en mi sub-franquicia.

**Sprint sugerido:** Sprint 4

**Justificación:**
El sub_franchise_owner es el dueño de una de las franquicias que abre el cliente SB. Su referencia operativa es el mapa "franquiciada" de la empresa madre, que describe exactamente cómo debe funcionar su negocio. Esta restricción de visibilidad (solo el mapa franquiciada, no el franquiciadora) es parte del modelo de negocio de Strategic Mates y debe estar reflejada en el sistema. Sin esta historia, el sub_franchise_owner vería mapas que no le corresponden o no tendría acceso a ninguno.

**Cita del documento:**
> "sub_franchise_owner: Dueño de una sub-franquicia del SB. Ve el mapa de procesos tipo franquiciada, repositorio, contabilidad e inventario de su franquicia."  
> "Falta: vista del SB para ingresar al mapa de su sub-franquicia."

**Qué pasa si no se agrega:**
El rol sub_franchise_owner no tiene la visibilidad operativa que necesita para gestionar su negocio según los estándares de Strategic Mates. El producto no cumple con lo prometido a los clientes que tienen sub-franquicias activas.

**Prioridad:** Alta  
Afecta directamente la experiencia de un rol de usuario pagante del sistema. Depende de Historia H (campo type).

---

### Historia J — 3 firmantes explícitos en contratos

**Historia de usuario:**
Como usuario con permiso en el módulo de Contratos, quiero asignar a los 3 firmantes de un contrato como "Elaborado por", "Revisado por" y "Aprobado por" de forma explícita para cumplir con el flujo obligatorio de firma establecido por Strategic Mates.

**Sprint sugerido:** Sprint 4

**Justificación:**
El proceso de firma de contratos en Strategic Mates no es una firma simple: requiere tres roles diferenciados (quien elabora, quien revisa y quien aprueba) que pueden ser personas distintas. Esta estructura refleja un proceso de control interno definido por el cliente. La integración con DocuSeal está iniciada pero el flujo completo con estos tres roles explícitos no está implementado. Sin esta distinción, los contratos firmados a través del portal no cumplen con el proceso formal de la empresa.

**Cita del documento:**
> "Los contratos tienen tres firmantes en el proceso formal de SM: Elaborado por, Revisado por y Aprobado por."  
> "Falta: flujo completo con 3 firmantes (Elaborado/Revisado/Aprobado) — la integración está iniciada pero el flujo completo está pendiente."

**Qué pasa si no se agrega:**
Los contratos generados en el portal no cumplen con el proceso de aprobación formal de Strategic Mates. Esto puede tener implicaciones legales si los contratos se consideran inválidos por no haber pasado por el flujo correcto de revisión y aprobación.

**Prioridad:** Alta  
Tiene implicaciones legales y operativas directas. La integración con DocuSeal ya está iniciada, por lo que el costo de completarla en este sprint es menor que posponerla.

---

### Historia K — Scope del BB a su empresa únicamente

**Historia de usuario:**
Como Business Bishop, quiero ver únicamente la contabilidad y los contratos de la empresa que patrocino y no poder acceder a datos de otras empresas, para cumplir con mi rol de inversor con visibilidad acotada.

**Sprint sugerido:** Sprint 5

**Justificación:**
El Business Bishop es el inversor que patrocina a un SB específico. Su acceso al sistema es de lectura y está estrictamente limitado a la empresa que patrocina: no debe ver ni la contabilidad ni los contratos de otras empresas del portal. Esta restricción protege la confidencialidad de los datos de todos los clientes. Si el scope no está implementado en el código, cualquier BB podría acceder a información financiera de empresas que no le corresponden, lo que es una violación de privacidad y confidencialidad.

**Cita del documento:**
> "BB has read-only access to accounting and contracts of their sponsored company only."  
> "Falta: acceso del BB diferenciado (solo lectura) implementado en código."

**Qué pasa si no se agrega:**
El rol BB no tiene las restricciones de acceso correctas implementadas. Un Business Bishop podría acceder a datos financieros y contractuales de empresas que no patrocina, lo que representa un problema de confidencialidad y posiblemente de cumplimiento legal para Strategic Mates y sus clientes.

**Prioridad:** Alta  
Afecta la confidencialidad de los datos financieros de todos los clientes del portal. Es un requisito de seguridad, no solo funcional.

---

### Historia L — Integración POS

**Historia de usuario:**
Como sb_owner, quiero conectar el sistema POS de mi negocio (Square, Stripe, Shopify, Clover o WooCommerce) para que las ventas diarias se importen automáticamente al módulo de contabilidad sin necesidad de cargarlas manualmente.

**Sprint sugerido:** Sprint 5

**Justificación:**
Una de las propuestas de valor del portal es la automatización del registro contable. Los negocios SB operan con sistemas POS existentes y uno de los diferenciadores del portal es que puede conectarse con ellos via OAuth para importar transacciones automáticamente. Sin esta integración, los clientes deben ingresar sus ventas manualmente, lo que elimina uno de los beneficios principales del módulo de contabilidad y reduce el valor percibido del producto.

**Cita del documento:**
> "El portal se conecta con sistemas POS (Square, Stripe, Shopify, Clover, WooCommerce) via OAuth para importar transacciones de ventas automáticamente."

**Qué pasa si no se agrega:**
Los clientes SB deben registrar manualmente todas sus transacciones de ventas en el portal. Esto es una carga operativa significativa que puede llevar al abandono del módulo de contabilidad y, por extensión, reducir el valor percibido del portal completo.

**Prioridad:** Media  
Es un diferenciador importante del producto pero no bloquea el funcionamiento básico del módulo de contabilidad. Se puede implementar en Sprint 5 cuando el módulo contable esté estable.

---

### Historia M — sb_employee con módulos limitados

**Historia de usuario:**
Como sb_employee, quiero acceder solo a los módulos que mi sb_owner me asignó en la configuración de permisos para no ver información fuera de mis responsabilidades y para que el sb_owner pueda gestionar el acceso de su equipo.

**Sprint sugerido:** Sprint 5

**Justificación:**
El rol sb_employee es el colaborador del SB, y su acceso al portal está determinado por lo que el sb_owner le habilita. Esta configuración granular de permisos por módulo es central al modelo de permisos del sistema, que está diseñado para permitir que cada empresa gestione internamente qué ve cada miembro de su equipo. Sin esta historia, todos los empleados de un SB verían los mismos módulos que el owner, lo que puede exponer información sensible (contabilidad, contratos) a personas que no deberían acceder a ella.

**Cita del documento:**
> "sb_employee: Colaborador del SB. Acceso configurado por el admin. Puede tener permisos limitados a ciertos módulos."  
> "Permissions are per-module and stored in user_permissions table (not JSON in users)."

**Qué pasa si no se agrega:**
El rol sb_employee no existe funcionalmente como un rol diferenciado del sb_owner. Todos los empleados de una empresa tienen el mismo nivel de acceso, lo que es un problema de seguridad y no refleja la estructura real de una empresa con roles diferenciados.

**Prioridad:** Alta  
Afecta la seguridad interna de cada empresa cliente y es parte del modelo de permisos central del sistema. Sin esto, la tabla user_permissions no tiene utilidad práctica.

---

### Historia N — sub_franchise_admin gestiona su equipo

**Historia de usuario:**
Como sub_franchise_admin, quiero administrar los usuarios y las operaciones de mi sub-franquicia de forma independiente para apoyar al sub_franchise_owner en la gestión operativa del negocio.

**Sprint sugerido:** Sprint 6

**Justificación:**
El rol sub_franchise_admin existe para dar soporte operativo al dueño de una sub-franquicia. Es un rol con acceso similar al owner pero orientado a la gestión, no a la propiedad. Sin una historia específica que defina sus capacidades dentro del portal, este rol queda sin funcionalidad diferenciada y sin valor para el usuario que lo ocupa. Las sub-franquicias necesitan poder delegar la gestión operativa sin dar acceso de propietario.

**Cita del documento:**
> "sub_franchise_admin: Admin de una sub-franquicia. Apoya la gestión operativa de la sub-franquicia con acceso similar al owner."

**Qué pasa si no se agrega:**
El rol sub_franchise_admin existe en el sistema pero no tiene funcionalidades específicas que lo diferencien de los demás roles. Cualquier sub-franquicia que necesite delegar la administración operativa no tiene forma de hacerlo a través del portal.

**Prioridad:** Media  
Es un rol válido del sistema con necesidades claras, pero su implementación puede hacerse en sprint posterior sin bloquear el funcionamiento de los módulos principales.

---

### Historia O — Crear sub-franquicias desde empresa SB

**Historia de usuario:**
Como admin_sm, quiero crear sub-franquicias dentro de una empresa SB ya existente para modelar en el sistema la expansión del negocio del cliente cuando abre nuevas ubicaciones.

**Sprint sugerido:** Sprint 6

**Justificación:**
El crecimiento de un cliente SB en el programa de Strategic Mates pasa por la apertura de sub-franquicias. El portal debe poder registrar este crecimiento: cada nueva sub-franquicia de un SB es una entidad diferenciada dentro del sistema, con su propio owner y admin. Sin esta funcionalidad, el portal no puede modelar el ciclo de vida completo de un cliente exitoso dentro del programa y las sub-franquicias deben gestionarse fuera del sistema.

**Cita del documento:**
> "Small Businesses / SBs (el negocio cliente): Business Bishop (investor sponsor, 1 per SB) y Sub-Franquicias (franquicias abiertas por el SB owner)."

**Qué pasa si no se agrega:**
El portal no puede representar el crecimiento de los clientes más exitosos del programa. Las sub-franquicias, que son el resultado del éxito del programa de Strategic Mates, quedan fuera del sistema y deben gestionarse manualmente o con herramientas externas.

**Prioridad:** Media  
Es importante para el modelo de negocio a largo plazo pero no bloquea la operación inicial. Se puede incorporar en Sprint 6 cuando la base de roles y empresas esté consolidada.

---

### Historia P — Panel global de métricas para superadmin

**Historia de usuario:**
Como superadmin, quiero ver un panel global con métricas consolidadas del portal para supervisar la salud general de la plataforma y tomar decisiones estratégicas informadas.

**Sprint sugerido:** Sprint 6

**Justificación:**
El equipo central de Strategic Mates necesita visibilidad sobre el estado global de la plataforma: cuántas franquicias están activas, cuántos SBs están en el programa, qué nivel de actividad hay en cada módulo. Este panel no es operativo sino estratégico: sirve para que el superadmin pueda tomar decisiones sobre el negocio basándose en datos del propio portal.

**Cita del documento:**
> "Strategic Mates (Superadmin): acceso total, crea franquicias SM, admins, gestiona catálogo."

**Qué pasa si no se agrega:**
El superadmin no tiene una vista consolidada del estado de la plataforma. Cualquier análisis de métricas debe hacerse directamente en la base de datos, lo que no es viable para el equipo no técnico de Strategic Mates.

**Prioridad:** Baja  
Tiene alto valor estratégico pero no afecta la operación de los clientes del portal. Se puede implementar en Sprint 6 una vez que los módulos principales estén funcionando y haya datos reales para mostrar.

---

### Historia Q — Preferencias de notificación

**Historia de usuario:**
Como usuario autenticado, quiero configurar mis preferencias de notificación por tipo de evento para recibir solo las alertas relevantes a mi rol y no saturarme con notificaciones irrelevantes.

**Sprint sugerido:** Sprint 6

**Justificación:**
Con múltiples módulos activos (feed, contratos, contabilidad, calendarios, mapas de proceso), el portal generará una variedad de eventos que pueden notificar a los usuarios. No todos los eventos son relevantes para todos los roles. Un sb_employee puede no querer notificaciones de contabilidad, mientras que el bb solo necesita alertas relacionadas con la empresa que patrocina. Sin preferencias configurables, los usuarios reciben todas las notificaciones o ninguna, ambas opciones degradan la experiencia de uso.

**Cita del documento:**
> "Feed: Configurable per user."  
> "Módulos configurables por usuario."

**Qué pasa si no se agrega:**
Los usuarios no pueden gestionar su flujo de notificaciones, lo que puede llevar a ignorar todas las alertas del sistema (si reciben demasiadas) o a perderse información importante. Esto reduce el valor del módulo de feed y de las notificaciones en general.

**Prioridad:** Baja  
Mejora significativa de la experiencia de usuario, pero no bloquea ningún módulo funcional. Se puede implementar al final del proyecto cuando los módulos principales estén completos.

---

## Recomendación de Incorporación a Linear

Se recomienda agregar las historias a Linear en el siguiente orden de prioridad, respetando las dependencias técnicas entre ellas:

### Primera incorporación (urgente — antes de Sprint 2)

Las historias **C, A y B** deben agregarse antes de iniciar Sprint 2, ya que la Historia C (seed de roles) es una dependencia técnica de prácticamente todas las demás, y las Historias A y B (invitación y recuperación de contraseña) son requisitos de seguridad básicos que deben estar presentes desde el primer sprint con usuarios reales.

### Segunda incorporación (antes de Sprint 3)

Las historias **E, F y G** deben incorporarse antes de Sprint 3. La Historia E (Close Deal atómico) es el flujo central del negocio y las Historias F y G son complementarias a ese mismo flujo. Implementarlas juntas garantiza que el onboarding de clientes quede completo y trazable desde el inicio.

### Tercera incorporación (antes de Sprint 4)

Las historias **H, I y J** deben incorporarse antes de Sprint 4. H e I son dependientes entre sí (el campo type es necesario para la vista del sub_franchise_owner) y J puede avanzar en paralelo completando la integración de DocuSeal ya iniciada.

### Cuarta incorporación (antes de Sprint 5)

Las historias **K y M** son las más críticas de este grupo por sus implicaciones de seguridad y confidencialidad. Deben agregarse antes de Sprint 5. La Historia L (integración POS) puede planificarse para el mismo sprint o deferirse si hay restricciones de tiempo.

### Quinta incorporación (antes de Sprint 6)

Las historias **N, O, P y Q** pueden incorporarse al inicio de Sprint 6. N y O están relacionadas con el modelo de sub-franquicias y conviene implementarlas juntas. P y Q son mejoras de experiencia que tienen menor impacto en la operación crítica del sistema.

### Historia D (Vista global del superadmin)

Esta historia puede incorporarse en Sprint 2 junto con las historias de autenticación, aprovechando la base ya construida. No tiene dependencias externas más allá del listado de franquicias y empresas que ya existe en la base de datos.

---

*Documento generado el 15 de abril de 2026 por Aquiles Dev para el proyecto SM Portal — Strategic Mates.*
