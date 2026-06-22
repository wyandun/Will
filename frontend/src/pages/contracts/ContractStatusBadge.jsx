import PropTypes from 'prop-types';
import { useTranslation } from 'react-i18next';

/** Status → badge styling. Labels come from i18n (contracts.status.*). */
const STATUS_STYLES = {
  draft: 'bg-slate-100 text-slate-600 ring-slate-500/20',
  sent: 'bg-amber-50 text-amber-700 ring-amber-600/20',
  signed: 'bg-green-50 text-green-700 ring-green-600/20',
  expired: 'bg-orange-50 text-orange-700 ring-orange-600/20',
  cancelled: 'bg-red-50 text-red-700 ring-red-600/20',
};

export default function ContractStatusBadge({ status }) {
  const { t } = useTranslation('common');
  const cls = STATUS_STYLES[status] || STATUS_STYLES.draft;
  return (
    <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold ring-1 ring-inset ${cls}`}>
      {t(`contracts.status.${status}`)}
    </span>
  );
}

ContractStatusBadge.propTypes = {
  status: PropTypes.string.isRequired,
};
