# SM Portal — Codebase Guidelines

## Stack Summary

| Layer | Tech | Version |
|-------|------|---------|
| Backend | Laravel | 12 (PHP 8.4) |
| Frontend | React | 18.3 |
| Database | PostgreSQL | 16 |
| Cache/Queue | Redis | 7 |
| Auth | Sanctum (tokens) + Spatie Permissions |  |
| State | Zustand | 5.0 |
| Routing | React Router | 7.x |
| Styling | Tailwind CSS | 4.x |
| i18n | i18next | 26.x (EN/ES) |
| Build | Vite | 5.x (frontend), Composer (backend) |
| Testing | PHPUnit 11 (backend), Vitest 4 (frontend) |  |
| Docs | l5-swagger (OpenAPI decorators on controllers) |  |
| Deployment | Docker Compose (local), Railway (prod) |  |

---

## Backend Architecture

### Request Flow
```
Route (api.php) → Middleware → Controller → Service → Model/Eloquent → Resource → JSON
```
Controllers are thin: validate via FormRequest, delegate to Service, return Resource.

### Directory Map
```
app/
├── Enums/          Role (final class w/ constants), ReactionEmoji (PHP enum)
├── Events/         FranchiseStatusChanged
├── Helpers/        StringHelper (maskEmail)
├── Http/
│   ├── Controllers/Api/   9 controllers
│   ├── Middleware/         EnsureModulePermission, TrackUserPresence
│   ├── Requests/          ~20 FormRequests across 8 domains
│   └── Resources/         CompanyResource, FranchiseResource, InvitationResource, UserProfileResource
├── Jobs/           MarkInvitationEmailSent (sm_queue)
├── Models/         User, Franchise, Company, Post, PostInteraction, ProcessMap, BbAssignment, UserPermission, + more
├── Notifications/  UserInvitationNotification (queued mail)
├── Policies/       FranchisePolicy, CompanyPolicy, UserPolicy, BbAssignmentPolicy
├── Providers/      AppServiceProvider (policies, events, rate limiters, HTTPS)
└── Services/       AuthService, FranchiseService, CompanyService, InvitationService, FeedService, BbAssignmentService, DashboardService
```

### Route Structure (routes/api.php)
All routes prefixed `/api/v1` (set in bootstrap/app.php).

**Public:**
- `POST /auth/login` (throttle:login — 5/min per email+IP)
- `GET /invitations/{token}/verify` (throttle:invitation — 10/min per IP)
- `POST /invitations/{token}/accept` (throttle:invitation)
- `GET /ping`, `GET /health`

**Protected (auth:sanctum):**
- Auth: `GET /auth/me`, `POST /auth/logout`
- Franchises: apiResource + `PATCH toggleStatus`, `GET {franchise}/members`
- Companies: apiResource + `POST closeDeal`
- BB Assignments: `POST /bb-assignments`, `DELETE /bb-assignments/{id}`
- System Admins: index/store/update/destroy (superadmin only)
- Invitations: index/store + `POST {id}/resend` + `DELETE {id}` (revoke)
- Profile: show/update/updatePassword/uploadAvatar
- Feed: posts/store/update/destroy/react/comments/addComment/deleteComment + presence
- Dashboard: 8 aggregate endpoints (kpis, feed, events, tracking, contracts, documents, processMaps)

**Convention:** Custom sub-routes BEFORE `apiResource()` to avoid wildcard capture.

### Controllers (12)
| Controller | Service | Key Pattern |
|---|---|---|
| AuthController | AuthService | Token gen, single-session logout |
| FranchiseController | FranchiseService | Policy auth, event dispatch |
| FranchiseMemberController | FranchiseMemberService | Franchise member listing |
| CompanyController | CompanyService | Transaction-wrapped closeDeal |
| InvitationController | InvitationService | Token lifecycle, lockForUpdate |
| FeedController | FeedService | File uploads, emoji reactions |
| NewsController | AiNewsService + RssNewsService | AI news fetch/publish/reject |
| BbAssignmentController | BbAssignmentService | Unique constraint enforcement |
| SystemAdminController | (direct User ops) | Module permission sync |
| ProfileController | (direct) | Avatar upload w/ disk storage |
| DashboardController | DashboardService | Multi-query aggregation |
| EventController | EventService | Calendar events CRUD + scoping |

