"""
SM Portal — Linear Update Script v2
=====================================
Actualiza el backlog de Linear para reflejar los cambios de v1 a v2 del portal.

Ejecutar DESPUES de linear_import_final.py (que ya corrio).
El script de linear_add_missing.py NO se ejecuto — este script lo absorbe.

Operaciones en orden:
  1. ARCHIVAR issues obsoletos (Inventory, Accounting complejo, Assessment 2 BB form)
  2. CREAR issues nuevos de v2 (Assessments rediseñado, Process Maps ampliado,
     Repositories reestructurado, Accounting simplificado, Applications con sector data)
  3. CREAR issues del script "missing" que nunca se ejecuto (9 issues validos)

Uso:
    pip install requests
    python linear_update_v2.py

IMPORTANTE: Actualizar API_KEY con la key vigente de Linear antes de ejecutar.
IMPORTANTE: Regenerar la API key en Linear despues de ejecutar.
"""

import requests
import time

# -----------------------------------------------------------------------------
# CONFIGURACION
# -----------------------------------------------------------------------------

API_KEY = "lin_api_tElh3IJZFhzdRBwS34JHpKk9sWTJxE2A8UpTr0Da"
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
# OBTENER TEAM ID
# -----------------------------------------------------------------------------

def get_team_id():
    data = gql("query { teams { nodes { id name } } }")
    teams = data["data"]["teams"]["nodes"]
    if not teams:
        print("ERROR: No se encontraron equipos en Linear.")
        exit(1)
    team = teams[0]
    print(f"  Team: {team['name']} ({team['id']})")
    return team["id"]


# -----------------------------------------------------------------------------
# OBTENER WORKFLOW STATE (todo/backlog para issues nuevos)
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
            print(f"  Estado para issues nuevos: {s['name']}")
            return s["id"]
    print("ERROR: No se encontro estado 'unstarted' o 'backlog'.")
    exit(1)


# -----------------------------------------------------------------------------
# OBTENER CYCLES (SPRINTS) — mapeo nombre -> id
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
    print(f"  Cycles encontrados: {list(cycle_map.keys())}")
    return cycle_map


# -----------------------------------------------------------------------------
# OBTENER PROJECTS (MODULOS) — mapeo nombre -> id
# -----------------------------------------------------------------------------

def get_projects():
    data = gql("query { projects { nodes { id name } } }")
    projects = data["data"]["projects"]["nodes"]
    project_map = {p["name"]: p["id"] for p in projects}
    print(f"  Projects encontrados: {len(project_map)}")
    return project_map


# -----------------------------------------------------------------------------
# BUSCAR ISSUES POR TITULO EXACTO
# -----------------------------------------------------------------------------

def find_issues_by_title(team_id, title):
    """
    Busca issues cuyo titulo coincide exactamente con el string dado.
    Retorna lista de dicts con id y title.
    """
    query = """
    query($teamId: ID!, $title: String!) {
        issues(filter: {
            team: { id: { eq: $teamId } },
            title: { eq: $title }
        }) {
            nodes { id title }
        }
    }
    """
    data = gql(query, {"teamId": team_id, "title": title})
    return data.get("data", {}).get("issues", {}).get("nodes", [])


# -----------------------------------------------------------------------------
# ARCHIVAR UN ISSUE POR ID
# -----------------------------------------------------------------------------

def archive_issue(issue_id, issue_title):
    """
    Archiva un issue usando la mutation issueArchive.
    Los issues archivados desaparecen del backlog activo pero no se eliminan.
    """
    mutation = """
    mutation($issueId: String!) {
        issueArchive(id: $issueId) {
            success
        }
    }
    """
    data = gql(mutation, {"issueId": issue_id})
    success = data.get("data", {}).get("issueArchive", {}).get("success", False)
    time.sleep(0.35)
    return success


# -----------------------------------------------------------------------------
# CREAR ISSUE
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


# =============================================================================
# PASO 1 — ISSUES A ARCHIVAR
#
# Se buscan por titulo exacto. Si hay mas de uno con el mismo titulo, se
# archivan todos. Si no se encuentra ninguno, se avisa y se continua.
#
# Razon de cada grupo:
#   - Inventory: modulo eliminado completamente en v2 (QBO lo gestiona)
#   - Accounting complejo: OCR/IA/conciliacion/POS eliminados en v2
#   - Assessment BB application: ya no existe como Assessment 2 en v2
# =============================================================================

