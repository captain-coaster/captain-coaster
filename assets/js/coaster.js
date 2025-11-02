/*
 * Coaster page specific JavaScript
 * 
 * This file contains JavaScript specific to coaster detail pages
 */

// Import lazy loading utilities
import { lazyLoad, lazyLoadOnVisible } from "./modules/lazy-loader";

// Import rating functionality for coaster pages (critical for coaster functionality)
import "./pages/rating";

// ApexCharts loader with proper code splitting
const loadApexCharts = async () => {
    return lazyLoad(
        () => import(/* webpackChunkName: "apexcharts" */ 'apexcharts'),
        'ApexCharts',
        {
            cache: true,
            timeout: 15000,
            onError: (error) => {
                console.warn('ApexCharts failed to load, charts will not be available');
            }
        }
    ).then(module => {
        const ApexCharts = module.default;
        
        // Make ApexCharts available globally for legacy template code
        window.ApexCharts = ApexCharts;
        
        // Dispatch a custom event to let the page know ApexCharts is ready
        window.dispatchEvent(new CustomEvent('apexcharts-ready', {
            detail: { ApexCharts }
        }));
        
        return ApexCharts;
    });
};

// Export loadApexCharts for use by other modules (like admin.js)
export { loadApexCharts };

// Initialize coaster-specific features with lazy loading
const initializeCoasterFeatures = () => {
    // Lazy load ApexCharts when chart containers become visible
    lazyLoadOnVisible(
        '[data-chart], .apexcharts-container, .chart-container',
        () => import(/* webpackChunkName: "apexcharts" */ 'apexcharts'),
        'ApexCharts',
        {
            cache: true,
            onError: (error) => {
                console.warn('Charts not available on this page');
            }
        }
    );
    
    // Lazy load additional coaster features when needed
    const coasterFeatureElements = document.querySelectorAll('[data-coaster-feature]');
    if (coasterFeatureElements.length > 0) {
        lazyLoadOnVisible(
            '[data-coaster-feature]',
            async () => {
                // Future: Load additional coaster-specific modules here
                return { loaded: true };
            },
            'CoasterFeatures',
            { cache: true }
        );
    }
    
    // Preload image optimization features for coaster galleries
    if (document.querySelector('.coaster-images, .image-gallery')) {
        setTimeout(() => {
            lazyLoad(
                async () => {
                    // Future: Load image optimization features
                    return { loaded: true };
                },
                'ImageOptimization',
                { cache: true }
            );
        }, 1000);
    }
};

// Initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeCoasterFeatures);
} else {
    initializeCoasterFeatures();
}