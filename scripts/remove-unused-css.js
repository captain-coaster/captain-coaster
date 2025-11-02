#!/usr/bin/env node

/**
 * CSS Cleanup Script
 * 
 * This script removes unused LESS files based on the analysis results.
 * It provides different modes for safe removal of unused CSS components.
 */

const fs = require('fs');
const path = require('path');

class CSSCleanupTool {
    constructor() {
        this.analysisDir = 'analysis';
        this.backupDir = 'css-backup';
        this.dryRun = false;
    }

    /**
     * Main cleanup function
     */
    async cleanup(options = {}) {
        this.dryRun = options.dryRun || false;
        
        console.log('🧹 Starting CSS cleanup process...\n');
        
        try {
            // Load analysis results
            const recommendations = this.loadRecommendations();
            
            // Create backup if not dry run
            if (!this.dryRun) {
                await this.createBackup();
            }
            
            // Remove high priority files (0% usage)
            await this.removeHighPriorityFiles(recommendations.highPriorityRemovals);
            
            // Optionally remove medium priority files
            if (options.includeMediumPriority) {
                await this.reviewMediumPriorityFiles(recommendations.mediumPriorityRemovals);
            }
            
            // Generate cleanup report
            await this.generateCleanupReport(recommendations);
            
            console.log('✅ CSS cleanup completed successfully!');
            
        } catch (error) {
            console.error('❌ Cleanup failed:', error.message);
            process.exit(1);
        }
    }

    /**
     * Load optimization recommendations
     */
    loadRecommendations() {
        const recommendationsPath = path.join(this.analysisDir, 'optimization-recommendations.json');
        
        if (!fs.existsSync(recommendationsPath)) {
            throw new Error('Analysis results not found. Please run analyze-css-usage.js first.');
        }
        
        return JSON.parse(fs.readFileSync(recommendationsPath, 'utf8'));
    }

    /**
     * Create backup of LESS files before removal
     */
    async createBackup() {
        console.log('💾 Creating backup of LESS files...');
        
        if (!fs.existsSync(this.backupDir)) {
            fs.mkdirSync(this.backupDir, { recursive: true });
        }
        
        // Copy entire assets/less directory to backup
        this.copyDirectory('assets/less', path.join(this.backupDir, 'less'));
        
        // Create backup manifest
        const manifest = {
            timestamp: new Date().toISOString(),
            originalPath: 'assets/less',
            backupPath: this.backupDir,
            note: 'Backup created before CSS optimization'
        };
        
        fs.writeFileSync(
            path.join(this.backupDir, 'backup-manifest.json'),
            JSON.stringify(manifest, null, 2)
        );
        
        console.log(`Backup created in ${this.backupDir}/\n`);
    }

    /**
     * Copy directory recursively
     */
    copyDirectory(src, dest) {
        if (!fs.existsSync(dest)) {
            fs.mkdirSync(dest, { recursive: true });
        }
        
        const items = fs.readdirSync(src);
        
        for (const item of items) {
            const srcPath = path.join(src, item);
            const destPath = path.join(dest, item);
            const stat = fs.statSync(srcPath);
            
            if (stat.isDirectory()) {
                this.copyDirectory(srcPath, destPath);
            } else {
                fs.copyFileSync(srcPath, destPath);
            }
        }
    }

    /**
     * Remove high priority files (0% usage)
     */
    async removeHighPriorityFiles(highPriorityFiles) {
        console.log(`🗑️  Removing ${highPriorityFiles.length} unused files (0% usage)...`);
        
        let removedCount = 0;
        let totalSizeSaved = 0;
        
        for (const fileInfo of highPriorityFiles) {
            const filePath = fileInfo.file;
            
            if (fs.existsSync(filePath)) {
                if (this.dryRun) {
                    console.log(`[DRY RUN] Would remove: ${filePath} (${fileInfo.size} bytes)`);
                } else {
                    fs.unlinkSync(filePath);
                    console.log(`Removed: ${filePath} (${fileInfo.size} bytes)`);
                }
                
                removedCount++;
                totalSizeSaved += fileInfo.size;
            } else {
                console.log(`File not found: ${filePath}`);
            }
        }
        
        console.log(`\n📊 High Priority Cleanup Results:`);
        console.log(`  - Files removed: ${removedCount}`);
        console.log(`  - Size saved: ${this.formatBytes(totalSizeSaved)}`);
        console.log('');
    }

    /**
     * Review medium priority files (low usage)
     */
    async reviewMediumPriorityFiles(mediumPriorityFiles) {
        console.log(`⚠️  Reviewing ${mediumPriorityFiles.length} files with low usage (1-20%)...`);
        
        // Sort by usage percentage (lowest first)
        const sortedFiles = mediumPriorityFiles.sort((a, b) => a.usagePercentage - b.usagePercentage);
        
        console.log('\nFiles with very low usage (consider for removal):');
        
        const veryLowUsage = sortedFiles.filter(f => f.usagePercentage <= 5);
        for (const fileInfo of veryLowUsage) {
            console.log(`  - ${fileInfo.file} (${fileInfo.usagePercentage}% usage, ${this.formatBytes(fileInfo.size)})`);
            console.log(`    Used: ${fileInfo.usedClasses}/${fileInfo.totalClasses} classes`);
        }
        
        console.log('\nFiles with low usage (review for optimization):');
        
        const lowUsage = sortedFiles.filter(f => f.usagePercentage > 5 && f.usagePercentage <= 15);
        for (const fileInfo of lowUsage) {
            console.log(`  - ${fileInfo.file} (${fileInfo.usagePercentage}% usage, ${this.formatBytes(fileInfo.size)})`);
            console.log(`    Used: ${fileInfo.usedClasses}/${fileInfo.totalClasses} classes`);
        }
        
        console.log('\n💡 Recommendation: Review these files manually for selective optimization.');
        console.log('');
    }

