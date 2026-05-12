/**
 * API request utilities
 * Centralized fetch helpers with error handling
 */

/**
 * Make a POST request
 * @param {string} url - The URL to send the request to
 * @param {Object|string} data - The data to send (object or URLSearchParams string)
 * @param {string|null} csrfToken - Optional CSRF token to include
 * @returns {Promise<any>} The JSON response
 * @throws {Error} If the request fails
 */
export async function post(url, data, csrfToken = null) {
    const headers = {
        'Content-Type': 'application/x-www-form-urlencoded',
        'X-Requested-With': 'XMLHttpRequest',
    };

    let body =
        typeof data === 'string' ? data : new URLSearchParams(data).toString();

    if (csrfToken) {
        body += `&_token=${csrfToken}`;
    }

    const response = await fetch(url.replace(/^http:/, 'https:'), {
        method: 'POST',
        headers,
        body,
    });

    if (!response.ok) {
        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
    }

    return response.json();
}

/**
 * Make a GET request
 * @param {string} url - The URL to fetch from
 * @returns {Promise<any>} The JSON response
 * @throws {Error} If the request fails
 */
export async function get(url) {
    const response = await fetch(url.replace(/^http:/, 'https:'), {
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
        },
    });

    if (!response.ok) {
        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
    }

    return response.json();
}
