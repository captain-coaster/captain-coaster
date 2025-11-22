const Encore = require("@symfony/webpack-encore");
const path = require("path");

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
    .addEntry("coaster", "./assets/js/coaster.js")
    .addEntry("top-list", "./assets/js/top-list.js")

    // Use Encore's default entry chunk splitting

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

    // Encore generates manifest.json by default

    // Configure Babel for modern JavaScript support
    .configureBabelPresetEnv((config) => {
        config.useBuiltIns = "usage";
        config.corejs = 3; // Let package.json manage exact version
        // Browser targets defined in package.json browserslist
    })

    // Enable LESS loader for Bootstrap 3.x compatibility
    .enableLessLoader((options) => {
        options.lessOptions = {
            // Enable inline JavaScript in LESS files (needed for Bootstrap 3.x)
            javascriptEnabled: true,
        };
    })

    // Enable PostCSS for autoprefixing (cssnano handled by Encore's CSS minimizer)
    .enablePostCssLoader((options) => {
        options.postcssOptions = {
            plugins: [
                require("autoprefixer")(),
                // Browser targets defined in package.json browserslist
            ],
        };
    })

    // Enable integrity hashes for production security
    .enableIntegrityHashes(Encore.isProduction())

    // Provide jQuery globally for legacy plugins
    .autoProvidejQuery()

    // Encore handles NODE_ENV automatically

    // Enable Stimulus bridge for modern JavaScript interactions
    .enableStimulusBridge("./assets/controllers.json")

    // Enable persistent build caching for faster rebuilds
    .enableBuildCache({ config: [__filename] })

    // Configure development server (Encore provides good defaults, just customize overlay)
    .configureDevServerOptions((options) => {
        options.client = {
            overlay: {
                errors: true,
                warnings: false,
            },
        };
        // Allow access from mobile devices on local network
        options.allowedHosts = "all";
    })

    // Add useful aliases for imports
    .addAliases({
        "@": path.resolve(__dirname, "assets"),
        "@images": path.resolve(__dirname, "assets/images"),
    })

    // Copy images from assets/images to build/images
    // .copyFiles({
    //     from: "./assets/images",
    //     to: "images/[path][name].[hash:8].[ext]",
    //     pattern: /\.(png|jpe?g|gif|svg|webp)$/i,
    //     includeSubdirectories: true,
    // })

    // Configure bundle splitting for stable vendor libraries
    .configureSplitChunks((splitChunks) => {
        splitChunks.cacheGroups = {
            // jQuery + Bootstrap - stable libraries, rarely changing
            vendor: {
                test: /[\\/]node_modules[\\/](jquery|bootstrap)[\\/]/,
                name: "vendor",
                chunks: "all",
                priority: 50,
                enforce: true,
            },
        };
    });

// Icons are now handled by Symfony UX Icons

// Encore handles tree shaking and optimization automatically
module.exports = Encore.getWebpackConfig();
