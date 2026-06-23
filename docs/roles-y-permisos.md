# Gestión de Roles y Permisos — SM Portal

Documento técnico descriptivo. Estado del sistema a la fecha **2026-06-15**.
Describe cómo está modulado el control de acceso en backend (Laravel 12 / PHP 8.2+) y
frontend (React 18). Todas las rutas y números de línea fueron verificados contra el
código vigente.

---

## 1. Resumen ejecutivo

### Modelo de dos capas

El control de acceso de SM Portal se organiza en **dos capas complementarias**:

1. **Capa de Roles (Spatie Permissions).** Cada usuario tiene exactamente **un** rol Spatie
   (`web` guard). El rol es la identidad de autorización principal: lo consumen las *policies*
   del backend y los *route guards* del frontend. Los strings de rol están centralizados en la
   clase `App\Enums\Role` (constantes, no enum nativo, porque Spatie requiere strings planos).

2. **Capa de Permisos granulares por módulo (tabla propia `user_permissions`).** Es una tabla
   independiente de Spatie. Para cada usuario guarda una fila por módulo con dos flags booleanos:
   `can_read` y `can_write`. Define el acceso fino a los 9 módulos funcionales del portal
   (feed, contracts, repository, processes, accounting, inventory, tracking, catalog, calendar).

**Relación entre capas.** El rol **siembra** los permisos iniciales por módulo mediante
`UserPermission::syncForRole($userId, $role)` (se llama al crear/invitar/actualizar un usuario).
Después, esos permisos se pueden **ajustar individualmente** por usuario y por módulo con
`UserPermission::updateForUser($userId, $permissions[])`, sin tocar el rol Spatie. Es decir:
el rol da el punto de partida; los flags por módulo son el override fino persistente.

### Los 9 roles (alto nivel)

| Constante (`App\Enums\Role`) | String | Alcance / capacidad a alto nivel |
|---|---|---|
| `SUPERADMIN` | `superadmin` | Todo, sin restricción. Bypassa el middleware de módulo. No es invitable. |
| `SYSTEM_ADMIN` | `system_admin` | Global, lectura + escritura en todos los módulos. |
| `SYSTEM_ADMIN_READONLY` | `system_admin_readonly` | Global, solo lectura en todos los módulos. |
| `ADMIN_SM` | `admin_sm` | Acotado a su `sm_franchise_id`. Lectura + escritura. Sus módulos se respetan vía `can_read`. |
| `SB_OWNER` | `sb_owner` | Acotado a su `company_id`. Solo lectura por defecto (ajustable por módulo). |
| `SB_EMPLOYEE` | `sb_employee` | Acotado a su `company_id`, módulos habilitados. Solo lectura por defecto. |
| `BB_EMPLOYEE` | `bb_employee` | Inversor; empresas asignadas, solo lectura por defecto. |
| `SUB_FRANCHISE_OWNER` | `sub_franchise_owner` | Alcance de sub-franquicia. Solo lectura por defecto. |
| `SUB_FRANCHISE_ADMIN` | `sub_franchise_admin` | Alcance admin de sub-franquicia. Solo lectura por defecto. |

`Role::invitable()` retorna los 8 roles invitables (todos menos `SUPERADMIN`).

### Flujo de autenticación y enforcement (textual)

