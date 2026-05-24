import PropTypes from 'prop-types';
import { useState, useEffect } from 'react';
import { useTranslation } from 'react-i18next';
import { franchisesApi } from '../../api/franchises';

const MODULES = [
  'feed',
  'contracts',
  'repository',
  'processes',
  'accounting',
  'inventory',
  'tracking',
  'catalog',
  'calendar',
];

function permLevel(perm) {
  if (perm.can_read && perm.can_write) return 'read_write';
  if (perm.can_read) return 'read_only';
  return 'no_access';
}

function fromLevel(level) {
  switch (level) {
    case 'read_write': return { can_read: true, can_write: true };
    case 'read_only': return { can_read: true, can_write: false };
    default: return { can_read: false, can_write: false };
  }
}

export default function AdminPermissionsModal({ admin, franchiseId, onClose, onSave, memberType }) {
  const { t } = useTranslation('common');

  const [permissions, setPermissions] = useState({});
  const [isLoading, setIsLoading] = useState(true);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [apiError, setApiError] = useState('');

  useEffect(() => {
    async function load() {
      try {
        const getPerms = memberType === 'client'
          ? franchisesApi.getClientPermissions
          : franchisesApi.getAdminPermissions;
        const data = await getPerms(franchiseId, admin.id);
        const map = {};
        data.forEach((p) => { map[p.module] = permLevel(p); });
        // Fill missing modules with no_access
        MODULES.forEach((m) => { if (!map[m]) map[m] = 'no_access'; });
        setPermissions(map);
      } catch {
        setApiError(t('common.unexpected_error'));
      } finally {
        setIsLoading(false);
      }
    }
    load();
  }, [admin.id, franchiseId, t]);

  function handleChange(module, level) {
    setPermissions((prev) => ({ ...prev, [module]: level }));
  }

  async function handleSubmit(e) {
    e.preventDefault();
    setApiError('');
    setIsSubmitting(true);

    const payload = MODULES.map((module) => ({
      module,
      ...fromLevel(permissions[module] ?? 'no_access'),
    }));

    try {
      await onSave(payload, admin.id, franchiseId);
    } catch (error) {
      const msgKey = error?.response?.data?.message;
      setApiError(msgKey ? t(msgKey, { defaultValue: msgKey }) : t('common.unexpected_error'));
    } finally {
      setIsSubmitting(false);
    }
  }

  const LEVELS = ['no_access', 'read_only', 'read_write'];

  return (
    <div
      className="fixed inset-0 z-40 flex items-center justify-center bg-black/50"
      onMouseDown={(e) => { if (e.target === e.currentTarget) onClose(); }}
    >
      <div className="relative z-50 w-full max-w-2xl mx-4 bg-white rounded-2xl shadow-xl overflow-hidden">
        {/* Header */}
        <div className="flex items-center justify-between px-6 py-4 border-b border-slate-200">
          <div>
            <h2 className="text-base font-semibold text-slate-800">
              {t('franchise_detail.permissions_title')}
            </h2>
            <p className="text-xs text-slate-400 mt-0.5">{admin.name} &mdash; {admin.email}</p>
          </div>
          <button
            type="button"
            onClick={onClose}
            aria-label={t('common.close')}
            className="p-1 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-colors"
          >
            <svg className="w-5 h-5" fill="none" stroke="currentColor" strokeWidth="1.75" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        {isLoading ? (
          <div className="flex items-center justify-center py-16">
            <svg className="w-6 h-6 text-blue-500 animate-spin" fill="none" viewBox="0 0 24 24">
              <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
              <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
            </svg>
          </div>
        ) : (
          <form onSubmit={handleSubmit}>
            <div className="px-6 py-5 max-h-[60vh] overflow-y-auto">
              {apiError && (
                <div className="rounded-lg bg-red-50 border border-red-200 px-4 py-3 mb-4">
                  <p className="text-sm text-red-700">{apiError}</p>
                </div>
              )}

              {/* Column headers */}
              <div className="grid grid-cols-4 gap-2 mb-2 px-1">
                <div className="text-xs font-medium text-slate-500 uppercase tracking-wider">
                  {t('franchise_detail.module_label')}
                </div>
                {LEVELS.map((level) => (
                  <div key={level} className="text-xs font-medium text-slate-500 uppercase tracking-wider text-center">
                    {t(`franchise_detail.perm_${level}`)}
                  </div>
                ))}
              </div>

              {/* Module rows */}
              <div className="divide-y divide-slate-100">
                {MODULES.map((module) => (
                  <div key={module} className="grid grid-cols-4 gap-2 py-3 px-1 items-center">
                    <div className="text-sm font-medium text-slate-700">
                      {t(`franchise_detail.module_${module}`)}
                    </div>
                    {LEVELS.map((level) => (
                      <div key={level} className="flex justify-center">
                        <label className="relative flex items-center cursor-pointer">
                          <input
                            type="radio"
                            name={`perm-${module}`}
                            checked={permissions[module] === level}
                            onChange={() => handleChange(module, level)}
                            disabled={isSubmitting}
                            className="sr-only peer"
                          />
                          <div className={`w-5 h-5 rounded-full border-2 transition-colors peer-disabled:opacity-50 ${
                            permissions[module] === level
                              ? level === 'no_access'
                                ? 'border-red-500 bg-red-500'
                                : level === 'read_only'
                                  ? 'border-amber-500 bg-amber-500'
                                  : 'border-emerald-500 bg-emerald-500'
                              : 'border-slate-300 bg-white hover:border-slate-400'
                          }`}>
                            {permissions[module] === level && (
                              <svg className="w-full h-full text-white p-0.5" fill="none" stroke="currentColor" strokeWidth="3" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                              </svg>
                            )}
                          </div>
                        </label>
                      </div>
                    ))}
                  </div>
                ))}
              </div>
            </div>

            {/* Footer */}
            <div className="flex items-center justify-end gap-3 px-6 py-4 border-t border-slate-200 bg-slate-50 rounded-b-2xl">
              <button
                type="button"
                onClick={onClose}
                disabled={isSubmitting}
                className="px-4 py-2 rounded-lg text-sm font-medium text-slate-600 bg-white border border-slate-300 hover:bg-slate-50 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
              >
                {t('common.cancel')}
              </button>
              <button
                type="submit"
                disabled={isSubmitting}
                className="px-4 py-2 rounded-lg text-sm font-semibold text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
              >
                {isSubmitting ? t('common.saving') : t('common.save')}
              </button>
            </div>
          </form>
        )}
      </div>
    </div>
  );
}

AdminPermissionsModal.propTypes = {
  admin: PropTypes.object.isRequired,
  franchiseId: PropTypes.number.isRequired,
  onClose: PropTypes.func.isRequired,
  onSave: PropTypes.func.isRequired,
  memberType: PropTypes.oneOf(['admin', 'client']),
};

AdminPermissionsModal.defaultProps = {
  memberType: 'admin',
};
