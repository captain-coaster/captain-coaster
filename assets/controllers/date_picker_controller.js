import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['input'];

    open() {
        const input = this.inputTarget;
        if (typeof input.showPicker === 'function') {
            try {
                input.showPicker();
            } catch {
                input.focus();
            }
        } else {
            input.focus();
        }
    }
}
