import { Controller } from '@hotwired/stimulus';
import { lockScroll, unlockScroll, show, hide } from '../js/utils/dom.js';

export default class extends Controller {
    static targets = ['link'];

    connect() {
        this.currentIndex = 0;
        this.images = this.linkTargets.map((link) => ({
            src: link.href,
        }));
        this.touchStartX = 0;
        this.touchEndX = 0;
    }

    linkTargetConnected(element) {
        this.boundOpen = this.open.bind(this);
        element.addEventListener('click', this.boundOpen);
    }

    linkTargetDisconnected(element) {
        if (this.boundOpen) {
            element.removeEventListener('click', this.boundOpen);
        }
    }

    open(event) {
        event.preventDefault();
        this.currentIndex = this.linkTargets.indexOf(event.currentTarget);
        this.showLightbox();
    }

    showLightbox() {
        const overlay = document.createElement('div');
        overlay.className = 'captain-gallery-lightbox';
        overlay.innerHTML = `
            <div class="captain-gallery-container">
                <div class="captain-gallery-loader"></div>
                <img class="captain-gallery-image hidden" src="" alt="">
                <button class="captain-gallery-close">&times;</button>
                <button class="captain-gallery-prev">&larr;</button>
                <button class="captain-gallery-next">&rarr;</button>
            </div>
        `;

        this.overlay = overlay;
        this.image = overlay.querySelector('.captain-gallery-image');
        this.loader = overlay.querySelector('.captain-gallery-loader');

        this.bindEvents();
        this.loadImage();

        document.body.appendChild(overlay);
        lockScroll();
    }

    bindEvents() {
        this.overlay.querySelector('.captain-gallery-close').onclick = () =>
            this.close();
        this.overlay.querySelector('.captain-gallery-prev').onclick = () =>
            this.prev();
        this.overlay.querySelector('.captain-gallery-next').onclick = () =>
            this.next();
        this.overlay.onclick = (e) => e.target === this.overlay && this.close();

        this.keyHandler = (e) => {
            if (e.key === 'Escape') this.close();
            if (e.key === 'ArrowLeft') this.prev();
            if (e.key === 'ArrowRight') this.next();
        };
        document.addEventListener('keydown', this.keyHandler);

        // Touch events for swipe on mobile
        this.overlay.addEventListener(
            'touchstart',
            (e) => this.handleTouchStart(e),
            { passive: true }
        );
        this.overlay.addEventListener(
            'touchend',
            (e) => this.handleTouchEnd(e),
            {
                passive: true,
            }
        );
    }

    handleTouchStart(e) {
        this.touchStartX = e.changedTouches[0].screenX;
    }

    handleTouchEnd(e) {
        this.touchEndX = e.changedTouches[0].screenX;
        this.handleSwipe();
    }

    handleSwipe() {
        const swipeThreshold = 50; // Minimum distance for a swipe
        const diff = this.touchStartX - this.touchEndX;

        if (Math.abs(diff) > swipeThreshold) {
            if (diff > 0) {
                // Swiped left - show next image
                this.next();
            } else {
                // Swiped right - show previous image
                this.prev();
            }
        }
    }

    loadImage() {
        const { src } = this.images[this.currentIndex];

        // Show loader
        show(this.loader);
        hide(this.image);

        // Load image
        const img = new Image();
        img.onload = () => {
            this.image.src = src;
            hide(this.loader);
            show(this.image);
        };
        img.onerror = () => {
            hide(this.loader);
        };
        img.src = src;
    }

    prev() {
        this.currentIndex =
            this.currentIndex > 0
                ? this.currentIndex - 1
                : this.images.length - 1;
        this.loadImage();
    }

    next() {
        this.currentIndex =
            this.currentIndex < this.images.length - 1
                ? this.currentIndex + 1
                : 0;
        this.loadImage();
    }

    close() {
        document.removeEventListener('keydown', this.keyHandler);
        unlockScroll();
        this.overlay?.remove();
    }
}
