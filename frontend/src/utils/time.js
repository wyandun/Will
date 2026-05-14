export function timeAgo(dateStr, locale = navigator.language) {
  if (!dateStr) return '';
  const normalized = /[Z+-]\d*$/.test(dateStr) ? dateStr : dateStr.replace(' ', 'T') + 'Z';
  const diffSeconds = Math.round((new Date(normalized).getTime() - Date.now()) / 1000);
  if (isNaN(diffSeconds)) return '';
  const rtf = new Intl.RelativeTimeFormat(locale, { numeric: 'auto' });
  const abs = Math.abs(diffSeconds);
  if (abs < 60) return rtf.format(diffSeconds, 'second');
  if (abs < 3600) return rtf.format(Math.round(diffSeconds / 60), 'minute');
  if (abs < 86400) return rtf.format(Math.round(diffSeconds / 3600), 'hour');
  return rtf.format(Math.round(diffSeconds / 86400), 'day');
}