```
1. POST /api/v1/auth/login  (rate limit: 5/min por email+IP)
        │
        ▼
2. AuthService::login()  →  Auth::attempt()
        │   genera token Sanctum (createToken('auth_token'))
        │   carga rol Spatie:        $user->getRoleNames()->first()
        │   carga permisos módulo:   user_permissions (module, can_read, can_write)
        ▼
3. Respuesta JSON: { user, token, role, permissions }
        │
        ▼
4. Frontend (authStore, Zustand) persiste { user, token, role, permissions,
        isAuthenticated } en localStorage clave "sm-portal-auth".
        El token se inyecta en cada request via Authorization: Bearer <token>.
        │
        ├─────────────── ENFORCEMENT BACKEND ───────────────
        │   a) middleware auth:sanctum  (valida token)
        │   b) middleware module.permission:<modulo>  (EnsureModulePermission)
        │        - superadmin bypassa
        │        - GET requiere can_read; POST/PUT/PATCH/DELETE requiere can_write
        │   c) Policies (Gate) por modelo: roles + scoping por franchise/company
        │
        └─────────────── ENFORCEMENT FRONTEND ──────────────
            a) RoleRoute       → restringe rutas a ADMIN_ROLES (o lista explícita)
            b) ModuleRoute     → exige can_read del módulo (MODULE_BYPASS_ROLES omiten)
            c) Sidebar         → buildNavItems() muestra ítems según rol + can_read
            d) usePermissions  → canWrite(module) gatea botones crear/editar/eliminar
```

El frontend refresca `role` y `permissions` desde el servidor con `GET /api/v1/auth/me`
(`AuthService::me()`), que devuelve la misma forma `{ user, token: null, role, permissions }`.
El enforcement real es siempre del backend; el frontend solo oculta/gatea UI.

---

## 2. Anexo A — Capa de Roles (Spatie)

### A.1 Clase de constantes de rol

Archivo: `backend/app/Enums/Role.php`

- `final class Role` (líneas 10-54), constructor privado (línea 33) — no instanciable.
- 9 constantes de string (líneas 12-28):
  `superadmin`, `system_admin`, `system_admin_readonly`, `admin_sm`, `sb_owner`,
  `sb_employee`, `bb_employee`, `sub_franchise_owner`, `sub_franchise_admin`.
- `public static function invitable(): array` (líneas 41-53): retorna los 8 roles invitables
  mediante **lista explícita** (excluye `SUPERADMIN`). Un test de reflexión verifica que la lista
  sigue sincronizada con las constantes (ver Anexo E).

### A.2 Tablas y configuración de Spatie

- **Migración de tablas Spatie:** `backend/database/migrations/2026_04_14_021951_create_permission_tables.php`.
  Crea las tablas estándar de Spatie: `roles`, `permissions`, `model_has_roles`,
  `model_has_permissions`, `role_has_permissions`.
- **Configuración:** `backend/config/permission.php`.
  - `'teams' => false` (línea 138): no se usa el feature de teams.
  - Caché de permisos: `'expiration_time' => DateInterval::createFromDateString('24 hours')`
    (línea 190) y clave `'key' => 'spatie.permission.cache'` (línea 196).
  - Modelos: `Permission::class` y `Role::class` de Spatie (líneas 20, 31).

### A.3 Integración en el modelo User

Archivo: `backend/app/Models/User.php`

- Traits (línea 35): `use HasApiTokens, HasFactory, HasRoles, Notifiable, SoftDeletes;`
  - `HasApiTokens` (Sanctum, importado línea 16) — tokens de API.
  - `HasRoles` (Spatie, importado línea 17) — asignación y consulta de roles.
- Relación a la capa de permisos por módulo: `userPermissions(): HasMany` (línea 123).
- Relación de invitación: `invitedBy(): BelongsTo` (línea 133).

En todo el sistema **cada usuario tiene exactamente un rol**: el código lee el rol con
`$user->getRoleNames()->first()` (p. ej. `AuthService` líneas 51 y 89).

---

## 3. Anexo B — Capa de Permisos por módulo (`user_permissions`)

### B.1 Modelo

Archivo: `backend/app/Models/UserPermission.php`

- `protected $fillable = ['user_id', 'module', 'can_read', 'can_write']` (líneas 38-43).
- Casts booleanos para `can_read` y `can_write` (líneas 45-51).
- Relación `user(): BelongsTo` (líneas 57-60).

### B.2 Migración

Archivo: `backend/database/migrations/2026_04_13_000006_create_user_permissions_table.php`