TITLES_TO_ARCHIVE = [
    # --- Inventory (modulo eliminado en v2) ---
    # Del script original (linear_import_final.py):
    "CRUD de productos con SKU y stock mínimo",
    "Movimientos de inventario con asiento automático",
    # Del script missing (linear_add_missing.py — nunca ejecutado):
    # "Alerta cuando un producto baja del minimo" — NO estaba en Linear, no se archiva

    # --- Accounting complejo (eliminado en v2, reemplazado por QBO + almacenamiento simple) ---
    # Del script original (linear_import_final.py):
    "OCR + IA para extracción de datos contables",
    "Revisión y aprobación de asientos con baja confianza",
    "Conciliación bancaria con matching automático",
    "Contabilidad en solo lectura para Business Bishop",
    "Integración POS (Square, Stripe, Shopify, Clover)",

    # --- Assessment 2 BB application form (ya no existe como Assessment 2 en v2) ---
    # Del script original (linear_import_final.py):
    "Formulario público de postulación de BB",
]


# =============================================================================
# PASO 2 — ISSUES NUEVOS DE v2
#
# Estos issues no existian en el backlog original.
# Se crean en el sprint y modulo (project) indicados.
#
# Formato de cada entry:
#   sprint_name  -> nombre exacto del cycle en Linear
#   project_name -> nombre exacto del project (modulo) en Linear
#   title        -> titulo corto, maximo 7 palabras, sin tecnicismos
#   description  -> historia de usuario completa con criterios de aceptacion
# =============================================================================

