import { useState, useEffect } from 'react';
import { useAuthStore } from '../../store/authStore';
import { franchisesApi } from '../../api/franchises';

const EMPTY_FORM = {
  name: '',
  sm_franchise_id: '',
  industry: '',
  phone: '',
  email: '',
  city: '',
  state: '',
  country: '',
  address: '',
  notes: '',
};

/**
 * Modal for creating and editing a company.
 *
 * Props:
 *   company    — null for create mode, company object for edit mode
 *   onClose    — called when the modal should be dismissed (no changes)
 *   onSave     — async fn(formData, id?) — called with cleaned payload on submit
 */
export default function CompanyFormModal({ company, onClose, onSave }) {
  const isEditing = company !== null;
  const role = useAuthStore((s) => s.role);
  const user = useAuthStore((s) => s.user);
  const isAdminSm = role === 'admin_sm';

  const [form, setForm] = useState(EMPTY_FORM);
  const [errors, setErrors] = useState({});
  const [apiError, setApiError] = useState('');
  const [isSubmitting, setIsSubmitting] = useState(false);

  const [franchises, setFranchises] = useState([]);
  const [franchisesLoading, setFranchisesLoading] = useState(true);

  // Load franchises for the dropdown
  useEffect(() => {
    setFranchisesLoading(true);
    franchisesApi
      .getFranchises()
      .then(({ data }) => setFranchises(Array.isArray(data) ? data : []))
      .catch(() => setFranchises([]))
      .finally(() => setFranchisesLoading(false));
  }, []);

  // Pre-fill form when editing or set franchise for admin_sm on create
  useEffect(() => {
    if (company) {
      setForm({
        name: company.name ?? '',
        sm_franchise_id: company.sm_franchise_id ? String(company.sm_franchise_id) : '',
        industry: company.industry ?? '',
        phone: company.phone ?? '',
        email: company.email ?? '',
        city: company.city ?? '',
        state: company.state ?? '',
        country: company.country ?? '',
        address: company.address ?? '',
        notes: company.notes ?? '',
      });
    } else {
      setForm({
        ...EMPTY_FORM,
        // Auto-select franchise for admin_sm on create
        sm_franchise_id: isAdminSm && user?.sm_franchise_id
          ? String(user.sm_franchise_id)
          : '',
      });
    }
    setErrors({});
    setApiError('');
  }, [company, isAdminSm, user]);

  function handleChange(e) {
    const { name, value } = e.target;
    setForm((prev) => ({ ...prev, [name]: value }));
    if (errors[name]) {
      setErrors((prev) => ({ ...prev, [name]: '' }));
    }
  }

  function validate() {
    const next = {};
    if (!form.name.trim()) next.name = 'Name is required.';
    if (!form.sm_franchise_id) next.sm_franchise_id = 'Franchise is required.';
    return next;
  }

  async function handleSubmit(e) {
    e.preventDefault();
    setApiError('');

    const fieldErrors = validate();
    if (Object.keys(fieldErrors).length > 0) {
      setErrors(fieldErrors);
      return;
    }

    // Build clean payload — only include non-empty optional fields
    const payload = {
      name: form.name.trim(),
      sm_franchise_id: Number(form.sm_franchise_id),
    };
    if (form.industry.trim()) payload.industry = form.industry.trim();
    if (form.phone.trim()) payload.phone = form.phone.trim();
    if (form.email.trim()) payload.email = form.email.trim();
    if (form.city.trim()) payload.city = form.city.trim();
    if (form.state.trim()) payload.state = form.state.trim();
    if (form.country.trim()) payload.country = form.country.trim();
    if (form.address.trim()) payload.address = form.address.trim();
    if (form.notes.trim()) payload.notes = form.notes.trim();

    setIsSubmitting(true);
    try {
      await onSave(payload, isEditing ? company.id : undefined);
    } catch (error) {
      const message =
        error?.response?.data?.message ?? 'An unexpected error occurred. Please try again.';
      setApiError(message);
    } finally {
      setIsSubmitting(false);
    }
  }

  const inputBase = [
    'w-full rounded-lg border px-3 py-2.5 text-sm text-slate-900 placeholder-slate-400',
    'focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent',
    'disabled:bg-slate-50 disabled:text-slate-400 transition',
  ].join(' ');

  return (
    /* Backdrop */
    <div
      className="fixed inset-0 z-40 flex items-center justify-center bg-black/50"
      onMouseDown={(e) => {
        if (e.target === e.currentTarget) onClose();
      }}
    >
      {/* Panel */}
      <div className="relative z-50 w-full max-w-2xl mx-4 bg-white rounded-2xl shadow-xl max-h-[90vh] flex flex-col">
        {/* Header */}
        <div className="flex items-center justify-between px-6 py-4 border-b border-slate-200 shrink-0">
          <h2 className="text-base font-semibold text-slate-800">
            {isEditing ? 'Edit Company' : 'New Company'}
          </h2>
          <button
            type="button"
            onClick={onClose}
            aria-label="Close"
            className="p-1 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-colors"
          >
            <svg className="w-5 h-5" fill="none" stroke="currentColor" strokeWidth="1.75" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        {/* Form */}
        <form onSubmit={handleSubmit} noValidate className="flex flex-col flex-1 min-h-0">
          <div className="px-6 py-5 space-y-4 overflow-y-auto flex-1">
            {/* API-level error */}
            {apiError && (
              <div className="rounded-lg bg-red-50 border border-red-200 px-4 py-3">
                <p className="text-sm text-red-700">{apiError}</p>
              </div>
            )}

            {/* Name */}
            <div>
              <label htmlFor="cf-name" className="block text-sm font-medium text-slate-700 mb-1">
                Name <span className="text-red-500">*</span>
              </label>
              <input
                id="cf-name"
                name="name"
                type="text"
                value={form.name}
                onChange={handleChange}
                disabled={isSubmitting}
                placeholder="e.g. Taco Express LLC"
                className={`${inputBase} ${errors.name ? 'border-red-400 bg-red-50' : 'border-slate-300'}`}
              />
              {errors.name && (
                <p className="mt-1 text-xs text-red-600">{errors.name}</p>
              )}
            </div>

            {/* Franchise */}
            <div>
              <label htmlFor="cf-franchise" className="block text-sm font-medium text-slate-700 mb-1">
                Franchise <span className="text-red-500">*</span>
              </label>
              <select
                id="cf-franchise"
                name="sm_franchise_id"
                value={form.sm_franchise_id}
                onChange={handleChange}
                disabled={isSubmitting || isAdminSm || franchisesLoading}
                className={`${inputBase} ${errors.sm_franchise_id ? 'border-red-400 bg-red-50' : 'border-slate-300'} ${isAdminSm ? 'cursor-not-allowed' : ''}`}
              >
                <option value="">
                  {franchisesLoading ? 'Loading franchises…' : 'Select a franchise'}
                </option>
                {franchises.map((f) => (
                  <option key={f.id} value={String(f.id)}>
                    {f.name}
                  </option>
                ))}
              </select>
              {errors.sm_franchise_id && (
                <p className="mt-1 text-xs text-red-600">{errors.sm_franchise_id}</p>
              )}
            </div>

            {/* Industry + Phone — 2 columns */}
            <div className="grid grid-cols-2 gap-4">
              <div>
                <label htmlFor="cf-industry" className="block text-sm font-medium text-slate-700 mb-1">
                  Industry
                </label>
                <input
                  id="cf-industry"
                  name="industry"
                  type="text"
                  value={form.industry}
                  onChange={handleChange}
                  disabled={isSubmitting}
                  placeholder="e.g. Food & Beverage"
                  className={`${inputBase} border-slate-300`}
                />
              </div>
              <div>
                <label htmlFor="cf-phone" className="block text-sm font-medium text-slate-700 mb-1">
                  Phone
                </label>
                <input
                  id="cf-phone"
                  name="phone"
                  type="tel"
                  value={form.phone}
                  onChange={handleChange}
                  disabled={isSubmitting}
                  placeholder="e.g. 5551234567"
                  className={`${inputBase} border-slate-300`}
                />
              </div>
            </div>

            {/* Email */}
            <div>
              <label htmlFor="cf-email" className="block text-sm font-medium text-slate-700 mb-1">
                Email
              </label>
              <input
                id="cf-email"
                name="email"
                type="email"
                value={form.email}
                onChange={handleChange}
                disabled={isSubmitting}
                placeholder="e.g. contact@tacoexpress.com"
                className={`${inputBase} border-slate-300`}
              />
            </div>

            {/* City + State — 2 columns */}
            <div className="grid grid-cols-2 gap-4">
              <div>
                <label htmlFor="cf-city" className="block text-sm font-medium text-slate-700 mb-1">
                  City
                </label>
                <input
                  id="cf-city"
                  name="city"
                  type="text"
                  value={form.city}
                  onChange={handleChange}
                  disabled={isSubmitting}
                  placeholder="e.g. Miami"
                  className={`${inputBase} border-slate-300`}
                />
              </div>
              <div>
                <label htmlFor="cf-state" className="block text-sm font-medium text-slate-700 mb-1">
                  State
                </label>
                <input
                  id="cf-state"
                  name="state"
                  type="text"
                  value={form.state}
                  onChange={handleChange}
                  disabled={isSubmitting}
                  placeholder="e.g. FL"
                  className={`${inputBase} border-slate-300`}
                />
              </div>
            </div>

            {/* Country */}
            <div>
              <label htmlFor="cf-country" className="block text-sm font-medium text-slate-700 mb-1">
                Country
              </label>
              <input
                id="cf-country"
                name="country"
                type="text"
                value={form.country}
                onChange={handleChange}
                disabled={isSubmitting}
                placeholder="e.g. USA"
                className={`${inputBase} border-slate-300`}
              />
            </div>

            {/* Address */}
            <div>
              <label htmlFor="cf-address" className="block text-sm font-medium text-slate-700 mb-1">
                Address
              </label>
              <input
                id="cf-address"
                name="address"
                type="text"
                value={form.address}
                onChange={handleChange}
                disabled={isSubmitting}
                placeholder="e.g. 123 Main St"
                className={`${inputBase} border-slate-300`}
              />
            </div>

            {/* Notes */}
            <div>
              <label htmlFor="cf-notes" className="block text-sm font-medium text-slate-700 mb-1">
                Notes
              </label>
              <textarea
                id="cf-notes"
                name="notes"
                rows={3}
                value={form.notes}
                onChange={handleChange}
                disabled={isSubmitting}
                placeholder="Any additional notes about this company…"
                className={`${inputBase} border-slate-300 resize-none`}
              />
            </div>
          </div>

          {/* Footer */}
          <div className="flex items-center justify-end gap-3 px-6 py-4 border-t border-slate-200 bg-slate-50 rounded-b-2xl shrink-0">
            <button
              type="button"
              onClick={onClose}
              disabled={isSubmitting}
              className="px-4 py-2 rounded-lg text-sm font-medium text-slate-600 bg-white border border-slate-300 hover:bg-slate-50 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
            >
              Cancel
            </button>
            <button
              type="submit"
              disabled={isSubmitting}
              className="px-4 py-2 rounded-lg text-sm font-semibold text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
            >
              {isSubmitting
                ? isEditing ? 'Saving…' : 'Creating…'
                : isEditing ? 'Save Changes' : 'Close Deal'}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}