### Services (11) — Key Patterns
1. **Scope resolution by role**: superadmin → no filter; admin_sm → franchise-scoped; SB/BB → company-scoped
2. **DB::transaction()** wraps all multi-step writes
3. **lockForUpdate()** for pessimistic concurrency (toggleStatus, accept invitation)
4. **Event dispatch after commit** (FranchiseStatusChanged)
5. **Delta arithmetic** for counts (FeedService.react: ±1 instead of COUNT(*))
6. **Logging** via `Log::info()` for audit trails

### Models — Key Relationships
- **User** → belongsTo Franchise (sm_franchise_id), optionally Company, optionally SubFranchise. Has invitation fields (token, expires, accepted_at, inviter_id). SoftDeletes.
- **Franchise** → hasMany Users, Companies. Types: SM franchise vs sub-franchise. SoftDeletes.
- **Company** → belongsTo Franchise. Has exactly 2 ProcessMaps (franquiciadora + franquiciada, auto-created). SoftDeletes.
- **Post** → belongsTo author (User), franchise. Visibility: global/franchise/company. SoftDeletes.
- **BbAssignment** → unique on company_id (1 BB per company)
- **UserPermission** → per-user module access (can_read, can_write)

### Policies (6)
- FranchisePolicy, CompanyPolicy, UserPolicy, BbAssignmentPolicy, EventPolicy, NewsArticlePolicy
- Registered in AppServiceProvider via `Gate::policy()`
- Pattern: superadmin gets all; admin_sm gets franchise-scoped; others restricted

### Custom Middleware (2)
- **EnsureModulePermission**: checks user_permissions table, superadmin bypass, 403 on deny
- **TrackUserPresence**: updates last_seen_at, throttled to 1 write/60s

### API Response Format
```json
{ "success": true/false, "data": { ... }, "message": "..." }
```
All shaped through API Resources.

### Queue
- Driver: Redis
- Queues: `sm_queue` (invitations), `news` (RSS + AI jobs), `default` (general)
- Worker container: `--sleep=3 --tries=3 --max-time=3600`
- Scheduler container: `php artisan schedule:run` every 60s (`sm_scheduler`)
- Jobs: `MarkInvitationEmailSent` (stamps email_sent_at), `FetchNewsJob` (RSS + AI summarization)

### Resources (6)
- CompanyResource, FranchiseResource, InvitationResource, UserProfileResource, EventResource, NewsArticleResource

### Testing (Backend)
- PHPUnit 11, SQLite in-memory, RefreshDatabase trait
- 10 test files in tests/Feature/
- Key: FranchiseTest, InvitationTest, ProfileTest, SystemAdminTest, FeedControllerTest, FeedServiceTest, TrackUserPresenceTest
- Run: `composer test` or `php artisan test --filter=ClassName`

---

## Frontend Architecture

### Directory Map
```
src/
├── api/          client.js (axios singleton) + 8 API modules
├── assets/       Static assets
├── components/   AuthenticatedLayout, Sidebar, ProtectedRoute, navConfig, modals
├── hooks/        useAuthVerify.js
├── locales/      en/common.json, es/common.json
├── pages/        Per-feature: dashboard, feed, franchises, companies, invitations, system_admins, profile, users
├── store/        authStore.js (Zustand, persisted to localStorage)
├── test/         setup.js (@testing-library/jest-dom)
├── App.jsx       Route tree + RoleRoute component
├── main.jsx      Entry point (mounts App, imports authStore for token getter)
├── i18n.js       i18next config (en/es, single 'common' namespace)
└── index.css     Tailwind imports + base styles
```

### Startup Sequence
1. AuthStore initializes → registers `setTokenGetter` with axios client
2. i18n loads translations
3. App mounts → ProtectedRoute checks auth
4. AuthenticatedLayout → useAuthVerify() validates token with server

### API Layer Pattern
- `src/api/client.js`: Axios instance, baseURL from `VITE_API_URL`, request interceptor injects Bearer token via getter function (avoids circular import)
- Each module (auth, companies, dashboard, feed, franchises, invitations, profile, systemAdmins) exports methods returning `.then(r => r.data.data)`

### State Management
- Single Zustand store: `authStore`
- Shape: `{ user, token, role, permissions, isAuthenticated }`
- Persisted to localStorage under key `sm-portal-auth`
- Actions: setAuth, updateUser, clearAuth