NEW_STORIES_V2 = [

    # ==========================================================================
    # SPRINT 3 — Assessments (modulo existente, rediseñado en v2)
    # ==========================================================================

    {
        "sprint_name":  "Sprint 3",
        "project_name": "Construir el módulo de Postulaciones Públicas — Assessments (Módulo 03)",
        "title": "Assessment 1: evaluacion de madurez del negocio",
        "description": (
            "**Como dueño de un pequeño negocio, quiero completar la evaluación de "
            "madurez en 4 etapas para recibir un diagnóstico personalizado de mi negocio "
            "y conocer mi potencial como franquiciador.**\n\n"
            "El Assessment 1 fue rediseñado en v2. Ya no es un formulario plano de "
            "63 preguntas — ahora tiene 4 etapas secuenciales con propósitos distintos:\n"
            "1. **Madurez operativa:** 7 dimensiones + legal + involucramiento del dueño\n"
            "2. **Alineación al programa de franquicias:** evaluación de fit con el modelo SM\n"
            "3. **Simulador Business Bishop:** proyección financiera a 5 años (ver issue aparte)\n"
            "4. **Resultados:** diagnóstico personalizado con recomendaciones\n\n"
            "**Criterios de aceptación:**\n"
            "- El formulario público lleva al usuario por las 4 etapas en orden\n"
            "- Cada etapa muestra el progreso (paso 1 de 4, paso 2 de 4...)\n"
            "- No se puede avanzar a la siguiente etapa sin completar la actual\n"
            "- Al terminar la etapa 4, el usuario puede descargar su reporte en PDF\n"
            "- El admin_sm ve el resultado completo por etapa en el inbox de assessments\n\n"
            "**Branch:** `feature/assessment-1-v2`\n"
            "**Responsable:** Aquiles"
        ),
    },
    {
        "sprint_name":  "Sprint 3",
        "project_name": "Construir el módulo de Postulaciones Públicas — Assessments (Módulo 03)",
        "title": "Simulador Business Bishop con proyeccion a 5 años",
        "description": (
            "**Como dueño de un negocio que completa el Assessment 1, quiero ver una "
            "proyección financiera a 5 años con el modelo Business Bishop para entender "
            "el retorno potencial de tener un inversor en mi negocio.**\n\n"
            "El Simulador BB es la etapa 3 del Assessment 1 rediseñado en v2. "
            "Calcula el retorno estimado para un Business Bishop potencial y la "
            "proyección de crecimiento del negocio con ese capital.\n\n"
            "**Criterios de aceptación:**\n"
            "- El simulador aparece como etapa 3 del Assessment 1 (no es un módulo separado)\n"
            "- El usuario ingresa datos base: ingresos actuales, margen, inversión estimada requerida\n"
            "- El sistema calcula y muestra: valuación del negocio, retorno proyectado del BB "
            "a 1, 3 y 5 años, distribución de capital estimada\n"
            "- Los resultados del simulador se incluyen en el reporte PDF de la etapa 4\n"
            "- El admin_sm puede ver los datos del simulador en el detalle del assessment\n\n"
            "**Branch:** `feature/assessment-bb-simulator`\n"
            "**Responsable:** Aquiles"
        ),
    },
    {
        "sprint_name":  "Sprint 3",
        "project_name": "Construir el módulo de Postulaciones Públicas — Assessments (Módulo 03)",
        "title": "Assessment 3: evaluacion complementaria (placeholder)",
        "description": (
            "**Como dueño de un negocio, quiero acceder a una segunda evaluación "
            "complementaria para obtener información adicional sobre mi potencial de "
            "crecimiento como franquiciador.**\n\n"
            "En v2 el Assessment 3 existe como evaluación pública adicional. "
            "Está en revisión de contenido — el cliente aún no definió las preguntas finales. "
            "Se implementa como placeholder funcional para no bloquear el desarrollo.\n\n"
            "**Criterios de aceptación:**\n"
            "- Existe una página pública `/assessment/3` accesible sin login\n"
            "- Muestra un mensaje claro de 'Próximamente' o 'En revisión'\n"
            "- El admin_sm ve en el inbox que hay un Assessment 3 disponible (aunque esté vacío)\n"
            "- La estructura de BD y endpoints están listos para cuando se defina el contenido\n\n"
            "**Nota técnica:** Reutiliza la misma arquitectura del Assessment 1. "
            "Solo cambia el contenido de las preguntas cuando esté definido.\n\n"
            "**Branch:** `feature/assessment-3-placeholder`\n"
            "**Responsable:** Aquiles"
        ),
    },
    {
        "sprint_name":  "Sprint 3",
        "project_name": "Construir el módulo de Postulaciones Públicas — Assessments (Módulo 03)",
        "title": "Descargar PDF con resultados del assessment",
        "description": (
            "**Como dueño de un negocio que completó el Assessment 1, quiero descargar "
            "mi reporte de resultados en PDF para tenerlo disponible fuera del portal "
            "y compartirlo con mi equipo o asesor.**\n\n"
            "El PDF de resultados es una funcionalidad nueva en v2. "
            "Al terminar la etapa 4 del Assessment 1, el usuario debe poder descargar "
            "su diagnóstico personalizado en un formato portable.\n\n"
            "**Criterios de aceptación:**\n"
            "- Al completar la etapa 4 del Assessment 1 aparece un botón 'Descargar reporte PDF'\n"
            "- El PDF incluye: datos del negocio, puntaje por dimensión de madurez, "
            "resultado del simulador BB, recomendaciones del diagnóstico\n"
            "- El archivo se genera en segundo plano y se descarga automáticamente\n"
            "- El admin_sm también puede descargar el PDF desde el detalle del assessment\n\n"
            "**Nota técnica:** Job `GenerateAssessmentPdf` en cola `pdf-export` usando dompdf. "
            "URL temporal de descarga con Storage::temporaryUrl() por 30 minutos.\n\n"
            "**Branch:** `feature/assessment-pdf-results`\n"
            "**Responsable:** Aquiles"
        ),
    },

    # ==========================================================================
    # SPRINT 4 — Process Maps (modulo existente, ampliado en v2)
    # ==========================================================================

    {
        "sprint_name":  "Sprint 4",
        "project_name": "Construir el módulo de Mapas de Proceso BPMN (Módulo 09)",
        "title": "Tercer nivel de procesos (sub-subproceso)",
        "description": (
            "**Como sb_owner o admin_sm, quiero organizar los procesos en tres niveles "
            "(proceso → subproceso → sub-subproceso) para documentar con mayor detalle "
            "los flujos operativos del negocio.**\n\n"
            "En v1 el árbol era de dos niveles: proceso → subproceso. "
            "En v2 se agrega un tercer nivel: sub-subproceso. Cada nivel tiene su "
            "propio diagrama BPMN y sus propios documentos adjuntos.\n\n"
            "**Criterios de aceptación:**\n"
            "- En la vista del mapa de procesos puedo expandir un subproceso para "
            "ver sus sub-subprocesos\n"
            "- Puedo crear, editar y eliminar sub-subprocesos\n"
            "- Cada sub-subproceso puede tener su propio diagrama BPMN\n"
            "- Cada sub-subproceso puede tener documentos adjuntos (ver issue de adjuntos)\n"
            "- La navegación visual deja claro en qué nivel estoy: "
            "Categoría > Proceso > Subproceso > Sub-subproceso\n\n"
            "**Nota técnica:** Nueva tabla `sub_sub_processes` con FK a `sub_processes`. "
            "Misma estructura que sub_processes pero un nivel más abajo.\n\n"
            "**Branch:** `feature/bpmn-third-level`\n"
            "**Responsable:** Aquiles"
        ),
    },
    {
        "sprint_name":  "Sprint 4",
        "project_name": "Construir el módulo de Mapas de Proceso BPMN (Módulo 09)",
        "title": "Recorrido guiado paso a paso de un proceso",
        "description": (
            "**Como sb_owner o empleado, quiero hacer un recorrido guiado por los pasos "
            "de un proceso para entender exactamente cómo ejecutarlo sin necesidad de "
            "interpretar el diagrama BPMN completo.**\n\n"
            "El diagrama BPMN completo puede ser difícil de leer para usuarios no técnicos. "
            "El walkthrough muestra cada paso del proceso en secuencia, con el diagrama "
            "resaltando el paso actual, facilitando la capacitación y la ejecución operativa.\n\n"
            "**Criterios de aceptación:**\n"
            "- En la vista de un subproceso o sub-subproceso hay un botón 'Iniciar recorrido'\n"
            "- El recorrido muestra los pasos del proceso en orden, uno a la vez\n"
            "- El diagrama BPMN resalta el nodo correspondiente al paso actual\n"
            "- El usuario puede navegar adelante y atrás entre pasos\n"
            "- Al finalizar el recorrido hay una pantalla de confirmación\n"
            "- El recorrido funciona en modo solo-vista, sin poder editar el diagrama\n\n"
            "**Branch:** `feature/bpmn-walkthrough`\n"
            "**Responsable:** Aquiles"
        ),
    },
    {
        "sprint_name":  "Sprint 4",
        "project_name": "Construir el módulo de Mapas de Proceso BPMN (Módulo 09)",
        "title": "Boton Ver Manual desde el diagrama de proceso",
        "description": (
            "**Como sb_owner o empleado, quiero hacer clic en un nodo del diagrama BPMN "
            "y ver el manual asociado a ese paso para consultar el procedimiento exacto "
            "sin salir del diagrama.**\n\n"
            "Los diagramas BPMN tienen documentos de tipo 'manual' adjuntos a procesos "
            "y sub-subprocesos. En v2 se agrega un acceso directo desde el propio nodo "
            "del diagrama para que el usuario pueda consultar el manual sin tener que "
            "navegar al repositorio de documentos.\n\n"
            "**Criterios de aceptación:**\n"
            "- Al hacer clic en un nodo del diagrama BPMN aparece un panel lateral (o tooltip)\n"
            "- Si el nodo tiene un manual adjunto, aparece un botón 'Ver Manual'\n"
            "- Al hacer clic en 'Ver Manual' se abre el documento en una nueva pestaña\n"
            "- Si el nodo no tiene manual adjunto, el botón no aparece\n"
            "- Funciona tanto en el diagrama en español como en inglés\n\n"
            "**Branch:** `feature/bpmn-manual-button`\n"
            "**Responsable:** Aquiles"
        ),
    },

    # ==========================================================================
    # SPRINT 4 — Repositories (modulo existente, reestructurado en v2)
    # ==========================================================================

    {
        "sprint_name":  "Sprint 4",
        "project_name": "Construir el módulo de Repositorio de Documentos (Módulo 08)",
        "title": "Repositorio en 3 secciones: Setup, Documentos y Registros",
        "description": (
            "**Como sb_owner, quiero ver el repositorio organizado en tres secciones "
            "claramente separadas para encontrar rápidamente el tipo de documento "
            "que necesito.**\n\n"
            "En v1 el repositorio tenía dos secciones (setup / proceso). "
            "En v2 se reestructura en 3 tabs con propósitos y contenidos distintos:\n"
            "- **Company Setup:** documentos iniciales del cliente (legales, RRHH, "
            "certificados, marketing, SOPs)\n"
            "- **Process Documents:** manuales y formatos del mapa de procesos, "
            "organizados en árbol por categoría → proceso → subproceso\n"
            "- **Records by Process:** registros llenados y subidos, organizados "
            "por proceso con botón 'Añadir registro'\n\n"
            "**Criterios de aceptación:**\n"
            "- El repositorio muestra 3 tabs bien diferenciadas\n"
            "- La tab 'Company Setup' lista documentos organizados por categoría\n"
            "- La tab 'Process Documents' muestra el árbol del mapa de procesos "
            "con los documentos adjuntos en cada nivel\n"
            "- La tab 'Records by Process' muestra los registros agrupados por proceso\n"
            "- Puedo subir documentos a cada sección con su tipo correcto\n\n"
            "**Nota técnica:** Actualizar `repository_documents` con tipos: setup, "
            "process_doc, record. Agregar FK opcional a proceso/subproceso para la tab 3.\n\n"
            "**Branch:** `feature/repository-3-tabs`\n"
            "**Responsable:** Aquiles"
        ),
    },
    {
        "sprint_name":  "Sprint 4",
        "project_name": "Construir el módulo de Repositorio de Documentos (Módulo 08)",
        "title": "Subir registros completados vinculados a un proceso",
        "description": (
            "**Como sb_owner o sb_employee, quiero subir un registro completado y "
            "vincularlo a un proceso específico para que quede organizado en la sección "
            "correcta del repositorio y sea fácil de consultar después.**\n\n"
            "Los registros son formularios u hojas llenados durante la operación del negocio "
            "(por ejemplo: checklist diario de apertura, hoja de control de temperatura). "
            "En v2 la tab 'Records by Process' del repositorio permite subirlos y "
            "organizarlos por el proceso al que pertenecen.\n\n"
            "**Criterios de aceptación:**\n"
            "- En la tab 'Records by Process' hay un botón 'Añadir registro'\n"
            "- Al subir un registro debo seleccionar a qué proceso/subproceso pertenece\n"
            "- El registro queda visible en la sección del proceso correspondiente\n"
            "- Puedo descargar cualquier registro subido anteriormente\n"
            "- Se guarda quién subió el registro y en qué fecha\n\n"
            "**Branch:** `feature/repository-records`\n"
            "**Responsable:** Aquiles"
        ),
    },

    # ==========================================================================
    # SPRINT 5 — Accounting simplificado (reemplaza el modulo complejo de v1)
    # ==========================================================================

    {
        "sprint_name":  "Sprint 5",
        "project_name": "Construir el módulo de Contabilidad y Finanzas con IA (Módulo 10)",
        "title": "Repositorio simple de facturas y extractos bancarios",
        "description": (
            "**Como sb_owner, quiero subir facturas y estados de cuenta al portal "
            "para tenerlos centralizados y disponibles para mi contador, sin necesidad "
            "de enviarlos por email o usar carpetas compartidas.**\n\n"
            "En v2 el módulo de contabilidad se simplifica drásticamente. "
            "Ya no hay OCR, ni asientos automáticos, ni conciliación bancaria — "
            "todo eso lo gestiona QuickBooks Online. El portal solo almacena los "
            "documentos fuente de forma organizada.\n\n"
            "**Criterios de aceptación:**\n"
            "- Puedo subir archivos (PDF, imagen) de facturas y estados de cuenta\n"
            "- Los documentos se organizan por tipo (factura / extracto bancario) y fecha\n"
            "- Puedo agregar notas a cada documento al subirlo\n"
            "- Puedo descargar cualquier documento subido anteriormente\n"
            "- El BB asignado a la empresa puede ver y descargar estos documentos "
            "en modo solo lectura\n\n"
            "**Nota técnica:** Simplificar `financial_documents` — solo almacenamiento. "
            "Sin campos de procesamiento IA, sin `ai_confidence`, sin `journal_entry_id`.\n\n"
            "**Branch:** `feature/accounting-simple-repository`\n"
            "**Responsable:** Aquiles"
        ),
    },
    {
        "sprint_name":  "Sprint 5",
        "project_name": "Construir el módulo de Contabilidad y Finanzas con IA (Módulo 10)",
        "title": "Acceso directo a QuickBooks Online desde el portal",
        "description": (
            "**Como sb_owner, quiero conectar mi cuenta de QuickBooks Online al portal "
            "y acceder a ella directamente desde aquí para no tener que alternar entre "
            "múltiples herramientas.**\n\n"
            "En v2 QuickBooks Online es el sistema de contabilidad real. "
            "El portal actúa como punto de entrada y almacén de documentos fuente, "
            "no como sistema contable. La integración con QBO permite al cliente "
            "gestionar sus finanzas sin salir del ecosistema del portal.\n\n"
            "**Criterios de aceptación:**\n"
            "- En el módulo de contabilidad hay una sección 'Conectar QuickBooks Online'\n"
            "- Al conectar, el sistema guarda el vínculo con la cuenta QBO del cliente "
            "(autenticación OAuth2 de QuickBooks)\n"
            "- Una vez conectado, hay un botón 'Abrir QuickBooks' que lleva directamente "
            "al panel de QBO del cliente en una nueva pestaña\n"
            "- Si la empresa no tiene QBO conectado, se muestra un mensaje explicativo "
            "y el botón de conectar\n"
            "- Solo sb_owner puede conectar/desconectar la cuenta QBO\n\n"
            "**Branch:** `feature/accounting-qbo-link`\n"
            "**Responsable:** Aquiles"
        ),
    },

    # ==========================================================================
    # SPRINT 5 o 6 — Applications (modulo existente, nueva funcionalidad en v2)
    # Nota: se coloca en Sprint 6 dentro del proyecto de Assessments para no
    # crear un proyecto nuevo innecesario — corresponde a la gestion de
    # aplicaciones que hace el superadmin al revisar una postulacion.
    # ==========================================================================

    {
        "sprint_name":  "Sprint 6",
        "project_name": "Construir el módulo de Postulaciones Públicas — Assessments (Módulo 03)",
        "title": "Informacion del sector del cliente en su aplicacion",
        "description": (
            "**Como superadmin, quiero ver información actualizada del sector del "
            "negocio postulante al revisar una aplicación para tomar decisiones más "
            "informadas sin tener que buscar esa información manualmente.**\n\n"
            "En v2 se agrega la integración con una fuente externa de datos sectoriales. "
            "Cuando el superadmin abre el detalle de una postulación, el sistema trae "
            "automáticamente información relevante del sector del negocio (industria, "
            "tendencias, datos de mercado).\n\n"
            "**Criterios de aceptación:**\n"
            "- En el detalle de una postulación hay una sección 'Información del sector'\n"
            "- La información se carga automáticamente al abrir la postulación, "
            "sin que el superadmin tenga que hacer nada\n"
            "- Si la API externa no responde, la sección muestra un mensaje discreto "
            "de error y el resto de la postulación sigue visible\n"
            "- Solo el superadmin ve esta sección\n"
            "- La información se actualiza cada vez que se abre el detalle "
            "(no se cachea indefinidamente)\n\n"
            "**Nota técnica:** Job `FetchSectorData` con la API externa definida por el cliente. "
            "Cache por sector en Redis por 24 horas para evitar llamadas repetidas.\n\n"
            "**Branch:** `feature/applications-sector-data`\n"
            "**Responsable:** Aquiles"
        ),
    },
]


