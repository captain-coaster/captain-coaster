import { Controller } from '@hotwired/stimulus';

/**
 * Thin enhancement over Symfony's native `<input type="date">` (DateType
 * with html5: true) -- sets min/max bounds and opens the native picker on
 * click anywhere in the field (Safari/Firefox otherwise only open it when
 * the calendar icon itself is clicked).
 */
export default class extends Controller {
    static values = {
        startDate: String,
        endDate: String,
    };

    connect() {
        if (this.hasStartDateValue) {
            this.element.min = this.startDateValue;
        }

        this.element.max = this.hasEndDateValue
            ? this.endDateValue
            : new Date().toISOString().split('T')[0];

        this.element.addEventListener('click', this._handleClick);
    }

    disconnect() {
        this.element.removeEventListener('click', this._handleClick);
    }

    _handleClick = () => {
        this.element.showPicker?.();
    };
}
