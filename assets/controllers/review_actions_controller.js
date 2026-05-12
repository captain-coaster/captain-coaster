import BaseController from './base_controller.js';
import { show, hide } from '../js/utils/dom.js';
import { scaleUp } from '../js/utils/animation.js';
import { trans } from '../translator';

/**
 * Review actions controller for handling upvotes, reports, and review expand/collapse
 */
export default class extends BaseController {
    static targets = [
        'upvoteButton',
        'upvoteCount',
        'reportButton',
        'reportModal',
        'reviewContent',
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
        if (this.hasUpvoteButtonTarget && this.upvotedValue) {
            this.#updateUpvoteButtonState();
        }

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

        if (!this.hasUpvoteUrlValue) return;

        this.#addZoomAnimation();

        fetch(this.upvoteUrlValue, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
            },
        })
            .then((response) => {
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }
                return response.json();
            })
            .then((data) => {
                if (data.success) {
                    if (this.hasUpvoteCountTarget) {
                        this.upvoteCountTarget.textContent = data.upvoteCount;
                    }
                    this.upvotedValue = data.action === 'added';
                    this.#updateUpvoteButtonState();
                }
            })
            .catch((error) => {
                console.error('Upvote error:', error);
            });
    }

    /**
     * Open the report modal
     */
    openReportModal(event) {
        event.preventDefault();

        if (this.hasReportModalTarget) {
            this.reportModalTarget.classList.remove('hidden');
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

        if (!this.hasDeleteUrlValue) return;

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
                    const listItem = this.element.closest('li');
                    if (listItem) {
                        listItem.remove();
                    } else {
                        this.element.remove();
                    }
                } else {
                    this.showError('Failed to delete review');
                }
            })
            .catch(() => {
                this.showError('Failed to delete review');
            });
    }

    /**
     * Submit a report
     */
    submitReport(event) {
        event.preventDefault();

        if (!this.hasReportUrlValue) return;

        const form = event.currentTarget;
        const submitButton = form.querySelector('button[type="submit"]');

        if (submitButton?.disabled) return;

        if (submitButton) {
            submitButton.disabled = true;
            submitButton.textContent = trans('review.submit_report');
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
                    if (this.hasReportModalTarget) {
                        this.reportModalTarget.classList.add('hidden');
                    }
                    if (this.hasReportButtonTarget) {
                        this.reportButtonTarget.disabled = true;
                    }
                    this.showSuccess(trans('review.report_success'));
                } else {
                    if (submitButton) {
                        submitButton.disabled = false;
                    }
                    this.showError(
                        data.message || trans('review.report_error')
                    );
                }
            })
            .catch(() => {
                if (submitButton) {
                    submitButton.disabled = false;
                }
                this.showError(trans('review.report_error'));
            });
    }

    /**
     * Close the report modal
     */
    closeReportModal(event) {
        event.preventDefault();

        if (this.hasReportModalTarget) {
            this.reportModalTarget.classList.add('hidden');
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

        if (shortReview && !shortReview.classList.contains('hidden')) {
            hide(shortReview);
            if (fullReview) show(fullReview);
        } else {
            if (shortReview) show(shortReview);
            if (fullReview) hide(fullReview);
        }
    }

    /** @private */
    #addZoomAnimation() {
        if (!this.hasUpvoteButtonTarget) return;

        const icon = this.upvoteButtonTarget.querySelector('svg');
        if (icon) scaleUp(icon, 200);
    }

    /** @private */
    #updateUpvoteButtonState() {
        if (!this.hasUpvoteButtonTarget) return;

        const icon = this.upvoteButtonTarget.querySelector('svg');
        if (!icon) return;

        const size = icon.classList.contains('w-3')
            ? 'w-3 h-3'
            : icon.classList.contains('w-3.5')
              ? 'w-3.5 h-3.5'
              : 'w-4 h-4';

        if (this.upvotedValue) {
            this.upvoteButtonTarget.setAttribute(
                'title',
                trans('review.remove_upvote')
            );
            icon.outerHTML = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="${size} text-red-500" aria-hidden="true">
                <path d="m11.645 20.91-.007-.003-.022-.012a15.247 15.247 0 0 1-.383-.218 25.18 25.18 0 0 1-4.244-3.17C4.688 15.36 2.25 12.174 2.25 8.25 2.25 5.322 4.714 3 7.688 3A5.5 5.5 0 0 1 12 5.052 5.5 5.5 0 0 1 16.313 3c2.973 0 5.437 2.322 5.437 5.25 0 3.925-2.438 7.111-4.739 9.256a25.175 25.175 0 0 1-4.244 3.17 15.247 15.247 0 0 1-.383.219l-.022.012-.007.004-.003.001a.752.752 0 0 1-.704 0l-.003-.001Z" />
            </svg>`;
        } else {
            this.upvoteButtonTarget.setAttribute(
                'title',
                trans('review.upvote')
            );
            icon.outerHTML = `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="${size}" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12Z" />
            </svg>`;
        }
    }

    /** @private */
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

    /** @private */
    #handleResize() {
        clearTimeout(this.#resizeTimeout);
        this.#resizeTimeout = setTimeout(() => {
            this.#updateTruncation();
        }, 150);
    }

    #resizeTimeout = null;
    #boundHandleResize = null;

    /** @private */
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
                const textP = shortReview.querySelector('p');
                if (textP) {
                    textP.textContent = fullText.slice(0, maxLength) + '...';
                }
                show(shortReview);
            }
            if (fullReview) hide(fullReview);
        } else {
            if (shortReview) hide(shortReview);
            if (fullReview) show(fullReview);
        }
    }
}
