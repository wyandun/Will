"""
SM Portal — Linear Import Script DEFINITIVO v3
===============================================
Importa el backlog completo al workspace de Linear con la jerarquía correcta:
    Sprints (Cycles) → Módulos (Projects) → Historias de Usuario (Issues)

Reglas aplicadas:
  - Módulos creados como Projects (projectCreate), NO como Issues padre.
  - Issues vinculados al proyecto vía projectId.
  - Títulos de issues: máximo 6-7 palabras (historia en description).
  - Sprints nombrados "Sprint N" (sin subtítulo).
  - Sin asignaciones a Giuliana, Nicolas ni Wilson (equipo unipersonal: Aquiles).
  - Historia de notificaciones del Sprint 6 EXCLUIDA (scope creep).
  - Relaciones de bloqueo entre issues clave vía issueRelationCreate(type: "blocks").

Uso:
    pip install requests
    python linear_import_final.py

⚠ IMPORTANTE: Regenerar la API key en Linear después de ejecutar este script.
"""

import requests
import time

# ─────────────────────────────────────────────────────────────────────────────
# CONFIGURACIÓN
# ─────────────────────────────────────────────────────────────────────────────

API_KEY = "lin_api_tElh3IJZFhzdRBwS34JHpKk9sWTJxE2A8UpTr0Da"
API_URL = "https://api.linear.app/graphql"

HEADERS = {
    "Authorization": API_KEY,
    "Content-Type": "application/json",
}

# ─────────────────────────────────────────────────────────────────────────────
# GRAPHQL CLIENT
# ─────────────────────────────────────────────────────────────────────────────

def gql(query, variables=None):
    payload = {"query": query}
    if variables:
        payload["variables"] = variables
    res = requests.post(API_URL, headers=HEADERS, json=payload)
    data = res.json()
    if "errors" in data:
        print(f"  ⚠  GraphQL error: {data['errors']}")
    return data


# ─────────────────────────────────────────────────────────────────────────────
# 1. OBTENER TEAM ID Y WORKSPACE ID
# ─────────────────────────────────────────────────────────────────────────────

def get_team_and_org():
    query = """
    query {
        teams {
            nodes { id name }
        }
        organization {
            id name
        }
    }
    """
    data = gql(query)
    teams = data["data"]["teams"]["nodes"]
    if not teams:
        print("❌ No se encontraron equipos. Crear el equipo 'SM-Portal' en Linear primero.")
        exit(1)
    team = teams[0]
    org_id = data["data"]["organization"]["id"]
    print(f"✅ Team: {team['name']} ({team['id']})")
    print(f"✅ Org:  {data['data']['organization']['name']} ({org_id})")
    return team["id"], org_id


# ─────────────────────────────────────────────────────────────────────────────
# 2. OBTENER WORKFLOW STATES
# ─────────────────────────────────────────────────────────────────────────────

def get_states(team_id):
    query = """
    query($teamId: ID!) {
        workflowStates(filter: { team: { id: { eq: $teamId } } }) {
            nodes { id name type }
        }
    }
    """
    data = gql(query, {"teamId": team_id})
    states = data["data"]["workflowStates"]["nodes"]
    state_map = {}
    for s in states:
        t = s["type"].lower()
        if t == "completed" and "done" not in state_map:
            state_map["done"] = s["id"]
        elif t == "started" and "in_progress" not in state_map:
            state_map["in_progress"] = s["id"]
        elif t == "unstarted" and "todo" not in state_map:
            state_map["todo"] = s["id"]
        elif t == "backlog" and "backlog" not in state_map:
            state_map["backlog"] = s["id"]
    print(f"✅ States encontrados: {list(state_map.keys())}")
    return state_map


# ─────────────────────────────────────────────────────────────────────────────
# 3. CREAR CYCLE (SPRINT)
# ─────────────────────────────────────────────────────────────────────────────

def create_cycle(team_id, name, start_date, end_date):
    mutation = """
    mutation($teamId: String!, $name: String!, $description: String!, $startsAt: DateTime!, $endsAt: DateTime!) {
        cycleCreate(input: {
            teamId: $teamId
            name: $name
            description: $description
            startsAt: $startsAt
            endsAt: $endsAt
        }) {
            success
            cycle { id name }
        }
    }
    """
    data = gql(mutation, {
        "teamId": team_id,
        "name": name,
        "description": name,
        "startsAt": f"{start_date}T00:00:00.000Z",
        "endsAt":   f"{end_date}T23:59:59.000Z",
    })
    cycle = data.get("data", {}).get("cycleCreate", {}).get("cycle")
    if not cycle:
        print(f"  ❌ No se pudo crear el cycle '{name}'")
        return None
    print(f"  ✅ Cycle creado: {cycle['name']} ({cycle['id']})")
    return cycle["id"]


# ─────────────────────────────────────────────────────────────────────────────
# 4. CREAR PROJECT (MÓDULO)
# ─────────────────────────────────────────────────────────────────────────────

def create_project(team_id, name, description):
    mutation = """
    mutation($teamIds: [String!]!, $name: String!, $description: String) {
        projectCreate(input: {
            teamIds: $teamIds
            name: $name
            description: $description
        }) {
            success
            project { id name }
        }
    }
    """
    # Linear limita description de proyectos a 255 caracteres
    desc = (description or "")
    if len(desc) > 252:
        desc = desc[:252] + "..."

    res = gql(mutation, {
        "teamIds": [team_id],
        "name": name,
        "description": desc,
    })
    time.sleep(0.4)
    if res is None or res.get("data") is None:
        print(f"    ❌ Error creando proyecto '{name[:70]}' (respuesta vacía)")
        return None
    project = res["data"].get("projectCreate", {}).get("project")
    if not project:
        print(f"    ❌ Error creando proyecto '{name[:70]}'")
        return None
    print(f"  📦 Proyecto creado: {project['name'][:80]} ({project['id']})")
    return project["id"]


# ─────────────────────────────────────────────────────────────────────────────
# 5. CREAR ISSUE (HISTORIA DE USUARIO)
# ─────────────────────────────────────────────────────────────────────────────

def create_issue(team_id, title, description, state_id, cycle_id, project_id=None):
    mutation = """
    mutation(
        $teamId: String!,
        $title: String!,
        $description: String,
        $stateId: String!,
        $cycleId: String,
        $projectId: String
    ) {
        issueCreate(input: {
            teamId: $teamId
            title: $title
            description: $description
            stateId: $stateId
            cycleId: $cycleId
            projectId: $projectId
        }) {
            success
            issue { id title identifier }
        }
    }
    """
    data = gql(mutation, {
        "teamId":      team_id,
        "title":       title,
        "description": description or "",
        "stateId":     state_id,
        "cycleId":     cycle_id,
        "projectId":   project_id,
    })
    time.sleep(0.35)
    issue = data.get("data", {}).get("issueCreate", {}).get("issue")
    if not issue:
        print(f"      ❌ Error creando issue '{title[:70]}'")
        return None
    print(f"      ✅ {issue['identifier']}: {issue['title'][:90]}")
    return issue["id"]


# ─────────────────────────────────────────────────────────────────────────────
# 6. CREAR RELACIÓN DE BLOQUEO
# ─────────────────────────────────────────────────────────────────────────────

def create_blocking_relation(blocker_id, blocked_id, blocker_title, blocked_title):
    mutation = """
    mutation($issueId: String!, $relatedIssueId: String!, $type: IssueRelationType!) {
        issueRelationCreate(input: {
            issueId: $issueId
            relatedIssueId: $relatedIssueId
            type: $type
        }) {
            success
        }
    }
    """
    data = gql(mutation, {
        "issueId":        blocker_id,
        "relatedIssueId": blocked_id,
        "type":           "blocks",
    })
    time.sleep(0.35)
    success = data.get("data", {}).get("issueRelationCreate", {}).get("success", False)
    if success:
        print(f"  ✅ BLOCKS: '{blocker_title[:55]}' → '{blocked_title[:55]}'")
    else:
        print(f"  ❌ Falló: '{blocker_title[:55]}' → '{blocked_title[:55]}'")
    return success


# ─────────────────────────────────────────────────────────────────────────────
# 7. DATOS DEL BACKLOG COMPLETO
#
# Jerarquía:
#   sprint (cycle)
#     └── module (project)
#           └── stories (issues)
#
# Cada story tiene:
#   - title:       Texto corto (máximo 6-7 palabras), ESCANEABLE
#   - description: Historia narrativa completa + detalles técnicos
#
# Sin menciones a Giuliana, Nicolas ni Wilson.
# ─────────────────────────────────────────────────────────────────────────────

