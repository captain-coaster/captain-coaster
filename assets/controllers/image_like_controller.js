import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
    static targets = ["icon", "counter"];
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
                Routing.generate("like_image_async", {
                    id: this.imageIdValue,
                    _locale: this.localeValue,
                }),
                {
                    method: "GET",
                    headers: { "X-Requested-With": "XMLHttpRequest" },
                }
            );

            if (response.status === 403) {
                // Forbidden - user trying to like their own picture
                // Animation already played, but don't change the heart state
                return;
            }

            if (!response.ok) throw new Error("Toggle like failed");

            const data = await response.json();

            // Update state from server response
            this.likedValue = data.liked;
            this.updateIcon();

            // Update counter with actual count from server
            if (this.hasCounterTarget && data.likeCount !== undefined) {
                this.counterTarget.textContent = data.likeCount;
            }
        } catch (error) {
            console.error("Like toggle error:", error);
        }
    }

    addZoomAnimation() {
        if (!this.hasIconTarget) return;

        const icon = this.iconTarget;
        icon.style.transform = "scale(1.3)";
        icon.style.transition = "transform 0.2s ease";

        setTimeout(() => {
            icon.style.transform = "scale(1)";
        }, 200);
    }

    updateIcon() {
        if (!this.hasIconTarget) return;

        const icon = this.iconTarget;

        if (this.likedValue) {
            // Liked state - solid heart
            icon.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-5 h-5">
                <path d="m11.645 20.91-.007-.003-.022-.012a15.247 15.247 0 0 1-.383-.218 25.18 25.18 0 0 1-4.244-3.17C4.688 15.36 2.25 12.174 2.25 8.25 2.25 5.322 4.714 3 7.688 3A5.5 5.5 0 0 1 12 5.052 5.5 5.5 0 0 1 16.313 3c2.973 0 5.437 2.322 5.437 5.25 0 3.925-2.438 7.111-4.739 9.256a25.175 25.175 0 0 1-4.244 3.17 15.247 15.247 0 0 1-.383.219l-.022.012-.007.004-.003.001a.752.752 0 0 1-.704 0l-.003-.001Z" />
            </svg>`;
        } else {
            // Not liked state - outline heart
            icon.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12Z" />
            </svg>`;
        }
    }

    likedValueChanged() {
        this.updateIcon();
    }
}
