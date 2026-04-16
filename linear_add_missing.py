"""
SM Portal — Linear Add Missing Stories
Agrega las 17 historias faltantes identificadas en el análisis de backlog.

NO borra ni modifica issues existentes.
Busca las épicas padre por título y agrega sub-tareas debajo de ellas.

Uso:
    pip install requests
    python linear_add_missing.py

IMPORTANTE: Regenerar la API key en Linear después de ejecutar este script.
"""

import requests
import time

API_KEY = "lin_api_tElh3IJZFhzdRBwS34JHpKk9sWTJxE2A8UpTr0Da"
API_URL = "https://api.linear.app/graphql"

HEADERS = {
    "Authorization": API_KEY,
    "Content-Type": "application/json",
}


# ─────────────────────────────────────────────
# GRAPHQL CLIENT
# ─────────────────────────────────────────────

def gql(query, variables=None):
    payload = {"query": query}
    if variables:
        payload["variables"] = variables
    res = requests.post(API_URL, headers=HEADERS, json=payload)
    data = res.json()
    if "errors" in data:
        print(f"  ⚠  GraphQL error: {data['errors']}")
    return data


# ─────────────────────────────────────────────
# GET TEAM ID
# ─────────────────────────────────────────────

def get_team_id():
    query = """
    query {
        teams {
            nodes { id name }
        }
    }
    """
    data = gql(query)
    teams = data["data"]["teams"]["nodes"]
    if not teams:
        print("❌ No teams found.")
        exit(1)
    team = teams[0]
    print(f"✅ Team: {team['name']} ({team['id']})")
    return team["id"]


# ─────────────────────────────────────────────
# GET WORKFLOW STATES
# ─────────────────────────────────────────────

def get_todo_state(team_id):
    query = """
    query($teamId: ID!) {
        workflowStates(filter: { team: { id: { eq: $teamId } } }) {
            nodes { id name type }
        }
    }
    """
    data = gql(query, {"teamId": team_id})
    states = data["data"]["workflowStates"]["nodes"]
    for s in states:
        if s["type"].lower() in ("unstarted", "backlog"):
            print(f"✅ Estado todo: {s['name']} ({s['id']})")
            return s["id"]
    print("❌ No se encontró estado 'todo' o 'backlog'")
    exit(1)


# ─────────────────────────────────────────────
# GET EXISTING ISSUES (épicas padre) BY TITLE
# ─────────────────────────────────────────────

def get_issues_map(team_id):
    """Devuelve un dict { título_parcial_en_minúsculas: issue_id }"""
    query = """
    query($teamId: String!) {
        issues(filter: { team: { id: { eq: $teamId } }, parent: { null: true } }) {
            nodes { id title identifier }
        }
    }
    """
    data = gql(query, {"teamId": team_id})
    issues = data.get("data", {}).get("issues", {}).get("nodes", [])
    issue_map = {}
    for issue in issues:
        issue_map[issue["title"].lower().strip()] = issue["id"]
        print(f"  📋 {issue['identifier']}: {issue['title'][:70]}")
    return issue_map


# ─────────────────────────────────────────────
# GET CYCLES (sprints) BY NAME
# ─────────────────────────────────────────────

def get_cycles_map(team_id):
    query = """
    query($teamId: String!) {
        cycles(filter: { team: { id: { eq: $teamId } } }) {
            nodes { id name }
        }
    }
    """
    data = gql(query, {"teamId": team_id})
    cycles = data.get("data", {}).get("cycles", {}).get("nodes", [])
    cycle_map = {}
    for cycle in cycles:
        cycle_map[cycle["name"].lower().strip()] = cycle["id"]
        print(f"  🔄 {cycle['name']} ({cycle['id']})")
    return cycle_map


# ─────────────────────────────────────────────
# FIND PARENT ID (búsqueda flexible por palabras clave)
# ─────────────────────────────────────────────