SPRINTS = [

    # ══════════════════════════════════════════════════════════
    # SPRINT 1 — Bootstrap e Infraestructura
    # ══════════════════════════════════════════════════════════
    {
        "name":  "Sprint 1",
        "start": "2026-04-14",
        "end":   "2026-04-27",
        "done":  False,
        "modules": [
            {
                "title": "Levantar el entorno de desarrollo con Docker",
                "description": (
                    "Configurar Docker Compose con todos los servicios necesarios para que el "
                    "desarrollador pueda trabajar sin instalar dependencias manualmente.\n\n"
                    "Servicios: PostgreSQL 16, Redis 7, Laravel (PHP-FPM), Nginx, Queue Worker, "
                    "DocuSeal y Adminer.\n\n"
                    "**Responsable:** Aquiles"
                ),
                "stories": [
                    {
                        "title": "Entorno Docker completo con un comando",
                        "description": (
                            "**Como desarrollador, quiero levantar todo el entorno con un solo comando "
                            "para que pueda trabajar sin configuración manual.**\n\n"
                            "Implementar `docker-compose.yml` con los 7 servicios: PostgreSQL 16, Redis 7, "
                            "Laravel (PHP-FPM), Nginx, Queue Worker, DocuSeal y Adminer.\n"
                            "Incluir `Dockerfile` con `entrypoint.sh` que ajusta permisos automáticamente.\n"
                            "Nginx configurado con named location `@php` para PHP-FPM.\n\n"
                            "**Branch:** `infra/docker-setup`\n"
                            "**Responsable:** Aquiles"
                        ),
                    },
                    {
                        "title": "Migraciones del schema completo v4",
                        "description": (
                            "**Como desarrollador, quiero tener todas las tablas de la base de datos "
                            "creadas con migraciones para tener un schema versionado y reproducible.**\n\n"
                            "Crear las 28 migraciones del schema v4: users, companies, franchises, "
                            "process_maps, user_permissions y todas las tablas de módulos.\n\n"
                            "**Branch:** `infra/migrations`\n"
                            "**Responsable:** Aquiles"
                        ),
                    },
                ],
            },
        ],
    },

    # ══════════════════════════════════════════════════════════
    # SPRINT 2 — Auth, Login, Permisos y Franquicias
    # ══════════════════════════════════════════════════════════
    {
        "name":  "Sprint 2",
        "start": "2026-04-28",
        "end":   "2026-05-11",
        "done":  False,
        "modules": [
            {
                "title": "Construir el módulo de Login y Autenticación",
                "description": (
                    "Implementar el sistema completo de autenticación: backend con Laravel Sanctum + "
                    "Spatie Permissions, y frontend con la pantalla de login, store de sesión y "
                    "sidebar dinámico por rol.\n\n"
                    "**Responsable:** Aquiles"
                ),
                "stories": [
                    {
                        "title": "Login y logout con email y contraseña",
                        "description": (
                            "**Como usuario, quiero iniciar y cerrar sesión con mi email y contraseña "
                            "para acceder al portal de forma segura.**\n\n"
                            "Backend: `POST /api/v1/auth/login` con Sanctum tokens y rate limiting "
                            "(5 intentos/minuto). `POST /api/v1/auth/logout` que revoca el token.\n\n"
                            "Frontend: `LoginPage` con formulario, validación de errores y mensajes claros.\n\n"
                            "**Branch:** `feature/auth-login`\n"
                            "**Responsable:** Aquiles"
                        ),
                    },
                    {
                        "title": "Persistencia de sesión entre recargas",
                        "description": (
                            "**Como usuario autenticado, quiero que el sistema recuerde mi sesión al "
                            "recargar la página para no tener que volver a iniciar sesión.**\n\n"
                            "Backend: `GET /api/v1/auth/me` que verifica el token y retorna usuario con "
                            "roles y permisos.\n\n"
                            "Frontend: `authStore` (Zustand + localStorage). Hook `useAuthVerify` que "
                            "verifica el token al montar la app y redirige a `/login` si hay error 401.\n\n"
                            "**Branch:** `feature/auth-session`\n"
                            "**Responsable:** Aquiles"
                        ),
                    },
                    {
                        "title": "Sidebar dinámico filtrado por rol",
                        "description": (
                            "**Como usuario autenticado, quiero ver en el sidebar solo los módulos a los "
                            "que tengo acceso según mi rol para navegar el portal sin confusión.**\n\n"
                            "Backend: middleware `EnsureModulePermission` que valida permisos por módulo. "
                            "Seeder con los 7 roles: superadmin, admin_sm, sb_owner, sb_employee, bb, "
                            "sub_franchise_owner, sub_franchise_admin.\n\n"
                            "Frontend: `AuthenticatedLayout` con sidebar dinámico filtrado por rol y permisos. "
                            "`ProtectedRoute` y `PublicRoute`.\n\n"
                            "**Branch:** `feature/auth-permissions`\n"
                            "**Responsable:** Aquiles"
                        ),
                    },
                    {
                        "title": "Invitación de usuario por email",
                        "description": (
                            "**Como admin_sm o superadmin, quiero invitar a un nuevo usuario por enlace "
                            "enviado a su email para que él mismo configure su contraseña al primer acceso.**\n\n"
                            "El flujo actual expone la contraseña del usuario al administrador (riesgo de "
                            "seguridad) y no escala en un entorno B2B con múltiples actores.\n\n"
                            "**Alcance:**\n"
                            "- Backend: endpoint `POST /api/v1/users/{id}/invite` que genera token firmado y envía email\n"
                            "- Backend: endpoint público `POST /api/v1/auth/accept-invite` que valida token y setea contraseña\n"
                            "- Frontend: página pública `/accept-invite?token=...` con formulario de contraseña\n\n"
                            "**Branch:** `feature/auth-invite`\n"
                            "**Responsable:** Aquiles"
                        ),
                    },
                    {
                        "title": "Recuperación de contraseña por email",
                        "description": (
                            "**Como usuario, quiero recuperar mi contraseña solicitando un enlace de "
                            "reseteo a mi email para no quedar bloqueado si olvido mis credenciales.**\n\n"
                            "Sin esta funcionalidad, cualquier usuario que olvide su contraseña queda "
                            "bloqueado permanentemente hasta que un administrador intervenga de forma manual.\n\n"
                            "**Alcance:**\n"
                            "- Backend: `POST /api/v1/auth/forgot-password` — envía email con link de reset\n"
                            "- Backend: `POST /api/v1/auth/reset-password` — valida token y actualiza contraseña\n"
                            "- Frontend: página `/forgot-password` y `/reset-password?token=...`\n\n"
                            "**Branch:** `feature/auth-password-reset`\n"
                            "**Responsable:** Aquiles"
                        ),
                    },
                    {
                        "title": "Seed formal de los 7 roles Spatie",
                        "description": (
                            "**Como sistema, quiero tener los 7 roles exactos creados formalmente con "
                            "Spatie Permissions para que todas las funcionalidades de control de acceso "
                            "funcionen correctamente.**\n\n"
                            "El documento define 7 roles exactos: `superadmin`, `admin_sm`, `sb_owner`, "
                            "`sb_employee`, `bb`, `sub_franchise_owner`, `sub_franchise_admin`.\n\n"
                            "Sin el seed formal de los 7 roles, las historias de acceso diferenciado (BB "
                            "read-only, sub_franchise_owner, sb_employee) no pueden implementarse ni probarse.\n\n"
                            "**Alcance:**\n"
                            "- Seeder `RolesAndPermissionsSeeder` con los 7 roles Spatie\n"
                            "- Permisos por defecto para BB: read en `accounting` y `contracts` de su empresa\n"
                            "- Permisos por defecto para sub_franchise_owner: read/write en módulos asignados\n\n"
                            "**Branch:** `feature/auth-roles-seed`\n"
                            "**Responsable:** Aquiles"
                        ),
                    },
                ],
            },

            {
                "title": "Construir el módulo de Franquicias y Empresas (Módulo 04)",
                "description": (
                    "Permitir al superadmin y admin_sm gestionar las franquicias SM y las empresas "
                    "cliente (SBs), incluyendo el flujo de Close Deal que configura automáticamente "
                    "todo el portal del nuevo cliente.\n\n"
                    "**Responsable:** Aquiles"
                ),
                "stories": [
                    {
                        "title": "CRUD de franquicias SM por región",
                        "description": (
                            "**Como superadmin o admin_sm, quiero crear y administrar las franquicias SM "
                            "para organizar la red de operaciones por región.**\n\n"
                            "CRUD `GET/POST/PUT/DELETE /api/v1/franchises` con `FranchisePolicy` "
                            "(admin_sm solo opera en su franquicia), soft deletes y paginación.\n\n"
                            "Frontend: `FranchisesPage` con tabla y modal de crear/editar/eliminar.\n\n"
                            "**Branch:** `feature/franchises`\n"
                            "**Responsable:** Aquiles"
                        ),
                    },
                    {
                        "title": "Registro base de Empresas cliente (SBs)",
                        "description": (
                            "**Como admin_sm, quiero registrar una nueva empresa cliente y ejecutar el "
                            "Close Deal para que el sistema configure automáticamente su portal, usuario "
                            "y mapas de proceso.**\n\n"
                            "CRUD `GET/POST/PUT/DELETE /api/v1/companies`. El método `closeDeal()` "
                            "ejecuta una transacción de DB que crea: la empresa, el usuario sb_owner, "
                            "la asignación del Business Bishop y los 2 process_maps "
                            "(`franquiciadora` y `franquiciada`). Soft deletes.\n\n"
                            "Frontend: `CompaniesPage` con tabla y modal de crear/editar/eliminar.\n\n"
                            "**Branch:** `feature/companies-close-deal`\n"
                            "**Responsable:** Aquiles"
                        ),
                    },
                ],
            },
        ],
    },

    # ══════════════════════════════════════════════════════════
    # SPRINT 3 — Assessments, Usuarios y Dashboard
    # ══════════════════════════════════════════════════════════
    {
        "name":  "Sprint 3",
        "start": "2026-05-12",
        "end":   "2026-05-25",
        "done":  False,
        "modules": [
            {
                "title": "Construir el módulo de Postulaciones Públicas — Assessments (Módulo 03)",
                "description": (
                    "Crear los formularios públicos de evaluación para negocios y Business Bishops, "
                    "el sistema de scoring automático y el flujo de Close Deal desde el assessment.\n\n"
                    "**Responsable:** Aquiles"
                ),
                "stories": [
                    {
                        "title": "Formulario público de postulación de SB",
                        "description": (
                            "**Como dueño de un pequeño negocio, quiero completar el formulario de "
                            "evaluación desde la web sin necesidad de tener una cuenta para postularme "
                            "al programa de Strategic Mates.**\n\n"
                            "Endpoints públicos: `POST /api/v1/public/assessments/sb-1` "
                            "(63 preguntas, 9 dimensiones A–I) y `POST /api/v1/public/assessments/sb-2`.\n"
                            "Migraciones: `assessment_contacts`, `assessment_decisions` + seeder de decisiones.\n\n"
                            "Frontend: páginas públicas sin sidebar `/assessment/1` y `/assessment/2`.\n\n"
                            "**Branch:** `feature/assessment-sb`\n"
                            "**Responsable:** Aquiles"
                        ),
                    },
                    {
                        "title": "Formulario público de postulación de BB",
                        "description": (
                            "**Como inversor interesado en ser Business Bishop, quiero enviar mi "
                            "postulación desde la web para que Strategic Mates evalúe mi perfil.**\n\n"
                            "`POST /api/v1/public/assessments/bb-application` con los campos: "
                            "datos personales, capacidad de inversión, experiencia, industrias de interés "
                            "y disponibilidad.\n\n"
                            "Frontend: página pública `/apply/business-bishop` sin sidebar.\n\n"
                            "**Branch:** `feature/assessment-bb`\n"
                            "**Responsable:** Aquiles"
                        ),
                    },
                    {
                        "title": "Inbox de assessments con scoring por dimensión",
                        "description": (
                            "**Como admin_sm, quiero ver el listado de postulaciones con el puntaje "
                            "por dimensión para identificar a los mejores candidatos y tomar una decisión.**\n\n"
                            "Servicio de scoring: cálculo de puntaje por dimensión (A–I) a partir de "
                            "las 63 respuestas.\n\n"
                            "Frontend: `AssessmentInboxPage` con tabla de contactos y puntajes. "
                            "`AssessmentDetailPage` con el detalle completo de cada postulación.\n\n"
                            "**Branch:** `feature/assessment-inbox`\n"
                            "**Responsable:** Aquiles"
                        ),
                    },
                    {
                        "title": "Close Deal atómico desde el assessment",
                        "description": (
                            "**Como admin_sm, quiero ejecutar el Close Deal desde el assessment para "
                            "que el sistema cree automáticamente el portal de la empresa, el usuario "
                            "owner y sus mapas de proceso.**\n\n"
                            "Endpoint protegido `POST /api/v1/assessment-contacts/{id}/close-deal` que "
                            "en una transacción crea: Company, User sb_owner, BbAssignment, los 2 "
                            "process_maps y despacha el job `SendInvitationEmail`.\n\n"
                            "Frontend: modal 'Close Deal' en `AssessmentDetailPage`.\n\n"
                            "**Branch:** `feature/assessment-close-deal`\n"
                            "**Responsable:** Aquiles"
                        ),
                    },
                    {
                        "title": "Transacción atómica del Close Deal completa",
                        "description": (
                            "**Como admin_sm, quiero que al ejecutar el Close Deal desde un assessment "
                            "se cree automáticamente la empresa, el usuario sb_owner, el vínculo con el "
                            "BB y los 2 mapas de proceso en una sola transacción.**\n\n"
                            "Los issues de Close Deal anteriores abordan partes del flujo, pero ninguno "
                            "garantiza explícitamente la creación atómica de los 2 mapas de proceso junto "
                            "con el resto.\n\n"
                            "**Alcance:**\n"
                            "- Endpoint `POST /api/v1/assessment-contacts/{id}/close-deal`\n"
                            "- Transacción DB: Company + User (sb_owner) + BbAssignment + ProcessMap ×2\n"
                            "- Job `SendInvitationEmail` para el nuevo sb_owner\n"
                            "- Campo `converted_company_id` en `assessment_contacts`\n\n"
                            "**Branch:** `feature/assessment-close-deal-atomic`\n"
                            "**Responsable:** Aquiles"
                        ),
                    },
                    {
                        "title": "Catálogo formal de decisiones de assessment",
                        "description": (
                            "**Como admin_sm, quiero registrar la decisión sobre un assessment eligiendo "
                            "de un catálogo formal para mantener trazabilidad consistente en todos los casos.**\n\n"
                            "Con un varchar libre, distintos admins pueden registrar la misma decisión con "
                            "texto diferente ('cerrado', 'Cerrado', 'close deal'), haciendo imposible "
                            "filtrar y reportar el estado real del pipeline de assessments.\n\n"
                            "**Alcance:**\n"
                            "- Migración: columna `decision_id` FK en `assessment_contacts` (ya existe como migración)\n"
                            "- Seeder: catálogo con decisiones: `pending_review`, `accepted`, `rejected`, `close_deal`, `waiting`\n"
                            "- Frontend: dropdown con opciones del catálogo en lugar de campo de texto libre\n\n"
                            "**Branch:** `feature/assessment-decisions-catalog`\n"
                            "**Responsable:** Aquiles"
                        ),
                    },
                    {
                        "title": "Link a empresa convertida desde el assessment",
                        "description": (
                            "**Como admin_sm, quiero ver en el detalle de un assessment a qué empresa "
                            "fue convertido para rastrear de dónde vino cada cliente del portal.**\n\n"
                            "Sin este vínculo no hay trazabilidad entre el formulario público que llenó "
                            "el negocio y el portal que se le creó, perdiendo el historial del origen de "
                            "cada cliente.\n\n"
                            "**Alcance:**\n"
                            "- Campo `converted_company_id` FK en `assessment_contacts` (se llena al ejecutar Close Deal)\n"
                            "- Frontend: badge 'Convertido' con link a la empresa en `AssessmentDetailPage`\n\n"
                            "**Branch:** `feature/assessment-converted-link`\n"
                            "**Responsable:** Aquiles"
                        ),
                    },
                ],
            },

            {
                "title": "Construir el módulo de Usuarios y Permisos (Módulo 05)",
                "description": (
                    "Permitir a superadmin y admin_sm gestionar los usuarios del portal, asignar roles "
                    "y configurar qué módulos puede ver cada persona.\n\n"
                    "**Responsable:** Aquiles"
                ),
                "stories": [
                    {
                        "title": "Listado de usuarios con scope por franquicia",
                        "description": (
                            "**Como admin_sm, quiero ver el listado de usuarios de mi franquicia para "
                            "saber quién tiene acceso al portal y con qué permisos.**\n\n"
                            "`GET /api/v1/users` con paginación y scope por rol: superadmin ve todos, "
                            "admin_sm ve solo los de su franquicia. `UserPolicy` con la lógica de scope.\n\n"
                            "Frontend: `UsersPage` con tabla y filtros de rol, empresa y franquicia.\n\n"
                            "**Branch:** `feature/users-list`\n"
                            "**Responsable:** Aquiles"
                        ),
                    },
                    {
                        "title": "CRUD de usuarios con asignación de rol",
                        "description": (
                            "**Como admin_sm, quiero crear y editar usuarios asignándoles un rol para "
                            "que puedan acceder al portal con los permisos correctos desde el primer día.**\n\n"
                            "`POST /api/v1/users`, `PUT /api/v1/users/{id}` y "
                            "`DELETE /api/v1/users/{id}` (soft delete).\n\n"
                            "Frontend: `UserFormPage` / modal con selección de rol.\n\n"
                            "**Branch:** `feature/users-crud`\n"
                            "**Responsable:** Aquiles"
                        ),
                    },
                    {
                        "title": "Editor de permisos granulares por módulo",
                        "description": (
                            "**Como admin_sm, quiero activar o desactivar módulos individuales para cada "
                            "usuario para personalizar el acceso sin cambiar su rol.**\n\n"
                            "`PUT /api/v1/users/{id}/permissions` que actualiza registros en la tabla "
                            "`user_permissions` (una fila por permiso, no JSON en el modelo User).\n\n"
                            "Frontend: `PermissionsEditor` con toggles can_read/can_write por módulo.\n\n"
                            "**Branch:** `feature/users-permissions`\n"
                            "**Responsable:** Aquiles"
                        ),
                    },
                    {
                        "title": "Restricciones de acceso para sb_employee",
                        "description": (
                            "**Como sb_employee, quiero acceder solo a los módulos que mi sb_owner me "
                            "habilitó para no ver información fuera de mis responsabilidades.**\n\n"
                            "El rol `sb_employee` existe en el sistema pero sin esta historia no tiene "
                            "ningún comportamiento diferenciado — ve todo igual que un `sb_owner`, lo cual "
                            "viola la definición del rol.\n\n"
                            "**Alcance:**\n"
                            "- El middleware `EnsureModulePermission` ya existe — verificar que aplica para `sb_employee`\n"
                            "- El sidebar filtra módulos usando `user_permissions` — verificar que `sb_employee` respeta el filtro\n"
                            "- Tests de integración: `sb_employee` sin permiso de Contabilidad recibe 403 en esos endpoints\n\n"
                            "**Branch:** `feature/permissions-sb-employee`\n"
                            "**Responsable:** Aquiles"
                        ),
                    },
                    {
                        "title": "Vista global de franquicias para superadmin",
                        "description": (
                            "**Como superadmin, quiero ver el listado global de todas las franquicias SM "
                            "con sus empresas activas para supervisar la operación completa de la red.**\n\n"
                            "Sin una vista global, el superadmin no tiene forma de supervisar el estado de "
                            "todas las franquicias y sus empresas en un solo lugar.\n\n"
                            "**Alcance:**\n"
                            "- Backend: `GET /api/v1/dashboard/summary` extendido con métricas globales para superadmin\n"
                            "- Frontend: sección del Dashboard para superadmin con conteo de franquicias, empresas, "
                            "assessments pendientes y contratos activos\n\n"
                            "**Branch:** `feature/dashboard-superadmin`\n"
                            "**Responsable:** Aquiles"
                        ),
                    },
                ],
            },

            {
                "title": "Construir el Dashboard Principal (Módulo 02)",
                "description": (
                    "Crear la página de inicio personalizada que muestra a cada usuario las métricas "
                    "y accesos rápidos relevantes para su rol.\n\n"
                    "**Responsable:** Aquiles"
                ),
                "stories": [
                    {
                        "title": "Dashboard con métricas por rol",
                        "description": (
                            "**Como usuario autenticado, quiero ver un dashboard con la información más "
                            "importante de mi rol al entrar al portal para tener visibilidad del estado "
                            "del negocio de un vistazo.**\n\n"
                            "Backend: `GET /api/v1/dashboard/summary` con métricas por rol: empresas "
                            "activas, contratos pendientes, próximos eventos, tareas de tracking.\n\n"
                            "Frontend: `DashboardPage` con widgets de resumen adaptados por rol.\n\n"
                            "**Branch:** `feature/dashboard`\n"
                            "**Responsable:** Aquiles"
                        ),
                    },
                ],
            },
        ],
    },

    # ══════════════════════════════════════════════════════════
    # SPRINT 4 — BPMN, Repositorio y Contratos
    # ══════════════════════════════════════════════════════════
    {
        "name":  "Sprint 4",
        "start": "2026-05-26",
        "end":   "2026-06-08",
        "done":  False,
        "modules": [
            {
                "title": "Construir el módulo de Mapas de Proceso BPMN (Módulo 09)",
                "description": (
                    "Permitir a las empresas documentar sus procesos operativos con diagramas BPMN "
                    "interactivos, organizados en una jerarquía de categorías y con traducción "
                    "automática español/inglés.\n\n"
                    "**Responsable:** Aquiles"
                ),
                "stories": [
                    {
                        "title": "Navegación de categorías y procesos BPMN",
                        "description": (
                            "**Como sb_owner, quiero ver los dos mapas de proceso de mi empresa y "
                            "navegar su estructura de categorías y procesos para entender cómo está "
                            "organizada operativamente.**\n\n"
                            "Migraciones: `process_categories`, `processes`, `sub_processes`, `process_documents`. "
                            "Seeder con categorías base y procesos estándar.\n\n"
                            "CRUD `/api/v1/process-maps/{mapId}/categories` y "
                            "`/api/v1/process-maps/{mapId}/processes`.\n\n"
                            "Frontend: `ProcessMapsPage` con selector franquiciadora/franquiciada y árbol "
                            "de navegación categorías → procesos → sub-procesos.\n\n"
                            "**Branch:** `feature/bpmn-structure`\n"
                            "**Responsable:** Aquiles"
                        ),
                    },
                    {
                        "title": "Editor BPMN embebido con bpmn-js",
                        "description": (
                            "**Como sb_owner o admin_sm, quiero editar el diagrama BPMN de un "
                            "sub-proceso directamente en el portal para documentar los flujos operativos "
                            "sin herramientas externas.**\n\n"
                            "CRUD `/api/v1/processes/{id}/sub-processes` y "
                            "`PUT /api/v1/sub-processes/{id}/bpmn` que guarda `bpmn_xml_es` o `bpmn_xml_en`.\n\n"
                            "Frontend: `BpmnEditor` integrado con bpmn-js. Switcher de idioma ES/EN.\n\n"
                            "**Branch:** `feature/bpmn-editor`\n"
                            "**Responsable:** Aquiles"
                        ),
                    },
                    {
                        "title": "Traducción automática ES/EN de diagramas BPMN",
                        "description": (
                            "**Como sb_owner, quiero que al guardar un diagrama en español el sistema "
                            "lo traduzca automáticamente al inglés para tener siempre ambas versiones "
                            "actualizadas sin trabajo extra.**\n\n"
                            "Job `TranslateBpmnXml` que usa OpenAI para traducir el XML BPMN ES↔EN "
                            "automáticamente. Se encola después de cada guardado.\n\n"
                            "**Branch:** `feature/bpmn-translation`\n"
                            "**Responsable:** Aquiles"
                        ),
                    },
                    {
                        "title": "Campo type en process_maps (franquiciadora/franquiciada)",
                        "description": (
                            "**Como sistema, quiero que cada mapa de proceso tenga un campo type "
                            "(franquiciadora o franquiciada) para que los roles correctos accedan al "
                            "mapa que les corresponde.**\n\n"
                            "El documento lista como faltante: campo `type` en `process_maps` "
                            "(franquiciadora vs franquiciada) — hoy todos los mapas son iguales.\n\n"
                            "Sin este campo no hay forma de distinguir los dos mapas ni de filtrar el "
                            "acceso por rol.\n\n"
                            "**Alcance:**\n"
                            "- Verificar que la migración de `process_maps` incluye columna `type`\n"
                            "- Seed de los 2 mapas con type correcto al ejecutar Close Deal\n"
                            "- `ProcessMapPolicy`: `sb_owner` ve ambos, `sub_franchise_owner` solo ve `franquiciada`\n\n"
                            "**Branch:** `feature/bpmn-map-type`\n"
                            "**Responsable:** Aquiles"
                        ),
                    },
                    {
                        "title": "Vista de mapa franquiciada para sub_franchise_owner",
                        "description": (
                            "**Como sub_franchise_owner, quiero ver únicamente el mapa de proceso tipo "
                            "franquiciada de la empresa SB a la que pertenezco para seguir la operación "
                            "estandarizada de la franquicia.**\n\n"
                            "El documento define: 'sub_franchise_owner: Ve el mapa de procesos tipo "
                            "franquiciada, repositorio, contabilidad e inventario de su franquicia.' "
                            "Y en los items faltantes: 'Falta: vista del SB para ingresar al mapa de "
                            "su sub-franquicia.'\n\n"
                            "Sin esta historia el rol `sub_franchise_owner` no puede ver los procesos "
                            "operativos que debe seguir, que es la razón de ser de su acceso al portal.\n\n"
                            "**Alcance:**\n"
                            "- `ProcessMapPolicy`: `sub_franchise_owner` solo puede hacer GET del mapa `franquiciada`\n"
                            "- Frontend: `ProcessMapsPage` filtra el selector de mapa según el rol del usuario\n\n"
                            "**Branch:** `feature/bpmn-subfran-view`\n"
                            "**Responsable:** Aquiles"
                        ),
                    },
                ],
            },

            {
                "title": "Construir el módulo de Repositorio de Documentos (Módulo 08)",
                "description": (
                    "Crear la biblioteca digital de cada empresa donde se almacenan, organizan y "
                    "versionan todos sus documentos importantes de forma privada y segura.\n\n"
                    "**Responsable:** Aquiles"
                ),
                "stories": [
                    {
                        "title": "Subida y organización de documentos por categoría",
                        "description": (
                            "**Como sb_owner o sb_employee, quiero subir documentos al repositorio de "
                            "mi empresa y organizarlos por categoría para tener todos los archivos "
                            "importantes centralizados.**\n\n"
                            "Migraciones: `repositories`, `repository_documents`.\n\n"
                            "`GET/POST /api/v1/companies/{id}/repository` — listar y subir documentos. "
                            "Almacenamiento en `Storage::disk('private')`.\n\n"
                            "Frontend: `RepositoryPage` con tabs setup/proceso, filtro por categoría y "
                            "componente drag-and-drop para carga de archivos.\n\n"
                            "**Branch:** `feature/repository`\n"
                            "**Responsable:** Aquiles"
                        ),
                    },
                    {
                        "title": "Versionado de documentos con historial",
                        "description": (
                            "**Como sb_owner, quiero subir una nueva versión de un documento existente "
                            "para mantener el historial de cambios sin perder los archivos anteriores.**\n\n"
                            "`PUT /api/v1/repository-documents/{id}` crea nueva versión con `parent_id` + "
                            "`is_current`. URL de descarga temporal vía `Storage::temporaryUrl()`.\n\n"
                            "Frontend: historial de versiones visible en `RepositoryPage`.\n\n"
                            "**Branch:** `feature/repository-versioning`\n"
                            "**Responsable:** Aquiles"
                        ),
                    },
                ],
            },

            {
                "title": "Construir el módulo de Contratos con Firma Electrónica (Módulo 07)",
                "description": (
                    "Gestionar los contratos de cada empresa con firma electrónica a través de DocuSeal, "
                    "con el flujo formal de 3 firmantes de Strategic Mates.\n\n"
                    "**Responsable:** Aquiles"
                ),
                "stories": [
                    {
                        "title": "Creación y envío de contratos a DocuSeal",
                        "description": (
                            "**Como admin_sm, quiero crear contratos, asignar los tres firmantes y "
                            "enviarlos a firma electrónica para formalizar los acuerdos con las "
                            "empresas cliente.**\n\n"
                            "Migración: `contracts`.\n"
                            "CRUD `GET/POST /api/v1/companies/{id}/contracts` y "
                            "`PUT/DELETE /api/v1/contracts/{id}`.\n"
                            "`DocusealService` con 3 firmantes: Elaborado por, Revisado por, Aprobado por.\n"
                            "`POST /api/v1/contracts/{id}/send-for-signature`.\n\n"
                            "Frontend: `ContractsPage` con badges de estado, "
                            "`ContractFormPage` con selección de plantilla y 3 firmantes.\n\n"
                            "**Branch:** `feature/contracts`\n"
                            "**Responsable:** Aquiles"
                        ),
                    },
                    {
                        "title": "Actualización de estado via webhook DocuSeal",
                        "description": (
                            "**Como admin_sm, quiero que el estado del contrato se actualice "
                            "automáticamente cuando alguien firma para no tener que hacerlo manualmente.**\n\n"
                            "`POST /api/v1/webhooks/docuseal` recibe eventos de DocuSeal y actualiza el "
                            "estado. Job `SyncDocusealSignatureStatus` como respaldo ante webhooks fallidos.\n\n"
                            "**Branch:** `feature/contracts-webhook`\n"
                            "**Responsable:** Aquiles"
                        ),
                    },
                    {
                        "title": "Vista de contratos solo lectura para BB",
                        "description": (
                            "**Como Business Bishop, quiero ver los contratos de la empresa que "
                            "patrocino en modo solo lectura para mantenerme informado sin poder "
                            "modificarlos.**\n\n"
                            "Filtro en el controlador de contratos: rol `bb` solo puede hacer GET "
                            "de los contratos de su empresa.\n\n"
                            "Frontend: badge y vista de solo lectura para rol bb.\n\n"
                            "**Branch:** `feature/contracts-bb-readonly`\n"
                            "**Responsable:** Aquiles"
                        ),
                    },
                    {
                        "title": "Flujo de 3 firmantes Elaborado/Revisado/Aprobado",
                        "description": (
                            "**Como admin_sm, quiero asignar explícitamente a los firmantes como "
                            "Elaborado por, Revisado por y Aprobado por al crear un contrato para "
                            "cumplir con el flujo formal de Strategic Mates.**\n\n"
                            "El documento establece: 'Los contratos tienen tres firmantes en el proceso "
                            "formal de SM: Elaborado por, Revisado por y Aprobado por. Cada uno con su "
                            "propio link de firma a través de DocuSeal.'\n\n"
                            "Sin esta historia los contratos pueden enviarse sin los 3 roles asignados, "
                            "rompiendo el proceso de aprobación formal de SM.\n\n"
                            "**Alcance:**\n"
                            "- `DocusealService`: crear submission con 3 submitters etiquetados\n"
                            "- Frontend: 3 campos de firmante con labels 'Elaborado por', 'Revisado por', 'Aprobado por'\n"
                            "- Validación: no se puede enviar a firma sin los 3 firmantes asignados\n\n"
                            "**Branch:** `feature/contracts-3-signers`\n"
                            "**Responsable:** Aquiles"
                        ),
                    },
                    {
                        "title": "Scope de BB restringido a su empresa",
                        "description": (
                            "**Como Business Bishop, quiero ver únicamente los contratos y contabilidad "
                            "de la empresa que patrocino, sin acceso a información de otras empresas "
                            "del sistema.**\n\n"
                            "La restricción es doble: (1) solo puede ver contabilidad y contratos, y "
                            "(2) solo de su empresa, no de otras aunque estén en la misma franquicia.\n\n"
                            "Sin esta historia, un BB podría ver datos financieros y contractuales de "
                            "empresas que no patrocina, violando la confidencialidad del sistema.\n\n"
                            "**Alcance:**\n"
                            "- `ContractPolicy` y controlador de contabilidad: scope a `bb_assignments.bb_user_id = auth()->id()`\n"
                            "- Verificar que los issues de contratos y contabilidad BB-readonly implementan este scope, no solo el read-only\n\n"
                            "**Branch:** `feature/bb-scope-enforcement`\n"
                            "**Responsable:** Aquiles"
                        ),
                    },
                ],
            },
        ],
    },

    # ══════════════════════════════════════════════════════════
    # SPRINT 5 — Contabilidad IA, Inventario y Feed
    # ══════════════════════════════════════════════════════════
    {
        "name":  "Sprint 5",
        "start": "2026-06-09",
        "end":   "2026-06-22",
        "done":  False,
        "modules": [
            {
                "title": "Construir el módulo de Contabilidad y Finanzas con IA (Módulo 10)",
                "description": (
                    "Implementar el ciclo contable completo: plan de cuentas, procesamiento de "
                    "documentos con OCR + OpenAI, asientos de doble partida con revisión humana "
                    "y conciliación bancaria.\n\n"
                    "**Responsable:** Aquiles"
                ),
                "stories": [
                    {
                        "title": "OCR + IA para extracción de datos contables",
                        "description": (
                            "**Como sb_owner, quiero subir una factura o estado de cuenta y que el "
                            "sistema extraiga automáticamente los datos contables para reducir el "
                            "ingreso manual.**\n\n"
                            "Migraciones: `chart_of_accounts`, `financial_documents`, `journal_entries`, "
                            "`journal_entry_lines`, `bank_transactions`, `pos_connections`.\n"
                            "Seeder: plan de cuentas US-GAAP simplificado por empresa.\n\n"
                            "Pipeline IA: `OcrService` (Tesseract) → `OpenAIService.extractTransactions()` "
                            "→ Job `ProcessFinancialDocument` en cola `ai-processing`.\n\n"
                            "Frontend: `FinancialDocumentsPage` con drop zone y estado de procesamiento IA.\n\n"
                            "**Branch:** `feature/accounting-ocr`\n"
                            "**Responsable:** Aquiles"
                        ),
                    },
                    {
                        "title": "Revisión y aprobación de asientos con baja confianza",
                        "description": (
                            "**Como sb_owner o contador, quiero revisar y aprobar los asientos contables "
                            "generados por la IA cuando la confianza es baja para garantizar la exactitud "
                            "de los registros.**\n\n"
                            "`AccountingService.buildJournalEntry()` genera el asiento de doble partida.\n"
                            "Regla: si `ai_confidence < 0.70` → `status='pending_review'`.\n"
                            "`POST /api/v1/journal-entries/{id}/approve`.\n\n"
                            "Frontend: `JournalEntriesPage` con filtro por estado y botón de aprobación. "
                            "`AccountingDashboard` con alertas de pendientes.\n\n"
                            "**Branch:** `feature/accounting-journal`\n"
                            "**Responsable:** Aquiles"
                        ),
                    },
                    {
                        "title": "Conciliación bancaria con matching automático",
                        "description": (
                            "**Como sb_owner, quiero ver qué transacciones bancarias están conciliadas "
                            "y cuáles faltan para mantener los libros contables al día.**\n\n"
                            "`BankReconciliationService.match()` con `match_confidence`.\n"
                            "`GET /api/v1/companies/{id}/bank-transactions` y "
                            "`POST /api/v1/bank-transactions/{id}/reconcile`.\n"
                            "Plan de cuentas: `GET/POST /api/v1/companies/{id}/chart-of-accounts`.\n\n"
                            "Frontend: `BankReconciliationPage` con matching visual. `ChartOfAccountsPage`.\n\n"
                            "**Branch:** `feature/accounting-reconciliation`\n"
                            "**Responsable:** Aquiles"
                        ),
                    },
                    {
                        "title": "Contabilidad en solo lectura para Business Bishop",
                        "description": (
                            "**Como Business Bishop, quiero ver la contabilidad de la empresa que "
                            "patrocino en modo solo lectura para hacer seguimiento de mi inversión.**\n\n"
                            "Filtro BB en todos los endpoints de contabilidad: solo GET de su empresa.\n\n"
                            "Frontend: badge de solo lectura en todas las vistas contables para rol bb.\n\n"
                            "**Branch:** `feature/accounting-bb-readonly`\n"
                            "**Responsable:** Aquiles"
                        ),
                    },
                    {
                        "title": "Integración POS (Square, Stripe, Shopify, Clover)",
                        "description": (
                            "**Como sb_owner, quiero conectar mi sistema POS (Square, Stripe, Shopify, "
                            "Clover, WooCommerce) para que las ventas se importen automáticamente a "
                            "la contabilidad.**\n\n"
                            "El portal se conecta con sistemas POS via OAuth para importar transacciones "
                            "de ventas automáticamente. Estas se concilian también con los movimientos bancarios.\n\n"
                            "Sin esta integración el sb_owner debe ingresar las ventas manualmente, "
                            "eliminando uno de los beneficios clave del módulo de contabilidad automatizada.\n\n"
                            "**Alcance:**\n"
                            "- `PosConnectionService` con OAuth flow por proveedor\n"
                            "- Job `ImportPosTransactions` en cola `integrations`\n"
                            "- Tabla `pos_connections` ya existe en el schema\n"
                            "- Frontend: `PosIntegrationsPage` con lista de proveedores y estado de conexión\n\n"
                            "**Branch:** `feature/accounting-pos`\n"
                            "**Responsable:** Aquiles"
                        ),
                    },
                ],
            },

            {
                "title": "Construir el módulo de Inventario (Módulo 11)",
                "description": (
                    "Control de stock integrado con la contabilidad: cada movimiento de inventario "
                    "genera automáticamente el asiento contable correspondiente.\n\n"
                    "**Responsable:** Aquiles"
                ),
                "stories": [
                    {
                        "title": "CRUD de productos con SKU y stock mínimo",
                        "description": (
                            "**Como sb_owner o sb_employee, quiero registrar los productos de mi "
                            "inventario con SKU, precios y stock mínimo para llevar el control desde "
                            "el portal.**\n\n"
                            "Migraciones: `inventory_items`, `inventory_movements`.\n"
                            "CRUD `GET/POST/PUT/DELETE /api/v1/companies/{id}/inventory-items`.\n\n"
                            "Frontend: `InventoryPage` con tabla de ítems y stock actual. "
                            "`InventoryItemFormPage` para crear/editar.\n\n"
                            "**Branch:** `feature/inventory-items`\n"
                            "**Responsable:** Aquiles"
                        ),
                    },
                    {
                        "title": "Movimientos de inventario con asiento automático",
                        "description": (
                            "**Como sb_owner, quiero registrar entradas y salidas de inventario y que "
                            "el sistema genere el asiento contable correspondiente de forma automática.**\n\n"
                            "`POST /api/v1/inventory-items/{id}/movements` registra el movimiento y "
                            "dispara la creación automática del `journal_entry`.\n"
                            "`GET /api/v1/inventory-items/{id}/movements` — historial.\n\n"
                            "Frontend: `MovementHistoryPage` con historial de entradas/salidas.\n\n"
                            "**Branch:** `feature/inventory-movements`\n"
                            "**Responsable:** Aquiles"
                        ),
                    },
                ],
            },

            {
                "title": "Construir el módulo de Feed Interno (Módulo 06)",
                "description": (
                    "Canal de comunicación interna del portal donde los admins publican novedades "
                    "y los usuarios pueden reaccionar y comentar.\n\n"
                    "**Responsable:** Aquiles"
                ),
                "stories": [
                    {
                        "title": "Publicación de anuncios en el feed interno",
                        "description": (
                            "**Como admin_sm o sb_owner, quiero publicar anuncios en el feed de mi "
                            "empresa o franquicia para mantener al equipo informado desde el portal.**\n\n"
                            "Migraciones: `posts`, `post_interactions` (like/comment/share en una tabla "
                            "con campo `type`).\n"
                            "`GET/POST /api/v1/feed` con filtros de visibilidad. "
                            "`PUT/DELETE /api/v1/posts/{id}`. Upload de archivo adjunto al post.\n\n"
                            "**Branch:** `feature/feed-posts`\n"
                            "**Responsable:** Aquiles"
                        ),
                    },
                    {
                        "title": "Reacciones y comentarios en publicaciones del feed",
                        "description": (
                            "**Como usuario autenticado, quiero reaccionar y comentar en las "
                            "publicaciones del feed para interactuar con el contenido de mi equipo.**\n\n"
                            "`POST /api/v1/posts/{id}/interactions` registra like, comment o share.\n\n"
                            "Frontend: `FeedPage` con timeline, compositor de posts, reacciones y comentarios.\n\n"
                            "**Branch:** `feature/feed-interactions`\n"
                            "**Responsable:** Aquiles"
                        ),
                    },
                ],
            },
        ],
    },

    # ══════════════════════════════════════════════════════════
    # SPRINT 6 — Catálogo, Tracking, Calendario, Perfil y Deploy
    # ══════════════════════════════════════════════════════════
    {
        "name":  "Sprint 6",
        "start": "2026-06-23",
        "end":   "2026-07-06",
        "done":  False,
        "modules": [
            {
                "title": "Construir el módulo de Catálogo de Servicios SM (Módulo 13)",
                "description": (
                    "Crear el catálogo jerárquico de servicios de Strategic Mates "
                    "(Bundle → Service → Deliverable), solo gestionable por el superadmin.\n\n"
                    "**Responsable:** Aquiles"
                ),
                "stories": [
                    {
                        "title": "Catálogo jerárquico Bundle → Service → Deliverable",
                        "description": (
                            "**Como superadmin, quiero gestionar el catálogo de servicios en estructura "
                            "jerárquica de bundles, servicios y entregables para organizar la oferta "
                            "comercial de Strategic Mates.**\n\n"
                            "Migración: `catalog_items` con campos `level` (bundle/service/deliverable) "
                            "y `parent_id`.\n"
                            "CRUD `GET/POST/PUT/DELETE /api/v1/catalog-items` solo para `superadmin`.\n\n"
                            "Frontend: `ServiceCatalogPage` con árbol bundle → service → deliverable.\n\n"
                            "**Branch:** `feature/catalog`\n"
                            "**Responsable:** Aquiles"
                        ),
                    },
                    {
                        "title": "Creación de sub-franquicias dentro de una empresa SB",
                        "description": (
                            "**Como admin_sm, quiero crear sub-franquicias dentro de una empresa SB "
                            "existente para que el sistema modele la expansión del negocio del cliente.**\n\n"
                            "El documento describe: 'Sub-Franquicias (las franquicias que abre el SB). "
                            "El SB es el franquiciador. Sus franquiciados ven el mapa de procesos tipo "
                            "franquiciada y tienen su propio portal.'\n\n"
                            "Sin esta historia el sistema no puede modelar el crecimiento de los clientes "
                            "SB que abren sus propias franquicias, que es el objetivo final del programa de SM.\n\n"
                            "**Alcance:**\n"
                            "- Endpoint `POST /api/v1/companies/{id}/sub-franchises`\n"
                            "- Crear usuario `sub_franchise_owner` vinculado a la sub-franquicia\n"
                            "- La sub-franquicia hereda el mapa `franquiciada` de su empresa SB padre\n\n"
                            "**Branch:** `feature/sub-franchises`\n"
                            "**Responsable:** Aquiles"
                        ),
                    },
                    {
                        "title": "Gestión de usuarios para sub_franchise_admin",
                        "description": (
                            "**Como sub_franchise_admin, quiero administrar los usuarios de mi "
                            "sub-franquicia de forma independiente para gestionar mi propio equipo "
                            "operativo.**\n\n"
                            "El documento define: 'sub_franchise_admin: Admin de una sub-franquicia. "
                            "Apoya la gestión operativa de la sub-franquicia con acceso similar al owner.'\n\n"
                            "Sin esta historia el rol `sub_franchise_admin` existe en el sistema pero "
                            "no tiene ninguna funcionalidad asociada — no puede gestionar usuarios ni "
                            "operaciones de su franquicia.\n\n"
                            "**Alcance:**\n"
                            "- `UserPolicy`: `sub_franchise_admin` puede crear/editar usuarios de su sub-franquicia\n"
                            "- Frontend: acceso a `UsersPage` filtrado por `sub_franchise_id`\n\n"
                            "**Branch:** `feature/sub-franchise-admin`\n"
                            "**Responsable:** Aquiles"
                        ),
                    },
                ],
            },

            {
                "title": "Construir el módulo de Tracking de Entregables (Módulo 12)",
                "description": (
                    "Seguimiento del avance de los servicios contratados por cada empresa cliente, "
                    "con vista kanban y cronograma.\n\n"
                    "**Responsable:** Aquiles"
                ),
                "stories": [
                    {
                        "title": "Tablero kanban de entregables por empresa",
                        "description": (
                            "**Como admin_sm o sb_owner, quiero ver el avance de los entregables "
                            "contratados por cada empresa para hacer seguimiento del progreso de "
                            "forma visual.**\n\n"
                            "Migración: `client_trackings`.\n"
                            "CRUD `GET/POST/PUT/DELETE /api/v1/companies/{id}/trackings`.\n\n"
                            "Frontend: `TrackingPage` con tablero kanban por estado.\n\n"
                            "**Branch:** `feature/tracking`\n"
                            "**Responsable:** Aquiles"
                        ),
                    },
                ],
            },

            {
                "title": "Construir el módulo de Calendario de Eventos (Módulo 14)",
                "description": (
                    "Calendario personal por usuario con vistas múltiples y posibilidad de "
                    "compartir eventos con otros miembros del equipo.\n\n"
                    "**Responsable:** Aquiles"
                ),
                "stories": [
                    {
                        "title": "Creación y gestión de eventos con FullCalendar",
                        "description": (
                            "**Como usuario autenticado, quiero crear y gestionar eventos en el "
                            "calendario del portal para coordinar reuniones y actividades con mi equipo.**\n\n"
                            "Migraciones: `events`, `event_shares`.\n"
                            "CRUD `GET/POST/PUT/DELETE /api/v1/events` con scope por rol.\n"
                            "`POST /api/v1/events/{id}/share` y `DELETE /api/v1/events/{id}/share/{userId}`.\n\n"
                            "Frontend: `CalendarPage` con FullCalendar (vistas mes/semana/día), "
                            "modal de crear/editar y UI para compartir con otros usuarios.\n\n"
                            "**Branch:** `feature/calendar`\n"
                            "**Responsable:** Aquiles"
                        ),
                    },
                ],
            },

            {
                "title": "Construir el módulo de Perfil de Usuario (Módulo 15)",
                "description": (
                    "Permitir a cada usuario gestionar su información personal, foto de perfil "
                    "y contraseña desde el portal.\n\n"
                    "**Responsable:** Aquiles"
                ),
                "stories": [
                    {
                        "title": "Edición de datos personales y avatar",
                        "description": (
                            "**Como usuario autenticado, quiero actualizar mis datos personales y foto "
                            "de perfil para mantener mi información al día en el portal.**\n\n"
                            "`PUT /api/v1/auth/me` (nombre, email) y "
                            "`POST /api/v1/auth/avatar` (subir foto de perfil).\n\n"
                            "Frontend: `ProfilePage` con formulario de datos y upload de avatar.\n\n"
                            "**Branch:** `feature/profile`\n"
                            "**Responsable:** Aquiles"
                        ),
                    },
                    {
                        "title": "Cambio de contraseña desde el perfil",
                        "description": (
                            "**Como usuario autenticado, quiero cambiar mi contraseña desde el perfil "
                            "para mantener la seguridad de mi cuenta.**\n\n"
                            "`PUT /api/v1/auth/password` — requiere contraseña actual + nueva + confirmación.\n\n"
                            "Frontend: sección de cambio de contraseña en `ProfilePage`.\n\n"
                            "**Branch:** `feature/profile-password`\n"
                            "**Responsable:** Aquiles"
                        ),
                    },
                ],
            },

            {
                "title": "QA Final y Despliegue en Producción",
                "description": (
                    "Revisión de código de todos los módulos, tests de humo sobre los flujos "
                    "críticos y despliegue del stack completo en VPS Hostinger.\n\n"
                    "**Responsable:** Aquiles"
                ),
                "stories": [
                    {
                        "title": "Code review final de todos los módulos",
                        "description": (
                            "**Como tech lead, quiero revisar el código de todos los módulos antes del "
                            "deploy para garantizar calidad y consistencia con los estándares del proyecto.**\n\n"
                            "Code review completo de todos los módulos.\n"
                            "Corrección de lint y estilo en backend y frontend.\n\n"
                            "**Branch:** `chore/code-review-final`\n"
                            "**Responsable:** Aquiles"
                        ),
                    },
                    {
                        "title": "Deploy en VPS con SSL y backups automáticos",
                        "description": (
                            "**Como tech lead, quiero que el sistema esté corriendo en producción con "
                            "SSL, backups automáticos y monitoreo para poder entregar el proyecto al "
                            "cliente.**\n\n"
                            "Configurar VPS Hostinger: Docker, SSH, dominio, SSL con Let's Encrypt.\n"
                            "Backups automáticos de PostgreSQL (cron diario).\n"
                            "Monitoreo básico: health checks y alertas.\n"
                            "Variables de entorno de producción. Build de React en Nginx.\n\n"
                            "**Branch:** `chore/deploy-production`\n"
                            "**Responsable:** Aquiles"
                        ),
                    },
                    {
                        "title": "Smoke tests en producción sobre flujos críticos",
                        "description": (
                            "**Como tech lead, quiero ejecutar tests sobre los flujos críticos en "
                            "producción para confirmar que el sistema entregado funciona correctamente "
                            "antes de la entrega.**\n\n"
                            "Smoke tests en producción:\n"
                            "- Login / logout\n"
                            "- Close Deal (crea empresa + mapas de proceso)\n"
                            "- Subir factura y procesar con IA\n"
                            "- Firmar contrato con DocuSeal\n\n"
                            "**Branch:** `chore/smoke-tests`\n"
                            "**Responsable:** Aquiles"
                        ),
                    },
                    {
                        "title": "Panel global de métricas para superadmin",
                        "description": (
                            "**Como superadmin, quiero un panel con métricas globales consolidadas "
                            "(franquicias, empresas, assessments, contratos) para supervisar el estado "
                            "de toda la plataforma.**\n\n"
                            "El documento establece: 'STRATEGIC MATES (Superadmin): Crea y gestiona "
                            "todo el sistema globalmente.' Para ejercer esa gestión global necesita "
                            "visibilidad consolidada de toda la operación.\n\n"
                            "**Alcance:**\n"
                            "- `GET /api/v1/dashboard/global` solo para superadmin: total franquicias, empresas "
                            "activas, assessments pendientes, contratos en proceso, asientos pendientes de aprobar\n"
                            "- Frontend: sección de métricas globales en `DashboardPage` visible solo para superadmin\n\n"
                            "**Branch:** `feature/dashboard-global-metrics`\n"
                            "**Responsable:** Aquiles"
                        ),
                    },
                ],
            },
        ],
    },

]


