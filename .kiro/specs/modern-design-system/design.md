# Design Document: Modern Design System

## Overview

The modern design system for Captain Coaster represents a complete architectural transformation from Bootstrap-based layouts to a mobile-first, Tailwind CSS-powered design system. This design focuses on refactoring the core layout components (navbar, sidebar, footer) and homepage while maintaining all existing data and functionality but completely transforming the visual presentation. The system emphasizes performance, accessibility, and modern visual aesthetics that align with 2025 design trends.

The design philosophy centers on three core principles:

1. **Mobile-First Responsive Design**: Every component begins with mobile constraints and progressively enhances for larger screens
2. **Utility-First CSS Architecture**: Leveraging Tailwind CSS utilities for consistent, maintainable styling
3. **Performance-Optimized Delivery**: Minimizing CSS bundle size and optimizing asset loading for fast user experiences

## Architecture

### Component Hierarchy

The design system follows a hierarchical component structure:

```
Base Layout (base.html.twig)
├── Navigation System
│   ├── Desktop Navbar (fixed top)
│   ├── Mobile Bottom Navigation
│   ├── Desktop Sidebar
│   └── Mobile Hamburger Drawer
├── Main Content Area
│   ├── Page Header (optional)
│   ├── Content Container
│   └── Flash Messages
└── Footer
```

### Responsive Breakpoint Strategy

The system uses Tailwind's default breakpoint system with mobile-first approach:

- **Base (0px+)**: Mobile-first styles, single-column layouts
- **sm (640px+)**: Small tablets, refined spacing
- **md (768px+)**: Tablets, sidebar appears, multi-column content
- **lg (1024px+)**: Desktop, full sidebar, optimized spacing
- **xl (1280px+)**: Large desktop, maximum content width constraints

### CSS Architecture

The Tailwind CSS v4 configuration leverages design tokens for consistency:

```css
@theme {
    /* Brand Colors */
    --color-cc-blue-500: #0c8ce9;
    --color-cc-warm-500: #e6b800;

    /* Neutral Palette */
    --color-neutral-50: #fafafa; /* Warm white alternative */
    --color-neutral-900: #171717; /* Soft black alternative */

    /* Typography */
    --font-family-sans: Geist, system-ui, sans-serif;

    /* Spacing Scale */
    --spacing-18: 4.5rem; /* Custom spacing for navigation */
}
```

## Components and Interfaces

### Navigation System

#### Desktop Navbar

- **Fixed positioning** at top of viewport with backdrop blur
- **Three-section layout**: Logo (left), search (center), actions (right)
- **Search integration** with autocomplete and keyboard shortcuts
- **User account dropdown** with profile, ratings, and logout options
- **Notification bell icon** with badge count for unread notifications
- **Theme toggle** for dark/light mode switching

#### Mobile Navigation Strategy

Given the manageable number of navigation items, the mobile strategy uses a **single hamburger menu** approach rather than bottom bar + hamburger:

- **Hamburger icon positioned to the LEFT of the logo** for easy thumb access
- **All navigation items** consolidated in the slide-out drawer
- **Primary navigation** (Home, Rankings, Discover, Map) at the top of drawer
- **Secondary navigation** (Reviews, Users, Tops) in middle section
- **User account functions** and settings at bottom of drawer
- **No bottom navigation bar** to maximize content space and simplify interface

#### Desktop Sidebar

- **Fixed positioning** with full height
- **Collapsible language dropdown** with current language indicator and smooth expand/collapse animation
- **Visual hierarchy** through typography and spacing
- **Active page indicators** with background color and text color changes

#### Mobile Hamburger Drawer

- **Slide-out panel** from left edge
- **Backdrop overlay** with blur effect
- **Primary navigation items** (Home, Rankings, Discover, Map) at top
- **Secondary navigation items** (Reviews, Users, Tops) in middle section
- **User account functions** for authenticated users
- **Collapsible language section** with dropdown behavior for language switching

### Homepage Layout Design

#### Current Content Analysis

The homepage currently contains:

- Live community stats (ratings, reviews, users, images)
- Recently liked picture display
- Latest reviews feed
- Missing pictures section (to be removed)

#### Proposed Mobile-First Transformation

**Mobile Layout (Single Column)**:

