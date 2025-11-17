# JavaScript Utilities

Shared utilities for consistent rendering across the application.

## Star Rating (`star-rating.js`)

Generates star rating HTML that matches the Twig macro in `templates/helper.html.twig`.

### Usage

```javascript
import { renderStarRating } from './utils/star-rating';

// Render star rating HTML
const html = renderStarRating(4.5);
// Returns: <span class="star-rating">...</span>

// Insert directly into DOM element
import { insertStarRating } from './utils/star-rating';
const element = document.querySelector('.rating-container');
insertStarRating(element, 4.5);
```

### Twig Equivalent

```twig
{% import 'helper.html.twig' as helper %}
{{ helper.starRating(4.5) }}
```

Both produce identical HTML output.

## Icons (`heroicon.js`)

Heroicons for JavaScript that match the Twig `heroicon()` function.

### Usage

```javascript
import { heroicon } from './utils/heroicon';

// Use in template literals
const html = `<button>${heroicon('cog-6-tooth', 'w-5 h-5')} Settings</button>`;

// With variant
const solidStar = heroicon('star', 'w-6 h-6', 'solid');
```

### Available Icons

Only frequently used icons are included inline:
- `bars-2` - Drag handle
- `cog-6-tooth` - Settings/menu
- `arrow-up` - Move up
- `arrow-down` - Move down
- `arrows-up-down` - Move to position
- `trash` - Delete/remove
- `star` (solid) - Rating star

### Twig Equivalent

```twig
{{ heroicon('cog-6-tooth', 'w-5 h-5') }}
{{ heroicon('star', 'w-6 h-6', 'solid') }}
```

Both produce identical HTML output.

### Adding New Icons

To add a new icon:
1. Find it in `node_modules/heroicons/24/{outline|solid}/`
2. Copy the SVG content to the `ICONS` object in `heroicon.js`
3. Use the same naming as the file (e.g., `cog-6-tooth.svg` → `'cog-6-tooth'`)

## Benefits

1. **Consistency**: Same HTML output whether rendered server-side (Twig) or client-side (JavaScript)
2. **Maintainability**: Single source of truth for each component
3. **Reusability**: Import and use anywhere in your Stimulus controllers
4. **Reduced duplication**: No more 150+ line methods with hardcoded SVGs

## Adding New Utilities

When adding new shared components:

1. Create the utility file in `assets/js/utils/`
2. Export functions that return HTML strings
3. Document usage in this README
4. Ensure it matches any existing Twig macros/extensions
