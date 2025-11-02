# CSS Usage Analysis Results

This directory contains the complete analysis of CSS usage in the Captain Coaster application, mapping template usage to LESS template files for optimization.

## Generated Files

### Analysis Reports
- **`css-usage-report.md`** - Human-readable summary of findings
- **`css-usage-report.json`** - Complete class usage data in JSON format
- **`css-usage-summary.md`** - Executive summary with recommendations
- **`less-mapping-report.json`** - Detailed LESS file analysis with usage percentages
- **`optimization-recommendations.json`** - Specific files categorized by removal priority
- **`template-analysis.json`** - Per-template class usage breakdown

### Tools and Scripts
- **`../scripts/analyze-css-usage.js`** - Analysis script (already run)
- **`../scripts/remove-unused-css.js`** - Cleanup script for removing unused files

## Quick Start

### 1. Review the Analysis
```bash
# Read the executive summary (updated with npm Bootstrap approach)
cat analysis/css-usage-summary.md

# Check current large CSS files that will be replaced
wc -l assets/css/*.css  # Shows 36,472 lines total
```

### 2. Install Modern Dependencies
```bash
# Install Bootstrap 5 and Sass support
npm install bootstrap@5 @popperjs/core sass-loader sass

# Remove large compiled CSS files
rm assets/css/bootstrap.css assets/css/components.css assets/css/core.css assets/css/colors.css
```

### 3. Clean Up Unused LESS Files
```bash
# Remove unused LESS files (dry run first)
node scripts/remove-unused-css.js --dry-run

# Remove 89 unused files (0% usage)
node scripts/remove-unused-css.js
```

### 4. Create Modern Structure
```bash
# Create new theme structure
mkdir -p assets/styles/theme/{layout,components,variables}

# Move high-usage custom files to new structure
# (This will be done in task 5 of the implementation plan)
```

## Key Findings Summary

- **479 unique CSS classes** found across 72 templates
- **89 LESS files (40%)** have 0% usage and can be safely removed
- **42 LESS files (19%)** have 1-20% usage and should be reviewed
- **91 LESS files (41%)** have 20%+ usage and should be kept

### Immediate Actions Available

#### Safe Removals (0% Usage)
These 89 files can be removed immediately with zero risk:
- Bootstrap components: `code.less`, `glyphicons.less`, `jumbotron.less`, `normalize.less`
- Template extensions: Pace themes, chart components, advanced form plugins
- Unused UI components: Image plugins, notification systems, date pickers

**Estimated savings**: 200KB+ of LESS source code

#### Review Required (1-20% Usage)
These 42 files need manual review:
- Large files with minimal usage (e.g., `handsontable.less` - 35KB, 1% usage)
- Bootstrap components with partial usage (e.g., `forms.less` - 20% usage)
- Template components that could be optimized

## Usage Examples

### Run Analysis Again
```bash
# Re-run the analysis (if templates changed)
node scripts/analyze-css-usage.js
```

### Cleanup with Different Options
```bash
# Dry run to see what would happen
node scripts/remove-unused-css.js --dry-run

# Remove only high-priority (0% usage) files
node scripts/remove-unused-css.js

# Include medium-priority review
node scripts/remove-unused-css.js --include-medium-priority

# Restore from backup if needed
node scripts/remove-unused-css.js --restore
```

### Check Specific File Usage
```bash
# Find usage of a specific LESS file
cat analysis/less-mapping-report.json | jq '.[] | select(.file | contains("buttons.less"))'

# Find templates using specific classes
cat analysis/template-analysis.json | jq '.[] | select(.allClasses | contains(["btn-primary"]))'
```

## Integration with Webpack Encore

After cleanup, you'll want to create a new selective import structure:

```less
// assets/styles/app.less - New optimized entry point
// Import only high-usage components
@import '../less/_bootstrap/variables.less';
@import '../less/_bootstrap/badges.less';        // 71% usage
@import '../less/_bootstrap/thumbnails.less';    // 80% usage
@import '../less/_bootstrap/media.less';         // 67% usage
@import '../less/_bootstrap/alerts.less';       // 60% usage

// Template framework (selective)
@import '../less/bootstrap-limitless/buttons.less';  // 52% usage
@import '../less/bootstrap-limitless/media.less';    // 70% usage

// Core layout (essential)
@import '../less/core/layout/content.less';     // 100% usage
@import '../less/core/variables/variables-custom.less'; // 100% usage
```

Then update `webpack.config.js`:
```javascript
.addStyleEntry('app', './assets/styles/app.less')
```

## Backup and Recovery

The cleanup script automatically creates backups in `css-backup/` directory:

```bash
# Manual backup before cleanup
cp -r assets/less css-backup/less-$(date +%Y%m%d)

# Restore from backup
cp -r css-backup/less/* assets/less/

# Or restore from git
git checkout HEAD~1 -- assets/less/
```

## Validation Checklist

After cleanup, verify:

- [ ] `npm run build` completes without errors
- [ ] Application loads without visual regressions
- [ ] All interactive elements work correctly
- [ ] Bundle size has decreased
- [ ] Build time has improved
- [ ] No console errors in browser

## Troubleshooting

### Build Errors After Cleanup
```bash
# Check what was removed
cat analysis/cleanup-report.json

# Restore specific file if needed
git checkout HEAD~1 -- assets/less/path/to/file.less

# Or restore everything
node scripts/remove-unused-css.js --restore
```

### Missing Styles
1. Check if the missing style was in a removed file
2. Look for the class in `css-usage-report.json`
3. If needed, restore the specific LESS file
4. Consider if the style is actually needed

### Performance Issues
- Monitor bundle size before/after
- Check build times
- Verify no critical styles were removed

## Next Steps

1. **Review Results**: Examine the analysis reports
2. **Test Cleanup**: Run dry-run mode first
3. **Implement Cleanup**: Remove unused files
4. **Optimize Further**: Review medium-priority files
5. **Create New Structure**: Build selective import system
6. **Monitor**: Track bundle size and performance improvements

---

*Analysis completed: $(date)*
*Files analyzed: 72 templates, 222 LESS files*
*Optimization potential: 89 files (40%) can be safely removed*