### Route Tree (App.jsx)
```
/login                    → LoginPage (public)
/invite/:token            → AcceptInvitationPage (public)
/ (ProtectedRoute + AuthenticatedLayout)
  ├── /                   → DashboardPage
  ├── /franchises         → RoleRoute[ADMIN_ROLES] → FranchisesPage
  ├── /franchises/:id     → RoleRoute[ADMIN_ROLES] → FranchiseDetailPage
  ├── /companies          → RoleRoute[ADMIN_ROLES] → CompaniesPage
  ├── /users              → RoleRoute[ADMIN_ROLES] → InvitationsPage
  ├── /system-admins      → RoleRoute[superadmin] → SystemAdminsPage
  ├── /feed               → FeedPage
  ├── /calendar           → EventsPage (IMPLEMENTED — custom Month/Week/List views)
  ├── /profile            → ProfilePage
  └── /contracts, /repository, /processes, /accounting, /inventory, /tracking, /catalog, /sb-applications → StubPage
* (catch-all)             → Navigate to /
```

ADMIN_ROLES = `['superadmin', 'system_admin', 'system_admin_readonly', 'admin_sm']`

### Hooks
- `useAuthVerify.js` — validates token with server on mount; 401 → clearAuth + redirect
- `usePermissions.js` — exposes `canWrite(module)`, `isReadonly` (system_admin_readonly), `role`

### Component Patterns
- **Layout**: AuthenticatedLayout (header + sidebar + outlet), ProtectedRoute (auth guard), RoleRoute (role guard)
- **Sidebar**: Dynamic nav via `buildNavItems(role, permissions)` in `navConfig.jsx` — filters by role and module permissions
- **Modal pattern**: `useState(null)` for entity (null=closed, undefined=create, object=edit) + `isModalOpen` boolean
- **Error handling**: try/catch with `error?.response?.data?.message ?? t('common.unexpected_error')`
- **Forms**: Controlled React state (no form library). Backend FormRequests handle validation.
- **PropTypes** at bottom of files for type checking
- **No TypeScript**: all files are `.jsx`

### i18n
- Two locales: en, es (single `common` namespace)
- Usage: `const { t } = useTranslation('common')` → `t('nav.dashboard')`
- Language toggle in header, persisted to localStorage

### Frontend Testing
- Vitest + React Testing Library + jest-dom
- Mocking: react-router-dom, authStore, API modules, i18next
- Run: `npm test` (single), `npm run test:watch`

---

## Infrastructure

### Docker Compose Services
| Service | Image | Port | Purpose |
|---|---|---|---|
| sm_postgres | postgres:16 | 5432 | Dual DB: sm_portal + sm_docuseal |
| sm_redis | redis:7 | 6379 | Cache, session, queue |
| sm_backend | Custom PHP 8.4-FPM | 9000 (internal) | Laravel API |
| sm_nginx | nginx:1.27-alpine | 80 | Reverse proxy + SPA serving |
| sm_queue | Same as backend | — | Queue worker (sm_queue, default, news) |
| sm_scheduler | Same as backend | — | schedule:run every 60s |
| sm_docuseal | docuseal/docuseal | 3000 | E-signing (separate sm_docuseal DB) |
| sm_frontend_dev | node:22-alpine | 5173 | Vite dev (dev profile) |
| sm_adminer | adminer | 8080 | DB admin (dev profile) |

### Nginx Routing
- `/api`, `/sanctum`, `/docs` → PHP-FPM (backend)
- `/storage` → Laravel public storage
- Everything else → React SPA (index.html fallback)
- Static assets: 1-year cache, immutable

### Railway Deployment
- Multi-stage Dockerfile with Railway-specific stage
- Nginx or Caddy + PHP-FPM in single container
- envsubst for $PORT injection
- Swagger docs regenerated on deploy (ephemeral filesystem)
- Separate worker dyno for queue:work

### CI/CD (GitHub Actions)
1. **laravel.yml**: PHP tests on push/PR to main (PostgreSQL service)
2. **ai-review.yml**: Two phases:
   - Phase 1 (blocking): Pint + PHPStan + PHPUnit + ESLint
   - Phase 2: Claude Sonnet review (only if Phase 1 passes, diff-based, 400-line cap)

---

## Roles & Permissions

