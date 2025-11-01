import { Controller } from "@hotwired/stimulus";

/**
 * Modern rating date controller
 *
 * Provides a clean, minimalist date picker experience when rating coasters
 */
export default class extends Controller {
    static targets = ["dateInput", "dateContainer", "dateTrigger"];
    static values = {
        coasterId: Number,
        currentDate: String,
        updateUrl: String,
    };

    connect() {
        // Set current date if available
        if (this.currentDateValue && this.hasDateInputTarget) {
            this.dateInputTarget.value = this.currentDateValue;
        }

        // Close date picker when clicking outside
        this.boundClickOutside = this.handleClickOutside.bind(this);
        document.addEventListener('click', this.boundClickOutside);
    }

    disconnect() {
        // Clean up event listener
        if (this.boundClickOutside) {
            document.removeEventListener('click', this.boundClickOutside);
        }
    }

    /**
     * Toggle the date picker visibility
     */
    toggleDatePicker(event) {
        event.stopPropagation();
        
        if (this.hasDateContainerTarget) {
            const isVisible = this.dateContainerTarget.style.display === 'block';
            
            if (isVisible) {
                this.hideDateInput();
            } else {
                this.showDateInput();
            }
        }
    }

    /**
     * Handle clicks outside the date picker to close it
     */
    handleClickOutside(event) {
        if (this.hasDateContainerTarget && 
            this.dateContainerTarget.style.display === 'block' &&
            !this.element.contains(event.target)) {
            this.hideDateInput();
        }
    }

    /**
     * Show the date input when user starts rating
     */
    showDateInput() {
        if (this.hasDateContainerTarget) {
            this.dateContainerTarget.style.display = "block";
            this.dateContainerTarget.classList.add("fade-in");

            // Focus the date input for better UX
            if (this.hasDateInputTarget) {
                setTimeout(() => {
                    this.dateInputTarget.focus();
                }, 150);
            }
        }
    }

    /**
     * Hide the date input
     */
    hideDateInput() {
        if (this.hasDateContainerTarget) {
            this.dateContainerTarget.style.display = "none";
            this.dateContainerTarget.classList.remove("fade-in");
        }
    }

    /**
     * Handle date change and save to backend
     */
    dateChanged(event) {
        const selectedDate = event.target.value;

        if (!this.updateUrlValue) {
            return;
        }

        // Show loading state
        this.setLoadingState(true);

        // Send date to backend
        fetch(this.updateUrlValue, {
            method: "POST",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded",
                "X-Requested-With": "XMLHttpRequest",
            },
            body: `riddenAt=${encodeURIComponent(selectedDate || '')}`,
        })
            .then((response) => {
                if (response.ok) {
                    this.showSuccess();
                    this.updateTriggerButton(selectedDate);
                    // Auto-hide after successful save
                    setTimeout(() => this.hideDateInput(), 1000);
                } else {
                    this.showError();
                }
            })
            .catch((error) => {
                console.error("Error updating ride date:", error);
                this.showError();
            })
            .finally(() => {
                this.setLoadingState(false);
            });
    }

    /**
     * Update the trigger button to show the selected date
     */
    updateTriggerButton(dateValue) {
        if (this.hasDateTriggerTarget) {
            let dateIndicator = this.dateTriggerTarget.querySelector('.date-indicator');
            
            if (dateValue) {
                const date = new Date(dateValue);
                const formattedDate = date.toLocaleDateString('en-US', { 
                    month: 'short', 
                    day: 'numeric',
                    year: 'numeric'
                });
                
                // Add date indicator dot if it doesn't exist
                if (!dateIndicator) {
                    dateIndicator = document.createElement('span');
                    dateIndicator.className = 'date-indicator';
                    this.dateTriggerTarget.appendChild(dateIndicator);
                }
                
                // Update tooltip
                this.dateTriggerTarget.title = `Rode on ${formattedDate} - click to change`;
            } else {
                // Remove date indicator if it exists
                if (dateIndicator) {
                    dateIndicator.remove();
                }
                this.dateTriggerTarget.title = 'Set ride date';
            }
        }
    }

    /**
     * Set loading state
     */
    setLoadingState(isLoading) {
        if (this.hasDateInputTarget) {
            this.dateInputTarget.disabled = isLoading;

            if (isLoading) {
                this.dateInputTarget.classList.add("loading");
            } else {
                this.dateInputTarget.classList.remove("loading");
            }
        }
    }

    /**
     * Show success feedback
     */
    showSuccess() {
        if (this.hasDateInputTarget) {
            this.dateInputTarget.classList.remove("error");
            this.dateInputTarget.classList.add("success");

            // Remove success class after animation
            setTimeout(() => {
                this.dateInputTarget.classList.remove("success");
            }, 2000);
        }
    }

    /**
     * Show error feedback
     */
    showError() {
        if (this.hasDateInputTarget) {
            this.dateInputTarget.classList.remove("success");
            this.dateInputTarget.classList.add("error");

            // Remove error class after animation
            setTimeout(() => {
                this.dateInputTarget.classList.remove("error");
            }, 3000);
        }
    }

    /**
     * Handle today button click
     */
    setToday() {
        const today = new Date().toISOString().split("T")[0];

        if (this.hasDateInputTarget) {
            this.dateInputTarget.value = today;
            // Trigger change event
            this.dateInputTarget.dispatchEvent(new Event("change"));
        }
    }

    /**
     * Clear the date
     */
    clearDate() {
        if (this.hasDateInputTarget) {
            this.dateInputTarget.value = "";
            // Trigger change event
            this.dateInputTarget.dispatchEvent(new Event("change"));
        }
    }
}
