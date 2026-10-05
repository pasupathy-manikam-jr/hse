// Laravel-style ":name" placeholders, matched as whole words (":to" never eats ":total").
// Module-level, so its identity is stable and safe in hook dependency lists.
const t = (key: string, replace: Record<string, string | number> = {}) =>
    key.replace(/:(\w+)/g, (match, name: string) =>
        name in replace ? String(replace[name]) : match,
    );

/**
 * Same API as hrms's useTranslation, so ported components work unchanged.
 * ponytail: English only; port hrms's lang/{locale}.json loader when a second language is needed.
 */
export function useTranslation() {
    return { t, locale: 'en', isRtl: false };
}
