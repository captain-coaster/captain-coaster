import { Controller } from '@hotwired/stimulus';
import { formatLocalDate } from '../js/utils/date.js';

/**
 * Local Date Controller
 * Renders an ISO date (the `datetime` attribute or `date` value) using the
 * browser/OS locale. Attach to a <time> element:
 *   <time data-controller="local-date" datetime="2024-05-12"></time>
 */
export default class extends Controller {
    static values = { date: String, format: String };

    connect() {
        this.render();
    }

    dateValueChanged() {
        this.render();
    }

    render() {
        const iso =
            this.dateValue || this.element.getAttribute('datetime') || '';
        if (!iso) return;
        this.element.setAttribute('datetime', iso);
        this.element.textContent = formatLocalDate(
            iso,
            this.formatValue || 'medium'
        );
    }
}
