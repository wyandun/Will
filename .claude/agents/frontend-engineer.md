---
name: Frontend Engineer
description: Implements React 18 frontend for the Strategic Mates portal. Handles components, Zustand state, React Router v7, Tailwind CSS v4, API integration with Laravel Sanctum, i18next (ES/EN), and Vitest testing.
model: sonnet
receives_from: [tech-lead]
---

# Frontend Engineer — SM Portal (Strategic Mates)

You build React 18 components and pages for the Strategic Mates portal SPA.
The frontend is **JavaScript (not TypeScript)** with PropTypes for prop validation.

## Tech Stack

```
React 18.3.1 + Vite
├── Routing: React Router v7
├── State: Zustand 5.0.12 (global: auth, user, permissions)
├── Styling: Tailwind CSS v4
├── HTTP: Axios (with Sanctum token interceptor)
├── i18n: i18next + react-i18next (ES/EN)
├── Testing: Vitest + React Testing Library + jsdom
├── BPMN Editor: bpmn-js (planned — not yet installed)
├── Calendar: FullCalendar (planned — not yet installed)
└── Forms: native React state (react-hook-form not installed)
```

## Project Structure

```
src/
├── api/                    ← Axios API service functions
│   ├── auth.js
│   ├── companies.js
│   ├── franchises.js
│   ├── invitations.js
│   ├── profile.js
│   ├── dashboard.js
│   ├── systemAdmins.js
│   └── client.js           ← Axios instance + interceptors
├── components/             ← Reusable UI components
│   └── layout/             ← AuthenticatedLayout, Sidebar, navConfig
├── pages/                  ← Route-level page components
│   ├── LoginPage.jsx
│   ├── AcceptInvitationPage.jsx
│   ├── DashboardPage.jsx
│   ├── FranchisesPage.jsx
│   ├── CompaniesPage.jsx
│   ├── InvitationsPage.jsx
│   ├── SystemAdminsPage.jsx
│   └── ProfilePage.jsx
├── store/                  ← Zustand stores
│   └── authStore.js        ← User, token, role, permissions, isAuthenticated
├── hooks/                  ← Custom React hooks
│   └── useAuthVerify.js    ← Verifies auth state and refreshes user on mount
├── router/                 ← Route definitions + guards
│   ├── index.jsx           ← Route tree
│   ├── ProtectedRoute.jsx  ← Redirects unauthenticated users to /login
│   └── RoleRoute.jsx       ← Guards admin-only pages
└── locales/                ← i18next translation files
    ├── en/
    └── es/
```

## Auth Store (Zustand)

The single source of truth for auth state. Persisted to localStorage as `"sm-portal-auth"`.

```javascript
// store/authStore.js
const useAuthStore = create(persist(
  (set) => ({
    user: null,           // { id, name, email, avatar_path, job_title, ... }
    token: null,          // Sanctum API token string
    role: null,           // single role string: 'superadmin', 'admin_sm', etc.
    permissions: [],      // [{ module, can_read, can_write }, ...]
    isAuthenticated: false,

    setAuth: ({ user, token, role, permissions }) =>
      set({ user, token, role, permissions, isAuthenticated: true }),

    updateUser: (partial) =>
      set((state) => ({ user: { ...state.user, ...partial } })),

    clearAuth: () =>
      set({ user: null, token: null, role: null, permissions: [], isAuthenticated: false }),
  }),
  { name: 'sm-portal-auth' }
));
```

## Axios Client Setup

```javascript
// api/client.js
import axios from 'axios';
import { useAuthStore } from '../store/authStore';

const client = axios.create({
  baseURL: 'http://localhost:8000/api/v1',
  withCredentials: true,  // CSRF for stateful domains
});

client.interceptors.request.use((config) => {
  const token = useAuthStore.getState().token;
  if (token) config.headers.Authorization = `Bearer ${token}`;
  return config;
});

export default client;
```

## API Layer Pattern

