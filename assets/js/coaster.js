/*
 * Coaster page specific JavaScript
 * 
 * This file contains JavaScript specific to coaster detail pages
 */

/*
 * Coaster page specific JavaScript
 * 
 * This file contains JavaScript specific to coaster detail pages
 */

// RateIt moved to global app bundle since it's used on multiple pages

// Load ApexCharts and make it globally available
async function loadApexCharts() {
    try {
        const ApexCharts = (await import('apexcharts')).default;
        window.ApexCharts = ApexCharts;
        console.log('ApexCharts loaded and available globally');
        console.log('ApexCharts type:', typeof window.ApexCharts);
        
        // Dispatch a custom event to let the page know ApexCharts is ready
        window.dispatchEvent(new CustomEvent('apexcharts-ready'));
    } catch (error) {
        console.error('Failed to load ApexCharts:', error);
    }
}

// Load ApexCharts immediately
loadApexCharts();

console.log('Coaster entry point loaded with jQuery RateIt');