/**
 * Form validation utilities
 */

/**
 * Validate email format
 * @param {string} email - The email address to validate
 * @returns {boolean} True if the email is valid
 */
export function isValidEmail(email) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
}

/**
 * Check if a value is not empty
 * @param {string} value - The value to check
 * @returns {boolean} True if the value is not empty after trimming
 */
export function isNotEmpty(value) {
    return value && value.trim().length > 0;
}

/**
 * Check if a value is only whitespace
 * @param {string} value - The value to check
 * @returns {boolean} True if the value is empty or only whitespace
 */
export function isWhitespaceOnly(value) {
    return !value || value.trim().length === 0;
}

/**
 * Show a field error message
 * @param {HTMLElement} field - The form field element
 * @param {string} message - The error message to display
 */
export function showFieldError(field, message) {
    field.classList.add('ring-red-500', 'border-red-500');

    const errorElement = document.createElement('p');
    errorElement.className = 'mt-2 text-sm text-red-600 dark:text-red-400';
    errorElement.textContent = message;
    errorElement.dataset.fieldError = '';

    field.parentElement.appendChild(errorElement);
}

/**
 * Clear a field error message
 * @param {HTMLElement} field - The form field element
 */
export function clearFieldError(field) {
    field.classList.remove('ring-red-500', 'border-red-500');

    const errorElement =
        field.parentElement.querySelector('[data-field-error]');
    if (errorElement) {
        errorElement.remove();
    }
}
