import BaseController from './base_controller.js';
import { show, hide } from '../js/utils/dom.js';
import { scaleUp } from '../js/utils/animation.js';
import { trans } from '../translator';

/**
 * Review actions controller for handling upvotes and reports
 */
export default class extends BaseController {
    static targets = [
        'upvoteButton',
        'upvoteCount',
        'reportButton',
        'reportModal',
        'reviewContent',
        'expandButton',
        'collapseButton',
    ];
    static outlets = ['modal'];
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
            this.#updateUpvoteButtonState();
        }

        // Initialize responsive truncation
        this.#initializeResponsiveTruncation();
        this.#boundHandleResize = this.#handleResize.bind(this);
        window.addEventListener('resize', this.#boundHandleResize);
    }

    disconnect() {
        window.removeEventListener('resize', this.#boundHandleResize);
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
        this.#addZoomAnimation();

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
                    this.#updateUpvoteButtonState();
                } else if (data.error) {
                    console.warn('Upvote error:', data.error);
                }
            })
            .catch((error) => {
                console.error('Error toggling upvote:', error);
            });
    }

    /**
     * Open the report modal
     */
    openReportModal(event) {
        event.preventDefault();

        // Use the modal outlet (custom modal controller)
        if (this.hasModalOutlet) {
            this.modalOutlet.open();
        } else if (this.hasReportModalTarget) {
            // Fallback: dispatch event to open modal
            const modalId = this.reportModalTarget.dataset.modalIdValue;
            if (modalId) {
                window.openModal(modalId);
            }
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
                'X-Requested-With': 'XMLHttpRequest',
            },
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
                    this.#showNotification(
                        'Review deleted successfully',
                        'success'
                    );
                } else {
                    this.#showNotification('Failed to delete review', 'danger');
                }
            })
            .catch((error) => {
                console.error('Error deleting review:', error);
                this.#showNotification('Failed to delete review', 'danger');
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

        // Prevent double submission
        if (submitButton?.disabled) {
            return;
        }

        if (submitButton) {
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
                    // Hide the modal
                    if (this.hasModalOutlet) {
                        this.modalOutlet.close();
                    } else if (this.hasReportModalTarget) {
                        this.reportModalTarget.classList.add('hidden');
                    }

                    // Disable the report button
                    if (this.hasReportButtonTarget) {
                        this.reportButtonTarget.disabled = true;
                        this.reportButtonTarget.classList.add('disabled');
                    }

                    this.#showNotification(
                        trans('review.report_success'),
                        'success'
                    );
                } else {
                    if (submitButton) {
                        submitButton.disabled = false;
                        submitButton.textContent = trans(
                            'review.submit_report'
                        );
                    }
                    this.#showNotification(
                        data.message || trans('review.report_error'),
                        'danger'
                    );
                }
            })
            .catch((error) => {
                console.error('Error submitting report:', error);
                if (submitButton) {
                    submitButton.disabled = false;
                    submitButton.textContent = trans('review.submit_report');
                }
                this.#showNotification(trans('review.report_error'), 'danger');
            });
    }

    /**
     * Add zoom animation to the upvote button icon
     * @private
     */
    #addZoomAnimation() {
        if (!this.hasUpvoteButtonTarget) return;

        const icon = this.upvoteButtonTarget.querySelector('svg');
        if (!icon) return;

        scaleUp(icon, 200);
    }

    /**
     * Update the upvote button state based on the upvoted value
     * @private
     */
    #updateUpvoteButtonState() {
        if (!this.hasUpvoteButtonTarget) return;

        if (this.upvotedValue) {
            this.upvoteButtonTarget.classList.add('active');
            this.upvoteButtonTarget.setAttribute(
                'title',
                trans('review.remove_upvote')
            );
            const icon = this.upvoteButtonTarget.querySelector('i');
            icon?.classList.add('text-primary');
        } else {
            this.upvoteButtonTarget.classList.remove('active');
            this.upvoteButtonTarget.setAttribute(
                'title',
                trans('review.upvote')
            );
            const icon = this.upvoteButtonTarget.querySelector('i');
            icon?.classList.remove('text-primary');
        }
    }

    /**
     * Toggle between short and full review content
     */
    toggleReview(event) {
        event.preventDefault();

        if (!this.hasReviewContentTarget) return;

        const shortReview =
            this.reviewContentTarget.querySelector('.review-short');
        const fullReview =
            this.reviewContentTarget.querySelector('.review-full');

        if (!shortReview.classList.contains('hidden')) {
            hide(shortReview);
            show(fullReview);
        } else {
            show(shortReview);
            hide(fullReview);
        }
    }

    /**
     * Initialize responsive truncation based on screen size
     * @private
     */
    #initializeResponsiveTruncation() {
        if (!this.hasReviewContentTarget) return;

        const fullText = this.reviewContentTarget.dataset.fullText;
        if (!fullText) {
            const fullReview =
                this.reviewContentTarget.querySelector('.review-full');
            if (fullReview) {
                this.reviewContentTarget.dataset.fullText =
                    fullReview.textContent.trim();
            }
        }

        this.#updateTruncation();
    }

    /**
     * Handle window resize events
     * @private
     */
    #handleResize() {
        clearTimeout(this.#resizeTimeout);
        this.#resizeTimeout = setTimeout(() => {
            this.#updateTruncation();
        }, 150);
    }

    #resizeTimeout = null;
    #boundHandleResize = null;

    /**
     * Update truncation based on current screen size
     * @private
     */
    #updateTruncation() {
        if (!this.hasReviewContentTarget) return;

        const isMobile = window.innerWidth < 768;
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
            if (shortReview) {
                const truncated = fullText.slice(0, maxLength);
                const textNode = shortReview.childNodes[0];
                if (textNode?.nodeType === Node.TEXT_NODE) {
                    textNode.textContent = truncated + '... ';
                }
            }
        } else {
            if (shortReview) hide(shortReview);
            if (fullReview) show(fullReview);

            const expandButton =
                this.reviewContentTarget.querySelector('.expand-review');
            const collapseButton =
                this.reviewContentTarget.querySelector('.collapse-review');
            if (expandButton) hide(expandButton);
            if (collapseButton) hide(collapseButton);
        }
    }

    /**
     * Show a notification using the notification controller
     * @private
     */
    #showNotification(message, type = 'info') {
        // Use base controller's notification methods
        switch (type) {
            case 'success':
                this.showSuccess(message);
                break;
            case 'warning':
                this.showWarning(message);
                break;
            case 'danger':
                this.showError(message);
                break;
            default:
                this.showInfo(message);
        }
    }
}
