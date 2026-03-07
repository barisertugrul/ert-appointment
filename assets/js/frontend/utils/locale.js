/**
 * Frontend locale helpers.
 * WordPress commonly provides locales like tr_TR; Intl APIs expect BCP47 (tr-TR).
 */

export function normalizeLocaleTag(locale) {
    if (!locale || typeof locale !== 'string') {
        return 'en-US';
    }

    const normalized = locale.replace(/_/g, '-').trim();
    return normalized || 'en-US';
}

export function getFrontendLocale() {
    return normalizeLocaleTag(window.ertaData?.locale ?? 'en-US');
}

export function formatDateSafe(value, locale = getFrontendLocale(), options = {}) {
    if (!value) return '';

    try {
        return new Date(value).toLocaleDateString(locale, options);
    } catch {
        return new Date(value).toLocaleDateString('en-US', options);
    }
}

export function formatTimeSafe(value, locale = getFrontendLocale(), options = {}) {
    if (!value) return '';

    try {
        return new Date(value).toLocaleTimeString(locale, options);
    } catch {
        return new Date(value).toLocaleTimeString('en-US', options);
    }
}

export function formatMonthYearSafe(value, locale = getFrontendLocale()) {
    if (!value) return '';

    try {
        return new Date(value).toLocaleString(locale, { month: 'long', year: 'numeric' });
    } catch {
        return new Date(value).toLocaleString('en-US', { month: 'long', year: 'numeric' });
    }
}