Columnas de la tabla `user_permissions` (líneas 20-39):
- `id` (línea 21).
- `user_id` FK a `users` con `cascadeOnDelete()` (líneas 23-25).
- `module` string(30) con comentario descriptivo (líneas 27-29).
- `can_read` boolean default `false` (línea 31).
- `can_write` boolean default `false` (línea 32).
- `timestamps` (línea 34).
- Índice único `(user_id, module)` (línea 37) — una fila por usuario por módulo.
- Índice simple sobre `user_id` (línea 38).

### B.3 Lista canónica de módulos (`ALL_MODULES`)

`UserPermission::ALL_MODULES` (líneas 22-32) contiene **9 módulos vigentes**:

```
feed, contracts, repository, processes, accounting, inventory, tracking, catalog, calendar
```

**Nota descriptiva sobre la discrepancia documental.** El comentario PHPDoc de la migración
(`2026_04_13_000006_...`, líneas 9-29) menciona un enum `App\Enums\PermissionModule`, dice que
`inventory` fue eliminado y que se añadió `applications`. Ese comentario **no refleja el estado
vigente en runtime**: en el código en ejecución la fuente de verdad es la constante
`UserPermission::ALL_MODULES`, que **sí incluye `inventory` y NO incluye `applications`**.
No existe enum `PermissionModule` en uso. El test
`PermissionsCoverageTest::test_sync_for_role_modules_match_all_modules_constant` fija exactamente
estos 9 módulos (incluyendo `inventory`, sin `applications`). El frontend usa la misma lista de 9
módulos (`AdminPermissionsModal.jsx`, líneas 6-16). **Estado vigente: 9 módulos con `inventory`,
sin `applications`.**

### B.4 `syncForRole($userId, $role)` — siembra de permisos por rol

`UserPermission::syncForRole()` (líneas 100-112), envuelto en `DB::transaction`:

- Calcula `can_write` = `true` **solo** si el rol está en
  `[Role::SUPERADMIN, Role::SYSTEM_ADMIN, Role::ADMIN_SM]` (línea 103).
- Itera `ALL_MODULES` y hace `updateOrCreate` por cada módulo con
  `can_read = true` para todos, y `can_write` según lo anterior (líneas 105-110).
- Roles desconocidos / cualquier otro rol → `can_read = true`, `can_write = false`
  (es la rama segura por defecto; el test
  `test_sync_for_role_unknown_role_defaults_to_read_only` lo verifica).

Resumen del resultado de la siembra:

| Rol | can_read | can_write |
|---|---|---|
| `superadmin`, `system_admin`, `admin_sm` | true (todos los módulos) | **true** (todos) |
| `system_admin_readonly` | true | false |
| `sb_owner`, `sb_employee`, `bb_employee`, `sub_franchise_owner`, `sub_franchise_admin` | true | false |
| rol desconocido | true | false |

### B.5 `updateForUser($userId, $permissions[])` — override granular

`UserPermission::updateForUser()` (líneas 88-98), envuelto en `DB::transaction`:

- Recibe un arreglo de `{module, can_read, can_write}` y por cada entrada hace `updateOrCreate`
  sobre la clave `(user_id, module)` (líneas 90-97).
- Es el mecanismo de ajuste fino por usuario/módulo después de la siembra inicial.

### B.6 Invariante validada en FormRequests

`can_write = true` exige `can_read = true`. Se valida en los FormRequest de actualización de
permisos:

- `backend/app/Http/Requests/FranchiseAdmin/UpdateFranchiseAdminPermissionsRequest.php`,
  método `withValidator()` (líneas 26-38): añade un error si una entrada trae `can_write`
  verdadero con `can_read` falso.
- `backend/app/Http/Requests/FranchiseClient/UpdateFranchiseClientPermissionsRequest.php`
  (mismo patrón).

Además, `permissions.*.module` se valida con `Rule::in(UserPermission::ALL_MODULES)`
(línea 20 del request de admin), bloqueando inyección de módulos arbitrarios.

---

## 4. Anexo C — Enforcement en Backend

### C.1 Registro de Policies

Archivo: `backend/app/Providers/AppServiceProvider.php`, método `boot()` (líneas 52-61).
Se registran vía `Gate::policy()`:

