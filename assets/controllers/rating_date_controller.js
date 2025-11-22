import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
  static targets = ['dateInput', 'dateContainer', 'calendarButton'];
  static values = { updateUrl: String, ratingId: Number };
  static outlets = ['csrf-protection'];

  connect() {
    document.addEventListener('rating:created', this.show.bind(this));
    document.addEventListener('rating:deleted', this.hide.bind(this));
    // Always enforce correct visibility on connect
    this.updateVisibility();
    // Ensure popup is hidden on connect
    this.hideDatePicker();
  }

  disconnect() {
    document.removeEventListener('rating:created', this.show.bind(this));
    document.removeEventListener('rating:deleted', this.hide.bind(this));
  }

  toggleDatePicker(event) {
    event.preventDefault();
    event.stopPropagation();

    if (!this.hasDateContainerTarget) return;

    const isVisible = this.dateContainerTarget.classList.contains('show');

    if (isVisible) {
      this.hideDatePicker();
    } else {
      this.showDatePicker();
    }
  }

  showDatePicker() {
    if (!this.hasDateContainerTarget) return;

    this.dateContainerTarget.classList.add('show');

    // Store current value before showing picker
    if (this.hasDateInputTarget) {
      this.originalValue = this.dateInputTarget.value || '';

      // Add blur listener to detect when iOS picker closes
      this.dateInputTarget.addEventListener(
        'blur',
        this.handleInputBlur.bind(this),
        { once: true }
      );
    }

    // Focus input after animation
    setTimeout(() => {
      if (this.hasDateInputTarget) {
        this.dateInputTarget.focus();
      }
    }, 100);
  }

  handleInputBlur() {
    // When input loses focus (iOS picker closes), close our modal too
    setTimeout(() => {
      this.hideDatePicker();
    }, 100);
  }

  hideDatePicker() {
    if (!this.hasDateContainerTarget) return;
    this.dateContainerTarget.classList.remove('show');

    // Save if value changed when closing
    if (this.hasDateInputTarget && this.originalValue !== undefined) {
      const currentValue = this.dateInputTarget.value || '';
      if (currentValue !== this.originalValue) {
        this.saveDate(currentValue);
      }
      this.originalValue = undefined;
    }
  }

  async dateChanged(event) {
    // On desktop, save immediately and close. On mobile, save and close when picker closes
    const isMobile = window.innerWidth <= 768;
    if (!isMobile) {
      await this.saveDate(event.target.value);
      this.hideDatePicker();
    }
  }

  async saveDate(date) {
    if (!this.updateUrlValue || !this.ratingIdValue) return;

    // Validate date
    if (date && date.trim()) {
      const selectedDate = new Date(date);
      const today = new Date();
      today.setHours(23, 59, 59, 999);

      if (isNaN(selectedDate.getTime())) {
        console.error('Invalid date format:', date);
        return;
      }

      if (selectedDate > today) {
        console.error('Date cannot be in the future:', date);
        if (this.hasDateInputTarget) {
          this.dateInputTarget.value = this.originalValue || '';
        }
        return;
      }
    }

    if (this.hasDateInputTarget) {
      this.dateInputTarget.disabled = true;
    }

    let body = `riddenAt=${encodeURIComponent(date || '')}`;
    if (this.hasCsrfProtectionOutlet) {
      body = this.csrfProtectionOutlet.addTokenToBody(body);
    }

    try {
      const response = await fetch(this.updateUrlValue, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
          'X-Requested-With': 'XMLHttpRequest',
        },
        body: body,
      });

      if (!response.ok) {
        throw new Error('Save failed');
      }
      this.updateDateIndicator(date);
    } catch (error) {
      console.error('Error saving ride date:', error);
    } finally {
      if (this.hasDateInputTarget) {
        this.dateInputTarget.disabled = false;
      }
    }
  }

  async setToday(event) {
    event.preventDefault();
    event.stopPropagation();

    if (this.hasDateInputTarget) {
      const today = new Date().toISOString().split('T')[0];
      this.dateInputTarget.value = today;
      await this.saveDate(today);
      this.hideDatePicker();
    }
  }

  async clearDate(event) {
    event.preventDefault();
    event.stopPropagation();

    if (this.hasDateInputTarget) {
      this.dateInputTarget.value = '';
      await this.saveDate('');
      this.hideDatePicker();
    }
  }

  show(event) {
    this.ratingIdValue = event.detail.ratingId;
    this.updateVisibility();
  }

  hide() {
    this.ratingIdValue = null;
    // Clear the date input when rating is deleted
    if (this.hasDateInputTarget) {
      this.dateInputTarget.value = '';
    }
    this.updateVisibility();
    this.hideDatePicker();
  }

  updateVisibility() {
    // Only show if we have a valid rating ID (not 0, null, or undefined)
    const shouldShow = this.hasRatingIdValue && this.ratingIdValue > 0;

    // Show/hide the button
    if (this.hasCalendarButtonTarget) {
      this.calendarButtonTarget.style.display = shouldShow
        ? 'inline-flex'
        : 'none';
    }
  }

  updateDateIndicator(date) {
    if (!this.hasCalendarButtonTarget) return;

    const indicator =
      this.calendarButtonTarget.querySelector('.date-indicator');

    if (date && date.trim()) {
      if (!indicator) {
        const dot = document.createElement('span');
        dot.className = 'date-indicator';
        this.calendarButtonTarget.appendChild(dot);
      }
    } else {
      if (indicator) {
        indicator.remove();
      }
    }
  }
}