# ─────────────────────────────────────────────────────────────────────────────
# 8. PARES DE BLOQUEO
#
# Formato: (título del issue bloqueador, título del issue bloqueado)
# Los títulos coinciden EXACTAMENTE con los campos "title" de las stories arriba.
# ─────────────────────────────────────────────────────────────────────────────

DEPENDENCY_PAIRS = [
    # Infraestructura → Auth
    (
        "Migraciones del schema completo v4",
        "Login y logout con email y contraseña",
    ),
    # Auth → Franquicias / Empresas
    (
        "Login y logout con email y contraseña",
        "CRUD de franquicias SM por región",
    ),
    (
        "Login y logout con email y contraseña",
        "Registro base de Empresas cliente (SBs)",
    ),
    # Registro de empresa → módulos que dependen de company_id
    (
        "Registro base de Empresas cliente (SBs)",
        "Formulario público de postulación de SB",
    ),
    (
        "Registro base de Empresas cliente (SBs)",
        "Listado de usuarios con scope por franquicia",
    ),
    (
        "Registro base de Empresas cliente (SBs)",
        "Navegación de categorías y procesos BPMN",
    ),
    # BPMN → Repositorio (la pólítica de acceso comparte lógica de process_maps)
    (
        "Campo type en process_maps (franquiciadora/franquiciada)",
        "Subida y organización de documentos por categoría",
    ),
    # Usuarios y permisos → Contratos, Feed, Tracking, Catálogo
    (
        "CRUD de usuarios con asignación de rol",
        "Creación y envío de contratos a DocuSeal",
    ),
    (
        "CRUD de usuarios con asignación de rol",
        "Publicación de anuncios en el feed interno",
    ),
    (
        "CRUD de usuarios con asignación de rol",
        "Tablero kanban de entregables por empresa",
    ),
    (
        "CRUD de usuarios con asignación de rol",
        "Catálogo jerárquico Bundle → Service → Deliverable",
    ),
    # Contabilidad → Inventario (inventario genera journal_entries)
    (
        "OCR + IA para extracción de datos contables",
        "CRUD de productos con SKU y stock mínimo",
    ),
    # Módulos finales → QA/Deploy
    (
        "Catálogo jerárquico Bundle → Service → Deliverable",
        "Deploy en VPS con SSL y backups automáticos",
    ),
    (
        "Creación y gestión de eventos con FullCalendar",
        "Deploy en VPS con SSL y backups automáticos",
    ),
    (
        "Edición de datos personales y avatar",
        "Deploy en VPS con SSL y backups automáticos",
    ),
    (
        "Tablero kanban de entregables por empresa",
        "Deploy en VPS con SSL y backups automáticos",
    ),
]