```
BbAssignment      → BbAssignmentPolicy
Franchise         → FranchisePolicy
Company           → CompanyPolicy
User              → UserPolicy
Event             → EventPolicy
ProcessMap        → ProcessMapPolicy
ProcessCategory   → ProcessCategoryPolicy
Process           → ProcessPolicy
SubProcess        → SubProcessPolicy
SubSubProcess     → SubSubProcessPolicy
```

(`NewsArticlePolicy` existe en `backend/app/Policies/` y se usa por inferencia de modelo
Laravel; no requiere registro explícito en este provider.)

#### UserPolicy — `backend/app/Policies/UserPolicy.php`

| Método | Roles permitidos | Scoping por franchise |
|---|---|---|
| `viewAnySystemAdmin` (18) | `superadmin` | — (guardarraíl anti-escalada) |
| `createSystemAdmin` (23) | `superadmin` | — |
| `updateSystemAdmin` (28) | `superadmin` | — |
| `deleteSystemAdmin` (33) | `superadmin` | — |
| `inviteUsers` (41) | `superadmin`, `system_admin`, `system_admin_readonly`, `admin_sm` | — |
| `updateFranchiseAdmin` (48) | `superadmin`, `system_admin` (target debe ser `admin_sm`) | — |
| `deleteFranchiseAdmin` (57) | `superadmin`, `system_admin` (target `admin_sm`) | — |
| `restoreFranchiseAdmin` (66) | `superadmin`, `system_admin` | — |
| `updateFranchiseAdminPermissions` (71) | `superadmin`, `system_admin` (target `admin_sm`) | — |
| `viewFranchiseAdminPermissions` (85) | `superadmin`, `system_admin`, `system_admin_readonly` (target `admin_sm`) | — (lectura permite readonly) |
| `updateFranchiseClient` (96) | `superadmin`; `admin_sm` solo misma franquicia (target `sb_owner`/`bb_employee`) | sí (`sm_franchise_id`) |
| `deleteFranchiseClient` (110) | `superadmin`; `admin_sm` misma franquicia | sí |
| `restoreFranchiseClient` (124) | `superadmin`, `admin_sm` | — |
| `viewFranchiseClientPermissions` (129) | `superadmin`; `admin_sm` misma franquicia | sí |
| `updateFranchiseClientPermissions` (143) | `superadmin`; `admin_sm` misma franquicia | sí |
| `manageInvitation` (164) | `superadmin`/`system_admin` (cualquiera); `admin_sm` solo su franquicia | sí |

#### FranchisePolicy — `backend/app/Policies/FranchisePolicy.php`

| Método | Roles permitidos | Scoping |
|---|---|---|
| `viewAny` (15) | `superadmin`, `system_admin`, `system_admin_readonly`, `admin_sm` | — |
| `view` (29) | los 3 system/super siempre; `admin_sm` solo su franquicia | sí |
| `addMember` (43) | `superadmin`, `system_admin`; `admin_sm` su franquicia | sí |
| `create` (56) | `superadmin`, `system_admin` | — |
| `update` (65) | `superadmin`, `system_admin`; `admin_sm` su franquicia | sí |
| `toggleStatus` (79) | `superadmin`, `system_admin`; `admin_sm` su franquicia | sí |
| `delete` (92) | `superadmin`, `system_admin` | — |

#### CompanyPolicy — `backend/app/Policies/CompanyPolicy.php`

| Método | Roles permitidos | Scoping |
|---|---|---|
| `viewAny` (16) | `superadmin`, `system_admin`, `system_admin_readonly`, `admin_sm` | — |
| `view` (30) | 3 system/super siempre; `admin_sm` si la empresa es de su franquicia | sí (`sm_franchise_id`) |
| `create` (43) | `superadmin`, `system_admin`; `admin_sm` solo si tiene franquicia asignada | sí (Response::deny con mensaje) |
| `update` (63) | `superadmin`, `system_admin`; `admin_sm` su franquicia | sí |
| `delete` (76) | `superadmin`, `system_admin`; `admin_sm` su franquicia | sí |

