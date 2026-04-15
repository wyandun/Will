import { useState, useEffect, useCallback } from 'react';
import { useAuthStore } from '../../store/authStore';
import { companiesApi } from '../../api/companies';
import CompanyFormModal from './CompanyFormModal';

// ─── Empty state ──────────────────────────────────────────────────────────────

function EmptyState({ onAdd, canManage }) {
  return (
    <div className="flex flex-col items-center justify-center py-20 text-center">
      <div className="w-14 h-14 rounded-full bg-slate-100 flex items-center justify-center mb-4">
        <svg className="w-7 h-7 text-slate-400" fill="none" stroke="currentColor" strokeWidth="1.75" viewBox="0 0 24 24">
          <path strokeLinecap="round" strokeLinejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21" />
        </svg>
      </div>
      <p className="text-sm font-semibold text-slate-700">No companies yet</p>
      <p className="mt-1 text-sm text-slate-400">
        {canManage
          ? 'Get started by closing the first deal.'
          : 'No companies have been assigned to you.'}
      </p>
      {canManage && (
        <button
          onClick={onAdd}
          className="mt-5 inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold text-white bg-blue-600 hover:bg-blue-700 transition-colors"
        >
          <svg className="w-4 h-4" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
          </svg>
          New Company
        </button>
      )}
    </div>
  );
}

// ─── Page ─────────────────────────────────────────────────────────────────────

