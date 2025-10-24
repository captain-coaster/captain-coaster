import {Controller} from '@hotwired/stimulus';
import {trans, REVIEW_REPORT_SUCCESS, REVIEW_REPORT_ERROR, REVIEW_REMOVE_UPVOTE, REVIEW_UPVOTE} from '../translator';

/**
 * Review actions controller for handling upvotes and reports
 */
export default class extends Controller {
    // No outlets needed anymore
    static targets = ['upvoteButton', 'upvoteCount', 'reportButton', 'reportModal', 'reviewContent', 'reviewText', 'expandButton', 'collapseButton'];
    static values = {
        id: Number,
        upvoted: Boolean,
        upvoteUrl: String,
        reportUrl: String
    };

    connect() {
        // Initialize the upvote button state based on the upvoted value
        if (this.hasUpvoteButtonTarget && this.upvotedValue) {
            this._updateUpvoteButtonState();
        }

        this.checkOverflow();
        window.addEventListener('resize', this.checkOverflow.bind(this));
    }

    disconnect() {
        window.removeEventListener('resize', this.checkOverflow);
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
     * Open the report modal
     */
    openReportModal(event) {
        event.preventDefault();

        if (this.hasReportModalTarget) {
            // Show the modal using Bootstrap's modal API
            $(this.reportModalTarget).modal('show');
        }
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
                    // Hide the modal
                    if (this.hasReportModalTarget) {
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

    checkOverflow() {
        if (!this.hasReviewTextTarget) return;

        const textElement = this.reviewTextTarget;

        textElement.style.webkitLineClamp = 'unset';
        textElement.style.overflow = 'visible';
        textElement.style.display = 'block';

        const fullHeight = textElement.scrollHeight;

        textElement.style.webkitLineClamp = '2';
        textElement.style.overflow = 'hidden';
        textElement.style.display = '-webkit-box'

        if (fullHeight > 40) {
            this.expandButtonTarget.style.display = 'block';
            this.collapseButtonTarget.style.display = 'none';
        } else {
            this.expandButtonTarget.style.display = 'none';
            this.collapseButtonTarget.style.display = 'none';
        }
    }

    /**
     * Toggle between short and full review content
     * @param {Event} event - The click event
     */
    toggleReview(event) {
        event.preventDefault();
        if (this.hasReviewTextTarget) {
            const textElement = this.reviewTextTarget;
            const isExpanded = this.collapseButtonTarget.style.display === 'block';

            if (isExpanded) {
                textElement.style.webkitLineClamp = '2';
                textElement.style.overflow = 'hidden';
                this.expandButtonTarget.style.display = 'block';
                this.collapseButtonTarget.style.display = 'none';
            } else {
                textElement.style.webkitLineClamp = 'unset';
                textElement.style.overflow = 'visible';
                this.expandButtonTarget.style.display = 'none';
                this.collapseButtonTarget.style.display = 'block';
            }
        }
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
