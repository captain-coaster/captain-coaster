import { Controller } from '@hotwired/stimulus';
import { isTabletUp } from '../js/utils/breakpoints';

/**
 * Filter panel toggle below md (Includes/filter_sidebar.html.twig), opened
 * by <twig:FilterToggle>. Attached to <body>: the state is a body class read
 * by sidebar.css. The panel renders above the page content, so opening it
 * scrolls it into view.
 */
export default class extends Controller {
    connect() {
        this._onResize = this._onResize.bind(this);
        window.addEventListener('resize', this._onResize);
    }

    disconnect() {
        window.removeEventListener('resize', this._onResize);
    }

    toggleMobileSecondary(event) {
        const open = document.body.classList.toggle('sidebar-mobile-secondary');
        event?.currentTarget.setAttribute('aria-expanded', String(open));
        if (open) {
            document
                .querySelector('.sidebar-secondary')
                ?.scrollIntoView({ block: 'start' });
        }
    }

    _onResize() {
        if (isTabletUp()) {
            document.body.classList.remove('sidebar-mobile-secondary');
        }
    }
}