export default function CompaniesPage() {
  const role = useAuthStore((s) => s.role);
  const canManage = role === 'superadmin' || role === 'admin_sm';

  const [companies, setCompanies] = useState([]);
  const [companiesTotal, setCompaniesTotal] = useState(null);
  const [isLoading, setIsLoading] = useState(true);
  const [fetchError, setFetchError] = useState('');

  // Modal state: null = closed, undefined/false = create mode, object = edit mode
  const [modalCompany, setModalCompany] = useState(null);
  const [isModalOpen, setIsModalOpen] = useState(false);

  // ── Fetch ──────────────────────────────────────────────────────────────────

  const loadCompanies = useCallback(async () => {
    setIsLoading(true);
    setFetchError('');
    try {
      const { data, meta } = await companiesApi.getCompanies();
      setCompanies(Array.isArray(data) ? data : []);
      setCompaniesTotal(meta?.total ?? null);
    } catch (error) {
      setFetchError(
        error?.response?.data?.message ?? 'Failed to load companies. Please try again.'
      );
    } finally {
      setIsLoading(false);
    }
  }, []);

  useEffect(() => {
    loadCompanies();
  }, [loadCompanies]);

  // ── Modal helpers ──────────────────────────────────────────────────────────

  function openCreateModal() {
    setModalCompany(null);
    setIsModalOpen(true);
  }

  function openEditModal(company) {
    setModalCompany(company);
    setIsModalOpen(true);
  }

  function closeModal() {
    setIsModalOpen(false);
    setModalCompany(null);
  }

  async function handleSave(payload, id) {
    if (id !== undefined) {
      await companiesApi.updateCompany(id, payload);
    } else {
      await companiesApi.createCompany(payload);
    }
    closeModal();
    await loadCompanies();
  }

  // ── Delete ─────────────────────────────────────────────────────────────────

  async function handleDelete(company) {
    const confirmed = window.confirm(
      `Delete "${company.name}"? This action cannot be undone.`
    );
    if (!confirmed) return;

    try {
      await companiesApi.deleteCompany(company.id);
      await loadCompanies();
    } catch (error) {
      const message =
        error?.response?.data?.message ?? 'Failed to delete the company. Please try again.';
      window.alert(message);
    }
  }

  // ── Location helper ────────────────────────────────────────────────────────

  function formatLocation(company) {
    const parts = [company.city, company.state].filter(Boolean);
    return parts.length > 0 ? parts.join(', ') : null;
  }

  // ── Render ─────────────────────────────────────────────────────────────────

  return (
    <>
      <div className="space-y-5">
        {/* Page header */}
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-2xl font-semibold text-slate-800">Companies</h1>
            <p className="mt-0.5 text-sm text-slate-500">
              Manage small business clients.
            </p>
          </div>
          {canManage && (
            <button
              onClick={openCreateModal}
              className="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors"
            >
              <svg className="w-4 h-4" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
              </svg>
              New Company
            </button>
          )}
        </div>

        {/* Loading */}
        {isLoading && (
          <div className="flex items-center justify-center py-20 gap-3">
            <svg className="w-6 h-6 text-blue-500 animate-spin" fill="none" viewBox="0 0 24 24">
              <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
              <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
            </svg>
            <p className="text-sm text-slate-500">Loading companies…</p>
          </div>
        )}

        {/* Fetch error */}
        {!isLoading && fetchError && (
          <div className="rounded-xl bg-red-50 border border-red-200 px-5 py-4 flex items-start gap-3">
            <svg className="w-5 h-5 text-red-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" strokeWidth="1.75" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
            </svg>
            <div>
              <p className="text-sm font-medium text-red-700">{fetchError}</p>
              <button
                onClick={loadCompanies}
                className="mt-1 text-xs text-red-600 underline hover:text-red-800"
              >
                Try again
              </button>
            </div>
          </div>
        )}

        {/* Empty state */}
        {!isLoading && !fetchError && companies.length === 0 && (
          <EmptyState onAdd={openCreateModal} canManage={canManage} />
        )}

        {/* Table */}
        {!isLoading && !fetchError && companies.length > 0 && (
          <div className="bg-white rounded-xl border border-slate-200 overflow-hidden">
            <table className="min-w-full divide-y divide-slate-200">
              <thead>
                <tr className="bg-slate-50">
                  <th className="px-5 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">
                    Name
                  </th>
                  <th className="px-5 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">
                    Franchise
                  </th>
                  <th className="px-5 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">
                    Industry
                  </th>
                  <th className="px-5 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">
                    Location
                  </th>
                  {canManage && (
                    <th className="px-5 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wider">
                      Actions
                    </th>
                  )}
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100">
                {companies.map((company) => (
                  <tr
                    key={company.id}
                    className="hover:bg-slate-50 transition-colors"
                  >
                    <td className="px-5 py-3.5 text-sm font-medium text-slate-800">
                      {company.name}
                    </td>
                    <td className="px-5 py-3.5 text-sm text-slate-600">
                      {company.franchise_name ?? <span className="text-slate-400">—</span>}
                    </td>
                    <td className="px-5 py-3.5 text-sm text-slate-600">
                      {company.industry ?? <span className="text-slate-400">—</span>}
                    </td>
                    <td className="px-5 py-3.5 text-sm text-slate-600">
                      {formatLocation(company) ?? <span className="text-slate-400">—</span>}
                    </td>
                    {canManage && (
                      <td className="px-5 py-3.5 text-right">
                        <div className="flex items-center justify-end gap-2">
                          <button
                            onClick={() => openEditModal(company)}
                            className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium text-slate-600 bg-slate-100 hover:bg-slate-200 transition-colors"
                          >
                            <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" strokeWidth="1.75" viewBox="0 0 24 24">
                              <path strokeLinecap="round" strokeLinejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125" />
                            </svg>
                            Edit
                          </button>
                          <button
                            onClick={() => handleDelete(company)}
                            className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium text-red-600 bg-red-50 hover:bg-red-100 transition-colors"
                          >
                            <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" strokeWidth="1.75" viewBox="0 0 24 24">
                              <path strokeLinecap="round" strokeLinejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                            </svg>
                            Delete
                          </button>
                        </div>
                      </td>
                    )}
                  </tr>
                ))}
              </tbody>
            </table>

            {/* Row count footer */}
            <div className="px-5 py-3 border-t border-slate-100 bg-slate-50">
              <p className="text-xs text-slate-400">
                {(companiesTotal ?? companies.length)}{' '}
                {(companiesTotal ?? companies.length) === 1 ? 'company' : 'companies'} total
              </p>
            </div>
          </div>
        )}
      </div>

      {/* Modal */}
      {isModalOpen && (
        <CompanyFormModal
          company={modalCompany}
          onClose={closeModal}
          onSave={handleSave}
        />
      )}
    </>
  );
}
