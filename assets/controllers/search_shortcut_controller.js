import { Controller } from '@hotwired/stimulus';

/** Desktop header search: `/` or Cmd/Ctrl+K focuses the field. */
export default class extends Controller {
    static targets = ['input'];

    focus(event) {
        const typing = event.target.closest(
            'input, textarea, select, [contenteditable]'
        );
        const slash = event.key === '/' && !typing;
        const k =
            event.key.toLowerCase() === 'k' && (event.metaKey || event.ctrlKey);
        if (!slash && !k) {
            return;
        }
        event.preventDefault();
        this.inputTarget.focus();
        this.inputTarget.select();
    }
}
