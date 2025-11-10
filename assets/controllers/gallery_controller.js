import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
    static targets = ["link"];
    
    connect() {
        this.currentIndex = 0;
        this.images = this.linkTargets.map(link => ({
            src: link.href
        }));
    }

    linkTargetConnected(element) {
        element.addEventListener('click', this.open.bind(this));
    }

    linkTargetDisconnected(element) {
        element.removeEventListener('click', this.open.bind(this));
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
                <img class="captain-gallery-image" src="" alt="" style="display: none;">
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
        document.body.style.overflow = 'hidden';
    }

    bindEvents() {
        this.overlay.querySelector('.captain-gallery-close').onclick = () => this.close();
        this.overlay.querySelector('.captain-gallery-prev').onclick = () => this.prev();
        this.overlay.querySelector('.captain-gallery-next').onclick = () => this.next();
        this.overlay.onclick = (e) => e.target === this.overlay && this.close();
        
        this.keyHandler = (e) => {
            if (e.key === 'Escape') this.close();
            if (e.key === 'ArrowLeft') this.prev();
            if (e.key === 'ArrowRight') this.next();
        };
        document.addEventListener('keydown', this.keyHandler);
    }

    loadImage() {
        const { src } = this.images[this.currentIndex];
        
        // Show loader
        this.loader.style.display = 'block';
        this.image.style.display = 'none';
        
        // Load image
        const img = new Image();
        img.onload = () => {
            this.image.src = src;
            this.loader.style.display = 'none';
            this.image.style.display = 'block';
        };
        img.onerror = () => {
            this.loader.style.display = 'none';
        };
        img.src = src;
    }

    prev() {
        this.currentIndex = this.currentIndex > 0 ? this.currentIndex - 1 : this.images.length - 1;
        this.loadImage();
    }

    next() {
        this.currentIndex = this.currentIndex < this.images.length - 1 ? this.currentIndex + 1 : 0;
        this.loadImage();
    }

    close() {
        document.removeEventListener('keydown', this.keyHandler);
        document.body.style.overflow = '';
        this.overlay?.remove();
    }
}