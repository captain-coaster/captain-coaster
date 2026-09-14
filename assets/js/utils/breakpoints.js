/**
 * Breakpoint helpers
 *
 * Reads --breakpoint-tablet straight off :root (assets/styles/tokens.css)
 * so JS-side breakpoint checks can never drift from the CSS value.
 */

/**
 * @returns {boolean} true at tablet width and above (--breakpoint-tablet).
 */
export function isTabletUp() {
    const tabletBreakpoint = getComputedStyle(
        document.documentElement
    ).getPropertyValue('--breakpoint-tablet');

    return window.matchMedia(`(min-width: ${tabletBreakpoint.trim()})`).matches;
}
