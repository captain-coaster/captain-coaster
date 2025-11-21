import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
    static targets = ["star"];
    static values = {
        coasterId: Number,
        currentValue: Number,
        ratingId: Number,
        locale: String,
        readonly: Boolean,
        formFieldId: String,
    };
    static outlets = ["csrf-protection"];

    connect() {
        this.renderStars();
        this.setupEventListeners();
    }

    renderStars() {
        const container = this.element;
        container.classList.add("rating-stars");

        for (let i = 1; i <= 5; i++) {
            const star = document.createElement("span");
            star.className = "rating-star";
            star.dataset.ratingTarget = "star";
            star.dataset.value = i;
            star.innerHTML = this.getStarSVG(i);
            container.appendChild(star);
        }

        this.updateStarDisplay(this.currentValueValue || 0);
    }

    getStarSVG(position) {
        const uniqueId = `star-gradient-${this.coasterIdValue}-${position}`;
        return `<svg class="star-svg" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
            <defs>
                <linearGradient id="${uniqueId}">
                    <stop offset="50%" class="star-fill-left"/>
                    <stop offset="50%" class="star-fill-right"/>
                </linearGradient>
            </defs>
            <path class="star-outline" stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 0 1 1.04 0l2.125 5.111a.563.563 0 0 0 .475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 0 0-.182.557l1.285 5.385a.562.562 0 0 1-.84.61l-4.725-2.885a.562.562 0 0 0-.586 0L6.982 20.54a.562.562 0 0 1-.84-.61l1.285-5.386a.562.562 0 0 0-.182-.557l-4.204-3.602a.562.562 0 0 1 .321-.988l5.518-.442a.563.563 0 0 0 .475-.345L11.48 3.5Z" />
            <path class="star-fill" fill-rule="evenodd" d="M10.788 3.21c.448-1.077 1.976-1.077 2.424 0l2.082 5.006 5.404.434c1.164.093 1.636 1.545.749 2.305l-4.117 3.527 1.257 5.273c.271 1.136-.964 2.033-1.96 1.425L12 18.354 7.373 21.18c-.996.608-2.231-.29-1.96-1.425l1.257-5.273-4.117-3.527c-.887-.76-.415-2.212.749-2.305l5.404-.434 2.082-5.005Z" clip-rule="evenodd" fill="url(#${uniqueId})" />
        </svg>`;
    }

    setupEventListeners() {
        if (this.readonlyValue) return;

        const isMobile = "ontouchstart" in window;

        if (isMobile) {
            this.setupMobileEvents();
        } else {
            this.setupDesktopEvents();
        }
    }

    setupMobileEvents() {
        this.touchStartY = 0;
        this.touchStartX = 0;
        this.isScrolling = false;
        this.hoverValue = 0;

        this.element.addEventListener(
            "touchstart",
            (e) => {
                this.touchStartY = e.touches[0].clientY;
                this.touchStartX = e.touches[0].clientX;
                this.isScrolling = false;

                const value = this.getValueFromEvent(e.touches[0]);
                this.hoverValue = value;
                this.updateStarDisplay(value);
            },
            { passive: true }
        );

        this.element.addEventListener(
            "touchmove",
            (e) => {
                const touchY = e.touches[0].clientY;
                const touchX = e.touches[0].clientX;
                const deltaY = Math.abs(touchY - this.touchStartY);
                const deltaX = Math.abs(touchX - this.touchStartX);

                // If vertical movement is greater than horizontal, user is scrolling
                if (deltaY > 10 && deltaY > deltaX) {
                    this.isScrolling = true;
                    this.updateStarDisplay(this.currentValueValue || 0);
                } else if (!this.isScrolling) {
                    // Update preview if not scrolling
                    const value = this.getValueFromEvent(e.touches[0]);
                    this.hoverValue = value;
                    this.updateStarDisplay(value);
                }
            },
            { passive: true }
        );

        this.element.addEventListener("touchend", (e) => {
            if (!this.isScrolling && this.hoverValue > 0) {
                this.setRating(this.hoverValue);
            } else {
                this.updateStarDisplay(this.currentValueValue || 0);
            }

            this.isScrolling = false;
            this.hoverValue = 0;
        });

        this.element.addEventListener("touchcancel", () => {
            this.isScrolling = false;
            this.hoverValue = 0;
            this.updateStarDisplay(this.currentValueValue || 0);
        });
    }

    setupDesktopEvents() {
        this.element.addEventListener("mousemove", (e) => {
            const value = this.getValueFromEvent(e);
            this.updateStarDisplay(value);
        });

        this.element.addEventListener("mouseleave", () => {
            this.updateStarDisplay(this.currentValueValue || 0);
        });

        this.element.addEventListener("click", (e) => {
            const value = this.getValueFromEvent(e);
            if (value > 0) {
                this.setRating(value);
            }
        });
    }

    getValueFromEvent(event) {
        const rect = this.element.getBoundingClientRect();
        const x = event.clientX - rect.left;
        const width = rect.width;
        const rawValue = (x / width) * 5;
        return Math.max(0.5, Math.min(5, Math.round(rawValue * 2) / 2));
    }

    updateStarDisplay(value) {
        this.starTargets.forEach((star, index) => {
            const starValue = index + 1;

            // Remove all state classes
            star.classList.remove("star-full", "star-half", "star-empty");

            if (value >= starValue) {
                star.classList.add("star-full");
            } else if (value >= starValue - 0.5) {
                star.classList.add("star-half");
            } else {
                star.classList.add("star-empty");
            }
        });
    }

    async setRating(value) {
        if (value === this.currentValueValue) return;

        const previousValue = this.currentValueValue;
        this.currentValueValue = value;
        this.updateStarDisplay(value);

        // Check if we're in form mode (has a form field to update)
        if (this.hasFormFieldIdValue) {
            const field = document.getElementById(this.formFieldIdValue);
            if (field) {
                field.value = value;
            }
            return;
        }

        // API mode: save to backend
        const wasNew = !this.ratingIdValue;

        try {
            const url = Routing.generate("rating_edit", {
                id: this.coasterIdValue,
                _locale: this.localeValue,
            });

            const response = await fetch(url.replace(/^http:/, "https:"), {
                method: "POST",
                headers: {
                    "Content-Type": "application/x-www-form-urlencoded",
                    "X-Requested-With": "XMLHttpRequest",
                },
                body: this.csrfProtectionOutlet
                    ? this.csrfProtectionOutlet.addTokenToBody(`value=${value}`)
                    : `value=${value}`,
            });

            if (!response.ok) throw new Error("Failed to save rating");

            const data = await response.json();
            if (data.id) this.ratingIdValue = data.id;

            // Add sparkle effect
            this.element.classList.add("rating-confirmed");
            setTimeout(() => {
                this.element.classList.remove("rating-confirmed");
            }, 600);

            this.dispatch(wasNew ? "created" : "updated", {
                detail: { ratingId: data.id || this.ratingIdValue },
                bubbles: true,
            });
        } catch (error) {
            console.error("Rating save failed:", error);

            // Revert to previous value
            this.currentValueValue = previousValue;
            this.updateStarDisplay(previousValue || 0);

            const errorMsg = error.message.includes("Network")
                ? "Network error. Rating not saved."
                : "Unable to save rating. Please try again.";

            this.dispatch("error", { detail: { message: errorMsg } });
        }
    }

    resetToZero() {
        this.currentValueValue = 0;
        this.ratingIdValue = null;
        this.updateStarDisplay(0);
        this.dispatch("deleted", { bubbles: true });
    }
}
