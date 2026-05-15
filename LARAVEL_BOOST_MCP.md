# laravel-boost MCP Server

A custom MCP server for the SM Portal that gives AI agents structured access to the Laravel backend — models, routes, DB schema, quality tools, and security context — all in a single call instead of reading dozens of files.

---

## Setup

The server is already registered in `.mcp.json`. After each build you must restart Claude Code for the new `dist/index.js` to be picked up.

```bash
cd mcp-servers/laravel-boost
npm install          # first time only
npm run build        # compile TypeScript → dist/
```

Restart Claude Code (close + reopen the window / terminal session).

---

## Quick verification

```bash
# List all 10 registered tools
echo '{"jsonrpc":"2.0","id":1,"method":"tools/list","params":{}}' \
  | node mcp-servers/laravel-boost/dist/index.js

# Test project_map (no Docker needed)
echo '{"jsonrpc":"2.0","id":1,"method":"tools/call","params":{"name":"project_map","arguments":{"section":"models"}}}' \
  | node mcp-servers/laravel-boost/dist/index.js
```

---

## Tools Reference

Every tool returns the same response envelope:

```json
{
  "success": true,
  "source": "docker | files | cache | mixed",
  "cached": false,
  "timestamp": "2026-01-01T00:00:00.000Z",
  "data": { ... },
  "warnings": ["optional non-fatal messages"],
  "metadata": { "executionMs": 42 }
}
```

---

### 1. `project_map` — Start here

Returns the entire backend structure in one call. No Docker needed.

**Params**

| Param | Type | Default | Options |
|-------|------|---------|---------|
| `section` | string | `"all"` | `models` `services` `controllers` `requests` `policies` `resources` `dependencies` `all` |

**Examples**

```
project_map                          → everything (models + services + controllers + ...)
project_map { section: "models" }    → all Eloquent models with fillable, casts, relationships
project_map { section: "services" }  → all services with methods, transaction usage, imports
project_map { section: "controllers" } → controller → service mapping + policy usage
project_map { section: "dependencies" } → Controller → Service → Model chains
```

**When to use:** Before reading any PHP file. One call replaces ~40 file reads.

---

### 2. `describe_model`

Full detail on one model. Merges file-parsed data with live `php artisan model:show` output (DB columns + observers) when Docker is running.

**Params**

| Param | Required | Example |
|-------|----------|---------|
| `model` | yes | `"User"`, `"Franchise"`, `"Company"` |

**Example**

```
describe_model { model: "User" }
```

Returns: fillable, hidden, casts, traits, relationships, scopes, and (with Docker) DB attributes + observers.

---

### 3. `list_routes`

All 55 registered API routes. Uses `php artisan route:list --json` (Docker), falls back to parsing `routes/api.php`.

**Params**

| Param | Required | Description |
|-------|----------|-------------|
| `filter` | no | Case-insensitive substring match on URI or controller name |

**Examples**

```
list_routes                                 → all 55 routes
list_routes { filter: "invitation" }        → invitation-related routes only
list_routes { filter: "FranchiseController" } → routes for that controller
```

---

### 4. `db_schema`

Table list or column detail. Uses `php artisan db:show / db:table` (Docker), falls back to parsing migration files.

**Params**

| Param | Required | Description |
|-------|----------|-------------|
| `table` | no | Table name (omit for all tables) |

**Examples**

```
db_schema                          → all tables
db_schema { table: "users" }       → columns with types, nullable, defaults
db_schema { table: "franchises" }
```

---

### 5. `run_pint`

Laravel Pint code style checker. **Default is dry-run** — no changes made.

**Params**

| Param | Type | Default | Description |
|-------|------|---------|-------------|
| `fix` | boolean | `false` | `true` = auto-fix, `false` = dry-run only |
| `path` | string | all | File or directory relative to backend root |

**Examples**

```
run_pint                                              → check all (dry-run)
run_pint { fix: true }                                → auto-fix all
run_pint { path: "app/Services/InvitationService.php" } → check one file
run_pint { fix: true, path: "app/Http/Controllers" }  → fix one directory
```

Requires Docker (`sm_backend` container running).

---

### 6. `run_phpstan`

PHPStan static analysis (level 5). Returns structured JSON results.

**Params**

| Param | Required | Default | Description |
|-------|----------|---------|-------------|
| `path` | no | `"app/"` | File or directory to analyse |

**Examples**

```
run_phpstan                                      → analyse all of app/
run_phpstan { path: "app/Services" }             → services only
run_phpstan { path: "app/Http/Controllers/Api/FranchiseController.php" }
```

Requires Docker. Can be slow on full `app/` (~20–30s).

---

### 7. `run_tests`

Runs PHPUnit tests via `php artisan test`. Returns exit code + full output.

