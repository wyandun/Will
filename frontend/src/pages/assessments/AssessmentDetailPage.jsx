import { useState, useEffect, useCallback } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { useAuthStore } from '../../store/authStore';
import { assessmentsApi } from '../../api/assessments';

// ─── Helpers ──────────────────────────────────────────────────────────────────

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

function Field({ label, value }) {
  if (!value) return null;
  return (
    <div>
      <dt className="text-xs font-medium text-slate-500 uppercase tracking-wide">{label}</dt>
      <dd className="mt-1 text-sm text-slate-800">{value}</dd>
    </div>
  );
}

// ─── Page ─────────────────────────────────────────────────────────────────────

export default function AssessmentDetailPage() {
  const { id } = useParams();
  const navigate = useNavigate();
  const role = useAuthStore((s) => s.role);

  // Only admin_sm and superadmin can write audit notes
  const canAddNote = role === 'admin_sm' || role === 'superadmin';

  const [contact, setContact] = useState(null);
  const [isLoading, setIsLoading] = useState(true);
  const [fetchError, setFetchError] = useState('');

  const [noteText, setNoteText] = useState('');
  const [isSaving, setIsSaving] = useState(false);
  const [saveError, setSaveError] = useState('');

  const loadContact = useCallback(async () => {
    setIsLoading(true);
    setFetchError('');
    try {
      const data = await assessmentsApi.getContact(id);
      setContact(data);
      setNoteText(data.admin_note ?? '');
    } catch (error) {
      setFetchError(
        error?.response?.data?.message ?? 'Failed to load assessment. Please try again.'
      );
    } finally {
      setIsLoading(false);
    }
  }, [id]);

  useEffect(() => {
    loadContact();
  }, [loadContact]);

  async function handleSaveAndClose() {
    if (!canAddNote) return;

    setSaveError('');
    setIsSaving(true);
    try {
      await assessmentsApi.saveAdminNote(id, noteText || null);
      navigate('/sb-applications');
    } catch (error) {
      const message =
        error?.response?.data?.message ??
        error?.response?.data?.errors?.admin_note?.[0] ??
        'Failed to save note. Please try again.';
      setSaveError(message);
    } finally {
      setIsSaving(false);
    }
  }

  if (isLoading) {
    return (
      <div className="flex justify-center py-20">
        <svg className="w-8 h-8 text-blue-500 animate-spin" fill="none" viewBox="0 0 24 24">
          <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
          <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
        </svg>
      </div>
    );
  }

  if (fetchError) {
    return (
      <div className="space-y-4">
        <button
          onClick={() => navigate('/sb-applications')}
          className="inline-flex items-center gap-1.5 text-sm text-slate-500 hover:text-slate-700 transition-colors"
        >
          <svg className="w-4 h-4" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
          </svg>
          Back to assessments
        </button>
        <div className="rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">
          {fetchError}
        </div>
      </div>
    );
  }

  if (!contact) return null;

  return (
    <div className="space-y-6 pb-28">
      {/* Back link */}
      <button
        onClick={() => navigate('/sb-applications')}
        className="inline-flex items-center gap-1.5 text-sm text-slate-500 hover:text-slate-700 transition-colors"
      >
        <svg className="w-4 h-4" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
          <path strokeLinecap="round" strokeLinejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
        </svg>
        Back to assessments
      </button>

      {/* Header */}
      <div className="flex items-start justify-between gap-4">
        <div>
          <h1 className="text-2xl font-semibold text-slate-800">
            {contact.company_name ?? 'Unnamed Company'}
          </h1>
          <p className="mt-1 text-sm text-slate-500">
            {contact.type?.replace(/_/g, ' ')} &mdash; submitted{' '}
            {contact.created_at ? new Date(contact.created_at).toLocaleDateString() : '—'}
          </p>
        </div>
        <StatusBadge status={contact.status} />
      </div>

      {/* Company details */}
      <div className="rounded-xl border border-slate-200 bg-white shadow-sm p-6">
        <h2 className="text-base font-semibold text-slate-700 mb-4">Company Information</h2>
        <dl className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
          <Field label="Company Name" value={contact.company_name} />
          <Field label="Industry" value={contact.company_industry} />
          <Field label="Phone" value={contact.company_phone} />
          <Field label="Email" value={contact.company_email} />
          <Field label="Address" value={contact.company_address} />
          <Field label="State" value={contact.company_state} />
          <Field label="ZIP" value={contact.company_zip} />
          <Field label="Years Operating" value={contact.years_operating?.toString()} />
          <Field label="Employees" value={contact.employees_count?.toString()} />
          <Field label="Annual Revenue" value={contact.annual_revenue} />
        </dl>
      </div>

      {/* Contact person */}
      <div className="rounded-xl border border-slate-200 bg-white shadow-sm p-6">
        <h2 className="text-base font-semibold text-slate-700 mb-4">Contact Person</h2>
        <dl className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
          <Field label="Name" value={contact.contact_name} />
          <Field label="Title" value={contact.contact_title} />
          <Field label="Phone" value={contact.contact_phone} />
          <Field label="Email" value={contact.contact_email} />
          <Field label="Preferred Language" value={contact.preferred_lang?.toUpperCase()} />
          <Field label="Best Time to Contact" value={contact.best_time} />
        </dl>
      </div>

      {/* Score */}
      {contact.score != null && (
        <div className="rounded-xl border border-slate-200 bg-white shadow-sm p-6">
          <h2 className="text-base font-semibold text-slate-700 mb-2">Assessment Score</h2>
          <div className="flex items-baseline gap-2">
            <span className="text-4xl font-bold text-slate-800">
              {Number(contact.score).toFixed(1)}
            </span>
            <span className="text-sm text-slate-500">/ 100</span>
          </div>
        </div>
      )}

      {/* Public notes from the contact */}
      {contact.notes && (
        <div className="rounded-xl border border-slate-200 bg-white shadow-sm p-6">
          <h2 className="text-base font-semibold text-slate-700 mb-2">Notes from Applicant</h2>
          <p className="text-sm text-slate-600 whitespace-pre-wrap">{contact.notes}</p>
        </div>
      )}

      {/* Internal audit note — visible only to admin_sm / superadmin */}
      {canAddNote && (
        <div className="rounded-xl border border-slate-200 bg-white shadow-sm p-6">
          <h2 className="text-base font-semibold text-slate-700 mb-1">Add Note</h2>
          <p className="text-xs text-slate-400 mb-3">
            Internal observation or directive. Not visible to the applicant. Max 2,000 characters.
          </p>

          {saveError && (
            <div className="mb-3 rounded-lg bg-red-50 border border-red-200 px-3 py-2 text-sm text-red-700">
              {saveError}
            </div>
          )}

          <textarea
            value={noteText}
            onChange={(e) => setNoteText(e.target.value)}
            maxLength={2000}
            rows={5}
            placeholder="Write your internal observation or directive here..."
            className="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent resize-y disabled:opacity-50"
            disabled={isSaving}
          />
          <div className="mt-1 text-right text-xs text-slate-400">
            {noteText.length} / 2,000
          </div>

          {/* Previous note metadata */}
          {contact.admin_noted_at && (
            <p className="mt-2 text-xs text-slate-400">
              Last saved on {new Date(contact.admin_noted_at).toLocaleString()}
              {contact.admin_noted_by?.name ? ` by ${contact.admin_noted_by.name}` : ''}
            </p>
          )}
        </div>
      )}

      {/* Floating save button — shown only to users who can write notes */}
      {canAddNote && (
        <div className="fixed bottom-6 right-6 z-50">
          <button
            onClick={handleSaveAndClose}
            disabled={isSaving}
            className="inline-flex items-center gap-2 px-5 py-3 rounded-xl text-sm font-semibold text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 shadow-lg transition-colors disabled:opacity-60 disabled:cursor-not-allowed"
          >
            {isSaving ? (
              <>
                <svg className="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                  <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                  <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                </svg>
                Saving...
              </>
            ) : (
              <>
                <svg className="w-4 h-4" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                </svg>
                Save and close preview
              </>
            )}
          </button>
        </div>
      )}
    </div>
  );
}
