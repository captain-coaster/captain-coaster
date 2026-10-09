import { Controller } from '@hotwired/stimulus';

// Tag chips (TagChoiceType): disables the unchecked boxes once `max` are
// checked, and collapses the chips past the first few on phones until
// "Show all" (the theme's CSS hides them only while `data-collapsed` is set,
// so without JS every chip shows).
export default class extends Controller {
    static targets = ['extra'];
    static values = { max: Number };

    connect() {
        if (this.hasExtraTarget) {
            this.element.dataset.collapsed = '';
        }
        this.update();
    }

    update() {
        const boxes = [
            ...this.element.querySelectorAll('input[type=checkbox]'),
        ];
        const full = boxes.filter((box) => box.checked).length >= this.maxValue;
        for (const box of boxes) {
            box.disabled = full && !box.checked;
        }
    }

    expand(event) {
        delete this.element.dataset.collapsed;
        event.currentTarget.remove();
        // The button is gone: keep keyboard focus on the first chip it revealed.
        this.extraTargets
            .find((chip) => !chip.querySelector(':checked'))
            ?.querySelector('input')
            ?.focus();
    }
}