def find_parent(issue_map, keywords):
    """
    Busca el issue cuyo título contenga TODAS las keywords dadas.
    keywords: lista de strings en minúsculas.
    """
    for title, issue_id in issue_map.items():
        if all(kw in title for kw in keywords):
            return issue_id
    return None


def find_cycle(cycle_map, sprint_number):
    """Busca el cycle por número de sprint."""
    keyword = f"sprint {sprint_number}"
    for name, cycle_id in cycle_map.items():
        if keyword in name:
            return cycle_id
    return None


# ─────────────────────────────────────────────
# CREATE ISSUE
# ─────────────────────────────────────────────

def create_issue(team_id, title, description, state_id, cycle_id, parent_id):
    mutation = """
    mutation(
        $teamId: String!,
        $title: String!,
        $description: String,
        $stateId: String!,
        $cycleId: String,
        $parentId: String
    ) {
        issueCreate(input: {
            teamId: $teamId
            title: $title
            description: $description
            stateId: $stateId
            cycleId: $cycleId
            parentId: $parentId
        }) {
            success
            issue { id title identifier }
        }
    }
    """
    data = gql(mutation, {
        "teamId": team_id,
        "title": title,
        "description": description or "",
        "stateId": state_id,
        "cycleId": cycle_id,
        "parentId": parent_id,
    })
    time.sleep(0.4)
    issue = data.get("data", {}).get("issueCreate", {}).get("issue")
    if not issue:
        print(f"    ❌ Error creando: '{title[:70]}'")
        return None
    print(f"    ✅ {issue['identifier']}: {issue['title'][:80]}")
    return issue["id"]


# ─────────────────────────────────────────────
# HISTORIAS FALTANTES
#
# Cada historia tiene:
#   - sprint: número de sprint (2-6)
#   - parent_keywords: palabras clave para encontrar la épica padre
#   - title: título de la sub-tarea
#   - description: descripción detallada
# ─────────────────────────────────────────────