#### Policies de mapas de procesos

Todas comparten el patrón: lectura (`canAccess`) permite `superadmin`/`system_admin`/
`system_admin_readonly` y `admin_sm` (acotado a la franquicia de la empresa del mapa);
escritura (`canWrite`) excluye `system_admin_readonly`. El scoping se resuelve subiendo la
cadena `subproceso → proceso → categoría → mapa → empresa → sm_franchise_id` con narrowing
`instanceof` en cada paso.

- `ProcessMapPolicy` — `backend/app/Policies/ProcessMapPolicy.php`:
  `viewAny` (16), `view` (33), `create($user, ?int $companyId)` (56), `delete` (85).
  `create` para `admin_sm` exige que la empresa pertenezca a su franquicia (líneas 62-75).
- `ProcessCategoryPolicy` — `backend/app/Policies/ProcessCategoryPolicy.php`:
  `view` (13), `update` (24). `canAccess` (45) / `canWrite` (60).
- `ProcessPolicy` — `backend/app/Policies/ProcessPolicy.php`:
  `view` (14), `create($user, ProcessCategory)` (25), `update` (36), `delete` (47).
  `canAccess` (81) / `canWrite` (96).
- `SubProcessPolicy` — `backend/app/Policies/SubProcessPolicy.php`:
  `view` (15), `create($user, Process)` (26), `update` (37), `delete` (48).
  `resolveMap` (59), `canAccess` (90), `canWrite` (105).
- `SubSubProcessPolicy` — `backend/app/Policies/SubSubProcessPolicy.php`:
  mismo patrón (`view`, `create`, `update`, `delete` + resolución de mapa y `canAccess`/`canWrite`).

#### EventPolicy — `backend/app/Policies/EventPolicy.php`

| Método | Lógica |
|---|---|
| `viewAny` (15) | todos los autenticados (`return true`) |
| `view` (24) | por `visibility`: `public` → todos; `franchise` → misma franquicia que el creador; `private` → creador o `superadmin`/`system_admin` |
| `create` (40) | `superadmin`/`system_admin` siempre; resto requiere `sm_franchise_id` no nulo (la escritura del módulo `calendar` ya la valida el middleware antes) |
| `update` (56) | creador, `superadmin` o `system_admin` |
| `delete` (65) | creador, `superadmin` o `system_admin` |

#### BbAssignmentPolicy — `backend/app/Policies/BbAssignmentPolicy.php`

| Método | Roles permitidos | Scoping |
|---|---|---|
| `create` (16) | `superadmin`, `system_admin`; `admin_sm` con franquicia asignada | sí (en el servicio) |
| `delete` (37) | `superadmin`, `system_admin`; `admin_sm` si la empresa relacionada es de su franquicia | sí |

#### NewsArticlePolicy — `backend/app/Policies/NewsArticlePolicy.php`

| Método | Roles permitidos |
|---|---|
| `viewAny` (11) | `superadmin`, `system_admin`, `system_admin_readonly`, `admin_sm` |
| `fetchAny` (16) | `superadmin`, `system_admin`, `admin_sm` |
| `publish` (21) | `superadmin`, `system_admin`, `admin_sm` |
| `reject` (26) | `superadmin`, `system_admin`, `admin_sm` |

### C.2 Middleware `module.permission`

Archivo: `backend/app/Http/Middleware/EnsureModulePermission.php`

- Alias de uso: `Route::middleware('module.permission:<modulo>')`.
- `WRITE_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE']` (línea 17).
- `superadmin` bypassa completamente (líneas 33-35).
- Para el resto: busca la fila `user_permissions` del módulo (líneas 38-40); si no existe o
  `can_read` es falso → **403 JSON** (líneas 43-49).
- En métodos de escritura, además exige `can_write`; si falta → **403 JSON** (líneas 52-58).