**Params**

| Param | Required | Description |
|-------|----------|-------------|
| `file` | no | Path to test file (e.g. `tests/Feature/FranchiseTest.php`) |
| `filter` | no | Method name filter (passed to `--filter`) |
| `suite` | no | `"Feature"` or `"Unit"` |

**Examples**

```
run_tests                                                     → full suite
run_tests { suite: "Feature" }                                → Feature tests only
run_tests { file: "tests/Feature/InvitationTest.php" }        → one file
run_tests { filter: "test_admin_can_create_franchise" }       → one test method
run_tests { file: "tests/Feature/FeedControllerTest.php", filter: "react" }
```

Requires Docker.

---

### 8. `artisan_command`

Safe escape hatch for allowlisted Artisan commands. Destructive commands are blocked.

**Allowed commands:**

`route:list` `model:show` `db:table` `db:show` `make:model` `make:controller` `make:request` `make:resource` `make:policy` `make:migration` `make:test` `migrate:status` `config:show` `about` `schedule:list` `queue:failed`

**Blocked:** `migrate` `db:wipe` `db:seed` `down` `up` `cache:clear` `config:clear` `tinker` `serve`

**Examples**

```
artisan_command { command: "about" }
artisan_command { command: "queue:failed" }
artisan_command { command: "migrate:status" }
artisan_command { command: "make:model Event -m" }
artisan_command { command: "make:controller Api/EventController --api" }
artisan_command { command: "model:show User --json" }
```

Requires Docker.

---

### 9. `get_security_context`

Returns the 15 finalized security decisions from CLAUDE.md. No Docker needed.

**Use this before suggesting any security-related change.** These decisions are final — do not re-flag them.

**Params**

| Param | Required | Description |
|-------|----------|-------------|
| `topic` | no | Filter keyword (e.g. `"token"`, `"rate-limiting"`, `"mass-assignment"`, `"email"`) |

**Examples**

```
get_security_context                           → all 15 decisions
get_security_context { topic: "token" }        → token-related decisions
get_security_context { topic: "rate-limiting" }
get_security_context { topic: "mass-assignment" }
get_security_context { topic: "email" }
```

---

### 10. `environment_health`

Checks the health of all Docker services in one call. Run this when something seems broken.

**Params**

| Param | Required | Options |
|-------|----------|---------|
| `check` | no | `all` `docker` `database` `redis` `queue` `api` |

**Checks performed:**

| Check | What it does |
|-------|-------------|
| `docker` | Container status for all 6 services (running/stopped) |
| `database` | `pg_isready` inside `sm_postgres` |
| `redis` | `redis-cli ping` inside `sm_redis` |
| `queue` | `queue:failed` count inside `sm_backend` |
| `api` | HTTP GET `http://localhost/api/v1/ping` |

**Overall result:** `healthy` / `degraded` / `down`

**Examples**

```
environment_health                       → full check (all subsystems)
environment_health { check: "docker" }   → container status only (fastest)
environment_health { check: "database" } → PostgreSQL only
environment_health { check: "api" }      → end-to-end API reachability
```

---

## Recommended workflow for new tasks

```
1. environment_health                    # Is everything running?
2. project_map { section: "all" }        # Understand the backend structure
3. get_security_context                  # Load security decisions before touching auth/invitations
4. describe_model { model: "X" }         # Drill into a specific model if needed
5. list_routes { filter: "keyword" }     # Find relevant routes
6. db_schema { table: "x" }             # Verify DB structure
   ... implement changes ...
7. run_pint                              # Check style (dry-run)
8. run_phpstan                           # Static analysis
9. run_tests { file: "..." }             # Run relevant tests
10. run_pint { fix: true }               # Auto-fix style if needed
```

---

## Updating the server

After modifying any TypeScript file in `mcp-servers/laravel-boost/src/`:

```bash
cd mcp-servers/laravel-boost
npm run build
```

Then restart Claude Code.

To run tests:

```bash
npm test            # single run
npm run test:watch  # watch mode
```

---

## Config

All container names, paths, and allowlists live in [mcp-servers/laravel-boost/boost.config.json](mcp-servers/laravel-boost/boost.config.json). Edit this file if you rename containers or move directories — no TypeScript rebuild needed for config-only changes.

---

## Troubleshooting

| Problem | Fix |
|---------|-----|
| Tools don't appear in Claude Code | Rebuild (`npm run build`) then restart Claude Code |
| Docker tools return "container not running" | `docker compose up -d` |
| `project_map` returns empty arrays | Check that `backend/app/Models/` path is correct in `boost.config.json` |
| PHPStan times out | Pass a specific `path` instead of scanning all of `app/` |
| Tests fail after code changes | Run `npm run build` before running tests |
