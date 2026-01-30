# Implementation Plan: Modern Design System

## Overview

This implementation plan transforms Captain Coaster's base layout and homepage from Bootstrap-based design to a modern, mobile-first Tailwind CSS system. The approach maintains all existing data and functionality while completely modernizing the visual presentation through systematic refactoring of templates and styles.

## Tasks

- [x]   1. Update Tailwind CSS configuration and design tokens
    - Update `assets/css/app.css` with Geist font configuration and refined color palette
    - Configure design tokens for consistent spacing, colors, and typography
    - Remove any remaining Bootstrap dependencies from CSS
    - _Requirements: 3.1, 4.1, 4.2, 4.3_

- [x]   2. Refactor base layout template
    - [x] 2.1 Update `templates/layouts/base.html.twig` with mobile-first responsive structure
        - Implement proper viewport meta tags and font loading
        - Update body classes and container structure for Tailwind
        - Ensure semantic HTML structure with proper ARIA landmarks
        - _Requirements: 1.4, 1.5_

    - [ ]\* 2.2 Write property test for base layout responsiveness
        - **Property 1: Data Preservation with Complete Presentation Transformation**
        - **Validates: Requirements 1.1, 1.2, 1.3, 1.5**

- [ ]   3. Refactor navigation system
    - [x] 3.1 Rebuild navbar template (`templates/partials/_navbar.html.twig`)
        - Implement three-section layout: logo (left), search (center), actions (right)
        - Add notification bell icon with badge count
        - Position hamburger to LEFT of logo on mobile only
        - Optimize search functionality with proper mobile modal
        - _Requirements: 1.1, 2.2, 2.3, 2.4_

    - [x] 3.2 Rebuild sidebar template (`templates/partials/_sidebar.html.twig`)
        - Implement collapsible language dropdown with smooth animations
        - Optimize desktop sidebar spacing and visual hierarchy
        - Ensure proper active state indicators
        - _Requirements: 1.2, 2.4_

    - [x] 3.3 Implement mobile hamburger drawer navigation
        - Create slide-out panel from the left, not covering the header (navbar)
        - Full size (no backdrop)
        - Organize navigation: menu items, user status (bottom) + login/logout
        - Implement collapsible language section
        - Remove bottom navigation bar implementation
        - Use one stimulus controller, and one template for the drawer
        - _Requirements: 2.1, 2.5_

    - [ ]\* 3.4 Write property test for navigation functionality
        - **Property 2: Mobile-First Responsive Navigation**
        - **Validates: Requirements 2.1, 2.5**

- [x]   4. Implement typography and spacing system
    - [x] 4.1 Update font loading to use Geist font family
        - Update font preconnect links and CSS font-family declarations
        - Implement font-display: swap for performance
        - Configure fallback font stack
        - _Requirements: 3.1_

    - [x] 4.2 Apply systematic typography scale across templates
        - Use 14px for dense content (navigation, lists), 15px for standard content, 16px for reading content
        - Implement consistent line-height values (1.4-1.7)
        - Ensure proportional scaling across device breakpoints
        - _Requirements: 3.2, 3.3, 3.5_

    - [ ]\* 4.3 Write property test for typography consistency
        - **Property 3: Typography Scale Consistency**
        - **Validates: Requirements 3.2, 3.3, 3.5**

- [x]   5. Implement color system updates
    - [x] 5.1 Replace pure white/black with warm color palette
        - Update background colors to use neutral-50 instead of pure white
        - Implement bluish/grayish dark mode colors instead of pure black
        - Apply brand colors (cc-blue, cc-warm) as accent colors
        - _Requirements: 4.1, 4.2, 4.3_

    - [x] 5.2 Implement interactive color feedback
        - Add hover, focus, and active states with color transitions
        - Ensure WCAG 2.2 contrast compliance (4.5:1 normal text, 3:1 large text)
        - _Requirements: 4.4, 4.5_

    - [ ]\* 5.3 Write property test for color system compliance
        - **Property 4: Color System Compliance**
        - **Validates: Requirements 4.1, 4.2, 4.3, 4.5**