Módulos protegidos por este middleware en `routes/api.php`:
`events` → `calendar` (línea 112); grupo `feed` → `feed` (línea 155);
grupo `news` → `feed` (línea 168).

### C.3 Rate limiters

Definidos en `backend/app/Providers/AppServiceProvider.php`, `boot()`:

- `login`: 5/min por `email + IP` (líneas 90-92).
- `invitation`: 10/min por IP (líneas 96-98).
- `api`: 120/min por usuario autenticado (o IP si anónimo) (líneas 103-105).

### C.4 Servicios que sincronizan permisos

Todos en `backend/app/Services/`. Cada vez que se crea/actualiza el rol de un usuario, se
re-siembra la capa de módulos con `UserPermission::syncForRole()`:

- **`SystemAdminService`** (`SystemAdminService.php`):
  `create()` → `assignRole` + `syncForRole` (líneas 40-41);
  `update()` → `syncRoles` + `syncForRole` (líneas 77-78);
  `delete()` → borra filas de `user_permissions` y soft-delete del usuario (líneas 104-107).
- **`FranchiseAdminService`** (`FranchiseAdminService.php`):
  `updatePermissions()` → `UserPermission::updateForUser()` (líneas 111-114);
  `getPermissions()` → mapper plano `{module, can_read, can_write}` (líneas 97-104).
- **`FranchiseClientService`** (`FranchiseClientService.php`):
  `updatePermissions()` → `updateForUser()` (líneas 136-139);
  `getPermissions()` (líneas 122-129).
- **`InvitationService`** (`InvitationService.php`):
  `send()` → `assignRole` + `syncForRole` para el nuevo usuario (líneas 166-167);
  `accept()` devuelve `role` + `permissions` (leídos de `user_permissions`) junto al token Sanctum
  (líneas 260-273).
- **`AuthService`** (`AuthService.php`):
  `login()` (líneas 21-68) y `me()` (líneas 75-104) leen `role` + `permissions` para el frontend.

### C.5 Endpoints de gestión de roles/permisos

Fuente: `backend/routes/api.php` (todas bajo `auth:sanctum` + `throttle:api`).

| Método | Ruta | Controller@método | Policy / FormRequest | Notas |
|---|---|---|---|---|
| GET | `system-admins` | `SystemAdminController@index` | `viewAnySystemAdmin` | solo `superadmin` |
| POST | `system-admins` | `SystemAdminController@store` | `createSystemAdmin` + `StoreSystemAdminRequest` | crea + `syncForRole` |
| PUT/PATCH | `system-admins/{id}` | `SystemAdminController@update` | `updateSystemAdmin` | re-asigna rol + `syncForRole` |
| DELETE | `system-admins/{id}` | `SystemAdminController@destroy` | `deleteSystemAdmin` | borra permisos + soft-delete |
| GET | `franchises/{f}/admins/{u}/permissions` | `FranchiseAdminController@permissions` | `viewFranchiseAdminPermissions` | (ruta línea 84) |
| PUT | `franchises/{f}/admins/{u}/permissions` | `FranchiseAdminController@updatePermissions` | `updateFranchiseAdminPermissions` + `UpdateFranchiseAdminPermissionsRequest` | (línea 85) |
| GET | `franchises/{f}/clients/{u}/permissions` | `FranchiseClientController@permissions` | `viewFranchiseClientPermissions` | (línea 94) |
| PUT | `franchises/{f}/clients/{u}/permissions` | `FranchiseClientController@updatePermissions` | `updateFranchiseClientPermissions` + `UpdateFranchiseClientPermissionsRequest` | (línea 95) |
| GET | `invitations` | `InvitationController@index` | `inviteUsers` (vía controller/servicio) | (línea 141) |
| POST | `invitations` | `InvitationController@store` | `inviteUsers` + FormRequest de invitación | (línea 142) |
| POST | `invitations/{user}/resend` | `InvitationController@resend` | `manageInvitation` | (línea 143) |
| DELETE | `invitations/{user}` | `InvitationController@destroy` | `manageInvitation` | (línea 144) |

