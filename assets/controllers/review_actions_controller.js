import { Controller } from '@hotwired/stimulus';
import { trans, REVIEW_REPORT_SUCCESS, REVIEW_REPORT_ERROR, REVIEW_REMOVE_UPVOTE, REVIEW_UPVOTE } from '../translator';

/**
 * Review actions controller for handling upvotes and reports
 * Optimized for Bootstrap 3.x with efficient jQuery usage
 */
export default class extends Controller {
    static targets = ['upvoteButton', 'upvoteCount', 'reportButton', 'reportModal', 'reviewContent', 'expandButton', 'collapseButton'];
    static outlets = ['modal'];
    static values = {
        id: Number,
        upvoted: Boolean,
        upvoteUrl: String,
        reportUrl: String,
        deleteUrl: String
    };

    connect() {
        // Initialize the upvote button state based on the upvoted value
        if (this.hasUpvoteButtonTarget && this.upvotedValue) {
            this._updateUpvoteButtonState();
        }

        // Set up modal event listeners for better integration
        this._setupModalEventListeners();
    }

    disconnect() {
        // Clean up event listeners
        if (this.hasReportModalTarget) {
            this.element.removeEventListener('modal:shown', this._onModalShown);
            this.element.removeEventListener('modal:form-submit', this._onModalFormSubmit);
        }
    }

    /**
     * Toggle upvote for a review
     */
    upvote(event) {
        event.preventDefault();

        if (!this.hasUpvoteUrlValue) {
            console.error('Upvote URL not provided');
            return;
        }

        fetch(this.upvoteUrlValue, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update the upvote count
                    if (this.hasUpvoteCountTarget) {
                        this.upvoteCountTarget.textContent = data.upvoteCount;
                    }

                    // Toggle the upvoted state
                    this.upvotedValue = data.action === 'added';
                    this._updateUpvoteButtonState();
                }
            })
            .catch(error => {
                console.error('Error toggling upvote:', error);
            });
    }

    /**
     * Open the report modal using the modal controller
     */
    openReportModal(event) {
        event.preventDefault();

        // Try to use the modal outlet first (modern approach)
        if (this.hasModalOutlet) {
            this.modalOutlet.show();
        } else if (this.hasReportModalTarget) {
            // Fallback to direct Bootstrap 3.x modal API for compatibility
            $(this.reportModalTarget).modal('show');
        }
    }

    /**
     * Delete a review
     */
    deleteReview(event) {
        event.preventDefault();

        if (!confirm('Are you sure you want to delete this review?')) {
            return;
        }

        if (!this.hasDeleteUrlValue) {
            console.error('Delete URL not provided');
            return;
        }

        fetch(this.deleteUrlValue, {
            method: 'DELETE',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
            .then(response => response.json())
            .then(data => {
                if (data.state === 'success') {
                    // Remove the entire list item (including title) from the DOM
                    const listItem = this.element.closest('li');
                    if (listItem) {
                        listItem.remove();
                    } else {
                        this.element.remove();
                    }
                    this._showNotification('Review deleted successfully', 'success');
                } else {
                    this._showNotification('Failed to delete review', 'danger');
                }
            })
            .catch(error => {
                console.error('Error deleting review:', error);
                this._showNotification('Failed to delete review', 'danger');
            });
    }

    /**
     * Submit a report
     */
    submitReport(event) {
        event.preventDefault();

        if (!this.hasReportUrlValue) {
            console.error('Report URL not provided');
            return;
        }

        const form = event.currentTarget;
        const formData = new FormData(form);

        fetch(this.reportUrlValue, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Hide the modal using the modal controller or fallback to jQuery
                    if (this.hasModalOutlet) {
                        this.modalOutlet.hide();
                    } else if (this.hasReportModalTarget) {
                        $(this.reportModalTarget).modal('hide');
                    }

                    // Disable the report button
                    if (this.hasReportButtonTarget) {
                        this.reportButtonTarget.disabled = true;
                        this.reportButtonTarget.classList.add('disabled');
                    }

                    // Show a success message
                    this._showNotification(trans(REVIEW_REPORT_SUCCESS), 'success');
                } else {
                    this._showNotification(data.message || trans(REVIEW_REPORT_ERROR), 'danger');
                }
            })
            .catch(error => {
                console.error('Error submitting report:', error);
                this._showNotification(trans(REVIEW_REPORT_ERROR), 'danger');
            });
    }

    /**
     * Update the upvote button state based on the upvoted value
     * @private
     */
    _updateUpvoteButtonState() {
        if (this.hasUpvoteButtonTarget) {
            if (this.upvotedValue) {
                this.upvoteButtonTarget.classList.add('active');
                this.upvoteButtonTarget.setAttribute('title', trans(REVIEW_REMOVE_UPVOTE));
                // Add a visual indicator for upvoted state
                const icon = this.upvoteButtonTarget.querySelector('i');
                if (icon) {
                    icon.classList.add('text-primary');
                }
            } else {
                this.upvoteButtonTarget.classList.remove('active');
                this.upvoteButtonTarget.setAttribute('title', trans(REVIEW_UPVOTE));
                // Remove visual indicator
                const icon = this.upvoteButtonTarget.querySelector('i');
                if (icon) {
                    icon.classList.remove('text-primary');
                }
            }
        }
    }

    /**
     * Toggle between short and full review content
     * @param {Event} event - The click event
     */
    toggleReview(event) {
        event.preventDefault();

        if (this.hasReviewContentTarget) {
            const reviewContent = this.reviewContentTarget;
            const shortReview = reviewContent.querySelector('.review-short');
            const fullReview = reviewContent.querySelector('.review-full');

            // Toggle visibility
            if (shortReview.style.display !== 'none') {
                shortReview.style.display = 'none';
                fullReview.style.display = 'block';
            } else {
                shortReview.style.display = 'block';
                fullReview.style.display = 'none';
            }
        }
    }

    /**
     * Set up modal event listeners for better integration
     * @private
     */
    _setupModalEventListeners() {
        if (!this.hasReportModalTarget) return;

        // Bind event handlers to maintain proper context
        this._onModalShown = this._onModalShown.bind(this);
        this._onModalFormSubmit = this._onModalFormSubmit.bind(this);

        // Listen for modal events
        this.element.addEventListener('modal:shown', this._onModalShown);
        this.element.addEventListener('modal:form-submit', this._onModalFormSubmit);
    }

    /**
     * Handle modal shown event
     * @private
     */
    _onModalShown(event) {
        // Focus on the first form element when modal is shown
        const firstInput = event.detail.modal.querySelector('select, input, textarea');
        if (firstInput) {
            firstInput.focus();
        }
    }

    /**
     * Handle form submission within modal
     * @private
     */
    _onModalFormSubmit(event) {
        // Prevent default form submission and handle via AJAX
        event.preventDefault();
        this.submitReport(event.detail.originalEvent);
    }

    /**
     * Show a notification using the notification controller
     * @param {string} message - The message to display
     * @param {string} type - The type of notification (success, info, warning, danger)
     * @private
     */
    _showNotification(message, type = 'info') {
        // Get the global notification controller
        const notificationController = this.application.getControllerForElementAndIdentifier(
            document.getElementById('notifications'),
            'notification'
        );

        if (notificationController) {
            // Use the appropriate method based on notification type
            switch (type) {
                case 'success':
                    notificationController.showSuccess(message);
                    break;
                case 'warning':
                    notificationController.showWarning(message);
                    break;
                case 'danger':
                    notificationController.showDanger(message);
                    break;
                default:
                    notificationController.showInfo(message);
            }
        }
    }
}