    /**
     * Generate cleanup report
     */
    async generateCleanupReport(recommendations) {
        const report = {
            timestamp: new Date().toISOString(),
            dryRun: this.dryRun,
            summary: {
                highPriorityFiles: recommendations.highPriorityRemovals.length,
                mediumPriorityFiles: recommendations.mediumPriorityRemovals.length,
                keepFiles: recommendations.keepFiles.length,
                totalSizeSaved: recommendations.highPriorityRemovals.reduce((sum, f) => sum + f.size, 0)
            },
            removedFiles: recommendations.highPriorityRemovals.map(f => ({
                file: f.file,
                size: f.size,
                reason: 'Zero usage (0% of classes used)'
            })),
            nextSteps: [
                'Test the application thoroughly',
                'Run npm run build to verify compilation',
                'Check for any visual regressions',
                'Consider optimizing medium-priority files',
                'Update documentation'
            ]
        };
        
        const reportPath = path.join(this.analysisDir, 'cleanup-report.json');
        fs.writeFileSync(reportPath, JSON.stringify(report, null, 2));
        
        // Generate markdown report
        const markdownReport = this.generateMarkdownCleanupReport(report);
        fs.writeFileSync(
            path.join(this.analysisDir, 'cleanup-report.md'),
            markdownReport
        );
        
        console.log(`📋 Cleanup report generated: ${reportPath}`);
    }

    /**
     * Generate markdown cleanup report
     */
    generateMarkdownCleanupReport(report) {
        return `# CSS Cleanup Report

Generated: ${report.timestamp}
Mode: ${report.dryRun ? 'DRY RUN' : 'ACTUAL CLEANUP'}

## Summary

- **High Priority Files Removed**: ${report.summary.highPriorityFiles}
- **Medium Priority Files (Review Needed)**: ${report.summary.mediumPriorityFiles}
- **Files Kept**: ${report.summary.keepFiles}
- **Total Size Saved**: ${this.formatBytes(report.summary.totalSizeSaved)}

## Removed Files

${report.removedFiles.map(f => `- \`${f.file}\` (${this.formatBytes(f.size)}) - ${f.reason}`).join('\n')}

## Next Steps

${report.nextSteps.map(step => `- [ ] ${step}`).join('\n')}

## Rollback Instructions

If you need to restore the removed files:

\`\`\`bash
# Restore from backup
cp -r ${this.backupDir}/less/* assets/less/

# Or restore from git (if committed)
git checkout HEAD~1 -- assets/less/
\`\`\`

## Verification Commands

\`\`\`bash
# Test build process
npm run build

# Check for compilation errors
npm run dev

# Run any existing tests
npm test
\`\`\`
`;
    }

    /**
     * Format bytes to human readable format
     */
    formatBytes(bytes) {
        if (bytes === 0) return '0 Bytes';
        
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    /**
     * Restore from backup
     */
    async restore() {
        console.log('🔄 Restoring LESS files from backup...');
        
        if (!fs.existsSync(this.backupDir)) {
            throw new Error('Backup directory not found. Cannot restore.');
        }
        
        const backupLessDir = path.join(this.backupDir, 'less');
        if (!fs.existsSync(backupLessDir)) {
            throw new Error('Backup LESS directory not found.');
        }
        
        // Remove current assets/less directory
        if (fs.existsSync('assets/less')) {
            fs.rmSync('assets/less', { recursive: true, force: true });
        }
        
        // Restore from backup
        this.copyDirectory(backupLessDir, 'assets/less');
        
        console.log('✅ LESS files restored from backup successfully!');
    }
}

// CLI interface
if (require.main === module) {
    const args = process.argv.slice(2);
    const tool = new CSSCleanupTool();
    
    if (args.includes('--help') || args.includes('-h')) {
        console.log(`
CSS Cleanup Tool

Usage:
  node scripts/remove-unused-css.js [options]

Options:
  --dry-run                 Show what would be removed without actually removing
  --include-medium-priority Include medium priority files (1-20% usage) for review
  --restore                 Restore files from backup
  --help, -h               Show this help message

Examples:
  node scripts/remove-unused-css.js --dry-run
  node scripts/remove-unused-css.js
  node scripts/remove-unused-css.js --include-medium-priority
  node scripts/remove-unused-css.js --restore
`);
        process.exit(0);
    }
    
    if (args.includes('--restore')) {
        tool.restore().catch(console.error);
    } else {
        const options = {
            dryRun: args.includes('--dry-run'),
            includeMediumPriority: args.includes('--include-medium-priority')
        };
        
        tool.cleanup(options).catch(console.error);
    }
}

module.exports = CSSCleanupTool;