### Role Hierarchy (Role.php — final class with constants)
| Role | Scope | Access Level |
|---|---|---|
| SUPERADMIN | Global | Everything |
| SYSTEM_ADMIN | Global | Module-based (can_read, can_write per module) |
| SYSTEM_ADMIN_READONLY | Global | Module-based (read only) |
| ADMIN_SM | Franchise | Own franchise: users, companies, invitations |
| SB_OWNER | Company | Own company data |
| SB_EMPLOYEE | Company | Limited company data |
| BB_EMPLOYEE | Assignment | Assigned companies |
| SUB_FRANCHISE_OWNER | Sub-franchise | Sub-franchise scope |
| SUB_FRANCHISE_ADMIN | Sub-franchise | Sub-franchise admin scope |

### Frontend Role Guards
- `ADMIN_ROLES = ['superadmin', 'system_admin', 'system_admin_readonly', 'admin_sm']` — used in RoleRoute
- `system-admins` route is `['superadmin']` only
- Sidebar nav filtered by role + module permissions via `buildNavItems(role, permissions)`

---

## Naming Conventions

### Backend
- Controllers: PascalCase, suffixed `Controller` (FranchiseController)
- Services: PascalCase, suffixed `Service` (FranchiseService)
- Models: PascalCase singular (Franchise, Company, User)
- Migrations: `YYYY_MM_DD_HHMMSS_create_tablename_table`
- FormRequests: PascalCase, prefixed with verb (StoreFranchiseRequest, UpdateFranchiseRequest)
- Resources: PascalCase, suffixed `Resource` (FranchiseResource)
- Policies: PascalCase, suffixed `Policy` (FranchisePolicy)
- DB columns: snake_case (sm_franchise_id, created_at)
- Routes: kebab-case plural (/bb-assignments, /system-admins)

### Frontend
- Components: PascalCase files and exports (LoginPage, FranchiseCard)
- Hooks: camelCase prefixed `use` (useAuthVerify)
- API modules: camelCase files (systemAdmins.js)
- Store: camelCase (authStore.js)
- Constants: UPPER_SNAKE_CASE (TIMEZONE_OPTIONS, DEFAULT_KPIS)
- Translation keys: dot-separated (nav.dashboard, roles.superadmin)

---

## Database Schema Highlights

- **66 migrations** covering ~58 tables (as of May 2026)
- Core tables: users, franchises, companies, bb_assignments, bb_applications, user_permissions, posts, post_interactions, news_articles, contracts, repositories, repository_documents, process_maps, process_categories, processes, sub_processes, sub_sub_processes, process_documents, events, event_shares, financial_documents, catalog_items, client_trackings, assessment_contacts, assessment_decisions, assessments
- Spatie tables: roles, permissions, model_has_roles, model_has_permissions, role_has_permissions
- Sanctum: personal_access_tokens
- Framework: failed_jobs, cache
- **Soft deletes** on: User, Franchise, Company, Post, Event, Contract, RepositoryDocument, ProcessDocument, Assessment, FinancialDocument
- **Unique constraints**: bb_assignments.company_id (1 BB per company), user_permissions (user_id, module), event_shares (event_id, user_id), process_maps (company_id, type)
- **FK deferred constraints** in separate migration for referential integrity (users↔franchises↔companies circular deps)
- **UserPermission modules (10)**: feed, contracts, repository, processes, accounting, inventory, tracking, catalog, calendar, applications

---

## Security Decisions (Reference)

See CLAUDE.md § "Security Decisions (Invitation System)" for 15 finalized security decisions that must NOT be re-flagged. Key ones:
- Rate limiting on verify/accept (10/min per IP)
- Identical 404 for invalid/expired tokens (anti-enumeration)
- DB::transaction + lockForUpdate on accept (anti-TOCTOU)
- invitation_token/inviter_id NOT in $fillable (anti-mass-assignment)
- activation_url only exposed in non-production environments
- Email masking via StringHelper::maskEmail() (always 3 stars)
- Application-level unique email constraint (SQLite compat)

---

## Unimplemented Features (StubPages)
Contracts, Repository, Processes, Accounting, Inventory, Tracking, Catalog, SB Applications — routes exist, render StubPage placeholder.
Calendar is **implemented** (EventsPage with Month/Week/List views, EventFormModal, SearchResultsPanel).