`FranchiseAdminController` (`backend/app/Http/Controllers/Api/FranchiseAdminController.php`)
ejecuta el patrón de doble verificación: `$this->authorize(<policy>, $user)` +
`ensureBelongsToFranchise()` (líneas 187-196), que devuelve 404 por pertenencia y 403 por rol.
`SystemAdminController` autoriza con `viewAnySystemAdmin`/`createSystemAdmin`/`updateSystemAdmin`/
`deleteSystemAdmin` (líneas 62, 111, 173, 218).

---

## 5. Anexo D — Consumo en Frontend

### D.1 Store de autenticación

Archivo: `frontend/src/store/authStore.js`

- Store Zustand con `persist`, clave de localStorage `'sm-portal-auth'` (línea 31).
- Estado persistido (`partialize`, líneas 34-40): `user`, `token`, `role`, `permissions`,
  `isAuthenticated`.
- `setAuth({ user, token, role, permissions })` (líneas 14-22) fija el estado tras login.
- El token se expone al cliente Axios vía `setTokenGetter` (línea 47) evitando import circular.

### D.2 Hook `usePermissions`

Archivo: `frontend/src/hooks/usePermissions.js`

- Construye un mapa `module → permiso` desde `permissions` (líneas 14-17).
- `isReadonly` = `role === 'system_admin_readonly'` (línea 19).
- `canWrite(module)` (líneas 21-25): `superadmin`/`system_admin` → siempre true;
  `system_admin_readonly` → false; resto → `permMap[module]?.can_write === true`.
- Expone `{ canWrite, isReadonly, role }` (línea 27).

### D.3 Route guards

Archivo: `frontend/src/App.jsx`

- `ADMIN_ROLES = ['superadmin', 'system_admin', 'system_admin_readonly', 'admin_sm']` (línea 47).
- `RoleRoute` (líneas 54-60): renderiza children solo si el rol está en la lista; si no,
  redirige a `/`. Protege: `/franchises`, `/franchises/:id`, `/companies`, `/users`
  (con `ADMIN_ROLES`), `/system-admins` (lista `['superadmin']`), y envuelve `/catalog` y
  `/sb-applications`.
- `MODULE_BYPASS_ROLES = ['superadmin', 'system_admin', 'system_admin_readonly']` (línea 69).
- `ModuleRoute` (líneas 76-82): los roles de bypass pasan siempre; el resto requiere
  `can_read === true` del módulo, o redirige a `/`. Protege: `/feed` (`feed`),
  `/contracts` (`contracts`), `/repository` (`repository`), `/processes` y sub-rutas
  (`processes`), `/accounting` (`accounting`), `/inventory` (`inventory`),
  `/tracking` (`tracking`), `/catalog` (`catalog`, anidado en `RoleRoute`), `/calendar` (`calendar`).

### D.4 Sidebar

Archivo: `frontend/src/components/Sidebar.jsx`

- `buildNavItems(role, permissions)` (líneas 17-47):
  - `adminRoles` para ítems administrativos (línea 18).
  - `alwaysHasModuleAccess = ['superadmin', 'system_admin', 'system_admin_readonly']`
    (línea 22): estos roles ven todos los módulos sin mirar `can_read`.
  - `hasModule(module)` (línea 26): `alwaysHasModuleAccess || canRead(module)`.
    Es decir, `admin_sm` (y roles cliente) deben tener `can_read = true` para ver el ítem.
  - El mapa `SHOW` (líneas 28-44) decide visibilidad de cada ítem: administrativos por
    `isAdmin`, `system_admins` solo `superadmin`, y los 9 módulos por `hasModule`.

### D.5 UI de gestión de permisos

- `frontend/src/pages/franchises/AdminPermissionsModal.jsx`: matriz **módulo × nivel** con 3
  niveles `no_access` / `read_only` / `read_write` (líneas 18-30). Lista local de 9 módulos
  (líneas 6-16, coincide con `ALL_MODULES`). `permLevel()` mapea flags a nivel; `fromLevel()`
  reconstruye `{can_read, can_write}` (read_write→ambos true, read_only→read true, no_access→ambos
  false). Sirve tanto a admins como a clientes según `memberType` (líneas 43-45). Al guardar envía
  el arreglo completo de 9 módulos (líneas 69-72).
