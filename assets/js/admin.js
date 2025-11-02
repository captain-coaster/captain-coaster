/*
 * Admin interface specific JavaScript
 * 
 * This file contains JavaScript specific to admin pages and EasyAdmin interface
 */

// Import jQuery for admin interface compatibility (required for EasyAdmin)
import $ from "jquery";
global.$ = global.jQuery = $;

// Import Bootstrap 3.3.7 components needed for admin interface
import "bootstrap/js/dropdown";
import "bootstrap/js/modal";
import "bootstrap/js/tooltip";
import "bootstrap/js/popover";
import "bootstrap/js/collapse";
import "bootstrap/js/tab";
import "bootstrap/js/alert";
import "bootstrap/js/button";
import "bootstrap/js/transition";

// Import admin-specific styles (using modern LESS-based styles)
import "../css/icons/icomoon/styles.css";
import "../styles/admin.less";

// Import lazy loading utilities for admin-specific code splitting
import { lazyLoad, lazyLoadOnInteraction, loadModules } from "./modules/lazy-loader";

// Admin-specific module configuration
const adminModuleConfig = {
    critical: [
        // Critical admin modules loaded immediately
    ],
    nonCritical: [
        {
            name: 'RateIt',
            importFn: async () => {
                await Promise.all([
                    import(/* webpackChunkName: "rateit" */ "jquery.rateit"),
                    import(/* webpackChunkName: "rateit" */ "jquery.rateit/scripts/rateit.css")
                ]);
                
                // Initialize rating widgets in admin forms
                $('.rateit').rateit();
                
                return { loaded: true };
            },
            trigger: {
                type: 'visible',
                selector: '.rateit, [data-rateit]'
            },
            options: { cache: true }
        },
        {
            name: 'AdminCharts',
            importFn: async () => {
                const { loadApexCharts } = await import(/* webpackChunkName: "admin-charts" */ './coaster');
                return await loadApexCharts();
            },
            trigger: {
                type: 'visible',
                selector: '[data-admin-chart], .admin-chart'
            },
            options: { cache: true }
        }
    ]
};

// Load admin-specific features based on page content
const initializeAdminFeatures = () => {
    // Initialize Bootstrap components for admin interface
    $('[data-toggle="tooltip"]').tooltip();
    $('[data-toggle="popover"]').popover();
    
    // Initialize admin-specific modal handling
    $('.modal').on('shown.bs.modal', function() {
        $(this).find('[autofocus]').focus();
    });
    
    // Admin form enhancements
    initializeAdminForms();
    
    // Load admin modules with lazy loading
    loadModules(adminModuleConfig);
};

// Admin form initialization and enhancements
const initializeAdminForms = () => {
    // Enhanced form validation feedback
    $('form').on('submit', function() {
        const $form = $(this);
        const $submitBtn = $form.find('[type="submit"]');
        
        // Disable submit button to prevent double submission
        $submitBtn.prop('disabled', true).addClass('loading');
        
        // Re-enable after a delay if form doesn't redirect
        setTimeout(() => {
            $submitBtn.prop('disabled', false).removeClass('loading');
        }, 3000);
    });
    
    // Auto-resize textareas
    $('textarea').each(function() {
        this.style.height = 'auto';
        this.style.height = this.scrollHeight + 'px';
    }).on('input', function() {
        this.style.height = 'auto';
        this.style.height = this.scrollHeight + 'px';
    });
    
    // Enhanced select dropdowns
    $('select[multiple]').addClass('form-control-multiple');
    
    // Lazy load advanced admin features when forms are interacted with
    lazyLoadOnInteraction(
        '[data-admin-advanced], .admin-advanced',
        async () => {
            // Future: Load advanced admin features here
            console.log('Advanced admin features loaded');
            return { loaded: true };
        },
        'AdvancedAdminFeatures',
        'focus',
        { cache: true }
    );
};

// Start the Stimulus application for admin controllers
import "../bootstrap";

// Initialize admin interface when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        initializeAdminFeatures();
        loadAdminModules();
    });
} else {
    initializeAdminFeatures();
    loadAdminModules();
}

console.log('Admin interface JavaScript loaded with modern module imports');