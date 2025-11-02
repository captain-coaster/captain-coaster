const Encore = require("@symfony/webpack-encore");
const path = require("path");
const ImageOptimizationPlugin = require("./webpack/image-optimization-plugin");

// Manually configure the runtime environment if not already configured yet by the "encore" command.
// It's useful when you use tools that rely on webpack.config.js file.
if (!Encore.isRuntimeEnvironmentConfigured()) {
    Encore.configureRuntimeEnvironment(process.env.NODE_ENV || "dev");
}

Encore
    // directory where compiled assets will be stored
    .setOutputPath("public/build/")
    // public path used by the web server to access the output path
    .setPublicPath("/build")
    // only needed for CDN's or subdirectory deploy
    //.setManifestKeyPrefix('build/')

    /*
     * ENTRY CONFIG
     *
     * Each entry will result in one JavaScript file (e.g. app.js)
     * and one CSS file (e.g. app.css) if your JavaScript imports CSS.
     */
    .addEntry("app", "./assets/js/app.js")
    .addEntry("translator", "./assets/translator.js")
    .addEntry("coaster", "./assets/js/coaster.js")

    // Disable automatic entry chunk splitting to prevent unnecessary vendor sharing
    // Each entrypoint should only include what it actually imports
    .splitEntryChunks(false)

    // will require an extra script tag for runtime.js
    // but, you probably want this, unless you're building a single-page app
    .enableSingleRuntimeChunk()

    /*
     * FEATURE CONFIG
     *
     * Enable & configure other features below. For a full
     * list of features, see:
     * https://symfony.com/doc/current/frontend.html#adding-more-features
     */
    .cleanupOutputBeforeBuild()

    // Enable build notifications for development
    .enableBuildNotifications()

    // Enable source maps in development, disable in production
    .enableSourceMaps(!Encore.isProduction())

    // Enable hashed filenames for production caching
    .enableVersioning(Encore.isProduction())

    // Configure manifest generation for asset resolution
    .configureManifestPlugin((options) => {
        // Generate both manifest.json and entrypoints.json
        options.writeToFileEmit = true;
    })

    // Configure Babel for modern JavaScript support
    .configureBabelPresetEnv((config) => {
        config.useBuiltIns = "usage";
        config.corejs = "3.38";
        // Target modern browsers for better performance
        config.targets = Encore.isProduction()
            ? "defaults and not IE 11"
            : "last 2 Chrome versions, last 2 Firefox versions";
    })

    // Enable LESS loader for template files
    .enableLessLoader((options) => {
        // Configure LESS options
        options.lessOptions = {
            // Enable inline JavaScript in LESS files (needed for some template features)
            javascriptEnabled: true,
            // Set up import paths for easier imports
            paths: ["./assets/less", "./node_modules"],
        };
    })

    // Enable PostCSS for autoprefixing and optimization
    .enablePostCssLoader((options) => {
        options.postcssOptions = {
            plugins: [
                require("autoprefixer")({
                    // Support last 2 versions of major browsers
                    overrideBrowserslist: [
                        "last 2 versions",
                        "> 1%",
                        "not dead",
                    ],
                }),
                // Enable CSS optimization in production
                ...(Encore.isProduction()
                    ? [
                          require("cssnano")({
                              preset: [
                                  "default",
                                  {
                                      // Preserve important comments
                                      discardComments: { removeAll: false },
                                      // Don't merge rules that might break specificity
                                      mergeRules: false,
                                  },
                              ],
                          }),
                      ]
                    : []),
            ],
        };
    })

    // Enable integrity hashes for production security
    .enableIntegrityHashes(Encore.isProduction())

    // Provide jQuery globally for legacy plugins
    .autoProvidejQuery()

    // Enable Stimulus bridge for modern JavaScript interactions
    .enableStimulusBridge("./assets/controllers.json")

    // Configure development server for better development experience
    .configureDevServerOptions((options) => {
        options.hot = true;
        options.liveReload = true;
        // Enable overlay for build errors
        options.client = {
            overlay: {
                errors: true,
                warnings: false,
            },
        };
    })

    // Configure webpack for optimization and performance
    .addAliases({
        "@": path.resolve(__dirname, "assets"),
        "@js": path.resolve(__dirname, "assets/js"),
        "@css": path.resolve(__dirname, "assets/css"),
        "@less": path.resolve(__dirname, "assets/less"),
        "@images": path.resolve(__dirname, "assets/images"),
    })

    // Copy and optimize images from assets/images to build/images
    .copyFiles({
        from: "./assets/images",
        to: "images/[path][name].[hash:8].[ext]",
        pattern: /\.(png|jpe?g|gif|svg|webp)$/i,
        includeSubdirectories: true,
    })

    // Configure selective bundle splitting - only split what's actually shared
    .configureSplitChunks((splitChunks) => {
        // Only apply splitting in production
        if (Encore.isProduction()) {
            splitChunks.chunks = "async"; // Only split async chunks, not entry chunks
            splitChunks.cacheGroups = {
                // Only split large libraries that are actually used by multiple entries
                apexcharts: {
                    test: /[\\/]node_modules[\\/]apexcharts[\\/]/,
                    name: "apexcharts",
                    chunks: "all",
                    priority: 40,
                    minChunks: 1, // Split even if used by only one entry (it's large)
                },
                photoswipe: {
                    test: /[\\/]node_modules[\\/]photoswipe[\\/]/,
                    name: "photoswipe",
                    chunks: "all",
                    priority: 40,
                    minChunks: 1, // Split even if used by only one entry (it's large)
                },
                // Only create common chunks for code actually shared between 2+ entries
                common: {
                    name: "common",
                    minChunks: 2, // Must be used by at least 2 entries
                    chunks: "all",
                    priority: 10,
                    reuseExistingChunk: true,
                    maxSize: 200000, // 200KB max chunk size
                    enforce: false, // Don't force splitting
                },
                // CSS should still be extracted
                styles: {
                    name: "styles",
                    test: /\.(css|less|scss)$/,
                    chunks: "all",
                    priority: 30,
                    enforce: true,
                },
            };
        }
    });

// Tree shaking is enabled by default in production mode

// Get the base webpack config
const config = Encore.getWebpackConfig();

// Add custom image optimization plugin for production builds
if (Encore.isProduction()) {
    config.plugins.push(
        new ImageOptimizationPlugin({
            quality: 85,
            webpQuality: 80,
            pngQuality: [0.6, 0.8],
            jpegQuality: 85,
            generateWebP: true,
        })
    );

    // Configure optimization settings for production
    config.optimization = {
        ...config.optimization,
        usedExports: true,
        sideEffects: false,
        chunkIds: "deterministic",
        moduleIds: "deterministic",
    };
}

module.exports = config;
