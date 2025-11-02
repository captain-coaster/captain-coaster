# Webpack Encore Configuration

This document describes the modern Webpack Encore configuration implemented for Captain Coaster.

## Features

### Entry Points
- **app**: Main application JavaScript and CSS
- **translator**: Symfony UX Translator functionality
- **coaster**: Coaster-specific functionality (ApexCharts)
- **admin**: Admin interface specific assets

### CSS Processing
- **LESS Loader**: Support for existing template LESS files
- **PostCSS**: Autoprefixing and optimization
- **CSS Optimization**: Minification and optimization in production

### JavaScript Processing
- **Babel**: Modern JavaScript transpilation with core-js polyfills
- **Tree Shaking**: Dead code elimination in production
- **Code Splitting**: Automatic vendor and common chunk splitting
- **Stimulus Bridge**: Modern JavaScript interactions

### Development Features
- **Source Maps**: Enabled in development for debugging
- **Hot Module Replacement**: Fast development with webpack-dev-server
- **Build Notifications**: Desktop notifications for build status
- **Fast Rebuilds**: Optimized for development workflow

### Production Optimizations
- **Asset Versioning**: Cache-busting with content hashes
- **Bundle Splitting**: Separate vendor and common chunks
- **CSS Minification**: Optimized CSS output
- **Performance Budgets**: Warnings for large bundles

## Build Commands

```bash
# Development build
npm run dev

# Development with file watching
npm run watch

# Development server with hot reload
npm run dev-server

# Production build
npm run build

# Production build with bundle analysis
npm run build:analyze

# Clean build directory
npm run clean
```

## Environment Configuration

### Development
- Source maps enabled
- Hot module replacement
- Build notifications
- Unminified output for debugging

### Production
- Asset versioning with content hashes
- CSS and JS minification
- Tree shaking and dead code elimination
- Performance warnings for large bundles

## Asset Organization

### Aliases
- `@`: assets/
- `@js`: assets/js/
- `@css`: assets/css/
- `@less`: assets/less/
- `@images`: assets/images/

### Entry Points Structure
```
assets/js/
├── app.js          # Main application entry
├── coaster.js      # Coaster-specific functionality
├── admin.js        # Admin interface entry
└── modules/        # Reusable modules
```

## LESS Support

The configuration includes full LESS support for the existing template files:

- Import paths configured for assets/less and node_modules
- JavaScript evaluation enabled for template features
- Selective compilation support for optimization

## PostCSS Configuration

Autoprefixing and optimization configured via postcss.config.js:

- Autoprefixer for cross-browser compatibility
- CSS optimization (cssnano) in production only
- Preserves important comments and CSS custom properties

## Performance

### Bundle Size Targets
- Main Bundle: < 250KB per asset
- Entry Point: < 400KB total
- Warnings displayed for oversized bundles

### Optimization Features
- Vendor chunk separation
- Common code extraction
- Tree shaking for unused code
- Asset compression and caching

## Migration Notes

This configuration maintains backward compatibility while adding modern features:

- All existing functionality preserved
- New admin entry point added
- LESS processing for template files
- Modern development workflow
- Production optimizations

## Troubleshooting

### Common Issues
1. **Build Errors**: Check console output for specific error messages
2. **Missing Dependencies**: Run `npm install` to ensure all packages are installed
3. **Cache Issues**: Use `npm run clean` to clear build directory
4. **Development Server**: Ensure webpack-dev-server is installed for hot reload

### Performance Issues
- Use `npm run build:analyze` to analyze bundle sizes
- Check for large dependencies that can be lazy-loaded
- Consider code splitting for large features