# ─────────────────────────────────────────────────────────────────────────────
# MAIN
# ─────────────────────────────────────────────────────────────────────────────

def main():
    print("\n🚀 SM Portal — Linear Import Definitivo v3\n")
    print("   Jerarquía: Sprints (Cycles) → Módulos (Projects) → Issues\n")

    team_id, _ = get_team_and_org()
    states      = get_states(team_id)

    done_state = states.get("done")
    todo_state = states.get("todo") or states.get("backlog")

    if not done_state or not todo_state:
        print(f"❌ Estados requeridos no encontrados. Disponibles: {states}")
        exit(1)

    # Mapas de IDs generados durante el import
    project_id_map = {}   # { module_title: project_id }
    issue_id_map   = {}   # { story_title:  issue_id  }

    total_modules = sum(len(s["modules"]) for s in SPRINTS)
    total_stories = sum(len(m["stories"]) for s in SPRINTS for m in s["modules"])
    print(f"\n📋 {len(SPRINTS)} sprints · {total_modules} módulos · {total_stories} historias de usuario\n")

    for sprint in SPRINTS:
        print(f"\n{'═'*65}")
        print(f"  {sprint['name']}")
        print(f"{'═'*65}")

        cycle_id = create_cycle(team_id, sprint["name"], sprint["start"], sprint["end"])
        if cycle_id is None:
            print(f"  ⚠  Saltando issues del sprint '{sprint['name']}' — cycle no creado.")
            continue

        state_id = done_state if sprint["done"] else todo_state

        for module in sprint["modules"]:
            print(f"\n  ── Módulo: {module['title']}")

            # Crear el Project (módulo) en lugar de un Issue padre
            project_id = create_project(team_id, module["title"], module["description"])

            if project_id:
                project_id_map[module["title"]] = project_id

                for story in module["stories"]:
                    issue_id = create_issue(
                        team_id=team_id,
                        title=story["title"],
                        description=story["description"],
                        state_id=state_id,
                        cycle_id=cycle_id,
                        project_id=project_id,
                    )
                    if issue_id:
                        issue_id_map[story["title"]] = issue_id

    # ── Relaciones de bloqueo ─────────────────────────────────────────────────
    print(f"\n\n{'═'*65}")
    print("  🔗 Creando relaciones de bloqueo entre issues clave...")
    print(f"{'═'*65}\n")

    ok = 0
    for blocker_title, blocked_title in DEPENDENCY_PAIRS:
        blocker_id = issue_id_map.get(blocker_title)
        blocked_id = issue_id_map.get(blocked_title)

        if not blocker_id:
            print(f"  ⚠  Bloqueador no encontrado en issue_id_map: '{blocker_title}'")
            continue
        if not blocked_id:
            print(f"  ⚠  Bloqueado no encontrado en issue_id_map: '{blocked_title}'")
            continue

        success = create_blocking_relation(blocker_id, blocked_id, blocker_title, blocked_title)
        if success:
            ok += 1

    # ── Resumen ───────────────────────────────────────────────────────────────
    print(f"\n\n{'═'*65}")
    print("  ✅ Import completo.")
    print(f"     Sprints   : {len(SPRINTS)}")
    print(f"     Módulos   : {len(project_id_map)}")
    print(f"     Issues    : {len(issue_id_map)}")
    print(f"     Relaciones: {ok} / {len(DEPENDENCY_PAIRS)}")
    print(f"{'═'*65}")
    print()
    print("⚠  IMPORTANTE: Regenerar la API key de Linear ahora.")
    print("   Linear → Settings → API → Revocar y recrear la key.")
    print(f"{'═'*65}\n")


if __name__ == "__main__":
    main()
