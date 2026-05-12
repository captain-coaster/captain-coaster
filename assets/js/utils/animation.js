/**
 * Animation utilities
 * Provides class-based animations instead of inline styles
 */

/**
 * Scale up an element with animation
 * @param {HTMLElement} element - The element to animate
 * @param {number} duration - Animation duration in milliseconds (default: 200)
 */
export function scaleUp(element, duration = 200) {
    element.classList.add('scale-130', 'transition-transform');
    element.style.transitionDuration = `${duration}ms`;

    setTimeout(() => {
        element.classList.remove('scale-130');
    }, duration);
}

/**
 * Fade in an element
 * @param {HTMLElement} element - The element to fade in
 * @param {number} duration - Animation duration in milliseconds (default: 300)
 */
export function fadeIn(element, duration = 300) {
    element.classList.add('animate-fade-in');
    setTimeout(() => {
        element.classList.remove('animate-fade-in');
    }, duration);
}

/**
 * Slide up an element
 * @param {HTMLElement} element - The element to slide up
 */
export function slideUp(element) {
    element.classList.add('animate-slide-up');
}

/**
 * Gentle bounce animation
 * @param {HTMLElement} element - The element to bounce
 */
export function bounceGentle(element) {
    element.classList.add('animate-bounce-gentle');
    setTimeout(() => {
        element.classList.remove('animate-bounce-gentle');
    }, 600);
}
