// "2026-12-29" is a calendar date, not a UTC instant: parse it as local so it never shifts a day.
function toDate(value: string | Date): Date {
    if (value instanceof Date) {
        return value;
    }

    const dateOnly = /^(\d{4})-(\d{2})-(\d{2})$/.exec(value);

    return dateOnly
        ? new Date(+dateOnly[1], +dateOnly[2] - 1, +dateOnly[3])
        : new Date(value);
}

const DATE = new Intl.DateTimeFormat('en-GB', { dateStyle: 'medium' });
const DATE_TIME = new Intl.DateTimeFormat('en-GB', {
    dateStyle: 'medium',
    timeStyle: 'short',
});

/**
 * Dates as "5 Oct 2026" and timestamps in the browser's local time.
 * ponytail: fixed format; add a Settings-driven format (as in hrms) when a site asks for one.
 */
export function useFormat() {
    return {
        date: (value: string | Date) => DATE.format(toDate(value)),
        dateTime: (value: string | Date) => DATE_TIME.format(toDate(value)),
    };
}