MISSING_STORIES = [

    # ── SPRINT 2 ─────────────────────────────────────────────

    {
        "sprint": 2,
        "parent_keywords": ["login", "autenticación"],
        "title": "Como admin_sm o superadmin, quiero invitar a un nuevo usuario por enlace enviado a su email para que él mismo configure su contraseña al primer acceso",
        "description": (
            "**Por qué existe esta historia:**\n"
            "El documento de negocio indica: *'Falta: flujo de invitación por link para nuevos "
            "usuarios (hoy el admin crea la cuenta manualmente con contraseña).'*\n\n"
            "El flujo actual expone la contraseña del usuario al administrador (riesgo de seguridad) "
            "y no escala en un entorno B2B con múltiples actores.\n\n"
            "**Alcance:**\n"
            "- Backend: endpoint `POST /api/v1/users/{id}/invite` que genera token firmado y envía email\n"
            "- Backend: endpoint público `POST /api/v1/auth/accept-invite` que valida token y setea contraseña\n"
            "- Frontend: página pública `/accept-invite?token=...` con formulario de contraseña\n\n"
            "**Branch:** `feature/auth-invite`"
        ),
    },
    {
        "sprint": 2,
        "parent_keywords": ["login", "autenticación"],
        "title": "Como usuario, quiero recuperar mi contraseña solicitando un enlace de reseteo a mi email para no quedar bloqueado si olvido mis credenciales",
        "description": (
            "**Por qué existe esta historia:**\n"
            "El documento de negocio indica: *'Falta: recuperación de contraseña por email.'*\n\n"
            "Sin esta funcionalidad, cualquier usuario que olvide su contraseña queda bloqueado "
            "permanentemente hasta que un administrador intervenga de forma manual.\n\n"
            "**Alcance:**\n"
            "- Backend: `POST /api/v1/auth/forgot-password` — envía email con link de reset\n"
            "- Backend: `POST /api/v1/auth/reset-password` — valida token y actualiza contraseña\n"
            "- Frontend: página `/forgot-password` y `/reset-password?token=...`\n\n"
            "**Branch:** `feature/auth-password-reset`"
        ),
    },
    {
        "sprint": 2,
        "parent_keywords": ["login", "autenticación"],
        "title": "Como sistema, quiero tener los 7 roles exactos creados formalmente con Spatie Permissions para que todas las funcionalidades de control de acceso funcionen correctamente",
        "description": (
            "**Por qué existe esta historia:**\n"
            "El documento define 7 roles exactos: `superadmin`, `admin_sm`, `sb_owner`, "
            "`sb_employee`, `bb`, `sub_franchise_owner`, `sub_franchise_admin`.\n\n"
            "El documento también indica: *'Falta: definición formal de permisos para roles BB "
            "y sub_franchise en el código.'*\n\n"
            "Sin el seed formal de los 7 roles, las historias de acceso diferenciado (BB read-only, "
            "sub_franchise_owner, sb_employee) no pueden implementarse ni probarse.\n\n"
            "**Alcance:**\n"
            "- Seeder `RolesAndPermissionsSeeder` con los 7 roles Spatie\n"
            "- Permisos por defecto para BB: read en `accounting` y `contracts` de su empresa\n"
            "- Permisos por defecto para sub_franchise_owner: read/write en módulos asignados\n\n"
            "**Branch:** `feature/auth-roles-seed`"
        ),
    },

    # ── SPRINT 3 ─────────────────────────────────────────────

    {
        "sprint": 3,
        "parent_keywords": ["usuarios", "permisos"],
        "title": "Como sb_employee, quiero acceder solo a los módulos que mi sb_owner me habilitó para no ver información fuera de mis responsabilidades",
        "description": (
            "**Por qué existe esta historia:**\n"
            "El documento define: *'sb_employee: Colaborador del SB. Acceso configurado por el admin. "
            "Puede tener permisos limitados a ciertos módulos.'*\n\n"
            "Sin esta historia el rol `sb_employee` existe en el sistema pero no tiene ningún "
            "comportamiento diferenciado — ve todo igual que un `sb_owner`, lo cual viola la "
            "definición del rol.\n\n"
            "**Alcance:**\n"
            "- El middleware `EnsureModulePermission` ya existe — verificar que aplica para `sb_employee`\n"
            "- El sidebar filtra módulos usando `user_permissions` — verificar que `sb_employee` respeta el filtro\n"
            "- Tests de integración: `sb_employee` sin permiso de Contabilidad recibe 403 en esos endpoints\n\n"
            "**Branch:** `feature/permissions-sb-employee`"
        ),
    },
    {
        "sprint": 3,
        "parent_keywords": ["usuarios", "permisos"],
        "title": "Como superadmin, quiero ver el listado global de todas las franquicias SM con sus empresas activas para supervisar la operación completa de la red",
        "description": (
            "**Por qué existe esta historia:**\n"
            "El documento define: *'STRATEGIC MATES (Superadmin): Crea y gestiona todo el sistema "
            "globalmente.'*\n\n"
            "Sin una vista global, el superadmin no tiene forma de supervisar el estado de todas "
            "las franquicias y sus empresas en un solo lugar.\n\n"
            "**Alcance:**\n"
            "- Backend: `GET /api/v1/dashboard/summary` extendido con métricas globales para superadmin\n"
            "- Frontend: sección del Dashboard para superadmin con conteo de franquicias, empresas, "
            "assessments pendientes y contratos activos\n\n"
            "**Branch:** `feature/dashboard-superadmin`"
        ),
    },
    {
        "sprint": 3,
        "parent_keywords": ["assessments", "postulaciones"],
        "title": "Como admin_sm, quiero que al ejecutar el Close Deal desde un assessment se cree automáticamente la empresa, el usuario sb_owner, el vínculo con el BB y los 2 mapas de proceso en una sola transacción",
        "description": (
            "**Por qué existe esta historia:**\n"
            "El documento de negocio establece: *'cuando deciden proceder con un negocio, cierran "
            "el trato [...] en ese momento el sistema crea automáticamente el portal del SB, su "
            "usuario owner, vincula al BB asignado y genera los dos mapas de procesos "
            "(franquiciadora y franquiciada).'*\n\n"
            "Los issues WILT-10 y WILT-15 abordan partes del Close Deal, pero ninguno garantiza "
            "explícitamente la creación atómica de los 2 mapas de proceso junto con el resto.\n\n"
            "**Alcance:**\n"
            "- Endpoint `POST /api/v1/assessment-contacts/{id}/close-deal`\n"
            "- Transacción DB: Company + User (sb_owner) + BbAssignment + ProcessMap ×2\n"
            "- Job `SendInvitationEmail` para el nuevo sb_owner\n"
            "- Campo `converted_company_id` en `assessment_contacts`\n\n"
            "**Branch:** `feature/assessment-close-deal-atomic`"
        ),
    },
    {
        "sprint": 3,
        "parent_keywords": ["assessments", "postulaciones"],
        "title": "Como admin_sm, quiero registrar la decisión sobre un assessment eligiendo de un catálogo formal para mantener trazabilidad consistente en todos los casos",
        "description": (
            "**Por qué existe esta historia:**\n"
            "El documento indica: *'Falta: tabla assessment_decisions como catálogo formal "
            "(hoy la decisión es un campo varchar libre).'*\n\n"
            "Con un varchar libre, distintos admins pueden registrar la misma decisión con "
            "texto diferente ('cerrado', 'Cerrado', 'close deal'), haciendo imposible filtrar "
            "y reportar el estado real del pipeline de assessments.\n\n"
            "**Alcance:**\n"
            "- Migración: columna `decision_id` FK en `assessment_contacts` (ya existe como migración)\n"
            "- Seeder: catálogo con decisiones: `pending_review`, `accepted`, `rejected`, `close_deal`, `waiting`\n"
            "- Frontend: dropdown con opciones del catálogo en lugar de campo de texto libre\n\n"
            "**Branch:** `feature/assessment-decisions-catalog`"
        ),
    },
    {
        "sprint": 3,
        "parent_keywords": ["assessments", "postulaciones"],
        "title": "Como admin_sm, quiero ver en el detalle de un assessment a qué empresa fue convertido para rastrear de dónde vino cada cliente del portal",
        "description": (
            "**Por qué existe esta historia:**\n"
            "El documento indica: *'Falta: campo converted_company_id en assessment_contacts "
            "para vincular la postulación con la company creada.'*\n\n"
            "Sin este vínculo no hay trazabilidad entre el formulario público que llenó el negocio "
            "y el portal que se le creó, perdiendo el historial del origen de cada cliente.\n\n"
            "**Alcance:**\n"
            "- Campo `converted_company_id` FK en `assessment_contacts` (se llena al ejecutar Close Deal)\n"
            "- Frontend: badge 'Convertido' con link a la empresa en `AssessmentDetailPage`\n\n"
            "**Branch:** `feature/assessment-converted-link`"
        ),
    },

    # ── SPRINT 4 ─────────────────────────────────────────────

    {
        "sprint": 4,
        "parent_keywords": ["bpmn", "proceso"],
        "title": "Como sistema, quiero que cada mapa de proceso tenga un campo type (franquiciadora o franquiciada) para que los roles correctos accedan al mapa que les corresponde",
        "description": (
            "**Por qué existe esta historia:**\n"
            "El documento establece: *'Cada empresa tiene dos mapas: el mapa franquiciadora que ve "
            "el SB [...] y el mapa franquiciada que ven sus sub-franquicias. Ambos se crean "
            "automáticamente cuando se registra una nueva empresa.'*\n\n"
            "El documento también lista como faltante: *'campo type en process_maps "
            "(franquiciadora vs franquiciada) — hoy todos los mapas son iguales.'*\n\n"
            "Sin este campo no hay forma de distinguir los dos mapas ni de filtrar el acceso por rol.\n\n"
            "**Alcance:**\n"
            "- Verificar que la migración de `process_maps` incluye columna `type`\n"
            "- Seed de los 2 mapas con type correcto al ejecutar Close Deal\n"
            "- `ProcessMapPolicy`: `sb_owner` ve ambos, `sub_franchise_owner` solo ve `franquiciada`\n\n"
            "**Branch:** `feature/bpmn-map-type`"
        ),
    },
    {
        "sprint": 4,
        "parent_keywords": ["bpmn", "proceso"],
        "title": "Como sub_franchise_owner, quiero ver únicamente el mapa de proceso tipo franquiciada de la empresa SB a la que pertenezco para seguir la operación estandarizada de la franquicia",
        "description": (
            "**Por qué existe esta historia:**\n"
            "El documento define: *'sub_franchise_owner: Ve el mapa de procesos tipo franquiciada, "
            "repositorio, contabilidad e inventario de su franquicia.'*\n\n"
            "Y en los items faltantes: *'Falta: vista del SB para ingresar al mapa de "
            "su sub-franquicia.'*\n\n"
            "Sin esta historia el rol `sub_franchise_owner` no puede ver los procesos operativos "
            "que debe seguir, que es la razón de ser de su acceso al portal.\n\n"
            "**Alcance:**\n"
            "- `ProcessMapPolicy`: `sub_franchise_owner` solo puede hacer GET del mapa `franquiciada`\n"
            "- Frontend: `ProcessMapsPage` filtra el selector de mapa según el rol del usuario\n\n"
            "**Branch:** `feature/bpmn-subfran-view`"
        ),
    },
    {
        "sprint": 4,
        "parent_keywords": ["contratos", "firma"],
        "title": "Como admin_sm, quiero asignar explícitamente a los firmantes como Elaborado por, Revisado por y Aprobado por al crear un contrato para cumplir con el flujo formal de Strategic Mates",
        "description": (
            "**Por qué existe esta historia:**\n"
            "El documento establece: *'Los contratos tienen tres firmantes en el proceso formal "
            "de SM: Elaborado por, Revisado por y Aprobado por. Cada uno con su propio link de "
            "firma a través de DocuSeal.'*\n\n"
            "Y en faltantes: *'Falta: flujo completo con 3 firmantes (Elaborado/Revisado/Aprobado) "
            "— la integración está iniciada pero el flujo completo está pendiente.'*\n\n"
            "Sin esta historia los contratos pueden enviarse sin los 3 roles asignados, "
            "rompiendo el proceso de aprobación formal de SM.\n\n"
            "**Alcance:**\n"
            "- `DocusealService`: crear submission con 3 submitters etiquetados\n"
            "- Frontend: 3 campos de firmante con labels 'Elaborado por', 'Revisado por', 'Aprobado por'\n"
            "- Validación: no se puede enviar a firma sin los 3 firmantes asignados\n\n"
            "**Branch:** `feature/contracts-3-signers`"
        ),
    },
    {
        "sprint": 4,
        "parent_keywords": ["contratos", "firma"],
        "title": "Como Business Bishop, quiero ver únicamente los contratos y contabilidad de la empresa que patrocino, sin acceso a información de otras empresas del sistema",
        "description": (
            "**Por qué existe esta historia:**\n"
            "El documento establece: *'BB (Business Bishop): investor sponsor, 1 per SB'* y "
            "*'bb: Ve home, feed, contabilidad y contratos de la empresa que apadrina. No ve "
            "procesos ni repositorio.'*\n\n"
            "La restricción es doble: (1) solo puede ver contabilidad y contratos, y "
            "(2) solo de su empresa, no de otras aunque estén en la misma franquicia.\n\n"
            "Sin esta historia, un BB podría ver datos financieros y contractuales de empresas "
            "que no patrocina, violando la confidencialidad del sistema.\n\n"
            "**Alcance:**\n"
            "- `ContractPolicy` y controlador de contabilidad: scope a `bb_assignments.bb_user_id = auth()->id()`\n"
            "- Verificar que WILT-32 y WILT-37 implementan este scope, no solo el read-only\n\n"
            "**Branch:** `feature/bb-scope-enforcement`"
        ),
    },

    # ── SPRINT 5 ─────────────────────────────────────────────

    {
        "sprint": 5,
        "parent_keywords": ["contabilidad", "finanzas"],
        "title": "Como sb_owner, quiero conectar mi sistema POS (Square, Stripe, Shopify, Clover, WooCommerce) para que las ventas se importen automáticamente a la contabilidad",
        "description": (
            "**Por qué existe esta historia:**\n"
            "El documento establece: *'El portal se conecta con sistemas POS (Square, Stripe, "
            "Shopify, Clover, WooCommerce) via OAuth para importar transacciones de ventas "
            "automáticamente. Estas se concilian también con los movimientos bancarios.'*\n\n"
            "Sin esta integración el sb_owner debe ingresar las ventas manualmente, "
            "eliminando uno de los beneficios clave del módulo de contabilidad automatizada.\n\n"
            "**Alcance:**\n"
            "- `PosConnectionService` con OAuth flow por proveedor\n"
            "- Job `ImportPosTransactions` en cola `integrations`\n"
            "- Tabla `pos_connections` ya existe en el schema\n"
            "- Frontend: `PosIntegrationsPage` con lista de proveedores y estado de conexión\n\n"
            "**Branch:** `feature/accounting-pos`"
        ),
    },

    # ── SPRINT 6 ─────────────────────────────────────────────

    {
        "sprint": 6,
        "parent_keywords": ["catálogo", "servicios"],
        "title": "Como admin_sm, quiero crear sub-franquicias dentro de una empresa SB existente para que el sistema modele la expansión del negocio del cliente",
        "description": (
            "**Por qué existe esta historia:**\n"
            "El documento describe: *'Sub-Franquicias (las franquicias que abre el SB). "
            "El SB es el franquiciador. Sus franquiciados ven el mapa de procesos tipo "
            "franquiciada y tienen su propio portal.'*\n\n"
            "Sin esta historia el sistema no puede modelar el crecimiento de los clientes SB "
            "que abren sus propias franquicias, que es el objetivo final del programa de SM.\n\n"
            "**Alcance:**\n"
            "- Endpoint `POST /api/v1/companies/{id}/sub-franchises`\n"
            "- Crear usuario `sub_franchise_owner` vinculado a la sub-franquicia\n"
            "- La sub-franquicia hereda el mapa `franquiciada` de su empresa SB padre\n\n"
            "**Branch:** `feature/sub-franchises`"
        ),
    },
    {
        "sprint": 6,
        "parent_keywords": ["catálogo", "servicios"],
        "title": "Como sub_franchise_admin, quiero administrar los usuarios de mi sub-franquicia de forma independiente para gestionar mi propio equipo operativo",
        "description": (
            "**Por qué existe esta historia:**\n"
            "El documento define: *'sub_franchise_admin: Admin de una sub-franquicia. "
            "Apoya la gestión operativa de la sub-franquicia con acceso similar al owner.'*\n\n"
            "Sin esta historia el rol `sub_franchise_admin` existe en el sistema pero no tiene "
            "ninguna funcionalidad asociada — no puede gestionar usuarios ni operaciones de su franquicia.\n\n"
            "**Alcance:**\n"
            "- `UserPolicy`: `sub_franchise_admin` puede crear/editar usuarios de su sub-franquicia\n"
            "- Frontend: acceso a `UsersPage` filtrado por `sub_franchise_id`\n\n"
            "**Branch:** `feature/sub-franchise-admin`"
        ),
    },
    {
        "sprint": 6,
        "parent_keywords": ["perfil", "usuario"],
        "title": "Como usuario autenticado, quiero configurar qué notificaciones quiero recibir (email, in-app) para no ser interrumpido por alertas que no me son relevantes",
        "description": (
            "**Por qué existe esta historia:**\n"
            "El portal genera notificaciones de múltiples módulos: contratos pendientes de firma, "
            "documentos nuevos en el repositorio, asientos contables para revisar, eventos "
            "del calendario. Sin control de preferencias, el usuario recibe todas las alertas "
            "aunque no tenga permisos en algunos módulos.\n\n"
            "**Alcance:**\n"
            "- Tabla o JSON de preferencias en el modelo User\n"
            "- Frontend: sección 'Notificaciones' en `ProfilePage` con toggles por tipo\n\n"
            "**Branch:** `feature/notification-preferences`"
        ),
    },
    {
        "sprint": 6,
        "parent_keywords": ["qa", "despliegue", "producción"],
        "title": "Como superadmin, quiero un panel con métricas globales consolidadas (franquicias, empresas, assessments, contratos) para supervisar el estado de toda la plataforma",
        "description": (
            "**Por qué existe esta historia:**\n"
            "El documento establece: *'STRATEGIC MATES (Superadmin): Crea y gestiona todo el "
            "sistema globalmente.'* Para ejercer esa gestión global necesita visibilidad "
            "consolidada de toda la operación.\n\n"
            "**Alcance:**\n"
            "- `GET /api/v1/dashboard/global` solo para superadmin: total franquicias, empresas "
            "activas, assessments pendientes, contratos en proceso, asientos pendientes de aprobar\n"
            "- Frontend: sección de métricas globales en `DashboardPage` visible solo para superadmin\n\n"
            "**Branch:** `feature/dashboard-global-metrics`"
        ),
    },

]


