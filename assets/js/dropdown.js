/**
 * Global menu controller for Captain Coaster-owned menu contracts.
 *
 * A single delegated listener, matching Bootstrap's own approach (one
 * document-level handler, not a per-instance Stimulus controller) -- menu
 * toggles exist in unrelated, independently-rendered components
 * (navbar account menu, its nested language/units submenus, Top/edit's
 * move-to-top menu), all wired the same way via `data-cc-menu-toggle`
 * with no per-instance state, so one global handler covers all of them
 * without touching templates.
 */

const TOGGLE_SELECTOR = '[data-cc-menu-toggle]';
const OPEN_SELECTOR = '.cc-menu.is-open, .cc-menu__submenu.is-open';

function getParent(toggleEl) {
    return (
        toggleEl.closest('.cc-menu, .cc-menu__submenu') ||
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
        parent.classList.remove('is-open');
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
    const wasOpen = parent.classList.contains('is-open');

    closeMenus(wasOpen ? null : parent);

    if (wasOpen) {
        parent.classList.remove('is-open');
        toggleEl.setAttribute('aria-expanded', 'false');
    } else {
        parent.classList.add('is-open');
        toggleEl.setAttribute('aria-expanded', 'true');
    }
});

document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
        closeMenus();
    }
});
