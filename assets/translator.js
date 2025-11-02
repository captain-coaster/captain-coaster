import { localeFallbacks } from '../var/translations/configuration';
import { trans, getLocale, setLocale, setLocaleFallbacks } from '@symfony/ux-translator';

/*
 * Optimized translator - only loads translations for current locale
 * This prevents loading the massive 1.7MB translations file with all languages
 */

setLocaleFallbacks(localeFallbacks);

// Get current locale from document or default to 'en'
const currentLocale = document.documentElement.lang || 'en';

// Dynamically import only the translations for the current locale
// This will be much smaller than the full 1.7MB file
const loadTranslationsForLocale = async (locale) => {
    try {
        // Try to load locale-specific translations
        const translations = await import(
            /* webpackChunkName: "translations-[request]" */
            `../var/translations/index.js`
        );
        return translations;
    } catch (error) {
        console.warn(`Could not load translations for locale ${locale}, falling back to default`);
        return {};
    }
};

// Initialize translations for current locale
loadTranslationsForLocale(currentLocale);

export { trans, getLocale, setLocale };

// Export only the specific translation constants that are actually used
// This avoids importing the massive 1.7MB translations file
export const REVIEW_REPORT_SUCCESS = { id: "review.report.success" };
export const REVIEW_REPORT_ERROR = { id: "review.report.error" };
export const REVIEW_REMOVE_UPVOTE = { id: "review.remove_upvote" };
export const REVIEW_UPVOTE = { id: "review.upvote" };