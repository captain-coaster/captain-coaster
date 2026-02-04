import { Controller } from '@hotwired/stimulus';
import { trans } from '../translator';

/**
 * Review actions controller for handling upvotes and reports
 * Optimized for Bootstrap 3.x with efficient jQuery usage
 */
export default class extends Controller {
    static targets = [
        'upvoteButton',
        'upvoteCount',
        'reportButton',
        'reportModal',
        'reviewContent',
        'expandButton',
        'collapseButton',
    ];
    static outlets = ['modal', 'csrf-protection'];
    static values = {
        id: Number,
        upvoted: Boolean,
        upvoteUrl: String,
        reportUrl: String,
        deleteUrl: String,
        mobileLength: { type: Number, default: 150 },
        desktopLength: { type: Number, default: 600 },
    };

    connect() {
        // Initialize the upvote button state based on the upvoted value
        if (this.hasUpvoteButtonTarget && this.upvotedValue) {
            this._updateUpvoteButtonState();
        }

        // Set up modal event listeners for better integration
        this._setupModalEventListeners();

        // Initialize responsive truncation
        this._initializeResponsiveTruncation();
        this._boundHandleResize = this._handleResize.bind(this);
        window.addEventListener('resize', this._boundHandleResize);
    }

    disconnect() {
        // Clean up event listeners
        window.removeEventListener('resize', this._boundHandleResize);
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

        // Add zoom animation
        this._addZoomAnimation();

        fetch(this.upvoteUrlValue, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
            },
        })
            .then((response) => response.json())
            .then((data) => {
                if (data.success) {
                    // Update the upvote count
                    if (this.hasUpvoteCountTarget) {
                        this.upvoteCountTarget.textContent = data.upvoteCount;
                    }

                    // Toggle the upvoted state
                    this.upvotedValue = data.action === 'added';
                    this._updateUpvoteButtonState();
                } else if (data.error) {
                    // Handle errors (like self-upvoting)
                    console.warn('Upvote error:', data.error);
                }
            })
            .catch((error) => {
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

        const headers = { 'X-Requested-With': 'XMLHttpRequest' };
        let body = null;

        if (this.hasCsrfProtectionOutlet) {
            headers['Content-Type'] = 'application/x-www-form-urlencoded';
            body = `_token=${this.csrfProtectionOutlet.getToken()}`;
        }

        fetch(this.deleteUrlValue, {
            method: 'DELETE',
            headers,
            body,
        })
            .then((response) => response.json())
            .then((data) => {
                if (data.state === 'success') {
                    // Remove the entire list item (including title) from the DOM
                    const listItem = this.element.closest('li');
                    if (listItem) {
                        listItem.remove();
                    } else {
                        this.element.remove();
                    }
                    this._showNotification(
                        'Review deleted successfully',
                        'success'
                    );
                } else {
                    this._showNotification('Failed to delete review', 'danger');
                }
            })
            .catch((error) => {
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
        const submitButton = form.querySelector('button[type="submit"]');

        // Prevent double submission by disabling the submit button immediately
        if (submitButton) {
            if (submitButton.disabled) {
                return; // Already submitting
            }
            submitButton.disabled = true;
            submitButton.textContent = 'Submitting...';
        }

        const formData = new FormData(form);

        fetch(this.reportUrlValue, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
            },
        })
            .then((response) => response.json())
            .then((data) => {
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
                    this._showNotification(
                        trans('review.report_success'),
                        'success'
                    );
                } else {
                    // Re-enable submit button on error
                    if (submitButton) {
                        submitButton.disabled = false;
                        submitButton.textContent = trans(
                            'review.submit_report'
                        );
                    }
                    this._showNotification(
                        data.message || trans('review.report_error'),
                        'danger'
                    );
                }
            })
            .catch((error) => {
                console.error('Error submitting report:', error);
                // Re-enable submit button on error
                if (submitButton) {
                    submitButton.disabled = false;
                    submitButton.textContent = trans('review.submit_report');
                }
                this._showNotification(trans('review.report_error'), 'danger');
            });
    }

    /**
     * Add zoom animation to the upvote button icon
     * @private
     */
    _addZoomAnimation() {
        if (!this.hasUpvoteButtonTarget) return;

        const icon = this.upvoteButtonTarget.querySelector('svg');
        if (!icon) return;

        icon.style.transform = 'scale(1.3)';
        icon.style.transition = 'transform 0.2s ease';

        setTimeout(() => {
            icon.style.transform = 'scale(1)';
        }, 200);
    }

    /**
     * Update the upvote button state based on the upvoted value
     * @private
     */
    _updateUpvoteButtonState() {
        if (this.hasUpvoteButtonTarget) {
            if (this.upvotedValue) {
                this.upvoteButtonTarget.classList.add('active');
                this.upvoteButtonTarget.setAttribute(
                    'title',
                    trans('review.remove_upvote')
                );
                // Add a visual indicator for upvoted state
                const icon = this.upvoteButtonTarget.querySelector('i');
                if (icon) {
                    icon.classList.add('text-primary');
                }
            } else {
                this.upvoteButtonTarget.classList.remove('active');
                this.upvoteButtonTarget.setAttribute(
                    'title',
                    trans('review.upvote')
                );
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
     * Initialize responsive truncation based on screen size
     * @private
     */
    _initializeResponsiveTruncation() {
        if (!this.hasReviewContentTarget) return;

        const fullText = this.reviewContentTarget.dataset.fullText;
        if (!fullText) {
            // Store the full text on first load
            const fullReview =
                this.reviewContentTarget.querySelector('.review-full');
            if (fullReview) {
                this.reviewContentTarget.dataset.fullText =
                    fullReview.textContent.trim();
            }
        }

        this._updateTruncation();
    }

    /**
     * Handle window resize events
     * @private
     */
    _handleResize() {
        // Debounce resize events
        clearTimeout(this._resizeTimeout);
        this._resizeTimeout = setTimeout(() => {
            this._updateTruncation();
        }, 150);
    }

    /**
     * Update truncation based on current screen size
     * @private
     */
    _updateTruncation() {
        if (!this.hasReviewContentTarget) return;

        const isMobile = window.innerWidth < 768; // Bootstrap's tablet breakpoint
        const maxLength = isMobile
            ? this.mobileLengthValue
            : this.desktopLengthValue;
        const fullText = this.reviewContentTarget.dataset.fullText;

        if (!fullText) return;

        const shortReview =
            this.reviewContentTarget.querySelector('.review-short');
        const fullReview =
            this.reviewContentTarget.querySelector('.review-full');

        if (fullText.length > maxLength) {
            // Show truncated version
            if (shortReview) {
                const truncated = fullText.slice(0, maxLength);
                const textNode = shortReview.childNodes[0];
                if (textNode && textNode.nodeType === Node.TEXT_NODE) {
                    textNode.textContent = truncated + '... ';
                }
            }
        } else {
            // Text is short enough, hide expand/collapse buttons
            if (shortReview) {
                shortReview.style.display = 'none';
            }
            if (fullReview) {
                fullReview.style.display = 'block';
            }
            const expandButton =
                this.reviewContentTarget.querySelector('.expand-review');
            const collapseButton =
                this.reviewContentTarget.querySelector('.collapse-review');
            if (expandButton) expandButton.style.display = 'none';
            if (collapseButton) collapseButton.style.display = 'none';
        }
    }

    /**
     * Set up modal event listeners for better integration
     * @private
     */
    _setupModalEventListeners() {
        // Removed duplicate event listeners that were causing double submissions
        // The form already has data-action="review-actions#submitReport" which handles submission
    }

    /**
     * Show a notification using the notification controller
     * @param {string} message - The message to display
     * @param {string} type - The type of notification (success, info, warning, danger)
     * @private
     */
    _showNotification(message, type = 'info') {
        // Get the global notification controller
        const notificationController =
            this.application.getControllerForElementAndIdentifier(
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