1. **Compact Stats Row**: Horizontal scrollable cards showing key metrics with icons
2. **Featured Content Hero**: Recently liked picture as engaging visual anchor
3. **Activity Feed**: Combined latest ratings and reviews in unified timeline
4. **Quick Actions**: Prominent CTAs for core user actions

**Desktop Layout (Multi-Column)**:

1. **Stats Dashboard**: 2x2 grid of metric cards with trend indicators
2. **Featured Content**: Larger hero image with overlay information
3. **Dual Feed Layout**: Latest ratings (left) and reviews (right) in parallel columns
4. **Quick Actions Sidebar**: Vertical action panel for authenticated users

#### Detailed Component Specifications

**Stats Cards (Mobile: Horizontal Scroll, Desktop: Grid)**:

```twig
<!-- Mobile: Horizontal scrollable container -->
<div class="flex gap-3 overflow-x-auto pb-4 snap-x snap-mandatory">
  <div class="flex-none w-32 bg-white dark:bg-neutral-900 rounded-xl p-4 snap-start">
    <div class="text-cc-blue-500 mb-2">{{ ux_icon('tabler:star') }}</div>
    <div class="text-2xl font-semibold">{{ stats.nb_ratings|number_format }}</div>
    <div class="text-xs text-neutral-600 dark:text-neutral-400">Ratings</div>
  </div>
  <!-- Repeat for other stats -->
</div>

<!-- Desktop: 2x2 Grid -->
<div class="hidden md:grid md:grid-cols-2 lg:grid-cols-4 gap-4">
  <!-- Larger cards with trend indicators -->
</div>
```

**Featured Image Hero**:

```twig
<div class="relative bg-white dark:bg-neutral-900 rounded-2xl overflow-hidden">
  <div class="aspect-video relative">
    <img src="{{ image.optimized_url }}" class="w-full h-full object-cover" />
    <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-transparent"></div>
    <div class="absolute bottom-4 left-4 right-4">
      <h3 class="text-white font-semibold text-lg">{{ image.coaster.name }}</h3>
      <p class="text-white/80 text-sm">{{ image.coaster.park.name }}</p>
    </div>
  </div>
  <div class="p-4">
    <p class="text-sm text-neutral-600 dark:text-neutral-400">
      Photo by {{ image.credit }} • {{ image.likes_count }} likes
    </p>
  </div>
</div>
```

**Unified Activity Feed**:

```twig
<div class="bg-white dark:bg-neutral-900 rounded-2xl overflow-hidden">
  <div class="p-4 border-b border-neutral-100 dark:border-neutral-800">
    <h2 class="font-semibold text-lg">Latest Activity</h2>
  </div>
  <div class="divide-y divide-neutral-100 dark:divide-neutral-800">
    {% for item in activityFeed %}
      <div class="p-4 hover:bg-neutral-50 dark:hover:bg-neutral-800/50 transition-colors">
        <!-- Unified rating/review item with consistent layout -->
      </div>
    {% endfor %}
  </div>
</div>
```

#### Space Optimization Strategies

**Eliminated Waste**:

- Remove missing pictures section (as requested)
- Consolidate stats into compact, scannable format
- Merge rating and review feeds into single activity stream
- Use horizontal scrolling on mobile for stats to save vertical space

**Enhanced Density**:

- Stats cards show more information in less space
- Activity feed combines multiple content types efficiently
- Featured image serves as both visual interest and functional content
- Quick actions integrated contextually rather than separate sections

**Mobile-First Improvements**:

- Horizontal scroll for stats prevents cramped vertical stacking
- Single activity feed easier to scan than multiple separate lists
- Featured image optimized for mobile aspect ratios
- Touch-friendly interaction areas throughout

#### Content Prioritization

**Primary (Always Visible)**:

1. Key community stats (ratings, reviews, users)
2. Featured visual content (recently liked image)
3. Latest activity (ratings + reviews combined)

**Secondary (Contextual)**:

1. Quick action buttons for authenticated users
2. Trending content indicators
3. Personalized recommendations (future enhancement)

**Removed**:

1. Missing pictures section (administrative, not user-focused)
2. Excessive whitespace and padding
3. Redundant section headers and dividers

### Card-Based Layout System

#### Homepage Stats Cards

