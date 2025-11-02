#!/usr/bin/env node

/**
 * CSS Usage Analysis Script
 * 
 * This script analyzes Twig templates to identify CSS classes being used
 * and maps them to corresponding LESS template files for optimization.
 */

const fs = require('fs');
const path = require('path');

class CSSUsageAnalyzer {
    constructor() {
        this.templateDir = 'templates';
        this.lessDir = 'assets/less';
        this.cssDir = 'assets/css';
        this.publicCssDir = 'public/build';
        
        // Store analysis results
        this.usedClasses = new Set();
        this.templateClassMap = new Map();
        this.lessFileMap = new Map();
        this.bootstrapClasses = new Set();
        this.customClasses = new Set();
        this.iconClasses = new Set();
        
        // Common Bootstrap/template patterns
        this.bootstrapPatterns = [
            /^(container|row|col-)/,
            /^(btn|button)/,
            /^(panel|card)/,
            /^(form|input|control)/,
            /^(nav|navbar)/,
            /^(alert|badge|label)/,
            /^(modal|dropdown)/,
            /^(table|list-group)/,
            /^(text-|bg-|border-)/,
            /^(pull-|float-)/,
            /^(hidden|visible|show|hide)/,
            /^(margin|padding|mt-|mb-|ml-|mr-|pt-|pb-|pl-|pr-)/,
            /^(thumbnail|media)/,
            /^(breadcrumb|pagination)/,
            /^(progress|spinner)/
        ];
        
        this.iconPatterns = [
            /^icon-/,
            /^glyphicon/,
            /^fa-/
        ];
    }

    /**
     * Recursively find files with a specific extension
     */
    findFiles(dir, extension) {
        const files = [];
        
        const scanDirectory = (currentDir) => {
            if (!fs.existsSync(currentDir)) {
                return;
            }
            
            const items = fs.readdirSync(currentDir);
            
            for (const item of items) {
                const fullPath = path.join(currentDir, item);
                const stat = fs.statSync(fullPath);
                
                if (stat.isDirectory()) {
                    scanDirectory(fullPath);
                } else if (stat.isFile() && fullPath.endsWith(extension)) {
                    files.push(fullPath);
                }
            }
        };
        
        scanDirectory(dir);
        return files;
    }

    /**
     * Main analysis function
     */
    async analyze() {
        console.log('🔍 Starting CSS usage analysis...\n');
        
        try {
            // Step 1: Analyze templates for CSS classes
            await this.analyzeTemplates();
            
            // Step 2: Map LESS files and their contents
            await this.analyzeLessFiles();
            
            // Step 3: Categorize classes
            this.categorizeClasses();
            
            // Step 4: Generate reports
            await this.generateReports();
            
            console.log('✅ Analysis complete! Check the generated reports in the analysis/ directory.');
            
        } catch (error) {
            console.error('❌ Analysis failed:', error.message);
            process.exit(1);
        }
    }

    /**
     * Analyze all Twig templates for CSS class usage
     */
    async analyzeTemplates() {
        console.log('📄 Analyzing Twig templates...');
        
        const templateFiles = this.findFiles(this.templateDir, '.twig');
        console.log(`Found ${templateFiles.length} template files`);
        
        for (const templateFile of templateFiles) {
            const content = fs.readFileSync(templateFile, 'utf8');
            const classes = this.extractClassesFromTemplate(content);
            
            this.templateClassMap.set(templateFile, classes);
            classes.forEach(cls => this.usedClasses.add(cls));
        }
        
        console.log(`Found ${this.usedClasses.size} unique CSS classes across all templates\n`);
    }

