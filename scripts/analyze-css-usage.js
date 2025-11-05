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
        this.stylesDir = 'assets/styles';
        this.controllersDir = 'assets/controllers';
        this.entryPoint = 'assets/styles/app.less';
        
        // Store analysis results
        this.usedClasses = new Set();
        this.templateClassMap = new Map();
        this.controllerClassMap = new Map();
        this.styleFileMap = new Map();
        this.importChain = new Map();
        this.notImportedFiles = new Set();
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
        console.log('🔍 Starting enhanced CSS/LESS usage analysis...\n');
        
        try {
            // Step 1: Build import chain from entry point
            await this.buildImportChain();
            
            // Step 2: Find all style files and check if they're imported
            await this.findUnimportedFiles();
            
            // Step 3: Analyze templates for CSS classes
            await this.analyzeTemplates();
            
            // Step 4: Analyze Stimulus controllers for CSS classes
            await this.analyzeControllers();
            
            // Step 5: Map style files and their contents
            await this.analyzeStyleFiles();
            
            // Step 6: Categorize classes
            this.categorizeClasses();
            
            // Step 7: Generate enhanced reports
            await this.generateReports();
            
            console.log('✅ Enhanced analysis complete! Check the generated reports in the analysis/ directory.');
            
        } catch (error) {
            console.error('❌ Analysis failed:', error.message);
            process.exit(1);
        }
    }

    /**
     * Build import chain starting from entry point
     */
    async buildImportChain() {
        console.log('🔗 Building import chain from entry point...');
        
        const visited = new Set();
        const importedFiles = new Set();
        
        const processFile = (filePath) => {
            if (visited.has(filePath)) {
                return;
            }
            
            if (!fs.existsSync(filePath)) {
                return;
            }
            
            visited.add(filePath);
            importedFiles.add(filePath);
            

            
            try {
                const content = fs.readFileSync(filePath, 'utf8');
                const imports = this.extractImports(content);
                
                this.importChain.set(filePath, imports);
                
                // Process each import
                imports.forEach(importPath => {
                    let resolvedPath = this.resolveImportPath(importPath, filePath);
                    if (resolvedPath) {
                        processFile(resolvedPath);
                    }
                });
            } catch (error) {
                console.warn(`Warning: Could not process ${filePath}: ${error.message}`);
            }
        };
        
        processFile(this.entryPoint);
        
        console.log(`Found ${importedFiles.size} files in import chain\n`);
        return importedFiles;
    }

    /**
     * Resolve import path relative to current file
     */
    resolveImportPath(importPath, currentFile) {
        const currentDir = path.dirname(currentFile);
        
        // Handle different import patterns
        if (importPath.startsWith('~')) {
            // Node modules import - skip these for our analysis
            return null;
        } 
        
        // Try to resolve the path
        let candidates = [];
        
        if (importPath.startsWith('./') || importPath.startsWith('../')) {
            // Explicit relative import
            candidates.push(path.resolve(currentDir, importPath));
        } else {
            // Implicit relative import (no ./ prefix)
            candidates.push(path.resolve(currentDir, importPath));
        }
        
        // For each candidate, try with and without extensions
        for (const candidate of candidates) {
            // Try exact path
            if (fs.existsSync(candidate)) return candidate;
            
            // Try with .less extension
            if (fs.existsSync(candidate + '.less')) return candidate + '.less';
            
            // Try with .css extension  
            if (fs.existsSync(candidate + '.css')) return candidate + '.css';
            
            // Try without extension if it has one
            if (path.extname(candidate)) {
                const withoutExt = candidate.replace(path.extname(candidate), '');
                if (fs.existsSync(withoutExt)) return withoutExt;
            }
        }
        
        return null;
    }

    /**
     * Find all style files and identify which ones are not imported
     */
    async findUnimportedFiles() {
        console.log('📁 Finding unimported style files...');
        
        const allStyleFiles = [
            ...this.findFiles(this.stylesDir, '.less'),
            ...this.findFiles(this.stylesDir, '.css')
        ];
        
        const importedFiles = new Set(this.importChain.keys());
        

        
        allStyleFiles.forEach(file => {
            // Normalize paths for comparison - convert to absolute paths
            const absoluteFile = path.resolve(file);
            if (!importedFiles.has(file) && !importedFiles.has(absoluteFile)) {
                this.notImportedFiles.add(file);
            }
        });
        
        console.log(`Found ${this.notImportedFiles.size} files NOT imported in the chain`);
        if (this.notImportedFiles.size > 0) {
            console.log('Unimported files:');
            this.notImportedFiles.forEach(file => console.log(`  - ${file}`));
        }
        console.log();
    }

    /**
     * Analyze Stimulus controllers for CSS class usage
     */
    async analyzeControllers() {
        console.log('🎮 Analyzing Stimulus controllers...');
        
        const controllerFiles = this.findFiles(this.controllersDir, '.js');
        console.log(`Found ${controllerFiles.length} controller files`);
        
        for (const controllerFile of controllerFiles) {
            try {
                const content = fs.readFileSync(controllerFile, 'utf8');
                const classes = this.extractClassesFromJavaScript(content);
                
                this.controllerClassMap.set(controllerFile, classes);
                classes.forEach(cls => this.usedClasses.add(cls));
            } catch (error) {
                console.warn(`Warning: Could not read ${controllerFile}: ${error.message}`);
            }
        }
        
        const controllerClasses = Array.from(this.controllerClassMap.values())
            .reduce((acc, classes) => acc + classes.length, 0);
        console.log(`Found ${controllerClasses} CSS classes in controllers\n`);
    }

    /**
     * Extract CSS classes from JavaScript/Stimulus controllers
     */
    extractClassesFromJavaScript(content) {
        const classes = new Set();
        
        // Patterns to match CSS classes in JavaScript
        const patterns = [
            // classList.add/remove/toggle/contains
            /classList\.(add|remove|toggle|contains)\s*\(\s*["']([^"']+)["']\s*\)/gi,
            // className assignments
            /className\s*=\s*["']([^"']+)["']/gi,
            // querySelector/querySelectorAll with class selectors
            /querySelector(?:All)?\s*\(\s*["']\.([^"']+)["']\s*\)/gi,
            // String literals that look like CSS classes (common patterns)
            /["']([a-zA-Z][a-zA-Z0-9_-]*(?:\s+[a-zA-Z][a-zA-Z0-9_-]*)*)["']/g,
            // Data attributes for CSS classes
            /data-.*-class\s*=\s*["']([^"']+)["']/gi
        ];
        
        patterns.forEach(pattern => {
            let match;
            while ((match = pattern.exec(content)) !== null) {
                const classString = match[1] || match[2];
                if (classString) {
                    // Split multiple classes and clean them
                    const classNames = classString
                        .split(/\s+/)
                        .map(cls => cls.trim())
                        .filter(cls => cls.length > 0 && /^[a-zA-Z][a-zA-Z0-9_-]*$/.test(cls));
                    
                    classNames.forEach(cls => classes.add(cls));
                }
            }
        });
        
        return Array.from(classes);
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
     * Analyze all style files (LESS and CSS) to understand available styles
     */
    async analyzeStyleFiles() {
        console.log('🎨 Analyzing style files (LESS and CSS)...');
        
        const styleFiles = [
            ...this.findFiles(this.stylesDir, '.less'),
            ...this.findFiles(this.stylesDir, '.css')
        ];
        console.log(`Found ${styleFiles.length} style files`);
        
        for (const styleFile of styleFiles) {
            try {
                const content = fs.readFileSync(styleFile, 'utf8');
                const classes = styleFile.endsWith('.less') ? 
                    this.extractClassesFromLess(content) : 
                    this.extractClassesFromCSS(content);
                
                const isImported = this.importChain.has(styleFile);
                
                this.styleFileMap.set(styleFile, {
                    classes: classes,
                    size: fs.statSync(styleFile).size,
                    imports: this.extractImports(content),
                    isImported: isImported,
                    type: styleFile.endsWith('.less') ? 'LESS' : 'CSS'
                });
            } catch (error) {
                console.warn(`Warning: Could not read ${styleFile}: ${error.message}`);
            }
        }
        
        console.log(`Analyzed ${this.styleFileMap.size} style files\n`);
    }

    /**
     * Extract CSS classes from CSS content
     */
    extractClassesFromCSS(content) {
        const classes = new Set();
        
        // Match CSS class selectors (more comprehensive than LESS version)
        const patterns = [
            // Standard class selectors
            /\.([a-zA-Z][a-zA-Z0-9_-]*)/g,
            // Classes in complex selectors
            /\.([a-zA-Z][a-zA-Z0-9_-]*)\s*[,\s>+~:]/g
        ];
        
        patterns.forEach(pattern => {
            let match;
            while ((match = pattern.exec(content)) !== null) {
                classes.add(match[1]);
            }
        });
        
        return Array.from(classes);
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
     * Extract @import statements from LESS/CSS content
     */
    extractImports(content) {
        const imports = [];
        const patterns = [
            // LESS/CSS @import statements
            /@import\s+["']([^"']+)["'];?/g,
            // CSS @import with url()
            /@import\s+url\s*\(\s*["']([^"']+)["']\s*\)/g
        ];
        
        patterns.forEach(pattern => {
            let match;
            while ((match = pattern.exec(content)) !== null) {
                imports.push(match[1]);
            }
        });
        
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
        
        // Generate style file mapping report
        await this.generateStyleMappingReport(analysisDir);
        
        // Generate optimization recommendations
        await this.generateOptimizationReport(analysisDir);
        
        // Generate template-specific reports
        await this.generateTemplateReport(analysisDir);
        
        // Generate controller-specific reports
        await this.generateControllerReport(analysisDir);
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
                controllersAnalyzed: this.controllerClassMap.size,
                styleFilesAnalyzed: this.styleFileMap.size,
                notImportedFiles: this.notImportedFiles.size
            },
            classBreakdown: {
                bootstrap: Array.from(this.bootstrapClasses).sort(),
                icons: Array.from(this.iconClasses).sort(),
                custom: Array.from(this.customClasses).sort()
            },
            importChainSummary: {
                totalImportedFiles: this.importChain.size,
                entryPoint: this.entryPoint
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
     * Generate enhanced style file mapping report
     */
    async generateStyleMappingReport(analysisDir) {
        const styleAnalysis = [];
        
        for (const [styleFile, data] of this.styleFileMap) {
            const usedClassesInFile = data.classes.filter(cls => this.usedClasses.has(cls));
            const unusedClassesInFile = data.classes.filter(cls => !this.usedClasses.has(cls));
            
            styleAnalysis.push({
                file: styleFile,
                type: data.type,
                isImported: data.isImported,
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
        
        // Sort by import status first, then by usage percentage
        styleAnalysis.sort((a, b) => {
            if (a.isImported !== b.isImported) {
                return a.isImported ? -1 : 1; // Imported files first
            }
            return a.usagePercentage - b.usagePercentage; // Then by usage
        });
        
        fs.writeFileSync(
            path.join(analysisDir, 'style-mapping-report.json'),
            JSON.stringify(styleAnalysis, null, 2)
        );
    }

    /**
     * Generate enhanced optimization recommendations
     */
    async generateOptimizationReport(analysisDir) {
        const recommendations = {
            notImported: [],
            zeroUsage: [],
            lowUsage: [],
            normalUsage: [],
            summary: {
                totalFiles: this.styleFileMap.size,
                notImportedCount: this.notImportedFiles.size,
                zeroUsageCount: 0,
                lowUsageCount: 0,
                normalUsageCount: 0
            }
        };
        
        // Files not imported at all (highest priority for removal)
        this.notImportedFiles.forEach(file => {
            const data = this.styleFileMap.get(file);
            if (data) {
                recommendations.notImported.push({
                    file: file,
                    type: data.type,
                    size: data.size,
                    totalClasses: data.classes.length,
                    reason: 'Not imported in entry point chain'
                });
            }
        });
        
        // Analyze imported files for usage
        for (const [styleFile, data] of this.styleFileMap) {
            if (this.notImportedFiles.has(styleFile)) continue; // Already handled above
            
            const usedClasses = data.classes.filter(cls => this.usedClasses.has(cls));
            const usagePercentage = data.classes.length > 0 ? 
                (usedClasses.length / data.classes.length) * 100 : 0;
            
            const fileInfo = {
                file: styleFile,
                type: data.type,
                isImported: data.isImported,
                usagePercentage: Math.round(usagePercentage),
                size: data.size,
                usedClasses: usedClasses.length,
                totalClasses: data.classes.length
            };
            
            if (usagePercentage === 0) {
                recommendations.zeroUsage.push(fileInfo);
                recommendations.summary.zeroUsageCount++;
            } else if (usagePercentage < 20) {
                recommendations.lowUsage.push(fileInfo);
                recommendations.summary.lowUsageCount++;
            } else {
                recommendations.normalUsage.push(fileInfo);
                recommendations.summary.normalUsageCount++;
            }
        }
        
        // Sort each category by size (largest first for potential savings)
        recommendations.notImported.sort((a, b) => b.size - a.size);
        recommendations.zeroUsage.sort((a, b) => b.size - a.size);
        recommendations.lowUsage.sort((a, b) => a.usagePercentage - b.usagePercentage);
        
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
     * Generate controller-specific analysis
     */
    async generateControllerReport(analysisDir) {
        const controllerAnalysis = [];
        
        for (const [controllerFile, classes] of this.controllerClassMap) {
            const bootstrapInController = classes.filter(cls => this.bootstrapClasses.has(cls));
            const iconsInController = classes.filter(cls => this.iconClasses.has(cls));
            const customInController = classes.filter(cls => this.customClasses.has(cls));
            
            controllerAnalysis.push({
                controller: controllerFile,
                totalClasses: classes.length,
                bootstrap: bootstrapInController.length,
                icons: iconsInController.length,
                custom: customInController.length,
                allClasses: classes
            });
        }
        
        // Sort by total classes (most complex controllers first)
        controllerAnalysis.sort((a, b) => b.totalClasses - a.totalClasses);
        
        fs.writeFileSync(
            path.join(analysisDir, 'controller-analysis.json'),
            JSON.stringify(controllerAnalysis, null, 2)
        );
    }

    /**
     * Generate human-readable markdown report
     */
    generateMarkdownReport(report) {
        return `# Enhanced CSS/LESS Usage Analysis Report

## Summary

- **Total CSS Classes Found**: ${report.summary.totalClasses}
- **Bootstrap/Template Classes**: ${report.summary.bootstrapClasses}
- **Icon Classes**: ${report.summary.iconClasses}
- **Custom Classes**: ${report.summary.customClasses}
- **Templates Analyzed**: ${report.summary.templatesAnalyzed}
- **Controllers Analyzed**: ${report.summary.controllersAnalyzed}
- **Style Files Analyzed**: ${report.summary.styleFilesAnalyzed}
- **Files Not Imported**: ${report.summary.notImportedFiles}

## Import Chain Analysis

- **Entry Point**: ${report.importChainSummary.entryPoint}
- **Files in Import Chain**: ${report.importChainSummary.totalImportedFiles}
- **Files NOT in Import Chain**: ${report.summary.notImportedFiles}

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

1. **High Priority**: Review files in \`optimization-recommendations.json\` under \`notImported\` - these can likely be deleted
2. **Medium Priority**: Check \`zeroUsage\` files - imported but no classes used
3. **Low Priority**: Review \`lowUsage\` files for potential optimization
4. Use \`style-mapping-report.json\` to see detailed usage per file
5. Check \`template-analysis.json\` and \`controller-analysis.json\` for usage patterns

## Files Generated

- \`css-usage-report.json\` - Complete class usage data with import chain info
- \`style-mapping-report.json\` - All style files analysis (LESS + CSS)
- \`optimization-recommendations.json\` - Categorized recommendations for cleanup
- \`template-analysis.json\` - Per-template class usage breakdown
- \`controller-analysis.json\` - Per-controller class usage breakdown
`;
    }
}

// Run the analysis if this script is executed directly
if (require.main === module) {
    const analyzer = new CSSUsageAnalyzer();
    analyzer.analyze().catch(console.error);
}

module.exports = CSSUsageAnalyzer;