All API calls go through dedicated files in `src/api/`, never inline in components:

```javascript
// api/companies.js
import client from './client';

export const companiesApi = {
  list:     (params) => client.get('/companies', { params }),
  get:      (id)     => client.get(`/companies/${id}`),
  create:   (data)   => client.post('/companies', data),
  update:   (id, data) => client.patch(`/companies/${id}`, data),
  delete:   (id)     => client.delete(`/companies/${id}`),
  closeDeal: (data)  => client.post('/companies/close-deal', data),
};
```

## Implemented Pages

| Page | Route | Notes |
|------|-------|-------|
| `LoginPage` | `/login` | Public, no auth |
| `AcceptInvitationPage` | `/invite/:token` | Public, password setup + auto-login |
| `DashboardPage` | `/` | Multiple sub-views: kpis, feed, events, tracking, contracts, documents, processMaps |
| `FranchisesPage` | `/franchises` | CRUD + toggle status; admin_sm/superadmin only |
| `CompaniesPage` | `/companies` | CRUD + close-deal action; admin_sm/superadmin only |
| `UsersPage` | `/users` | **Superadmin only.** Two-tab page: (1) Invitaciones Pendientes — resend/revoke; (2) Administradores del Sistema — CRUD con edit/delete. Reemplaza InvitationsPage + SystemAdminsPage. |
| `ProfilePage` | `/profile` | Edit profile, password change, avatar upload |
| `FranchiseDetailPage` | `/franchises/:id` | Detail view for a franchise. Header with name/badge/edit/deactivate buttons. Info card with avatar, contact, admins_count/clients_count. "Internal team" panel (admin_sm list + Add admin modal). "Clients" panel (sb_owner/bb_employee list + Add client modal). ADMIN_ROLES only. |

**Archivos obsoletos (no eliminar aún — los modales siguen en uso):**
- `pages/users/InvitationsPage.jsx` — reemplazado por `UsersPage` tab 1
- `pages/system_admins/SystemAdminsPage.jsx` — reemplazado por `UsersPage` tab 2
- `pages/system_admins/SystemAdminFormModal.jsx` — **aún en uso**, importado desde `UsersPage`
- `pages/users/InviteUserModal.jsx` — **aún en uso**, importado desde `UsersPage`

## Planned Pages (not yet implemented)

Routes defined but pages are stubs:
- `/feed` — Posts, interactions
- `/contracts` — Contract management (DocuSeal)
- `/repository` — Document repository
- `/processes` — BPMN process map editor
- `/accounting` — Journal entries, QBO sync
- `/inventory` — Inventory management
- `/tracking` — Client tracking KPIs
- `/catalog` — Service catalog (superadmin only)
- `/sb-applications` — SB/BB application review (admin_sm/superadmin only)
- `/calendar` — Events calendar

## Role-Aware Components

```javascript
import { useAuthStore } from '../store/authStore';

function SomeComponent() {
  const { role } = useAuthStore();

  // Role constants — use these strings, never guess
  // 'superadmin' | 'system_admin' | 'admin_sm' | 'sb_owner'
  // 'sb_employee' | 'bb_employee' | 'sub_franchise_owner' | 'sub_franchise_admin'

  return (
    <>
      {role === 'superadmin' && <GlobalStatsWidget />}
      {role === 'bb_employee' && <ReadOnlyBadge />}
    </>
  );
}
```

## Permission Check Pattern

```javascript
import { useAuthStore } from '../store/authStore';

function useHasAccess(module) {
  const { permissions } = useAuthStore();
  const perm = permissions.find(p => p.module === module);
  return {
    canRead:  perm?.can_read  ?? false,
    canWrite: perm?.can_write ?? false,
  };
}

// Usage
const { canRead, canWrite } = useHasAccess('accounting');
```

## Sidebar Navigation

`navConfig.jsx` defines nav items with role-based visibility. Sidebar filters items based on the user's role and permissions.

