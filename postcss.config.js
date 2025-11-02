module.exports = {
    plugins: [
        require('autoprefixer')({
            overrideBrowserslist: [
                'last 2 versions',
                '> 1%',
                'not dead',
                'not IE 11'
            ]
        }),
        // Only apply cssnano in production
        ...(process.env.NODE_ENV === 'production' ? [
            require('cssnano')({
                preset: ['default', {
                    // Preserve important comments
                    discardComments: { removeAll: false },
                    // Don't merge rules that might break specificity
                    mergeRules: false,
                    // Preserve CSS custom properties
                    reduceIdents: false,
                    // Don't remove unused CSS (we'll handle this separately)
                    discardUnused: false
                }]
            })
        ] : [])
    ]
};