# ─────────────────────────────────────────────
# MAIN
# ─────────────────────────────────────────────

def main():
    print("\n🚀 SM Portal — Agregar historias faltantes a Linear\n")
    print(f"📋 {len(MISSING_STORIES)} historias a agregar\n")

    team_id = get_team_id()
    todo_state = get_todo_state(team_id)

    print("\n📂 Cargando épicas existentes...")
    issue_map = get_issues_map(team_id)

    print("\n🔄 Cargando sprints existentes...")
    cycle_map = get_cycles_map(team_id)

    created = 0
    skipped = 0

    print("\n─────────────────────────────────────────")

    for story in MISSING_STORIES:
        sprint_num = story["sprint"]
        keywords   = story["parent_keywords"]
        title      = story["title"]
        description = story["description"]

        parent_id = find_parent(issue_map, keywords)
        cycle_id  = find_cycle(cycle_map, sprint_num)

        if not parent_id:
            print(f"\n  ⚠  Sprint {sprint_num} | No se encontró épica con keywords: {keywords}")
            print(f"     Saltando: '{title[:70]}'")
            skipped += 1
            continue

        if not cycle_id:
            print(f"\n  ⚠  No se encontró el cycle para Sprint {sprint_num}")
            print(f"     Saltando: '{title[:70]}'")
            skipped += 1
            continue

        print(f"\n  📦 Sprint {sprint_num} | épica encontrada")
        issue_id = create_issue(
            team_id=team_id,
            title=title,
            description=description,
            state_id=todo_state,
            cycle_id=cycle_id,
            parent_id=parent_id,
        )
        if issue_id:
            created += 1

    print(f"\n\n✅ Listo.")
    print(f"   Creadas  : {created}")
    print(f"   Saltadas : {skipped}")
    print()
    print("=" * 60)
    print("⚠  IMPORTANTE: Regenerar la API key de Linear ahora.")
    print("   Linear → Settings → API → Revocar y recrear la key.")
    print("=" * 60)


if __name__ == "__main__":
    main()
