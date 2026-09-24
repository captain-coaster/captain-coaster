/**
 * SVG markup of an icon for markup built in JS, rendered server-side once per
 * page in <template id="js-icons"> (JsIcons component): coaster, park, user
 * (search type markers), search, arrow-right, check, success, info, warning,
 * error, close, loading.
 */
export function icon(name) {
    const template = document.getElementById('js-icons');
    return (
        template?.content.querySelector(`[data-icon="${CSS.escape(name)}"]`)?.innerHTML ??
        ''
    );
}
