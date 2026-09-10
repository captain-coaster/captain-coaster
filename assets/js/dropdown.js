/**
 * Replaces bootstrap/js/dropdown (Bootstrap 3.4.1's jQuery plugin).
 *
 * A single delegated listener, matching Bootstrap's own approach (one
 * document-level handler, not a per-instance Stimulus controller) --
 * dropdown-toggles exist in unrelated, independently-rendered components
 * (navbar account menu, its nested language/units submenus, Top/edit's
 * move-to-top menu), all wired the same way via plain `data-toggle="dropdown"`
 * with no per-instance state, so one global handler covers all of them
 * without touching templates.
 */

const TOGGLE_SELECTOR = '[data-toggle="dropdown"]';
const OPEN_SELECTOR = '.dropdown.open, .dropdown-submenu.open, .dropup.open';

function getParent(toggleEl) {
    return (
        toggleEl.closest('.dropdown, .dropdown-submenu, .dropup') ||
        toggleEl.parentElement
    );
}

function closeMenus(exceptParent) {
    document.querySelectorAll(OPEN_SELECTOR).forEach((parent) => {
        if (
            exceptParent &&
            (parent === exceptParent || parent.contains(exceptParent))
        ) {
            return;
        }
        parent.classList.remove('open');
        const toggleEl = parent.querySelector(TOGGLE_SELECTOR);
        if (toggleEl) {
            toggleEl.setAttribute('aria-expanded', 'false');
        }
    });
}

document.addEventListener('click', (event) => {
    const toggleEl = event.target.closest(TOGGLE_SELECTOR);

    if (!toggleEl) {
        closeMenus();
        return;
    }

    event.preventDefault();

    const parent = getParent(toggleEl);
    const wasOpen = parent.classList.contains('open');

    closeMenus(wasOpen ? null : parent);

    if (wasOpen) {
        parent.classList.remove('open');
        toggleEl.setAttribute('aria-expanded', 'false');
    } else {
        parent.classList.add('open');
        toggleEl.setAttribute('aria-expanded', 'true');
    }
});

document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
        closeMenus();
    }
});