    /**
     * Extract CSS classes from a Twig template
     */
    extractClassesFromTemplate(content) {
        const classes = new Set();
        
        // Patterns to match class attributes
        const patterns = [
            // Standard class="..." attributes
            /class\s*=\s*["']([^"']+)["']/gi,
            // Twig variables in class attributes
            /class\s*=\s*["'][^"']*\{\{[^}]+\}\}[^"']*["']/gi,
            // Bootstrap utility classes in templates
            /\b(btn-\w+|text-\w+|bg-\w+|border-\w+|col-\w+)\b/gi
        ];
        
        patterns.forEach(pattern => {
            let match;
            while ((match = pattern.exec(content)) !== null) {
                if (match[1]) {
                    // Split multiple classes and clean them
                    const classNames = match[1]
                        .split(/\s+/)
                        .map(cls => cls.trim())
                        .filter(cls => cls.length > 0 && !cls.includes('{'));
                    
                    classNames.forEach(cls => classes.add(cls));
                }
            }
        });
        
        return Array.from(classes);
    }

    /**
     * Analyze LESS files to understand available styles
     */
    async analyzeLessFiles() {
        console.log('🎨 Analyzing LESS files...');
        
        const lessFiles = this.findFiles(this.lessDir, '.less');
        console.log(`Found ${lessFiles.length} LESS files`);
        
        for (const lessFile of lessFiles) {
            try {
                const content = fs.readFileSync(lessFile, 'utf8');
                const classes = this.extractClassesFromLess(content);
                
                this.lessFileMap.set(lessFile, {
                    classes: classes,
                    size: fs.statSync(lessFile).size,
                    imports: this.extractImports(content)
                });
            } catch (error) {
                console.warn(`Warning: Could not read ${lessFile}: ${error.message}`);
            }
        }
        
        console.log(`Analyzed ${this.lessFileMap.size} LESS files\n`);
    }

    /**
     * Extract CSS classes from LESS content
     */
    extractClassesFromLess(content) {
        const classes = new Set();
        
        // Match CSS class selectors
        const classPattern = /\.([a-zA-Z][a-zA-Z0-9_-]*)/g;
        let match;
        
        while ((match = classPattern.exec(content)) !== null) {
            classes.add(match[1]);
        }
        
        return Array.from(classes);
    }

    /**
     * Extract @import statements from LESS content
     */
    extractImports(content) {
        const imports = [];
        const importPattern = /@import\s+["']([^"']+)["'];?/g;
        let match;
        
        while ((match = importPattern.exec(content)) !== null) {
            imports.push(match[1]);
        }
        
        return imports;
    }

    /**
     * Categorize classes into Bootstrap, custom, icons, etc.
     */
    categorizeClasses() {
        console.log('🏷️  Categorizing CSS classes...');
        
        for (const className of this.usedClasses) {
            // Check if it's an icon class
            if (this.iconPatterns.some(pattern => pattern.test(className))) {
                this.iconClasses.add(className);
                continue;
            }
            
            // Check if it's a Bootstrap class
            if (this.bootstrapPatterns.some(pattern => pattern.test(className))) {
                this.bootstrapClasses.add(className);
                continue;
            }
            
            // Otherwise, it's likely a custom class
            this.customClasses.add(className);
        }
        
        console.log(`Categorized classes:`);
        console.log(`  - Bootstrap/Template: ${this.bootstrapClasses.size}`);
        console.log(`  - Icons: ${this.iconClasses.size}`);
        console.log(`  - Custom: ${this.customClasses.size}\n`);
    }

    /**
     * Generate comprehensive analysis reports
     */
    async generateReports() {
        console.log('📊 Generating analysis reports...');
        
        // Create analysis directory
        const analysisDir = 'analysis';
        if (!fs.existsSync(analysisDir)) {
            fs.mkdirSync(analysisDir, { recursive: true });
        }
        
        // Generate main usage report
        await this.generateUsageReport(analysisDir);
        
        // Generate LESS mapping report
        await this.generateLessMappingReport(analysisDir);
        
        // Generate optimization recommendations
        await this.generateOptimizationReport(analysisDir);
        
        // Generate template-specific reports
        await this.generateTemplateReport(analysisDir);
    }

    /**
     * Generate main CSS usage report
     */
    async generateUsageReport(analysisDir) {
        const report = {
            summary: {
                totalClasses: this.usedClasses.size,
                bootstrapClasses: this.bootstrapClasses.size,
                iconClasses: this.iconClasses.size,
                customClasses: this.customClasses.size,
                templatesAnalyzed: this.templateClassMap.size,
                lessFilesAnalyzed: this.lessFileMap.size
            },
            classBreakdown: {
                bootstrap: Array.from(this.bootstrapClasses).sort(),
                icons: Array.from(this.iconClasses).sort(),
                custom: Array.from(this.customClasses).sort()
            }
        };
        
        fs.writeFileSync(
            path.join(analysisDir, 'css-usage-report.json'),
            JSON.stringify(report, null, 2)
        );
        
        // Generate human-readable markdown report
        const markdown = this.generateMarkdownReport(report);
        fs.writeFileSync(
            path.join(analysisDir, 'css-usage-report.md'),
            markdown
        );
    }

    /**
     * Generate LESS file mapping report
     */
    async generateLessMappingReport(analysisDir) {
        const lessAnalysis = [];
        
        for (const [lessFile, data] of this.lessFileMap) {
            const usedClassesInFile = data.classes.filter(cls => this.usedClasses.has(cls));
            const unusedClassesInFile = data.classes.filter(cls => !this.usedClasses.has(cls));
            
            lessAnalysis.push({
                file: lessFile,
                size: data.size,
                totalClasses: data.classes.length,
                usedClasses: usedClassesInFile.length,
                unusedClasses: unusedClassesInFile.length,
                usagePercentage: data.classes.length > 0 ? 
                    Math.round((usedClassesInFile.length / data.classes.length) * 100) : 0,
                imports: data.imports,
                usedClassesList: usedClassesInFile,
                unusedClassesList: unusedClassesInFile
            });
        }
        
        // Sort by usage percentage (lowest first - these are candidates for removal)
        lessAnalysis.sort((a, b) => a.usagePercentage - b.usagePercentage);
        
        fs.writeFileSync(
            path.join(analysisDir, 'less-mapping-report.json'),
            JSON.stringify(lessAnalysis, null, 2)
        );
    }

    /**
     * Generate optimization recommendations
     */
    async generateOptimizationReport(analysisDir) {
        const recommendations = {
            highPriorityRemovals: [],
            mediumPriorityRemovals: [],
            keepFiles: [],
            coreBootstrapComponents: [],
            customComponentsToKeep: []
        };
        
        // Analyze LESS files for removal candidates
        for (const [lessFile, data] of this.lessFileMap) {
            const usedClasses = data.classes.filter(cls => this.usedClasses.has(cls));
            const usagePercentage = data.classes.length > 0 ? 
                (usedClasses.length / data.classes.length) * 100 : 0;
            
            const fileInfo = {
                file: lessFile,
                usagePercentage: Math.round(usagePercentage),
                size: data.size,
                usedClasses: usedClasses.length,
                totalClasses: data.classes.length
            };
            
            if (usagePercentage === 0) {
                recommendations.highPriorityRemovals.push(fileInfo);
            } else if (usagePercentage < 20) {
                recommendations.mediumPriorityRemovals.push(fileInfo);
            } else {
                recommendations.keepFiles.push(fileInfo);
            }
        }
        
        // Identify core Bootstrap components to keep
        const coreComponents = [
            'grid', 'buttons', 'forms', 'navbar', 'panels', 'alerts', 
            'badges', 'labels', 'modals', 'dropdowns', 'tables'
        ];
        
        for (const component of coreComponents) {
            const hasUsage = Array.from(this.bootstrapClasses).some(cls => 
                cls.includes(component) || cls.startsWith(component)
            );
            
            if (hasUsage) {
                recommendations.coreBootstrapComponents.push(component);
            }
        }
        
        fs.writeFileSync(
            path.join(analysisDir, 'optimization-recommendations.json'),
            JSON.stringify(recommendations, null, 2)
        );
    }

    /**
     * Generate template-specific analysis
     */
    async generateTemplateReport(analysisDir) {
        const templateAnalysis = [];
        
        for (const [templateFile, classes] of this.templateClassMap) {
            const bootstrapInTemplate = classes.filter(cls => this.bootstrapClasses.has(cls));
            const iconsInTemplate = classes.filter(cls => this.iconClasses.has(cls));
            const customInTemplate = classes.filter(cls => this.customClasses.has(cls));
            
            templateAnalysis.push({
                template: templateFile,
                totalClasses: classes.length,
                bootstrap: bootstrapInTemplate.length,
                icons: iconsInTemplate.length,
                custom: customInTemplate.length,
                allClasses: classes
            });
        }
        
        // Sort by total classes (most complex templates first)
        templateAnalysis.sort((a, b) => b.totalClasses - a.totalClasses);
        
        fs.writeFileSync(
            path.join(analysisDir, 'template-analysis.json'),
            JSON.stringify(templateAnalysis, null, 2)
        );
    }

    /**
     * Generate human-readable markdown report
     */
    generateMarkdownReport(report) {
        return `# CSS Usage Analysis Report

## Summary

- **Total CSS Classes Found**: ${report.summary.totalClasses}
- **Bootstrap/Template Classes**: ${report.summary.bootstrapClasses}
- **Icon Classes**: ${report.summary.iconClasses}
- **Custom Classes**: ${report.summary.customClasses}
- **Templates Analyzed**: ${report.summary.templatesAnalyzed}
- **LESS Files Analyzed**: ${report.summary.lessFilesAnalyzed}

## Class Distribution

### Bootstrap/Template Classes (${report.classBreakdown.bootstrap.length})
Most commonly used Bootstrap and template framework classes:

\`\`\`
${report.classBreakdown.bootstrap.slice(0, 20).join(', ')}
${report.classBreakdown.bootstrap.length > 20 ? '... and ' + (report.classBreakdown.bootstrap.length - 20) + ' more' : ''}
\`\`\`

### Icon Classes (${report.classBreakdown.icons.length})
Icon classes in use:

\`\`\`
${report.classBreakdown.icons.slice(0, 20).join(', ')}
${report.classBreakdown.icons.length > 20 ? '... and ' + (report.classBreakdown.icons.length - 20) + ' more' : ''}
\`\`\`

### Custom Classes (${report.classBreakdown.custom.length})
Project-specific custom classes:

\`\`\`
${report.classBreakdown.custom.slice(0, 20).join(', ')}
${report.classBreakdown.custom.length > 20 ? '... and ' + (report.classBreakdown.custom.length - 20) + ' more' : ''}
\`\`\`

## Next Steps

1. Review the \`less-mapping-report.json\` to see which LESS files have low usage
2. Check \`optimization-recommendations.json\` for files safe to remove
3. Use \`template-analysis.json\` to understand per-template CSS usage

## Files Generated

- \`css-usage-report.json\` - Complete class usage data
- \`less-mapping-report.json\` - LESS file analysis and usage mapping
- \`optimization-recommendations.json\` - Recommendations for safe removals
- \`template-analysis.json\` - Per-template class usage breakdown
`;
    }
}

// Run the analysis if this script is executed directly
if (require.main === module) {
    const analyzer = new CSSUsageAnalyzer();
    analyzer.analyze().catch(console.error);
}

module.exports = CSSUsageAnalyzer;