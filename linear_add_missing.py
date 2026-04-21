"""
SM Portal - Linear Import ADICIONAL v1
=======================================
Agrega UNICAMENTE las historias faltantes al backlog existente en Linear.
NO recrea sprints ni modulos - los busca por nombre y les agrega los issues.

Historias nuevas (10 en total):
  Sprint 1  -> Instalar backend, Instalar frontend, CI automatico
  Sprint 2  -> Vincular Business Bishop a empresa
  Sprint 3  -> Exportar reporte PDF de postulacion
  Sprint 4  -> Adjuntar documentos a procesos, Versionar esos documentos
  Sprint 5  -> Alerta de stock minimo
  Sprint 6  -> Cronograma de entregables, Tareas automaticas del sistema

Uso:
    pip install requests
    python linear_add_missing.py

IMPORTANTE: Actualizar API_KEY con la key vigente de Linear antes de ejecutar.
IMPORTANTE: Regenerar la API key en Linear despues de ejecutar.
"""

import requests
import time

# -----------------------------------------------------------------------------
# CONFIGURACION - actualizar API_KEY antes de ejecutar
# -----------------------------------------------------------------------------

API_KEY = "PEGAR_AQUI_LA_API_KEY_DE_LINEAR"
API_URL = "https://api.linear.app/graphql"

HEADERS = {
    "Authorization": API_KEY,
    "Content-Type": "application/json",
}


# -----------------------------------------------------------------------------
# GRAPHQL CLIENT
# -----------------------------------------------------------------------------

def gql(query, variables=None):
    payload = {"query": query}
    if variables:
        payload["variables"] = variables
    res = requests.post(API_URL, headers=HEADERS, json=payload)
    data = res.json()
    if "errors" in data:
        print(f"  GQL error: {data['errors']}")
    return data


# -----------------------------------------------------------------------------
# 1. OBTENER TEAM ID
# -----------------------------------------------------------------------------

def get_team_id():
    data = gql("query { teams { nodes { id name } } }")
    teams = data["data"]["teams"]["nodes"]
    if not teams:
        print("ERROR: No se encontraron equipos en Linear.")
        exit(1)
    team = teams[0]
    print(f"Team: {team['name']} ({team['id']})")
    return team["id"]


# -----------------------------------------------------------------------------
# 2. OBTENER WORKFLOW STATE (todo/backlog)
# -----------------------------------------------------------------------------

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
            print(f"Estado para nuevos issues: {s['name']}")
            return s["id"]
    print("ERROR: No se encontro estado 'unstarted' o 'backlog'.")
    exit(1)


# -----------------------------------------------------------------------------
# 3. OBTENER CYCLES (SPRINTS) - mapeo nombre -> id
# -----------------------------------------------------------------------------

def get_cycles(team_id):
    query = """
    query($teamId: ID!) {
        cycles(filter: { team: { id: { eq: $teamId } } }) {
            nodes { id name }
        }
    }
    """
    data = gql(query, {"teamId": team_id})
    cycles = data["data"]["cycles"]["nodes"]
    cycle_map = {c["name"]: c["id"] for c in cycles}
    print(f"Cycles encontrados: {list(cycle_map.keys())}")
    return cycle_map


# -----------------------------------------------------------------------------
# 4. OBTENER PROJECTS (MODULOS) - mapeo nombre -> id
# -----------------------------------------------------------------------------

def get_projects():
    data = gql("query { projects { nodes { id name } } }")
    projects = data["data"]["projects"]["nodes"]
    project_map = {p["name"]: p["id"] for p in projects}
    print(f"Projects encontrados: {len(project_map)}")
    return project_map


# -----------------------------------------------------------------------------
# 5. CREAR ISSUE
# -----------------------------------------------------------------------------

def create_issue(team_id, title, description, state_id, cycle_id, project_id):
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
        print(f"      ERROR creando: '{title[:70]}'")
        return None
    print(f"      OK {issue['identifier']}: {issue['title'][:90]}")
    return issue["id"]


# -----------------------------------------------------------------------------
# 6. HISTORIAS FALTANTES
#
# sprint_name  -> nombre exacto del cycle en Linear ("Sprint 1", etc.)
# project_name -> nombre exacto del project (modulo) en Linear
# title        -> titulo corto en lenguaje de negocio (sin tecnicismos)
# description  -> historia completa con criterios de aceptacion simples
# -----------------------------------------------------------------------------

