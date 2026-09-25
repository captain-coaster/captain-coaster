import { Controller } from '@hotwired/stimulus';
import { isTabletUp } from '../js/utils/breakpoints';

/**
 * Filter bottom sheet below md (Includes/filter_sidebar.html.twig), opened
 * by <twig:FilterToggle>. Attached to <body>: the toggle lives in the page
 * header, the sheet in the page's sidebar block.
 */
export default class extends Controller {
    static targets = ['dialog'];

    connect() {
        this._onResize = this._onResize.bind(this);
        window.addEventListener('resize', this._onResize);
    }

    disconnect() {
        window.removeEventListener('resize', this._onResize);
    }

    open() {
        this.dialogTarget.showModal();
    }

    // A click on the dialog box itself, not its content, is the backdrop.
    backdropClose(event) {
        if (event.target === this.dialogTarget) {
            this.dialogTarget.close();
        }
    }

    // From md the panel is the side column; a sheet left open would stay modal.
    _onResize() {
        if (isTabletUp() && this.hasDialogTarget && this.dialogTarget.open) {
            this.dialogTarget.close();
        }
    }
}