# =============================================================================
# PASO 3 — ISSUES DEL SCRIPT "MISSING" QUE NUNCA SE EJECUTO
#
# Del archivo linear_add_missing.py original (sin ejecutar).
# Se excluye "Alerta cuando un producto baja del minimo" porque Inventory
# fue eliminado en v2.
# Los demas 9 issues se incluyen respetando sprint y modulo originales.
# =============================================================================

MISSING_STORIES = [

    # --- Sprint 1 ---
    {
        "sprint_name":  "Sprint 1",
        "project_name": "Levantar el entorno de desarrollo con Docker",
        "title": "Instalar y configurar el proyecto backend",
        "description": (
            "**Como desarrollador, quiero tener el proyecto backend instalado y listo "
            "para poder empezar a escribir código sin perder tiempo configurando.**\n\n"
            "El entorno de Docker existe pero el proyecto en sí no está inicializado. "
            "Sin este paso, ningún trabajo de backend puede comenzar.\n\n"
            "**Criterios de aceptación:**\n"
            "- El framework backend instalado con todos sus paquetes: autenticación, "
            "gestión de roles, conector de firma electrónica\n"
            "- Herramientas de calidad de código configuradas y listas\n"
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
            "para poder empezar a construir pantallas sin configuración adicional.**\n\n"
            "El Docker existe pero el proyecto de pantallas no está inicializado. "
            "Sin este paso ninguna interfaz puede construirse.\n\n"
            "**Criterios de aceptación:**\n"
            "- El framework de pantallas instalado con todas las dependencias: manejo "
            "de estado global, navegación entre páginas, estilos visuales, "
            "formularios y validaciones\n"
            "- Revisión automática de estilo de código configurada y funcionando\n"
            "- Archivo de variables de entorno de ejemplo con todo documentado\n"
            "- Levantar en modo desarrollo y generar el build de producción funcionan "
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
            "**Como desarrollador, quiero que al subir un cambio de código se ejecuten "
            "automáticamente las revisiones de calidad para detectar errores antes "
            "de que lleguen al proyecto principal.**\n\n"
            "Sin un proceso automático, los errores solo se detectan manualmente. "
            "Código con problemas puede entrar al proyecto sin que nadie se dé cuenta.\n\n"
            "**Criterios de aceptación:**\n"
            "- Al abrir o actualizar una solicitud de cambio se ejecutan automáticamente: "
            "revisión de estilo del backend, revisión del frontend y pruebas del sistema\n"
            "- Una solicitud con errores no puede fusionarse con el proyecto principal\n"
            "- El proceso corre automáticamente sin configuración manual cada vez\n\n"
            "**Branch:** `infra/ci-pipeline`\n"
            "**Responsable:** Aquiles"
        ),
    },

    # --- Sprint 2 ---
    {
        "sprint_name":  "Sprint 2",
        "project_name": "Construir el módulo de Franquicias y Empresas (Módulo 04)",
        "title": "Vincular un inversor (BB) a una empresa",
        "description": (
            "**Como admin_sm, quiero poder asignar o cambiar el Business Bishop de una "
            "empresa en cualquier momento para mantener actualizado quién está "
            "patrocinando a cada cliente.**\n\n"
            "Hoy el vínculo entre el inversor y la empresa solo se crea automáticamente "
            "al cerrar el trato inicial. Si el inversor cambia, o si se quiere asignar "
            "uno a una empresa ya existente, no hay forma de hacerlo desde el portal.\n\n"
            "**Criterios de aceptación:**\n"
            "- En la ficha de una empresa puedo ver quién es el Business Bishop actual\n"
            "- Puedo asignar un BB a una empresa que todavía no tiene uno\n"
            "- Puedo cambiar el BB asignado si la situación cambia\n"
            "- Solo el admin_sm y el superadmin pueden hacer esta asignación\n"
            "- Una vez asignado, el BB ve contabilidad y contratos de esa empresa "
            "en modo solo lectura\n\n"
            "**Branch:** `feature/bb-assignment-management`\n"
            "**Responsable:** Aquiles"
        ),
    },

    # --- Sprint 3 ---
    {
        "sprint_name":  "Sprint 3",
        "project_name": "Construir el módulo de Postulaciones Públicas — Assessments (Módulo 03)",
        "title": "Exportar reporte PDF de una postulacion",
        "description": (
            "**Como admin_sm, quiero poder descargar el reporte completo de una "
            "postulación en PDF para compartirlo con el equipo o archivarlo fuera "
            "del portal.**\n\n"
            "El portal muestra los puntajes y el detalle de cada evaluación en pantalla "
            "pero no hay forma de llevarse esa información en un formato portable. "
            "El PDF permite compartir el reporte en reuniones o enviarlo por correo "
            "sin necesidad de acceder al portal.\n\n"
            "**Criterios de aceptación:**\n"
            "- En el detalle de una postulación hay un botón para descargar el reporte\n"
            "- El PDF incluye los datos del postulante, el puntaje por cada área "
            "evaluada y la decisión que se tomó\n"
            "- El archivo se genera y descarga en pocos segundos\n\n"
            "**Branch:** `feature/assessment-pdf-export`\n"
            "**Responsable:** Aquiles"
        ),
    },

    # --- Sprint 4 (BPMN) ---
    {
        "sprint_name":  "Sprint 4",
        "project_name": "Construir el módulo de Mapas de Proceso BPMN (Módulo 09)",
        "title": "Adjuntar documentos a un proceso operativo",
        "description": (
            "**Como sb_owner o admin_sm, quiero poder adjuntar documentos a cada proceso "
            "del mapa operativo para que el equipo encuentre todo en un solo lugar.**\n\n"
            "Cada proceso tiene documentos asociados: el manual de cómo realizarlo, "
            "el formulario a completar, el certificado requerido, etc. Sin esta función, "
            "esos documentos quedan sueltos en el repositorio sin conexión con el "
            "proceso que describen.\n\n"
            "**Criterios de aceptación:**\n"
            "- Puedo subir un archivo a cualquier proceso del mapa\n"
            "- Al subir elijo el tipo del documento: manual, formulario, registro, "
            "política o certificado\n"
            "- El documento puede existir en español y en inglés\n"
            "- En la vista del proceso aparecen todos sus documentos adjuntos "
            "con opción de descarga\n\n"
            "**Branch:** `feature/process-documents`\n"
            "**Responsable:** Aquiles"
        ),
    },
    {
        "sprint_name":  "Sprint 4",
        "project_name": "Construir el módulo de Mapas de Proceso BPMN (Módulo 09)",
        "title": "Actualizar versiones de documentos de procesos",
        "description": (
            "**Como sb_owner o admin_sm, quiero subir una nueva versión de un documento "
            "de proceso para mantener el historial sin perder los archivos anteriores.**\n\n"
            "Los manuales y formularios cambian con el tiempo. Sin control de versiones, "
            "subir una actualización sobreescribe el documento anterior y se pierde "
            "el historial.\n\n"
            "**Criterios de aceptación:**\n"
            "- Puedo subir una nueva versión de un documento existente\n"
            "- La versión anterior queda guardada y se puede consultar\n"
            "- El sistema muestra siempre la versión más reciente por defecto\n"
            "- Puedo descargar cualquier versión anterior desde el historial\n\n"
            "**Branch:** `feature/process-documents-versioning`\n"
            "**Responsable:** Aquiles"
        ),
    },

    # --- Sprint 6 (Tracking) ---
    {
        "sprint_name":  "Sprint 6",
        "project_name": "Construir el módulo de Tracking de Entregables (Módulo 12)",
        "title": "Ver entregables en cronograma por fechas",
        "description": (
            "**Como admin_sm o sb_owner, quiero ver los entregables en una vista de "
            "cronograma para entender de un vistazo si el proyecto va en tiempo o "
            "está atrasado.**\n\n"
            "El tablero muestra el estado de cada entregable pero no las fechas. "
            "Sin un cronograma no es posible saber si el proyecto va adelantado o "
            "atrasado respecto al plan original.\n\n"
            "**Criterios de aceptación:**\n"
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

    # --- Sprint 6 (QA/Deploy) ---
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
            "sobre entregables próximos a vencer. Sin tareas programadas, estas cosas "
            "se quedan sin hacer.\n\n"
            "**Criterios de aceptación:**\n"
            "- El sistema verifica automáticamente cada hora si los contratos enviados "
            "a firma ya fueron firmados y actualiza su estado\n"
            "- Las invitaciones por email que lleven más de 7 días sin usarse se "
            "marcan como vencidas automáticamente\n"
            "- Este servicio de tareas programadas está incluido en el entorno de "
            "producción y corre sin intervención manual\n\n"
            "**Branch:** `feature/scheduler-tasks`\n"
            "**Responsable:** Aquiles"
        ),
    },
]


