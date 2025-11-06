import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['dateInput', 'dateContainer', 'calendarButton'];
    static values = { updateUrl: String, ratingId: Number };

    connect() {
        document.addEventListener('rating:created', this.show.bind(this));
        document.addEventListener('rating:deleted', this.hide.bind(this));
        document.addEventListener('click', this.closeOnOutsideClick.bind(this));
        this.updateVisibility();
    }

    disconnect() {
        document.removeEventListener('rating:created', this.show.bind(this));
        document.removeEventListener('rating:deleted', this.hide.bind(this));
        document.removeEventListener('click', this.closeOnOutsideClick.bind(this));
    }

    toggleDatePicker(event) {
        event.preventDefault();
        event.stopPropagation();
        
        if (!this.hasDateContainerTarget) return;
        
        const isVisible = this.dateContainerTarget.style.display === 'block';
        
        if (isVisible) {
            this.hideDatePicker();
        } else {
            this.showDatePicker();
        }
    }

    showDatePicker() {
        if (!this.hasDateContainerTarget) return;
        
        this.dateContainerTarget.style.display = 'block';
        // Add fade-in class for smooth animation
        setTimeout(() => {
            this.dateContainerTarget.classList.add('fade-in');
        }, 10);
    }

    hideDatePicker() {
        if (!this.hasDateContainerTarget) return;
        
        this.dateContainerTarget.classList.remove('fade-in');
        setTimeout(() => {
            this.dateContainerTarget.style.display = 'none';
        }, 200);
    }

    closeOnOutsideClick(event) {
        if (this.hasDateContainerTarget && 
            this.dateContainerTarget.style.display === 'block' &&
            !this.element.contains(event.target)) {
            this.hideDatePicker();
        }
    }

    async dateChanged(event) {
        const date = event.target.value;
        if (!this.updateUrlValue) return;
        
        // Add loading state
        if (this.hasDateInputTarget) {
            this.dateInputTarget.classList.add('loading');
        }
        
        try {
            const response = await fetch(this.updateUrlValue, {
                method: 'POST',
                headers: { 
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: `riddenAt=${encodeURIComponent(date || '')}`
            });
            
            if (response.ok) {
                // Add success state
                if (this.hasDateInputTarget) {
                    this.dateInputTarget.classList.remove('loading');
                    this.dateInputTarget.classList.add('success');
                    setTimeout(() => {
                        this.dateInputTarget.classList.remove('success');
                    }, 1000);
                }
                
                this.hideDatePicker();
                this.updateDateIndicator(date);
            } else {
                throw new Error('Save failed');
            }
        } catch (error) {
            console.error('Date save error:', error);
            
            // Add error state
            if (this.hasDateInputTarget) {
                this.dateInputTarget.classList.remove('loading');
                this.dateInputTarget.classList.add('error');
                setTimeout(() => {
                    this.dateInputTarget.classList.remove('error');
                }, 2000);
            }
            
            alert('Error saving date');
        }
    }

    setToday() {
        if (this.hasDateInputTarget) {
            this.dateInputTarget.value = new Date().toISOString().split('T')[0];
            this.dateInputTarget.dispatchEvent(new Event('change'));
        }
    }

    clearDate() {
        if (this.hasDateInputTarget) {
            this.dateInputTarget.value = '';
            this.dateInputTarget.dispatchEvent(new Event('change'));
        }
    }

    show(event) {
        this.ratingIdValue = event.detail.ratingId;
        this.updateVisibility();
    }

    hide() {
        this.ratingIdValue = null;
        this.updateVisibility();
        this.hideDatePicker();
    }

    updateVisibility() {
        if (this.hasCalendarButtonTarget) {
            this.calendarButtonTarget.style.display = this.ratingIdValue ? 'inline-block' : 'none';
        }
    }

    updateDateIndicator(date) {
        if (!this.hasCalendarButtonTarget) return;
        
        const indicator = this.calendarButtonTarget.querySelector('.date-indicator');
        
        if (date && date.trim()) {
            // Show indicator if date is set
            if (!indicator) {
                const dot = document.createElement('span');
                dot.className = 'date-indicator';
                this.calendarButtonTarget.appendChild(dot);
            }
        } else {
            // Remove indicator if no date
            if (indicator) {
                indicator.remove();
            }
        }
    }
}