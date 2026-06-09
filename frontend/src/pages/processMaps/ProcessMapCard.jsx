import PropTypes from 'prop-types';
import { Link } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { usePermissions } from '../../hooks/usePermissions';

/**
 * Card representing a single Process Map in the grid view.
 *
 * The whole card is a link to the map detail route. Clicking the delete button
 * (top-right, hover-only, write-gated) does NOT navigate — it stops the event
 * from reaching the card link.
 */
export default function ProcessMapCard({ map, onDelete }) {
  const { t, i18n } = useTranslation('common');
  const { canWrite } = usePermissions();

  const displayName =
    (i18n.language?.startsWith('es') ? map.name_es : map.name_en) ||
    map.name_en ||
    map.name_es ||
    '—';

  const description = map.description ?? '';
  const franchiseName = map.company?.franchise?.name ?? map.franchise?.name ?? '—';
  const companyName = map.company?.name ?? '—';

  const handleDelete = (e) => {
    e.preventDefault();
    e.stopPropagation();
    onDelete(map);
  };

  return (
    <Link
      to={`/processes/${map.id}`}
      className="group relative block bg-white rounded-xl border border-slate-200 border-t-4 border-t-[#1e3a5f] hover:border-yellow-400 hover:border-t-[#1e3a5f] hover:shadow-lg transition-all duration-200 p-5 flex flex-col"
    >
      {/* Delete button (hover-only, write-gated) */}
      {canWrite('processes') && (
        <button
          type="button"
          onClick={handleDelete}
          aria-label={t('processMaps.delete_btn')}
          className="absolute top-3 right-3 p-1.5 rounded-lg text-red-500 bg-red-50 hover:bg-red-100 opacity-0 group-hover:opacity-100 transition-opacity"
        >
          <svg className="w-4 h-4" fill="none" stroke="currentColor" strokeWidth="1.75" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
          </svg>
        </button>
      )}

      {/* Header: icon + name + description */}
      <div className="flex items-start gap-3">
        <div className="shrink-0 w-11 h-11 rounded-lg bg-slate-100 group-hover:bg-[#1e3a5f]/10 flex items-center justify-center transition-colors">
          {/* Network icon (Lucide-style, inline SVG) */}
          <svg className="w-5 h-5 text-[#1e3a5f]" fill="none" stroke="currentColor" strokeWidth="1.75" viewBox="0 0 24 24">
            <rect x="9" y="2" width="6" height="6" rx="1" />
            <rect x="3" y="16" width="6" height="6" rx="1" />
            <rect x="15" y="16" width="6" height="6" rx="1" />
            <path strokeLinecap="round" d="M12 8v4M6 16v-2a2 2 0 012-2h8a2 2 0 012 2v2" />
          </svg>
        </div>
        <div className="min-w-0 flex-1 pr-6">
          <h3 className="text-sm font-semibold text-slate-800 truncate">{displayName}</h3>
          {description && (
            <p className="mt-0.5 text-xs text-slate-500 line-clamp-2">{description}</p>
          )}
        </div>
      </div>

      {/* Divider */}
      <div className="my-4 border-t border-slate-100" />

      {/* Franchise row */}
      <div className="flex items-center gap-2 text-xs text-slate-600">
        <svg className="w-3.5 h-3.5 text-slate-400 shrink-0" fill="none" stroke="currentColor" strokeWidth="1.75" viewBox="0 0 24 24">
          <path strokeLinecap="round" strokeLinejoin="round" d="M3 21V7l9-4 9 4v14M3 21h18M9 21v-6h6v6M8 11h.01M12 11h.01M16 11h.01M8 15h.01M16 15h.01" />
        </svg>
        <span className="truncate">{franchiseName}</span>
      </div>

      {/* Company row */}
      <div className="mt-1.5 flex items-center gap-2 text-xs text-slate-600">
        <svg className="w-3.5 h-3.5 text-slate-400 shrink-0" fill="none" stroke="currentColor" strokeWidth="1.75" viewBox="0 0 24 24">
          <path strokeLinecap="round" strokeLinejoin="round" d="M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2M9 11a4 4 0 100-8 4 4 0 000 8z" />
        </svg>
        <span className="truncate">{companyName}</span>
      </div>

      {/* Open affordance */}
      <div className="mt-4 pt-3 border-t border-slate-100 flex justify-end">
        <span className="text-xs font-semibold text-blue-600 group-hover:text-blue-700 transition-colors">
          {t('processMaps.open')} →
        </span>
      </div>
    </Link>
  );
}

ProcessMapCard.propTypes = {
  map: PropTypes.shape({
    id: PropTypes.oneOfType([PropTypes.number, PropTypes.string]).isRequired,
    type: PropTypes.string,
    name_es: PropTypes.string,
    name_en: PropTypes.string,
    description: PropTypes.string,
    is_active: PropTypes.bool,
    created_at: PropTypes.string,
    company: PropTypes.shape({
      id: PropTypes.oneOfType([PropTypes.number, PropTypes.string]),
      name: PropTypes.string,
      sm_franchise_id: PropTypes.oneOfType([PropTypes.number, PropTypes.string]),
      franchise: PropTypes.shape({
        id: PropTypes.oneOfType([PropTypes.number, PropTypes.string]),
        name: PropTypes.string,
      }),
    }),
    franchise: PropTypes.shape({
      id: PropTypes.oneOfType([PropTypes.number, PropTypes.string]),
      name: PropTypes.string,
    }),
  }).isRequired,
  onDelete: PropTypes.func.isRequired,
};
