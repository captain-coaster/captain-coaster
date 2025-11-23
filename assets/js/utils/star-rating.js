/**
 * Star Rating Utility
 *
 * Generates star rating HTML that matches the Twig macro in templates/helper.html.twig
 * Uses the SVG sprite system for consistent rendering.
 *
 * Usage:
 *   import { renderStarRating } from './utils/star-rating';
 *   const html = renderStarRating(4.5);
 */

// Inline SVG templates for performance
const SOLID_STAR =
    '<svg class="w-6 h-6" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path fill-rule="evenodd" d="M10.788 3.21c.448-1.077 1.976-1.077 2.424 0l2.082 5.006l5.404.434c1.164.093 1.636 1.545.749 2.305l-4.117 3.527l1.257 5.273c.271 1.136-.964 2.033-1.96 1.425L12 18.354L7.373 21.18c-.996.608-2.231-.29-1.96-1.425l1.257-5.273l-4.117-3.527c-.887-.76-.415-2.212.749-2.305l5.404-.434z" clip-rule="evenodd"/></svg>';

const HALF_STAR =
    '<svg class="w-6 h-6" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><defs><clipPath id="half"><rect x="0" y="0" width="12" height="24"/></clipPath><clipPath id="right-half"><rect x="12" y="0" width="12" height="24"/></clipPath></defs><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M11.48 3.499a.562.562 0 0 1 1.04 0l2.125 5.111a.56.56 0 0 0 .475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.56.56 0 0 0-.182.557l1.285 5.385a.562.562 0 0 1-.84.61l-4.725-2.885a.56.56 0 0 0-.586 0L6.982 20.54a.562.562 0 0 1-.84-.61l1.285-5.386a.56.56 0 0 0-.182-.557l-4.204-3.602a.562.562 0 0 1 .321-.988l5.518-.442a.56.56 0 0 0 .475-.345z" clip-path="url(#right-half)"/><path clip-path="url(#half)" fill="currentColor" fill-rule="evenodd" d="M10.788 3.21c.448-1.077 1.976-1.077 2.424 0l2.082 5.006l5.404.434c1.164.093 1.636 1.545.749 2.305l-4.117 3.527l1.257 5.273c.271 1.136-.964 2.033-1.96 1.425L12 18.354L7.373 21.18c-.996.608-2.231-.29-1.96-1.425l1.257-5.273l-4.117-3.527c-.887-.76-.415-2.212.749-2.305l5.404-.434z" clip-rule="evenodd"/></svg>';

/**
 * Render star rating HTML
 *
 * @param {number|string} rating - Rating value (0-5)
 * @returns {string} HTML string with star rating
 */
export function renderStarRating(rating) {
    if (!rating) {
        return '';
    }

    const ratingValue = parseFloat(rating);
    if (isNaN(ratingValue) || ratingValue < 0 || ratingValue > 5) {
        return '';
    }

    const fullStars = Math.floor(ratingValue);
    const hasHalfStar = ratingValue - fullStars >= 0.5;

    let starsHtml = '';

    // Add full stars
    for (let i = 0; i < fullStars; i++) {
        starsHtml += SOLID_STAR;
    }

    // Add half star if needed
    if (hasHalfStar) {
        starsHtml += HALF_STAR;
    }

    return `<span class="star-rating">
        <span class="text-warning star-rating-stars">
            ${starsHtml}
        </span>
    </span>`;
}

/**
 * Render star rating and insert into DOM element
 *
 * @param {HTMLElement} element - Target element
 * @param {number|string} rating - Rating value (0-5)
 */
export function insertStarRating(element, rating) {
    if (!element) return;
    element.innerHTML = renderStarRating(rating);
}
