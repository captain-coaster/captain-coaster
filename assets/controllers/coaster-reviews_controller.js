import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['container'];
    static values = { 
        slug: String,
        locale: String
    };

    connect() {
        // Load initial reviews when controller connects
        // Add a small delay to ensure Routing is available
        setTimeout(() => {
            this.loadReviews(1);
        }, 100);
    }

    loadReviews(page = 1) {
        // Make AJAX request to load reviews
        fetch(this.#buildReviewsUrl(page), {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.text();
            })
            .then(html => {
                // Replace container content
                this.containerTarget.outerHTML = html;
                
                // Scroll to reviews if not first page
                if (page !== 1) {
                    const reviewsElement = document.getElementById('coaster-reviews');
                    if (reviewsElement) {
                        reviewsElement.scrollIntoView();
                    }
                }
                
                // Re-attach pagination handlers
                this.#attachPaginationHandlers();
            })
            .catch(error => {
                console.error('Error loading reviews:', error);
                // Hide reviews section if it fails to load
                this.containerTarget.style.display = 'none';
            });
    }

    #buildReviewsUrl(page) {
        // Use Symfony's exposed routing (requires FOSJsRoutingBundle)
        if (typeof Routing !== 'undefined' && Routing.generate) {
            try {
                return Routing.generate('coaster_reviews_ajax_load', {
                    'slug': this.slugValue,
                    'page': page,
                    '_locale': this.localeValue,
                });
            } catch (error) {
                console.warn('Routing.generate failed, using fallback URL:', error);
            }
        }
        
        // Fallback to manual URL construction
        const baseUrl = window.location.origin;
        const locale = this.localeValue;
        const slug = this.slugValue;
        
        return `${baseUrl}/${locale}/coasters/${slug}/reviews/ajax/${page}`;
    }

    #attachPaginationHandlers() {
        // Find pagination links and attach click handlers
        const paginationLinks = document.querySelectorAll('ul.pagination a');
        paginationLinks.forEach(link => {
            link.addEventListener('click', (event) => {
                event.preventDefault();
                const page = link.dataset.page || 1;
                this.loadReviews(parseInt(page));
            });
        });
    }
}