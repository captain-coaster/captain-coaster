const sharp = require('sharp');
const path = require('path');
const fs = require('fs').promises;

class ImageOptimizationPlugin {
    constructor(options = {}) {
        this.options = {
            // Default options
            quality: 85,
            webpQuality: 80,
            pngQuality: [0.6, 0.8],
            jpegQuality: 85,
            generateWebP: true,
            ...options
        };
    }

    apply(compiler) {
        const pluginName = 'ImageOptimizationPlugin';

        compiler.hooks.emit.tapAsync(pluginName, async (compilation, callback) => {
            try {
                // Process all image assets
                const imageAssets = Object.keys(compilation.assets).filter(filename =>
                    /\.(png|jpe?g|gif|svg)$/i.test(filename)
                );

                for (const filename of imageAssets) {
                    const asset = compilation.assets[filename];
                    const source = asset.source();
                    
                    // Skip if source is not a buffer (already processed)
                    if (!Buffer.isBuffer(source)) {
                        continue;
                    }

                    const ext = path.extname(filename).toLowerCase();
                    const baseName = path.basename(filename, ext);
                    const dirName = path.dirname(filename);

                    try {
                        let optimizedBuffer;
                        
                        // Optimize based on file type
                        if (ext === '.png') {
                            optimizedBuffer = await sharp(source)
                                .png({ 
                                    quality: Math.round(this.options.pngQuality[1] * 100),
                                    compressionLevel: 9,
                                    adaptiveFiltering: true
                                })
                                .toBuffer();
                        } else if (ext === '.jpg' || ext === '.jpeg') {
                            optimizedBuffer = await sharp(source)
                                .jpeg({ 
                                    quality: this.options.jpegQuality,
                                    progressive: true,
                                    mozjpeg: true
                                })
                                .toBuffer();
                        } else if (ext === '.gif') {
                            // For GIFs, we'll keep them as-is since sharp doesn't handle animated GIFs well
                            optimizedBuffer = source;
                        } else {
                            // For other formats (like SVG), keep as-is
                            optimizedBuffer = source;
                        }

                        // Replace the original asset with optimized version
                        compilation.assets[filename] = {
                            source: () => optimizedBuffer,
                            size: () => optimizedBuffer.length
                        };

                        // Generate WebP version if enabled and supported format
                        if (this.options.generateWebP && (ext === '.png' || ext === '.jpg' || ext === '.jpeg')) {
                            const webpFilename = path.join(dirName, `${baseName}.webp`);
                            
                            const webpBuffer = await sharp(source)
                                .webp({ 
                                    quality: this.options.webpQuality,
                                    effort: 6
                                })
                                .toBuffer();

                            // Add WebP version as new asset
                            compilation.assets[webpFilename] = {
                                source: () => webpBuffer,
                                size: () => webpBuffer.length
                            };
                        }

                    } catch (error) {
                        // If optimization fails, keep original
                        console.warn(`Failed to optimize ${filename}:`, error.message);
                    }
                }

                callback();
            } catch (error) {
                callback(error);
            }
        });
    }
}

module.exports = ImageOptimizationPlugin;