# =============================================================================
# MAIN
# =============================================================================

def main():
    print("\n" + "=" * 65)
    print("  SM Portal — Linear Update v2")
    print("  Operaciones: Archivar obsoletos | Crear nuevos v2 | Crear missing")
    print("=" * 65 + "\n")

    # -- Setup inicial --
    team_id     = get_team_id()
    state_id    = get_todo_state(team_id)
    cycle_map   = get_cycles(team_id)
    project_map = get_projects()

    archived_count = 0
    archive_not_found = 0
    created_v2 = 0
    skipped_v2 = 0
    created_missing = 0
    skipped_missing = 0

    # =========================================================================
    # PASO 1 — ARCHIVAR ISSUES OBSOLETOS
    # =========================================================================
    print("\n" + "=" * 65)
    print("  PASO 1: Archivando issues obsoletos")
    print("=" * 65)
    print(f"  Issues a buscar y archivar: {len(TITLES_TO_ARCHIVE)}\n")

    for title in TITLES_TO_ARCHIVE:
        issues = find_issues_by_title(team_id, title)
        if not issues:
            print(f"  AVISO: No encontrado (puede que ya este archivado): '{title[:65]}'")
            archive_not_found += 1
            continue

        for issue in issues:
            success = archive_issue(issue["id"], issue["title"])
            if success:
                print(f"  ARCHIVADO: '{issue['title'][:75]}'")
                archived_count += 1
            else:
                print(f"  ERROR al archivar: '{issue['title'][:65]}'")

    # =========================================================================
    # PASO 2 — CREAR ISSUES NUEVOS DE v2
    # =========================================================================
    print("\n" + "=" * 65)
    print("  PASO 2: Creando issues nuevos de v2")
    print("=" * 65)
    print(f"  Issues a crear: {len(NEW_STORIES_V2)}\n")

    for story in NEW_STORIES_V2:
        sprint_name  = story["sprint_name"]
        project_name = story["project_name"]

        cycle_id = cycle_map.get(sprint_name)
        if not cycle_id:
            print(f"\n  AVISO: Sprint '{sprint_name}' no encontrado — saltando: {story['title']}")
            skipped_v2 += 1
            continue

        project_id = project_map.get(project_name)
        if not project_id:
            print(f"\n  AVISO: Modulo '{project_name[:55]}' no encontrado — saltando: {story['title']}")
            skipped_v2 += 1
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
            created_v2 += 1

    # =========================================================================
    # PASO 3 — CREAR ISSUES DEL SCRIPT "MISSING" QUE NUNCA SE EJECUTO
    # =========================================================================
    print("\n" + "=" * 65)
    print("  PASO 3: Creando issues faltantes del script 'missing' original")
    print("=" * 65)
    print(f"  Issues a crear: {len(MISSING_STORIES)}")
    print(f"  (Excluidos: 'Alerta de inventario' — modulo eliminado en v2)\n")

    for story in MISSING_STORIES:
        sprint_name  = story["sprint_name"]
        project_name = story["project_name"]

        cycle_id = cycle_map.get(sprint_name)
        if not cycle_id:
            print(f"\n  AVISO: Sprint '{sprint_name}' no encontrado — saltando: {story['title']}")
            skipped_missing += 1
            continue

        project_id = project_map.get(project_name)
        if not project_id:
            print(f"\n  AVISO: Modulo '{project_name[:55]}' no encontrado — saltando: {story['title']}")
            skipped_missing += 1
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
            created_missing += 1

    # =========================================================================
    # RESUMEN FINAL
    # =========================================================================
    total_created = created_v2 + created_missing
    total_skipped = skipped_v2 + skipped_missing

    print("\n\n" + "=" * 65)
    print("  RESUMEN DE EJECUCION")
    print("=" * 65)
    print(f"\n  PASO 1 — Archivar obsoletos:")
    print(f"    Archivados correctamente : {archived_count}")
    print(f"    No encontrados (ya arch.): {archive_not_found}")
    print(f"\n  PASO 2 — Issues nuevos v2:")
    print(f"    Creados  : {created_v2}")
    print(f"    Saltados : {skipped_v2}")
    print(f"\n  PASO 3 — Issues 'missing' originales:")
    print(f"    Creados  : {created_missing}")
    print(f"    Saltados : {skipped_missing}")
    print(f"\n  TOTAL creados : {total_created}")
    print(f"  TOTAL saltados: {total_skipped}")
    print("\n" + "=" * 65)
    print()
    print("  IMPORTANTE: Regenerar la API key de Linear ahora.")
    print("  Linear > Settings > API > Revocar y recrear la key.")
    print("=" * 65 + "\n")


if __name__ == "__main__":
    main()