- `frontend/src/pages/franchises/FranchiseDetailPage.jsx`: página contenedora que abre el modal
  de permisos para admins/clientes de la franquicia.
- `frontend/src/pages/users/InvitationsPage.jsx`: gestión de invitaciones; calcula
  `canWrite = role === 'superadmin' || 'system_admin' || 'admin_sm'` (línea 86) y usa
  `invitationsApi` para listar/reenviar/revocar (líneas 106, 131, 152).

### D.6 Capa API

- `frontend/src/api/franchises.js`:
  - `getAdminPermissions(franchiseId, userId)` → `GET /franchises/{f}/admins/{u}/permissions`
    (líneas 71-72).
  - `updateAdminPermissions(franchiseId, userId, permissions)` →
    `PUT /franchises/{f}/admins/{u}/permissions` con body `{ permissions }` (líneas 74-75).
  - `getClientPermissions(...)` → `GET /franchises/{f}/clients/{u}/permissions` (líneas 91-92).
  - `updateClientPermissions(...)` → `PUT /franchises/{f}/clients/{u}/permissions` (líneas 94-95).
- `frontend/src/api/auth.js`:
  - `login(email, password)` → `POST /auth/login`, retorna `{ user, token, role, permissions }`
    (líneas 8-12).
  - `getMe()` → `GET /auth/me`, retorna `{ user, token, role, permissions }` (líneas 26-29).
  - `logout()` → `POST /auth/logout` (líneas 16-18).

---

## 6. Anexo E — Tests de cobertura

### E.1 `PermissionsCoverageTest`

Archivo: `backend/tests/Feature/PermissionsCoverageTest.php` (usa `RefreshDatabase`, SQLite
en memoria; `setUp` crea los 9 roles Spatie, líneas 27-40).

- **`syncForRole()` por cada rol** (líneas 58-194): un test por rol que verifica el conteo de 9
  filas y el valor esperado de `can_read`/`can_write`:
  - `superadmin`, `system_admin`, `admin_sm` → read+write en los 9 (líneas 58-92).
  - `system_admin_readonly` → solo lectura (líneas 94-104).
  - `sb_owner`, `sb_employee`, `bb_employee`, `sub_franchise_owner`, `sub_franchise_admin`,
    y rol desconocido → solo lectura (líneas 106-170).
  - Idempotencia y upgrade readonly→admin (líneas 172-194).
  - `test_sync_for_role_modules_match_all_modules_constant` (líneas 196-200): fija exactamente
    los 9 módulos `['feed','contracts','repository','processes','accounting','inventory',
    'tracking','catalog','calendar']`.
- **Feed read/write para system_admin vs readonly** (líneas 206-255):
  `system_admin` puede crear post (201) y leer (200); `system_admin_readonly` no puede crear
  (403) pero sí leer (200) — valida el middleware `module.permission:feed`.
- **Invitations por rol** (líneas 261-334): `superadmin`, `system_admin`,
  `system_admin_readonly`, `admin_sm` pueden listar (200); `sb_owner`, `sb_employee`,
  `bb_employee` no (403); anónimo (401).

### E.2 `InvitationTest` — sincronía de `Role::invitable()`

Archivo: `backend/tests/Feature/InvitationTest.php`

- `test_role_invitable_excludes_superadmin` (líneas 1440-1443): verifica que `SUPERADMIN`
  no esté en `Role::invitable()`.
- `test_role_invitable_covers_all_non_superadmin_constants` (líneas 1445-1457): usa reflexión
  sobre `Role::class` para comprobar que `invitable()` contiene **todas** las constantes menos
  `SUPERADMIN`. Si se añade una nueva constante de rol sin actualizar `invitable()`, este test
  falla de inmediato en CI.
