import { useState, useEffect, useCallback } from 'react';
import { useNavigate } from 'react-router-dom';
import { assessmentsApi } from '../../api/assessments';

// ─── Status badge ─────────────────────────────────────────────────────────────

const STATUS_STYLES = {
  in_progress: 'bg-amber-50 text-amber-700 ring-amber-600/20',
  pending: 'bg-blue-50 text-blue-700 ring-blue-600/20',
  reviewed: 'bg-indigo-50 text-indigo-700 ring-indigo-600/20',
  approved: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
  rejected: 'bg-red-50 text-red-700 ring-red-600/20',
  converted: 'bg-purple-50 text-purple-700 ring-purple-600/20',
};

function StatusBadge({ status }) {
  const style = STATUS_STYLES[status] ?? 'bg-slate-50 text-slate-600 ring-slate-600/20';
  return (
    <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ring-1 ring-inset ${style}`}>
      {status?.replace(/_/g, ' ')}
    </span>
  );
}

// ─── Empty state ──────────────────────────────────────────────────────────────

function EmptyState() {
  return (
    <div className="flex flex-col items-center justify-center py-20 text-center">
      <div className="w-14 h-14 rounded-full bg-slate-100 flex items-center justify-center mb-4">
        <svg className="w-7 h-7 text-slate-400" fill="none" stroke="currentColor" strokeWidth="1.75" viewBox="0 0 24 24">
          <path strokeLinecap="round" strokeLinejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
        </svg>
      </div>
      <p className="text-sm font-semibold text-slate-700">No assessments found</p>
      <p className="mt-1 text-sm text-slate-400">Submitted assessments will appear here.</p>
    </div>
  );
}

// ─── Page ─────────────────────────────────────────────────────────────────────

export default function AssessmentsPage() {
  const navigate = useNavigate();

  const [contacts, setContacts] = useState([]);
  const [isLoading, setIsLoading] = useState(true);
  const [fetchError, setFetchError] = useState('');

  const loadContacts = useCallback(async () => {
    setIsLoading(true);
    setFetchError('');
    try {
      const { data } = await assessmentsApi.getContacts();
      setContacts(Array.isArray(data) ? data : []);
    } catch (error) {
      setFetchError(
        error?.response?.data?.message ?? 'Failed to load assessments. Please try again.'
      );
    } finally {
      setIsLoading(false);
    }
  }, []);

  useEffect(() => {
    loadContacts();
  }, [loadContacts]);

  return (
    <div className="space-y-5">
      {/* Page header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-semibold text-slate-800">SB Applications</h1>
          <p className="mt-0.5 text-sm text-slate-500">
            Review submitted assessments and add internal audit notes.
          </p>
        </div>
      </div>

      {/* Error */}
      {fetchError && (
        <div className="rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">
          {fetchError}
        </div>
      )}

      {/* Loading */}
      {isLoading ? (
        <div className="flex justify-center py-20">
          <svg className="w-8 h-8 text-blue-500 animate-spin" fill="none" viewBox="0 0 24 24">
            <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
            <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
          </svg>
        </div>
      ) : contacts.length === 0 ? (
        <EmptyState />
      ) : (
        /* Table */
        <div className="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
          <table className="min-w-full divide-y divide-slate-100 text-sm">
            <thead className="bg-slate-50">
              <tr>
                <th className="px-4 py-3 text-left font-semibold text-slate-500 uppercase tracking-wide text-xs">Company</th>
                <th className="px-4 py-3 text-left font-semibold text-slate-500 uppercase tracking-wide text-xs">Contact</th>
                <th className="px-4 py-3 text-left font-semibold text-slate-500 uppercase tracking-wide text-xs">Type</th>
                <th className="px-4 py-3 text-left font-semibold text-slate-500 uppercase tracking-wide text-xs">Status</th>
                <th className="px-4 py-3 text-left font-semibold text-slate-500 uppercase tracking-wide text-xs">Score</th>
                <th className="px-4 py-3 text-left font-semibold text-slate-500 uppercase tracking-wide text-xs">Admin Note</th>
                <th className="px-4 py-3" />
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {contacts.map((contact) => (
                <tr key={contact.id} className="hover:bg-slate-50 transition-colors">
                  <td className="px-4 py-3 font-medium text-slate-800 max-w-[200px] truncate">
                    {contact.company_name ?? '—'}
                  </td>
                  <td className="px-4 py-3 text-slate-600 max-w-[180px] truncate">
                    <div className="truncate">{contact.contact_name ?? '—'}</div>
                    {contact.contact_email && (
                      <div className="text-xs text-slate-400 truncate">{contact.contact_email}</div>
                    )}
                  </td>
                  <td className="px-4 py-3 text-slate-500 text-xs">
                    {contact.type?.replace(/_/g, ' ') ?? '—'}
                  </td>
                  <td className="px-4 py-3">
                    <StatusBadge status={contact.status} />
                  </td>
                  <td className="px-4 py-3 text-slate-600">
                    {contact.score != null ? Number(contact.score).toFixed(1) : '—'}
                  </td>
                  <td className="px-4 py-3 max-w-[200px]">
                    {contact.admin_note ? (
                      <span className="text-xs text-slate-600 line-clamp-2">{contact.admin_note}</span>
                    ) : (
                      <span className="text-xs text-slate-300 italic">No note</span>
                    )}
                  </td>
                  <td className="px-4 py-3 text-right">
                    <button
                      onClick={() => navigate(`/sb-applications/${contact.id}`)}
                      className="text-xs font-medium text-blue-600 hover:text-blue-800 transition-colors"
                    >
                      Review
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </div>
  );
}