- [x]   6. Refactor homepage template
    - [x] 6.1 Transform homepage layout (`templates/Default/index.html.twig`)
        - Implement horizontal scrollable stats cards for mobile
        - Create featured image hero section with overlay information
        - Build unified activity feed combining ratings and reviews
        - Remove missing pictures section
        - _Requirements: 5.1, 5.2, 5.3_

    - [x] 6.2 Optimize homepage card layouts
        - Implement consistent card styling (border-radius, shadows, spacing)
        - Add responsive image optimization with AVIF/WebP formats
        - Ensure proper mobile single-column and desktop multi-column layouts
        - _Requirements: 5.4, 5.5_

    - [ ]\* 6.3 Write property test for responsive card behavior
        - **Property 5: Responsive Card Layout Behavior**
        - **Validates: Requirements 5.1, 5.2, 5.3, 5.4, 5.5**

- [ ]   7. Create reusable component library
    - [ ] 7.1 Build card component template
        - Create `templates/components/card.html.twig` with flexible props
        - Implement variants: default, featured, compact
        - Use exclusively Tailwind CSS utility classes
        - _Requirements: 6.1, 6.2_

    - [ ] 7.2 Build navigation component templates
        - Create reusable navigation item templates
        - Implement proper interactive states (hover, focus, active, disabled)
        - Add semantic HTML structure with ARIA attributes
        - _Requirements: 6.3, 6.5_

    - [ ] 7.3 Enable component customization through design tokens
        - Ensure components support Tailwind theme configuration overrides
        - Document component usage and customization options
        - _Requirements: 6.4_

    - [ ]\* 7.4 Write property test for component implementation standards
        - **Property 6: Component Implementation Standards**
        - **Validates: Requirements 6.2, 6.3, 6.5**

- [ ]   8. Update footer template
    - [ ] 8.1 Refactor footer template (`templates/partials/_footer.html.twig`)
        - Apply modern spacing and typography patterns
        - Preserve all existing links and content
        - Implement responsive layout for mobile and desktop
        - _Requirements: 1.3, 1.5_

- [ ]   9. Checkpoint - Ensure all templates render correctly
    - Ensure all templates render correctly, ask the user if questions arise.

- [ ]   10. Performance and accessibility validation
    - [ ] 10.1 Validate CSS bundle size and performance
        - Confirm production CSS bundle remains under 10KB
        - Test font loading performance and layout shift prevention
        - Verify image optimization implementation
        - _Requirements: 7.1, 7.2, 7.3_

    - [ ] 10.2 Conduct accessibility audit
        - Validate semantic HTML structure and ARIA attributes
        - Test keyboard navigation and focus management
        - Verify color contrast compliance across all components
        - _Requirements: 7.4, 7.5_

- [ ]   11. Final integration and testing
    - [ ] 11.1 Integration testing across all refactored templates
        - Test navigation functionality across all screen sizes
        - Verify data preservation and functionality maintenance
        - Validate responsive behavior on mobile, tablet, and desktop
        - _Requirements: 1.5, 8.1, 8.2, 8.3, 8.4, 8.5_

    - [ ]\* 11.2 Write integration tests for complete system
        - Test end-to-end user flows through refactored interface
        - Validate cross-device consistency and responsive behavior

- [ ]   12. Final checkpoint - Complete system validation
    - Ensure all tests pass, ask the user if questions arise.

## Notes

- Tasks marked with `*` are optional and can be skipped for faster MVP
- Each task references specific requirements for traceability
- Checkpoints ensure incremental validation and user feedback
- Property tests validate universal correctness properties from the design document
- Focus on maintaining identical functionality while completely transforming presentation
- All templates should use exclusively Tailwind CSS utilities, avoiding custom CSS classes