MISSING_STORIES = [

    # ==========================================================================
    # SPRINT 1 - Setup inicial del proyecto (Fase 0 del plan de arquitectura)
    # Modulo: Levantar el entorno de desarrollo con Docker
    # ==========================================================================
    {
        "sprint_name":  "Sprint 1",
        "project_name": "Levantar el entorno de desarrollo con Docker",
        "title": "Instalar y configurar el proyecto backend",
        "description": (
            "**Como desarrollador, quiero tener el proyecto backend instalado y listo "
            "para poder empezar a escribir codigo sin perder tiempo configurando.**\n\n"
            "El entorno de Docker existe pero el proyecto en si no esta inicializado. "
            "Sin este paso, ningun trabajo de backend puede comenzar.\n\n"
            "**Que tiene que estar listo:**\n"
            "- El framework backend instalado con todos sus paquetes: autenticacion, "
            "gestion de roles, conector de inteligencia artificial y firma electronica\n"
            "- Herramientas de calidad de codigo configuradas y listas\n"
            "- Archivo de variables de entorno de ejemplo con todo documentado\n"
            "- Los tests corren sin errores desde el primer momento\n\n"
            "**Branch:** `infra/backend-setup`\n"
            "**Responsable:** Aquiles"
        ),
    },
    {
        "sprint_name":  "Sprint 1",
        "project_name": "Levantar el entorno de desarrollo con Docker",
        "title": "Instalar y configurar el proyecto frontend",
        "description": (
            "**Como desarrollador, quiero tener el proyecto frontend instalado y listo "
            "para poder empezar a construir pantallas sin configuracion adicional.**\n\n"
            "El Docker existe pero el proyecto de pantallas no esta inicializado. "
            "Sin este paso ninguna interfaz puede construirse.\n\n"
            "**Que tiene que estar listo:**\n"
            "- El framework de pantallas instalado con todas las dependencias: manejo "
            "de estado global, navegacion entre paginas, estilos visuales, "
            "formularios y validaciones\n"
            "- Revision automatica de estilo de codigo configurada y funcionando\n"
            "- Archivo de variables de entorno de ejemplo con todo documentado\n"
            "- Levantar en modo desarrollo y generar el build de produccion funcionan "
            "sin errores\n\n"
            "**Branch:** `infra/frontend-setup`\n"
            "**Responsable:** Aquiles"
        ),
    },
    {
        "sprint_name":  "Sprint 1",
        "project_name": "Levantar el entorno de desarrollo con Docker",
        "title": "Revision automatica del codigo en cada cambio",
        "description": (
            "**Como desarrollador, quiero que al subir un cambio de codigo se ejecuten "
            "automaticamente las revisiones de calidad para detectar errores antes "
            "de que lleguen al proyecto principal.**\n\n"
            "Sin un proceso automatico, los errores solo se detectan manualmente. "
            "Codigo con problemas puede entrar al proyecto sin que nadie se de cuenta.\n\n"
            "**Que tiene que funcionar:**\n"
            "- Al abrir o actualizar una solicitud de cambio se ejecutan automaticamente: "
            "revision de estilo del backend, revision del frontend y pruebas del sistema\n"
            "- Una solicitud con errores no puede fusionarse con el proyecto principal\n"
            "- El proceso corre automaticamente sin configuracion manual cada vez\n\n"
            "**Branch:** `infra/ci-pipeline`\n"
            "**Responsable:** Aquiles"
        ),
    },

    # ==========================================================================
    # SPRINT 2 - Franquicias y Empresas (Modulo 04)
    # ==========================================================================
    {
        "sprint_name":  "Sprint 2",
        "project_name": "Construir el módulo de Franquicias y Empresas (Módulo 04)",
        "title": "Vincular un inversor (BB) a una empresa",
        "description": (
            "**Como admin_sm, quiero poder asignar o cambiar el Business Bishop de una "
            "empresa en cualquier momento para mantener actualizado quien esta "
            "patrocinando a cada cliente.**\n\n"
            "Hoy el vinculo entre el inversor y la empresa solo se crea automaticamente "
            "al cerrar el trato inicial. Si el inversor cambia, o si se quiere asignar "
            "uno a una empresa ya existente, no hay forma de hacerlo desde el portal.\n\n"
            "**Que tiene que funcionar:**\n"
            "- En la ficha de una empresa puedo ver quien es el Business Bishop actual\n"
            "- Puedo asignar un BB a una empresa que todavia no tiene uno\n"
            "- Puedo cambiar el BB asignado si la situacion cambia\n"
            "- Solo el admin_sm y el superadmin pueden hacer esta asignacion\n"
            "- Una vez asignado, el BB ve contabilidad y contratos de esa empresa "
            "en modo solo lectura\n\n"
            "**Branch:** `feature/bb-assignment-management`\n"
            "**Responsable:** Aquiles"
        ),
    },

    # ==========================================================================
    # SPRINT 3 - Assessments (Modulo 03)
    # ==========================================================================
    {
        "sprint_name":  "Sprint 3",
        "project_name": "Construir el módulo de Postulaciones Públicas — Assessments (Módulo 03)",
        "title": "Exportar reporte PDF de una postulacion",
        "description": (
            "**Como admin_sm, quiero poder descargar el reporte completo de una "
            "postulacion en PDF para compartirlo con el equipo o archivarlo fuera "
            "del portal.**\n\n"
            "El portal muestra los puntajes y el detalle de cada evaluacion en pantalla "
            "pero no hay forma de llevarse esa informacion en un formato portable. "
            "El PDF permite compartir el reporte en reuniones o enviarlo por correo "
            "sin necesidad de acceder al portal.\n\n"
            "**Que tiene que funcionar:**\n"
            "- En el detalle de una postulacion hay un boton para descargar el reporte\n"
            "- El PDF incluye los datos del postulante, el puntaje por cada area "
            "evaluada y la decision que se tomo\n"
            "- El archivo se genera y descarga en pocos segundos, sin que el usuario "
            "tenga que esperar en pantalla\n\n"
            "**Branch:** `feature/assessment-pdf-export`\n"
            "**Responsable:** Aquiles"
        ),
    },

    # ==========================================================================
    # SPRINT 4 - Mapas de Proceso BPMN (Modulo 09)
    # ==========================================================================
    {
        "sprint_name":  "Sprint 4",
        "project_name": "Construir el módulo de Mapas de Proceso BPMN (Módulo 09)",
        "title": "Adjuntar documentos a un proceso operativo",
        "description": (
            "**Como sb_owner o admin_sm, quiero poder adjuntar documentos a cada proceso "
            "del mapa operativo para que el equipo encuentre todo en un solo lugar.**\n\n"
            "Cada proceso tiene documentos asociados: el manual de como realizarlo, "
            "el formulario a completar, el certificado requerido, etc. Sin esta funcion, "
            "esos documentos quedan sueltos en el repositorio sin conexion con el "
            "proceso que describen.\n\n"
            "**Que tiene que funcionar:**\n"
            "- Puedo subir un archivo a cualquier proceso del mapa\n"
            "- Al subir elijo el tipo del documento: manual, formulario, registro, "
            "politica o certificado\n"
            "- El documento puede existir en espanol y en ingles\n"
            "- En la vista del proceso aparecen todos sus documentos adjuntos "
            "con opcion de descarga\n\n"
            "**Branch:** `feature/process-documents`\n"
            "**Responsable:** Aquiles"
        ),
    },
    {
        "sprint_name":  "Sprint 4",
        "project_name": "Construir el módulo de Mapas de Proceso BPMN (Módulo 09)",
        "title": "Actualizar versiones de documentos de procesos",
        "description": (
            "**Como sb_owner o admin_sm, quiero subir una nueva version de un documento "
            "de proceso para mantener el historial sin perder los archivos anteriores.**\n\n"
            "Los manuales y formularios cambian con el tiempo. Sin control de versiones, "
            "subir una actualizacion sobreescribe el documento anterior y se pierde "
            "el historial, algo problematico cuando se necesita saber como era el proceso "
            "en una fecha anterior.\n\n"
            "**Que tiene que funcionar:**\n"
            "- Puedo subir una nueva version de un documento existente\n"
            "- La version anterior queda guardada y se puede consultar\n"
            "- El sistema muestra siempre la version mas reciente por defecto\n"
            "- Puedo descargar cualquier version anterior desde el historial\n\n"
            "**Branch:** `feature/process-documents-versioning`\n"
            "**Responsable:** Aquiles"
        ),
    },

    # ==========================================================================
    # SPRINT 5 - Inventario (Modulo 11)
    # ==========================================================================
    {
        "sprint_name":  "Sprint 5",
        "project_name": "Construir el módulo de Inventario (Módulo 11)",
        "title": "Alerta cuando un producto baja del minimo",
        "description": (
            "**Como sb_owner, quiero recibir una alerta en el portal cuando un producto "
            "baje de su cantidad minima para poder reabastecer a tiempo y evitar "
            "quedarme sin existencias.**\n\n"
            "Cada producto tiene definida una cantidad minima. Sin alertas, el dueno "
            "tiene que revisar manualmente el inventario para darse cuenta de que "
            "necesita reponer, lo que en la practica ocurre cuando ya es tarde y "
            "el producto ya se agoto.\n\n"
            "**Que tiene que funcionar:**\n"
            "- Cuando el stock de un producto cae por debajo del minimo definido, "
            "aparece un aviso visible en el portal\n"
            "- El aviso muestra cuales productos estan bajos y cuanto les falta para "
            "llegar al minimo\n"
            "- El aviso desaparece cuando el stock vuelve a estar por encima del minimo\n\n"
            "**Branch:** `feature/inventory-stock-alerts`\n"
            "**Responsable:** Aquiles"
        ),
    },

    # ==========================================================================
    # SPRINT 6 - Tracking (Modulo 12) y QA/Deploy
    # ==========================================================================
    {
        "sprint_name":  "Sprint 6",
        "project_name": "Construir el módulo de Tracking de Entregables (Módulo 12)",
        "title": "Ver entregables en cronograma por fechas",
        "description": (
            "**Como admin_sm o sb_owner, quiero ver los entregables en una vista de "
            "cronograma para entender de un vistazo si el proyecto va en tiempo o "
            "esta atrasado.**\n\n"
            "El tablero muestra el estado de cada entregable pero no las fechas. "
            "Sin un cronograma no es posible saber si el proyecto va adelantado o "
            "atrasado respecto al plan original.\n\n"
            "**Que tiene que funcionar:**\n"
            "- Puedo cambiar la vista de 'tablero' a 'cronograma'\n"
            "- En el cronograma cada entregable aparece como una barra entre su "
            "fecha de inicio y su fecha de fin estimadas\n"
            "- Los entregables completados se ven en verde\n"
            "- Los entregables que ya superaron su fecha de fin sin completarse "
            "se ven en rojo para alertar el atraso\n\n"
            "**Branch:** `feature/tracking-timeline`\n"
            "**Responsable:** Aquiles"
        ),
    },
    {
        "sprint_name":  "Sprint 6",
        "project_name": "QA Final y Despliegue en Producción",
        "title": "Tareas automaticas que corren sin intervencion",
        "description": (
            "**Como sistema, quiero ejecutar tareas de mantenimiento en horarios fijos "
            "para que el portal funcione correctamente sin que nadie tenga que "
            "hacerlo a mano.**\n\n"
            "Algunas cosas del portal no dependen de que alguien haga clic: verificar "
            "si un contrato ya fue firmado, limpiar invitaciones vencidas, alertar "
            "sobre entregables proximos a vencer. Sin tareas programadas, estas cosas "
            "se quedan sin hacer.\n\n"
            "**Que tiene que funcionar:**\n"
            "- El sistema verifica automaticamente cada hora si los contratos enviados "
            "a firma ya fueron firmados y actualiza su estado\n"
            "- Las invitaciones por email que lleven mas de 7 dias sin usarse se "
            "marcan como vencidas automaticamente\n"
            "- Este servicio de tareas programadas esta incluido en el entorno de "
            "produccion y corre sin intervencion manual\n\n"
            "**Branch:** `feature/scheduler-tasks`\n"
            "**Responsable:** Aquiles"
        ),
    },
]


