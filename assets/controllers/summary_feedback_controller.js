import { Controller } from '@hotwired/stimulus';

/**
 * Summary feedback controller for handling thumbs up/down voting on AI summaries
 */
export default class extends Controller {
    static targets = ['thumbsUpButton', 'thumbsDownButton', 'thumbsUpIcon', 'thumbsDownIcon', 'positiveCount', 'negativeCount', 'totalCount', 'loadingIndicator'];
    static values = {
        summaryId: Number,
        feedbackUrl: String,
        csrfToken: String,
        userVote: String,  // 'positive', 'negative', or null
        hasVoted: Boolean
    };

    connect() {
        // Initialize button states based on user's current vote
        this._updateButtonStates();
    }

    /**
     * Handle thumbs up vote
     */
    thumbsUp(event) {
        event.preventDefault();
        this._submitVote(true);
    }

    /**
     * Handle thumbs down vote
     */
    thumbsDown(event) {
        event.preventDefault();
        this._submitVote(false);
    }

    /**
     * Submit a vote to the server
     * @param {boolean} isPositive - true for thumbs up, false for thumbs down
     * @private
     */
    _submitVote(isPositive) {
        // Prevent multiple simultaneous requests
        if (this._isLoading) {
            return;
        }

        // Show loading state
        this._setLoadingState(true);

        // Prepare form data
        const formData = new FormData();
        formData.append('isPositive', isPositive.toString());
        formData.append('_token', this.csrfTokenValue);

        // Submit the vote
        fetch(this.feedbackUrlValue, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update vote counts
                this._updateVoteCounts(data);
                
                // Update user's vote state
                this.userVoteValue = data.hasVoted ? (data.userVote ? 'positive' : 'negative') : null;
                this.hasVotedValue = data.hasVoted;
                
                // Update button states
                this._updateButtonStates();
                
                // Show success feedback
                this._showNotification('Thank you for your feedback!', 'success');
            } else {
                // Show error message
                this._showNotification(data.message || 'An error occurred while submitting your feedback.', 'danger');
            }
        })
        .catch(error => {
            console.error('Error submitting feedback:', error);
            this._showNotification('An error occurred while submitting your feedback.', 'danger');
        })
        .finally(() => {
            // Hide loading state
            this._setLoadingState(false);
        });
    }

    /**
     * Update vote counts in the UI
     * @param {Object} data - Response data containing vote counts
     * @private
     */
    _updateVoteCounts(data) {
        if (this.hasPositiveCountTarget) {
            this.positiveCountTarget.textContent = data.positiveVotes || 0;
        }
        
        if (this.hasNegativeCountTarget) {
            this.negativeCountTarget.textContent = data.negativeVotes || 0;
        }
        
        if (this.hasTotalCountTarget) {
            this.totalCountTarget.textContent = data.totalVotes || 0;
        }
    }

    /**
     * Update button states based on user's current vote
     * @private
     */
    _updateButtonStates() {
        // Reset all button states
        this._resetButtonStates();
        
        // Apply active state based on user's vote
        if (this.hasVotedValue) {
            if (this.userVoteValue === 'positive' && this.hasThumbsUpIconTarget) {
                // Change to solid icon
                this.thumbsUpIconTarget.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-6 h-6">
                    <path d="M7.493 18.5c-.425 0-.82-.236-.975-.632A7.48 7.48 0 0 1 6 15.125c0-1.75.599-3.358 1.602-4.634.151-.192.373-.309.6-.397.473-.183.89-.514 1.212-.924a9.042 9.042 0 0 1 2.861-2.4c.723-.384 1.35-.956 1.653-1.715a4.498 4.498 0 0 0 .322-1.672V2.75A.75.75 0 0 1 15 2a2.25 2.25 0 0 1 2.25 2.25c0 1.152-.26 2.243-.723 3.218-.266.558.107 1.282.725 1.282h3.126c1.026 0 1.945.694 2.054 1.715.045.422.068.85.068 1.285a11.95 11.95 0 0 1-2.649 7.521c-.388.482-.987.729-1.605.729H14.23c-.483 0-.964-.078-1.423-.23l-3.114-1.04a4.501 4.501 0 0 0-1.423-.23h-.777ZM2.331 10.727a11.969 11.969 0 0 0-.831 4.398 12 12 0 0 0 .52 3.507C2.28 19.482 3.105 20 3.994 20H4.9c.445 0 .72-.498.523-.898a8.963 8.963 0 0 1-.924-3.977c0-1.708.476-3.305 1.302-4.666.245-.403-.028-.959-.5-.959H4.25c-.832 0-1.612.453-1.918 1.227Z" />
                </svg>`;
                this.thumbsUpButtonTarget.setAttribute('title', 'You voted this helpful');
            } else if (this.userVoteValue === 'negative' && this.hasThumbsDownIconTarget) {
                // Change to solid icon
                this.thumbsDownIconTarget.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-6 h-6">
                    <path d="M15.73 5.5h1.035A7.465 7.465 0 0 1 18 9.625a7.465 7.465 0 0 1-1.235 4.125h-.148c-.806 0-1.534.446-2.031 1.08a9.04 9.04 0 0 1-2.861 2.4c-.723.384-1.35.956-1.653 1.715a4.499 4.499 0 0 0-.322 1.672v.633A.75.75 0 0 1 9 22a2.25 2.25 0 0 1-2.25-2.25c0-1.152.26-2.243.723-3.218.266-.558-.107-1.282-.725-1.282H3.622c-1.026 0-1.945-.694-2.054-1.715A12.137 12.137 0 0 1 1.5 12.25c0-2.848.992-5.464 2.649-7.521C4.537 4.247 5.136 4 5.754 4H9.77a4.5 4.5 0 0 1 1.423.23l3.114 1.04a4.5 4.5 0 0 0 1.423.23ZM21.669 14.023c.536-1.362.831-2.845.831-4.398 0-1.22-.182-2.398-.52-3.507-.26-.85-1.084-1.368-1.973-1.368H19.1c-.445 0-.72.498-.523.898.591 1.2.924 2.55.924 3.977a8.958 8.958 0 0 1-1.302 4.666c-.245.403.028.959.5.959h1.053c.832 0 1.612-.453 1.918-1.227Z" />
                </svg>`;
                this.thumbsDownButtonTarget.setAttribute('title', 'You voted this not helpful');
            }
        }
    }

    /**
     * Reset button states to default
     * @private
     */
    _resetButtonStates() {
        if (this.hasThumbsUpIconTarget) {
            // Reset to outline icon
            this.thumbsUpIconTarget.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6.633 10.25c.806 0 1.533-.446 2.031-1.08a9.041 9.041 0 0 1 2.861-2.4c.723-.384 1.35-.956 1.653-1.715a4.498 4.498 0 0 0 .322-1.672V2.75a.75.75 0 0 1 .75-.75 2.25 2.25 0 0 1 2.25 2.25c0 1.152-.26 2.243-.723 3.218-.266.558.107 1.282.725 1.282m0 0h3.126c1.026 0 1.945.694 2.054 1.715.045.422.068.85.068 1.285a11.95 11.95 0 0 1-2.649 7.521c-.388.482-.987.729-1.605.729H13.48c-.483 0-.964-.078-1.423-.23l-3.114-1.04a4.501 4.501 0 0 0-1.423-.23H5.904m10.598-9.75H14.25M5.904 18.5c.083.205.173.405.27.602.197.4-.078.898-.523.898h-.908c-.889 0-1.713-.518-1.972-1.368a12 12 0 0 1-.521-3.507c0-1.553.295-3.036.831-4.398C3.387 10.203 4.167 9.75 5 9.75h1.053c.472 0 .745.556.5.96a8.958 8.958 0 0 0-1.302 4.665c0 1.194.232 2.333.654 3.375Z" />
            </svg>`;
            this.thumbsUpButtonTarget.setAttribute('title', 'Mark as helpful');
        }
        
        if (this.hasThumbsDownIconTarget) {
            // Reset to outline icon
            this.thumbsDownIconTarget.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                <path stroke-linecap="round" stroke-linejoin="round" d="M7.498 15.25H4.372c-1.026 0-1.945-.694-2.054-1.715a12.137 12.137 0 0 1-.068-1.285c0-2.848.992-5.464 2.649-7.521C5.287 4.247 5.886 4 6.504 4h4.016a4.5 4.5 0 0 1 1.423.23l3.114 1.04a4.5 4.5 0 0 0 1.423.23h1.294M7.498 15.25c.618 0 .991.724.725 1.282A7.471 7.471 0 0 0 7.5 19.75 2.25 2.25 0 0 0 9.75 22a.75.75 0 0 0 .75-.75v-.633c0-.573.11-1.14.322-1.672.304-.76.93-1.33 1.653-1.715a9.04 9.04 0 0 0 2.86-2.4c.498-.634 1.226-1.08 2.032-1.08h.384m-10.253 1.5H9.7m8.075-9.75c.01.05.027.1.05.148.593 1.2.925 2.55.925 3.977 0 1.487-.36 2.89-.999 4.125m.023-8.25c-.076-.365.183-.75.575-.75h.908c.889 0 1.713.518 1.972 1.368.339 1.11.521 2.287.521 3.507 0 1.553-.295 3.036-.831 4.398-.306.774-1.086 1.227-1.918 1.227h-1.053c-.472 0-.745-.556-.5-.96a8.95 8.95 0 0 0 .303-.54" />
            </svg>`;
            this.thumbsDownButtonTarget.setAttribute('title', 'Mark as not helpful');
        }
    }

    /**
     * Set loading state for the feedback buttons
     * @param {boolean} isLoading - Whether to show loading state
     * @private
     */
    _setLoadingState(isLoading) {
        this._isLoading = isLoading;
        
        // Disable/enable buttons
        if (this.hasThumbsUpButtonTarget) {
            this.thumbsUpButtonTarget.disabled = isLoading;
        }
        
        if (this.hasThumbsDownButtonTarget) {
            this.thumbsDownButtonTarget.disabled = isLoading;
        }
        
        // Show/hide loading indicator
        if (this.hasLoadingIndicatorTarget) {
            this.loadingIndicatorTarget.style.display = isLoading ? 'inline-block' : 'none';
        }
        
        // Add loading class to buttons for visual feedback
        const buttons = [this.thumbsUpButtonTarget, this.thumbsDownButtonTarget].filter(Boolean);
        buttons.forEach(button => {
            if (isLoading) {
                button.classList.add('loading');
            } else {
                button.classList.remove('loading');
            }
        });
    }

    /**
     * Show a notification using the notification controller
     * @param {string} message - The message to display
     * @param {string} type - The type of notification (success, info, warning, danger)
     * @private
     */
    _showNotification(message, type = 'info') {
        // Try to get the global notification controller
        const notificationElement = document.getElementById('notifications');
        if (notificationElement) {
            const notificationController = this.application.getControllerForElementAndIdentifier(
                notificationElement,
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
                return;
            }
        }
        
        // Fallback: show a simple alert if notification controller is not available
        alert(message);
    }
}