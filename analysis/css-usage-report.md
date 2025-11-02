# CSS Usage Analysis Report

## Summary

- **Total CSS Classes Found**: 479
- **Bootstrap/Template Classes**: 232
- **Icon Classes**: 78
- **Custom Classes**: 169
- **Templates Analyzed**: 72
- **LESS Files Analyzed**: 222

## Class Distribution

### Bootstrap/Template Classes (232)
Most commonly used Bootstrap and template framework classes:

```
alert, alert-arrow-left, alert-bordered, alert-component, alert-danger, alert-dismissible, alert-heading, alert-info, alert-styled-left, alert-warning, badge, badge-flat, badge-primary, bg-blue, bg-blue-400, bg-danger, bg-primary, bg-primary-400, bg-success, bg-success-400
... and 212 more
```

### Icon Classes (78)
Icon classes in use:

```
icon-2x, icon-3x, icon-add, icon-alarm, icon-arrow-down12, icon-arrow-down22, icon-arrow-down5, icon-arrow-right14, icon-arrow-right5, icon-arrow-right8, icon-arrow-up22, icon-arrow-up5, icon-arrow-up8, icon-bell2, icon-bin, icon-bubble-lines4, icon-bubbles4, icon-calendar, icon-camera, icon-checkmark3
... and 58 more
```

### Custom Classes (169)
Project-specific custom classes:

```
%}, 0, ==, >, action-icon, active, ai-summary-loading, border, caption, caption-overflow, caret, category-content, category-title, center, checkbox, checkbox-right, checkbox-switchery, className|default(, clear-btn, close
... and 149 more
```

## Next Steps

1. Review the `less-mapping-report.json` to see which LESS files have low usage
2. Check `optimization-recommendations.json` for files safe to remove
3. Use `template-analysis.json` to understand per-template CSS usage

## Files Generated

- `css-usage-report.json` - Complete class usage data
- `less-mapping-report.json` - LESS file analysis and usage mapping
- `optimization-recommendations.json` - Recommendations for safe removals
- `template-analysis.json` - Per-template class usage breakdown
