# CSS/LESS Organization

This document describes the new unified styling organization under `assets/styles/`.

## Directory Structure

```
assets/styles/
├── app.less                    # Main entrypoint (LESS)
├── README.md                   # This documentation
│
├── components/                 # Modern CSS components for Stimulus
│   ├── enhanced-select.css     # Custom select component
│   ├── feedback.css           # Summary feedback component
│   ├── map.css                # Leaflet map styling
│   ├── search.css             # Search component
│   ├── toggle-switch.css      # Toggle switch component
│   └── top-list.css           # Drag & drop top list
│
├── utilities/                 # Standalone utility CSS
│   └── image-optimization.css # Image optimization styles
│
├── icons/                     # Icon fonts
│   └── icomoon/              # Icomoon icon font
│
└── theme/                     # Purchased theme (LESS)
    ├── bootstrap-limitless/   # Bootstrap 3.3.7 overrides
    ├── components/           # Theme components
    └── core/                 # Theme core (variables, mixins, etc.)
```

## Architecture Principles

### 1. Format Strategy
- **LESS**: Bootstrap 3.3.7 and purchased theme (legacy, locked-in)
- **CSS**: Modern components for Stimulus controllers (future-proof)

### 2. Import Order in app.less
1. Bootstrap 3.3.7 imports from node_modules
2. Theme LESS files (variables, mixins, overrides)
3. Modern CSS components
4. Utilities

### 3. Component Guidelines
- **New components**: Write in modern CSS in `components/`
- **Stimulus controllers**: Corresponding CSS files in `components/`
- **Utilities**: Standalone CSS files in `utilities/`
- **Theme modifications**: Keep in LESS under `theme/`

## Migration Benefits

- ✅ **Unified location**: All styling in one directory
- ✅ **Cleaner imports**: Relative paths from app.less
- ✅ **Future-proof**: New components use modern CSS
- ✅ **Maintainable**: Clear separation of concerns
- ✅ **Performance**: Better webpack optimization
- ✅ **Removed unused files**: circular-progress.css, roundcircle.css

## Development Workflow

1. **For new Stimulus components**: Create CSS file in `components/`
2. **For utilities**: Create CSS file in `utilities/`
3. **For theme modifications**: Modify LESS files in `theme/`
4. **Import in app.less**: Add @import statement for new files

## Build Verification

The reorganization has been tested and builds successfully with no breaking changes.