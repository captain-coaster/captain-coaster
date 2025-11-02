import { localeFallbacks } from '../var/translations/configuration';
import { trans, getLocale, setLocale, setLocaleFallbacks } from '@symfony/ux-translator';

/*
 * Proper Symfony UX Translator setup - now with tree shaking
 * Only the translations we import will be included in the bundle
 */

setLocaleFallbacks(localeFallbacks);

export { trans, getLocale, setLocale };

// Import only the specific translations we need (tree shaking will handle the rest)
export { 
    REVIEW_REPORT_SUCCESS,
    REVIEW_REPORT_ERROR, 
    REVIEW_REMOVE_UPVOTE,
    REVIEW_UPVOTE 
} from '../var/translations';