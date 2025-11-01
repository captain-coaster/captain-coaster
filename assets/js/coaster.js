/*
 * Coaster page specific JavaScript
 * 
 * This file contains JavaScript specific to coaster detail pages
 */

// Load ApexCharts and make it globally available
async function loadApexCharts() {
    try {
        const ApexCharts = (await import('apexcharts')).default;
        window.ApexCharts = ApexCharts;
        
        // Dispatch a custom event to let the page know ApexCharts is ready
        window.dispatchEvent(new CustomEvent('apexcharts-ready'));
    } catch (error) {
        console.error('Failed to load ApexCharts:', error);
    }
}

// Load ApexCharts immediately
loadApexCharts();