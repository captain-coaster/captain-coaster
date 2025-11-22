import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = {
        format: String,
        autoclose: Boolean,
        todayHighlight: Boolean,
        startDate: String,
        endDate: String,
    };

    connect() {
        // Only enhance if it's an input element
        if (this.element.tagName !== 'INPUT') {
            return;
        }

        // Set default values
        this.formatValue = this.formatValue || 'yyyy-mm-dd';
        this.autocloseValue = this.hasAutocloseValue
            ? this.autocloseValue
            : true;
        this.todayHighlightValue = this.hasTodayHighlightValue
            ? this.todayHighlightValue
            : true;

        // Set input type to date for modern browsers
        this.element.type = 'date';

        // Add CSS classes for styling
        this.element.classList.add('datepicker-input');

        // Set constraints if provided
        if (this.hasStartDateValue) {
            this.element.min = this.startDateValue;
        }

        if (this.hasEndDateValue) {
            this.element.max = this.endDateValue;
        }

        // Set today as max date if no end date specified
        if (!this.hasEndDateValue) {
            const today = new Date().toISOString().split('T')[0];
            this.element.max = today;
        }

        // Add event listeners
        this.element.addEventListener(
            'change',
            this.handleDateChange.bind(this)
        );
        this.element.addEventListener('focus', this.handleFocus.bind(this));
        this.element.addEventListener('blur', this.handleBlur.bind(this));
        this.element.addEventListener('click', this.handleClick.bind(this));
    }

    handleClick(event) {
        // Open the date picker when clicking anywhere on the field
        this.element.showPicker();
    }

    handleDateChange(event) {
        const selectedDate = event.target.value;

        // Validate date if needed
        if (selectedDate && !this.isValidDate(selectedDate)) {
            this.showError('Invalid date selected');
            return;
        }

        // Clear any previous errors
        this.clearError();

        // Dispatch custom event for other controllers to listen
        this.element.dispatchEvent(
            new CustomEvent('datepicker:change', {
                detail: {
                    date: selectedDate,
                    formattedDate: this.formatDate(selectedDate),
                },
                bubbles: true,
            })
        );
    }

    handleFocus(event) {
        this.element.classList.add('focused');
    }

    handleBlur(event) {
        this.element.classList.remove('focused');
    }

    isValidDate(dateString) {
        const date = new Date(dateString);
        return date instanceof Date && !isNaN(date);
    }

    formatDate(dateString) {
        if (!dateString) return '';

        const date = new Date(dateString);

        // Format based on the format value
        switch (this.formatValue) {
            case 'dd/mm/yyyy':
                return date.toLocaleDateString('en-GB');
            case 'mm/dd/yyyy':
                return date.toLocaleDateString('en-US');
            case 'yyyy-mm-dd':
            default:
                return dateString; // Already in correct format
        }
    }

    showError(message) {
        this.element.classList.add('error');

        // Create or update error message
        let errorEl =
            this.element.parentNode.querySelector('.datepicker-error');
        if (!errorEl) {
            errorEl = document.createElement('div');
            errorEl.className = 'datepicker-error';
            this.element.parentNode.appendChild(errorEl);
        }

        errorEl.textContent = message;
        errorEl.style.display = 'block';
    }

    clearError() {
        this.element.classList.remove('error');

        const errorEl =
            this.element.parentNode.querySelector('.datepicker-error');
        if (errorEl) {
            errorEl.style.display = 'none';
        }
    }

    // Public methods for programmatic control
    setDate(dateString) {
        this.element.value = dateString;
        this.handleDateChange({ target: this.element });
    }

    getDate() {
        return this.element.value;
    }

    clear() {
        this.element.value = '';
        this.clearError();
    }

    setToday() {
        const today = new Date().toISOString().split('T')[0];
        this.setDate(today);
    }
}
