import PropTypes from 'prop-types';
import { useState, useEffect } from 'react';
import { useTranslation } from 'react-i18next';
import { repositoriesApi } from '../../api/repositories';

function IconClose() {
  return (
    <svg className="w-5 h-5" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
      <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
    </svg>
  );
}

function IconInfo() {
  return (
    <svg className="w-4 h-4 shrink-0 text-blue-500" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
      <path strokeLinecap="round" strokeLinejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
    </svg>
  );
}

export default function NewRepositoryModal({ onClose, onCreated }) {
  const { t } = useTranslation('common');

  const [companies, setCompanies] = useState([]);
  const [loadingCompanies, setLoadingCompanies] = useState(true);
  const [companyId, setCompanyId] = useState('');

  const [submitting, setSubmitting] = useState(false);
  const [apiError, setApiError] = useState('');

  useEffect(() => {
    let cancelled = false;
    repositoriesApi
      .listCompanies()
      .then((data) => {
        if (!cancelled) setCompanies(Array.isArray(data) ? data : []);
      })
      .catch(() => {
        if (!cancelled) setCompanies([]);
      })
      .finally(() => {
        if (!cancelled) setLoadingCompanies(false);
      });
    return () => {
      cancelled = true;
    };
  }, []);

  const handleSubmit = async (e) => {
    e.preventDefault();
    if (!companyId) return;

    setSubmitting(true);
    setApiError('');

    try {
      const repo = await repositoriesApi.create({ company_id: parseInt(companyId, 10) });
      onCreated(repo);
    } catch (err) {
      const message =
        err?.response?.data?.message ||
        err?.response?.data?.error ||
        t('common.unexpected_error');
      setApiError(message);
    } finally {
      setSubmitting(false);
    }
  };

  const handleBackdrop = (e) => {
    if (e.target === e.currentTarget && !submitting) onClose();
  };

  return (
    <div
      className="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
      onMouseDown={handleBackdrop}
    >
      <div className="relative w-full max-w-md mx-4 bg-white rounded-2xl shadow-xl">
        {/* Header */}
        <div className="flex items-center justify-between px-6 pt-5 pb-4 border-b border-slate-100">
          <h2 className="text-base font-semibold text-slate-800">
            {t('repository.modal_title')}
          </h2>
          <button
            type="button"
            onClick={onClose}
            disabled={submitting}
            className="text-slate-400 hover:text-slate-600 transition-colors disabled:opacity-50"
            aria-label={t('common.close')}
          >
            <IconClose />
          </button>
        </div>

        <form onSubmit={handleSubmit}>
          <div className="px-6 py-5 space-y-5">
            {/* Hint */}
            <div className="flex items-start gap-2 rounded-lg bg-blue-50 px-3 py-2.5">
              <IconInfo />
              <p className="text-xs text-blue-700 leading-relaxed">
                {t('repository.modal_hint')}
              </p>
            </div>

            {/* Company select */}
            <div>
              <label
                htmlFor="company-select"
                className="block text-sm font-medium text-slate-700 mb-1.5"
              >
                {t('repository.company_label')}
                <span className="text-red-500 ml-0.5">{t('common.required')}</span>
              </label>
              {loadingCompanies ? (
                <p className="text-sm text-slate-400">{t('common.loading')}</p>
              ) : (
                <select
                  id="company-select"
                  value={companyId}
                  onChange={(e) => setCompanyId(e.target.value)}
                  required
                  disabled={submitting}
                  className="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:opacity-60"
                >
                  <option value="">{t('repository.company_placeholder')}</option>
                  {companies.map((c) => (
                    <option key={c.id} value={c.id}>
                      {c.name}
                    </option>
                  ))}
                </select>
              )}
            </div>

            {/* API error */}
            {apiError && (
              <p className="text-sm text-red-600">{apiError}</p>
            )}
          </div>

          {/* Footer */}
          <div className="flex items-center justify-end gap-3 px-6 pb-5">
            <button
              type="button"
              onClick={onClose}
              disabled={submitting}
              className="px-4 py-2 rounded-lg text-sm font-medium text-slate-600 bg-white border border-slate-300 hover:bg-slate-50 disabled:opacity-50 transition-colors"
            >
              {t('common.cancel')}
            </button>
            <button
              type="submit"
              disabled={submitting || !companyId}
              className="px-4 py-2 rounded-lg text-sm font-semibold text-white bg-blue-600 hover:bg-blue-700 disabled:opacity-50 transition-colors"
            >
              {submitting ? t('common.saving') : t('repository.create_btn')}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}

NewRepositoryModal.propTypes = {
  onClose: PropTypes.func.isRequired,
  onCreated: PropTypes.func.isRequired,
};
