# Enhanced CSS/LESS Usage Analysis Report

## Summary

- **Total CSS Classes Found**: 752
- **Bootstrap/Template Classes**: 245
- **Icon Classes**: 79
- **Custom Classes**: 428
- **Templates Analyzed**: 69
- **Controllers Analyzed**: 17
- **Style Files Analyzed**: 60
- **Files Not Imported**: 13

## Import Chain Analysis

- **Entry Point**: assets/styles/app.less
- **Files in Import Chain**: 47
- **Files NOT in Import Chain**: 13

## Class Distribution

### Bootstrap/Template Classes (245)
Most commonly used Bootstrap and template framework classes:

```
alert, alert-arrow-left, alert-bordered, alert-component, alert-danger, alert-dismissible, alert-heading, alert-info, alert-styled-left, alert-warning, badge, badge-flat, badge-primary, bg-blue, bg-blue-400, bg-danger, bg-primary, bg-primary-400, bg-success, bg-success-400
... and 225 more
```

### Icon Classes (79)
Icon classes in use:

```
icon-2x, icon-3x, icon-add, icon-alarm, icon-arrow-down12, icon-arrow-down22, icon-arrow-down5, icon-arrow-right14, icon-arrow-right5, icon-arrow-right8, icon-arrow-up22, icon-arrow-up5, icon-arrow-up8, icon-bell2, icon-bin, icon-bubble-lines4, icon-bubbles4, icon-calendar, icon-camera, icon-checkmark3
... and 59 more
```

### Custom Classes (428)
Project-specific custom classes:

```
%}, 0, ==, >, AbortError, Accept, ArrowDown, ArrowUp, Auto-save, Content-Type, Drag, Drop, Enter, Error, Escape, FOSJsRoutingBundle, Filter, GET, Hero, IMG
... and 408 more
```

## Next Steps

1. **High Priority**: Review files in `optimization-recommendations.json` under `notImported` - these can likely be deleted
2. **Medium Priority**: Check `zeroUsage` files - imported but no classes used
3. **Low Priority**: Review `lowUsage` files for potential optimization
4. Use `style-mapping-report.json` to see detailed usage per file
5. Check `template-analysis.json` and `controller-analysis.json` for usage patterns

## Files Generated

- `css-usage-report.json` - Complete class usage data with import chain info
- `style-mapping-report.json` - All style files analysis (LESS + CSS)
- `optimization-recommendations.json` - Categorized recommendations for cleanup
- `template-analysis.json` - Per-template class usage breakdown
- `controller-analysis.json` - Per-controller class usage breakdown
