import { Controller } from '@hotwired/stimulus';
import TomSelect from 'tom-select/base';
import removeButton from 'tom-select/plugins/remove_button/plugin.js';
import 'tom-select/dist/css/tom-select.css';

// stimulusFetch: 'lazy' — only used on the review form

TomSelect.define('remove_button', removeButton);

/**
 * Searchable multi-tag select, backed by Tom Select. Replaces a hand-rolled
 * custom dropdown that had no keyboard support and no ARIA attributes at
 * all -- Tom Select gives both for free (full combobox ARIA + arrow/enter/
 * escape navigation).
 */
export default class extends Controller {
    static values = {
        multiple: Boolean,
        placeholder: String,
        maximumSelectionLength: Number,
    };

    connect() {
        if (this.element.tagName !== 'SELECT') {
            return;
        }

        this.tomSelect = new TomSelect(this.element, {
            plugins: this.multipleValue ? { remove_button: {} } : {},
            maxItems: this.multipleValue
                ? this.hasMaximumSelectionLengthValue
                    ? this.maximumSelectionLengthValue
                    : null
                : 1,
            placeholder: this.placeholderValue || undefined,
        });

        // Tom Select keeps the placeholder visible even once maxItems is
        // reached, which reads as if more can still be typed -- blank it
        // instead. It rewrites the input's placeholder from
        // `settings.placeholder` on every state refresh (item add/remove,
        // focus/blur), so mutating the DOM attribute directly would just
        // get clobbered on the next one -- update the setting itself, then
        // force a refresh.
        if (this.multipleValue) {
            const syncPlaceholder = () => {
                this.tomSelect.settings.placeholder = this.tomSelect.isFull() ? '' : this.placeholderValue || '';
                this.tomSelect.inputState();
            };
            this.tomSelect.on('item_add', syncPlaceholder);
            this.tomSelect.on('item_remove', syncPlaceholder);
            syncPlaceholder();
        }
    }

    disconnect() {
        this.tomSelect?.destroy();
    }
}
