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
    }

    disconnect() {
        this.tomSelect?.destroy();
    }
}