# -----------------------------------------------------------------------------
# MAIN
# -----------------------------------------------------------------------------

def main():
    print("\nSM Portal - Linear Import ADICIONAL v1\n")
    print("Agrega historias faltantes a sprints y modulos existentes.\n")

    team_id     = get_team_id()
    state_id    = get_todo_state(team_id)
    cycle_map   = get_cycles(team_id)
    project_map = get_projects()

    created = 0
    skipped = 0

    for story in MISSING_STORIES:
        sprint_name  = story["sprint_name"]
        project_name = story["project_name"]

        cycle_id = cycle_map.get(sprint_name)
        if not cycle_id:
            print(f"\n  AVISO: Sprint '{sprint_name}' no encontrado - saltando: {story['title']}")
            skipped += 1
            continue

        project_id = project_map.get(project_name)
        if not project_id:
            print(f"\n  AVISO: Modulo '{project_name[:55]}' no encontrado - saltando: {story['title']}")
            skipped += 1
            continue

        print(f"\n  {sprint_name} / {project_name[:55]}...")
        issue_id = create_issue(
            team_id=team_id,
            title=story["title"],
            description=story["description"],
            state_id=state_id,
            cycle_id=cycle_id,
            project_id=project_id,
        )
        if issue_id:
            created += 1

    print(f"\n\n{'=' * 60}")
    print("  Import adicional completo.")
    print(f"  Issues creados : {created}")
    print(f"  Saltados       : {skipped}")
    print(f"{'=' * 60}")
    print()
    print("IMPORTANTE: Regenerar la API key de Linear ahora.")
    print("  Linear > Settings > API > Revocar y recrear la key.")
    print(f"{'=' * 60}\n")


if __name__ == "__main__":
    main()
