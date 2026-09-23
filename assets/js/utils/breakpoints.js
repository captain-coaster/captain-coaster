/**
 * Breakpoint helpers
 *
 * Reads --breakpoint-md straight off :root (assets/styles/tokens.css)
 * so JS-side breakpoint checks can never drift from the CSS value.
 */

/**
 * @returns {boolean} true at tablet width and above (--breakpoint-md).
 */
export function isTabletUp() {
    const tabletBreakpoint = getComputedStyle(
        document.documentElement
    ).getPropertyValue('--breakpoint-md');

    return window.matchMedia(`(min-width: ${tabletBreakpoint.trim()})`).matches;
}
