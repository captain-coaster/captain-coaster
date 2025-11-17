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

import { heroicon } from './heroicon';

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
    const hasHalfStar = (ratingValue - fullStars) >= 0.5;
    
    let starsHtml = '';
    
    // Add full stars
    for (let i = 0; i < fullStars; i++) {
        starsHtml += heroicon('star', 'w-6 h-6', 'solid');
    }
    
    // Add half star if needed
    if (hasHalfStar) {
        starsHtml += heroicon('star-half', 'w-6 h-6');
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
