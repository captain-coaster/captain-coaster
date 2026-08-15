/**
 * Format an ISO `YYYY-MM-DD` string using the browser/OS locale.
 * Builds the Date from parts to avoid UTC-parsing day shifts.
 *
 * @param {string} iso - date in `YYYY-MM-DD` form
 * @param {'medium'|'short'} style - localized style (default: medium)
 * @returns {string} localized date, or '' if input is empty/invalid
 */
export function formatLocalDate(iso, style = 'medium') {
    if (!iso) return '';
    const [y, m, d] = String(iso).split('-').map(Number);
    if (!y || !m || !d) return '';
    return new Date(y, m - 1, d).toLocaleDateString(undefined, {
        dateStyle: style,
    });
}

/** Today as `YYYY-MM-DD` in the local timezone. */
export function todayIso() {
    const now = new Date();
    const offset = now.getTimezoneOffset() * 60000;
    return new Date(now - offset).toISOString().split('T')[0];
}
