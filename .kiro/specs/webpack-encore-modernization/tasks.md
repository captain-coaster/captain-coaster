# Implementation Plan

-   [x] 1. Set up modern Webpack Encore configuration

    -   Update webpack.config.js with LESS loader support and modern optimization settings
    -   Configure proper entry points for different page types (app, coaster, admin)
    -   Enable PostCSS processing for autoprefixing and optimization
    -   Set up environment-specific configurations for development and production
    -   _Requirements: 1.1, 1.3, 5.1, 5.4_

-   [x] 2. Implement build artifact management

    -   Update .gitignore to exclude all build artifacts from version control
    -   Clean existing committed build files from public/build directory
    -   Configure proper output paths and public path settings
    -   Set up asset versioning and manifest generation
    -   _Requirements: 1.1, 1.2, 4.4_

-   [x] 3. Analyze and map current CSS usage

    -   Create analysis script to identify which CSS classes are currently used in templates
    -   Map current CSS usage to corresponding LESS template files in assets/less
    -   Document which template components are actively used vs unused
    -   Generate report of potentially unused LESS components for safe removal
    -   _Requirements: 2.1, 2.2, 4.1_

-   [x] 4. Install and configure npm Bootstrap 3.3.7

    -   Install Bootstrap 3.3.7 from npm to match current version (replacing 36KB+ compiled CSS)
    -   Install jQuery as dependency (required for Bootstrap 3.x)
    -   Configure Bootstrap 3.x JavaScript imports in assets/js/app.js (modals, dropdowns, tooltips)
    -   Replace public/js/core/libraries/bootstrap.min.js with npm version
    -   _Requirements: 2.1, 3.1, 3.2_

-   [x] 5. Create custom theme structure with Bootstrap 3.3.7 integration

    -   Move relevant custom theme LESS files from assets/less to assets/styles/theme/
    -   Keep LESS format for Bootstrap 3.x compatibility (no SCSS conversion needed)
    -   Create assets/styles/app.less as main entry point importing Bootstrap 3.3.7 and custom theme
    -   Set up Bootstrap 3.x variable overrides for custom colors, fonts, and component styling
    -   _Requirements: 2.1, 2.3, 2.5_

-   [x] 6. Migrate public JS files to assets and analyze usage

    -   Analyze public/js/ directory structure and identify which files are actively used
    -   Move public/js/core/app.min.js and layout_fixed_custom.js to assets/js/theme/
    -   Move public/js/pages/rating.js to assets/js/pages/ and integrate with Webpack
    -   Migrate public/js/plugins/ (typeahead, rateit, etc.) to npm dependencies where possible
    -   _Requirements: 3.1, 3.2, 4.2_

-   [x] 7. Modernize JavaScript entry points

    -   Refactor assets/js/app.js to import Bootstrap 3.3.7 and jQuery from npm
    -   Update assets/js/coaster.js to use proper module imports for ApexCharts
    -   Create new assets/js/admin.js entry point for admin-specific functionality
    -   Implement proper code splitting and lazy loading for non-critical JavaScript
    -   _Requirements: 3.1, 3.2, 3.5_

-   [x] 8. Modernize jQuery usage and Stimulus integration

    -   Keep jQuery for Bootstrap 3.x compatibility but optimize usage
    -   Update review_actions_controller.js to use Bootstrap 3.x modal API efficiently
    -   Create modal_controller.js Stimulus controller for Bootstrap modal management
    -   Ensure all interactive functionality continues to work with jQuery + Bootstrap 3.x
    -   _Requirements: 3.1, 3.3, 3.5_

-   [x] 9. Implement image asset optimization

    -   Configure Webpack to process and optimize images from assets/images directory
    -   Set up automatic WebP generation for modern browsers with fallbacks
    -   Implement proper asset hashing and versioning for images
    -   Create image processing pipeline with compression and optimization
    -   _Requirements: 4.3, 4.4_

-   [x] 10. Clean up legacy assets and reorganize structure

    -   Remove large compiled CSS files (bootstrap.css, components.css, core.css, colors.css) - 36KB+ total
    -   Clean up unused LESS template files based on analysis (89 files with 0% usage identified)
    -   Remove unused public/js files after migration to assets/
    -   Reorganize remaining custom theme files to assets/styles/theme/ directory
    -   _Requirements: 2.2, 4.2_

-   [ ] 11. Set up development workflow

    -   Configure webpack-dev-server for hot module replacement during development
    -   Implement watch mode with fast incremental builds
    -   Set up source map generation for CSS and JavaScript debugging
    -   Create npm scripts for different build scenarios (dev, watch, production)
    -   _Requirements: 5.1, 5.2, 5.3, 5.5_

-   [ ] 12. Implement production optimizations

    -   Configure tree shaking for JavaScript modules to eliminate dead code
    -   Set up CSS optimization and minification for production builds
    -   Implement proper asset compression and caching headers
    -   Configure bundle splitting for optimal loading performance
    -   _Requirements: 1.5, 2.4, 4.4_

-   [ ]\* 13. Create build verification tests

    -   Write tests to verify all assets compile without errors
    -   Implement bundle size monitoring and alerts for size regressions
    -   Create visual regression tests to ensure styling consistency
    -   Set up automated testing for cross-browser asset compatibility
    -   _Requirements: 1.4, 2.5, 3.5_

-   [x] 14. Update template references and finalize migration
    -   Update base.html.twig to remove public/js references and use Webpack entries
    -   Remove old asset references and ensure all pages load new optimized assets
    -   Verify all Stimulus controllers are properly registered and functional
    -   Test complete application functionality with new asset system
    -   _Requirements: 1.3, 2.5, 3.5, 4.4, 5.4_
-   [x] 15. Analyze and integrate missing purchased theme components
    -   Analyze assets/less reference folder to identify missing theme components
    -   Review public/fonts, public/js/core, and public/js/plugins for essential assets
    -   Use CSS usage analysis scripts to identify which theme files are actually needed
    -   Integrate missing theme LESS files into assets/styles/theme structure
    -   Clean up unused Bootstrap imports and focus on theme-specific styling
    -   Migrate essential JavaScript from public/js/core and public/js/plugins
    -   Update font loading strategy for OpenSans and other theme fonts
    -   _Requirements: 1.1, 2.1, 3.1, 4.1, 5.1_