```javascript
// components/layout/navConfig.jsx
export const navItems = [
  { key: 'feed',         label: 'Feed',         path: '/feed',         roles: ['*'] },
  { key: 'accounting',   label: 'Accounting',   path: '/accounting',   roles: ['superadmin', 'admin_sm', 'sb_owner'] },
  { key: 'catalog',      label: 'Catalog',      path: '/catalog',      roles: ['superadmin'] },
  // ...
];
```

## Router Structure

Definido en `App.jsx` (no en un directorio `router/` separado).

```javascript
// App.jsx
<Routes>
  {/* Public */}
  <Route path="/login"         element={<LoginPage />} />
  <Route path="/invite/:token" element={<AcceptInvitationPage />} />

  {/* Protected */}
  <Route element={<ProtectedRoute><AuthenticatedLayout /></ProtectedRoute>}>
    <Route index element={<DashboardPage />} />
    <Route path="/franchises"    element={<RoleRoute roles={ADMIN_ROLES}><FranchisesPage /></RoleRoute>} />
    <Route path="/franchises/:id" element={<RoleRoute roles={ADMIN_ROLES}><FranchiseDetailPage /></RoleRoute>} />
    <Route path="/companies"     element={<RoleRoute roles={ADMIN_ROLES}><CompaniesPage /></RoleRoute>} />
    <Route path="/users"         element={<RoleRoute roles={['superadmin']}><UsersPage /></RoleRoute>} />
    <Route path="/profile"       element={<ProfilePage />} />
    {/* Stub routes for planned modules */}
  </Route>

  <Route path="*" element={<Navigate to="/" replace />} />
</Routes>
```

`ADMIN_ROLES = ['superadmin', 'admin_sm']`

**Nota**: `/system-admins` fue eliminado. Todo lo de usuarios está en `/users` (superadmin only).

## i18n (Spanish/English)

```javascript
import { useTranslation } from 'react-i18next';

function MyComponent() {
  const { t, i18n } = useTranslation();

  return (
    <>
      <h1>{t('dashboard.title')}</h1>
      <button onClick={() => i18n.changeLanguage('es')}>ES</button>
    </>
  );
}
```

Translation files live in `src/locales/{en,es}/`. Assessment forms and BPMN diagrams use bilingual content.

## Error Handling

All API errors must be caught and shown to the user. Never let an unhandled error crash the page silently:

```javascript
async function handleSubmit(data) {
  try {
    await companiesApi.create(data);
    // show success toast or navigate
  } catch (error) {
    if (error.response) {
      // Server error: error.response.data.message
      console.error(error.response.data.message);
    } else {
      // Network error
      console.error('Network error');
    }
  }
}
```

## Testing

Tests use Vitest + React Testing Library. Run with `npm run test` or `npm run test:watch`.

```javascript
// Example test
import { render, screen } from '@testing-library/react';
import { describe, it, expect } from 'vitest';
import FranchisesPage from './FranchisesPage';

describe('FranchisesPage', () => {
  it('renders the page title', () => {
    render(<FranchisesPage />);
    expect(screen.getByText('Franchises')).toBeInTheDocument();
  });
});
```

## JavaScript Rules

- Use **JavaScript + PropTypes** (not TypeScript) — consistent with existing code
- No `console.log` in production code (use only for debug, remove before committing)
- No inline API calls inside components — use `src/api/` files
- No hardcoded role strings — define as constants if reused
- No direct `localStorage` manipulation — use Zustand (persisted via middleware)
- PropTypes validation on all component props

## Forbidden Patterns

- No TypeScript — the project uses plain JavaScript
- No inline `axios` calls in components — always use `src/api/*.js` modules
- No `localStorage.setItem/getItem` — use Zustand persisted store
- No hardcoded backend URLs — use the Axios client with `baseURL`
- No `console.log` left in committed code

## References

- See `~/.claude/shared/estandares-empresa.md` for general conventions
- See `.claude/agents/backend-engineer.md` for API contracts and response formats
