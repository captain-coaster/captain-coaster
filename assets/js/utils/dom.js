/**
 * DOM manipulation utilities
 * Replaces inline style manipulation with class-based approach
 */

/**
 * Show an element by removing the 'hidden' class
 * @param {HTMLElement} element - The element to show
 */
export function show(element) {
    element.classList.remove('hidden');
}

/**
 * Hide an element by adding the 'hidden' class
 * @param {HTMLElement} element - The element to hide
 */
export function hide(element) {
    element.classList.add('hidden');
}

/**
 * Toggle an element's visibility by toggling the 'hidden' class
 * @param {HTMLElement} element - The element to toggle
 */
export function toggle(element) {
    element.classList.toggle('hidden');
}

/**
 * Add one or more classes to an element
 * @param {HTMLElement} element - The element to modify
 * @param {...string} classes - The classes to add
 */
export function addClass(element, ...classes) {
    element.classList.add(...classes);
}

/**
 * Remove one or more classes from an element
 * @param {HTMLElement} element - The element to modify
 * @param {...string} classes - The classes to remove
 */
export function removeClass(element, ...classes) {
    element.classList.remove(...classes);
}

/**
 * Toggle a class on an element
 * @param {HTMLElement} element - The element to modify
 * @param {string} className - The class to toggle
 */
export function toggleClass(element, className) {
    element.classList.toggle(className);
}

/**
 * Check if an element has a specific class
 * @param {HTMLElement} element - The element to check
 * @param {string} className - The class to check for
 * @returns {boolean} True if the element has the class
 */
export function hasClass(element, className) {
    return element.classList.contains(className);
}

/**
 * Lock body scroll (for modals, drawers)
 * Prevents scrolling of the page background
 */
export function lockScroll() {
    document.body.classList.add('overflow-hidden');
}

/**
 * Unlock body scroll
 * Restores normal scrolling behavior
 */
export function unlockScroll() {
    document.body.classList.remove('overflow-hidden');
}