```twig
<div class="bg-white dark:bg-neutral-900 rounded-2xl shadow-sm overflow-hidden">
  <div class="p-6">
    <!-- Card content with consistent spacing -->
  </div>
</div>
```

#### Content Cards

- **Consistent border radius** (2xl = 1rem) for modern appearance
- **Subtle shadows** for depth without overwhelming
- **Responsive padding** that scales with screen size
- **Image optimization** with responsive formats and sizing
- **Hover states** with subtle scale and shadow transitions

### Typography System

#### Font Loading Strategy

```html
<link rel="preconnect" href="https://fonts.googleapis.com" />
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
<link
    href="https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600;700&display=swap"
    rel="stylesheet"
/>
```

#### Size Scale

- **Body text**: 14px for dense content (lists, navigation), 15px for standard content, 16px for reading content
- **Line height**: 1.4-1.7 for optimal readability
- **Heading scale**: Systematic relationships using Tailwind's default scale
- **Mobile optimization**: Proportional scaling without requiring zoom

### Color System

#### Light Mode Palette

- **Background**: `neutral-50` (#fafafa) instead of pure white
- **Cards**: `white` for contrast against warm background
- **Text**: `neutral-900` for primary text, `neutral-600` for secondary
- **Brand accents**: `cc-blue-500` for primary actions, `cc-warm-500` for highlights

#### Dark Mode Palette

- **Background**: `neutral-900` (#171717) instead of pure black
- **Cards**: `neutral-900` with subtle transparency
- **Text**: `white` for primary text, `neutral-400` for secondary
- **Brand accents**: Lighter variants (`cc-blue-400`, `cc-warm-400`) for better contrast

## Data Models

### Navigation Data Structure

```php
// Navigation items with route mapping
$navigationItems = [
    'primary' => [
        ['route' => 'default_index', 'icon' => 'tabler:home', 'label' => 'sidebar.index'],
        ['route' => 'ranking_index', 'icon' => 'tabler:trophy', 'label' => 'sidebar.ranking'],
        ['route' => 'coaster_search_index', 'icon' => 'tabler:search', 'label' => 'sidebar.search'],
        ['route' => 'map_index', 'icon' => 'tabler:map-pin', 'label' => 'sidebar.map'],
    ],
    'secondary' => [
        ['route' => 'top_list', 'icon' => 'tabler:clipboard', 'label' => 'sidebar.top'],
        ['route' => 'review_list', 'icon' => 'tabler:message-2', 'label' => 'sidebar.review'],
        ['route' => 'user_list', 'icon' => 'tabler:users', 'label' => 'sidebar.users'],
    ]
];
```

### Homepage Data Structure

```php
// Homepage statistics and content
$homepageData = [
    'stats' => [
        'nb_ratings' => int,
        'nb_new_ratings' => int,
        'nb_reviews' => int,
        'nb_users' => int,
        'nb_images' => int,
    ],
    'featuredImage' => Image,
    'ratingFeed' => RiddenCoaster[],
    'reviews' => Review[],
    'missingImages' => RiddenCoaster[],
];
```

### Twig Template Data Structures

**Card Component Variables**:

```twig
{# Card template expects these variables #}
{% set card = {
    title: 'Card Title',
    subtitle: 'Optional subtitle',
    image: {
        src: '/path/to/image.jpg',
        alt: 'Image description',
        aspectRatio: '16:9'  {# or 'square', '4:3' #}
    },
    actions: [
        { label: 'View', url: '/path', variant: 'primary' },
        { label: 'Edit', url: '/edit', variant: 'secondary' }
    ],
    variant: 'default'  {# or 'featured', 'compact' #}
} %}
```

**Navigation Item Structure**:

```twig
{# Navigation items array structure #}
{% set navigationItems = [
    {
        route: 'default_index',
        icon: 'tabler:home',
        label: 'sidebar.index',
        badge: null,
        external: false
    },
    {
        route: 'ranking_index',
        icon: 'tabler:trophy',
        label: 'sidebar.ranking',
        badge: 5,  {# Optional notification count #}
        external: false
    }
] %}
```

## Correctness Properties

_A property is a characteristic or behavior that should hold true across all valid executions of a system—essentially, a formal statement about what the system should do. Properties serve as the bridge between human-readable specifications and machine-verifiable correctness guarantees._

### Property Reflection

After analyzing the acceptance criteria, several properties can be consolidated to avoid redundancy:

- Typography properties (3.2, 3.3, 3.5) can be combined into comprehensive typography validation
- Color properties (4.1, 4.2, 4.3, 4.4, 4.5) can be grouped into color system validation
- Card layout properties (5.1, 5.2, 5.3, 5.4, 5.5) can be unified into responsive card behavior validation
- Component properties (6.2, 6.3, 6.5) can be merged into component implementation validation

### Core Properties

**Property 1: Data Preservation with Complete Presentation Transformation**
_For any_ page element in the refactored system, all original data and functionality should remain intact while the visual presentation is completely modernized using Tailwind CSS patterns
**Validates: Requirements 1.1, 1.2, 1.3, 1.5**

**Property 2: Mobile-First Responsive Navigation**
_For any_ screen size, the navigation system should provide appropriate interface patterns (hamburger drawer on mobile, sidebar on desktop) with proper touch target sizing and consolidated navigation
**Validates: Requirements 2.1, 2.5**

**Property 3: Typography Scale Consistency**
_For any_ text content, font sizes and line heights should follow the defined scale (14-15px for dense content, 16px for reading content) with proportional scaling across devices
**Validates: Requirements 3.2, 3.3, 3.5**

**Property 4: Color System Compliance**
_For any_ color usage, the system should avoid pure white/black backgrounds, maintain WCAG contrast ratios, and use brand colors appropriately
**Validates: Requirements 4.1, 4.2, 4.3, 4.5**

**Property 5: Responsive Card Layout Behavior**
_For any_ card-based content, cards should reflow appropriately (single-column on mobile, multi-column on desktop) while maintaining consistent styling and image optimization
**Validates: Requirements 5.1, 5.2, 5.3, 5.4, 5.5**

**Property 6: Component Implementation Standards**
_For any_ reusable component, it should use exclusively Tailwind utilities, provide proper interactive states, and include semantic HTML with accessibility attributes
**Validates: Requirements 6.2, 6.3, 6.5**

## Error Handling

### Responsive Breakpoint Failures

- **Graceful degradation** when CSS Grid or Flexbox features are unavailable
- **Fallback layouts** for older browsers that don't support modern CSS features
- **Progressive enhancement** ensuring core functionality works without JavaScript

### Font Loading Failures

- **System font fallbacks** when web fonts fail to load
- **Font-display: swap** to prevent invisible text during font loading
- **Consistent metrics** between web fonts and fallback fonts to minimize layout shift

### Image Loading Failures

- **Alt text** for all images to maintain content accessibility
- **Placeholder backgrounds** for images that fail to load
- **Responsive image fallbacks** when modern formats (AVIF/WebP) are unsupported

### JavaScript Failures

- **Progressive enhancement** ensuring navigation works without JavaScript
- **Graceful degradation** of interactive features like search autocomplete
- **Accessible alternatives** for JavaScript-dependent interactions

## Testing Strategy

### Dual Testing Approach

The testing strategy combines unit tests for specific functionality with property-based tests for universal behavior validation:

**Unit Tests**:

- Specific navigation interactions (hamburger menu opening, search modal behavior)
- Component rendering with various props combinations
- Responsive breakpoint behavior at specific screen sizes
- Color contrast validation for specific color combinations
- Font loading and fallback behavior

**Property-Based Tests**:

- Navigation functionality preservation across all navigation elements
- Typography consistency across all text content
- Color system compliance across all interface elements
- Responsive behavior across all screen sizes
- Component implementation standards across all reusable components

### Property Test Configuration

- **Minimum 100 iterations** per property test to ensure comprehensive coverage
- **Test tags** referencing design document properties: `Feature: modern-design-system, Property {number}: {property_text}`
- **Responsive testing** across mobile (375px), tablet (768px), and desktop (1024px) viewports
- **Cross-browser validation** ensuring compatibility with modern browsers
- **Accessibility testing** with automated tools and manual screen reader verification

### Performance Testing

- **CSS bundle size validation** ensuring production builds remain under 10KB
- **Font loading performance** measuring time to first contentful paint
- **Image optimization verification** confirming AVIF/WebP usage and appropriate sizing
- **Core Web Vitals monitoring** for Largest Contentful Paint, First Input Delay, and Cumulative Layout Shift
