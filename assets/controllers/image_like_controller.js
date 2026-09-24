import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['icon', 'counter'];
    static values = {
        imageId: Number,
        locale: String,
        liked: Boolean,
    };

    async toggle(event) {
        event.preventDefault();

        // Add zoom animation
        this.addZoomAnimation();

        try {
            const response = await fetch(
                Routing.generate('like_image_async', {
                    id: this.imageIdValue,
                    _locale: this.localeValue,
                }),
                {
                    method: 'GET',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                }
            );

            if (response.status === 403) {
                // Forbidden - user trying to like their own picture
                // Animation already played, but don't change the heart state
                return;
            }

            if (!response.ok) throw new Error('Toggle like failed');

            const data = await response.json();

            // Update state from server response
            this.likedValue = data.liked;
            this.updateIcon();

            // Update counter with actual count from server
            if (this.hasCounterTarget && data.likeCount !== undefined) {
                this.counterTarget.textContent = data.likeCount;
            }
        } catch (error) {
            console.error('Like toggle error:', error);
        }
    }

    addZoomAnimation() {
        if (!this.hasIconTarget) return;

        const icon = this.iconTarget;
        icon.style.transform = 'scale(1.3)';
        icon.style.transition = 'transform 0.2s ease';

        setTimeout(() => {
            icon.style.transform = 'scale(1)';
        }, 200);
    }

    updateIcon() {
        if (!this.hasIconTarget) return;

        // The heart fills through CSS (group-aria-pressed on the icon).
        this.iconTarget.setAttribute('aria-pressed', String(this.likedValue));
    }

    likedValueChanged() {
        this.updateIcon();
    }
}
