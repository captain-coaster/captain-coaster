/*
 * Lazy Loading Utility Module
 * 
 * Provides utilities for lazy loading JavaScript modules and libraries
 * to improve initial page load performance
 */

/**
 * Lazy load a module with caching and error handling
 * @param {Function} importFn - Dynamic import function
 * @param {string} moduleName - Name of the module for logging
 * @param {Object} options - Loading options
 * @returns {Promise} - Promise that resolves to the loaded module
 */
export const lazyLoad = async (importFn, moduleName, options = {}) => {
    const { 
        cache = true, 
        timeout = 10000,
        fallback = null,
        onError = null 
    } = options;
    
    // Check cache first
    if (cache && window._lazyLoadCache && window._lazyLoadCache[moduleName]) {
        return window._lazyLoadCache[moduleName];
    }
    
    try {
        // Create timeout promise
        const timeoutPromise = new Promise((_, reject) => {
            setTimeout(() => reject(new Error(`Timeout loading ${moduleName}`)), timeout);
        });
        
        // Race between import and timeout
        const module = await Promise.race([importFn(), timeoutPromise]);
        
        // Cache the loaded module
        if (cache) {
            window._lazyLoadCache = window._lazyLoadCache || {};
            window._lazyLoadCache[moduleName] = module;
        }
        
        console.log(`Successfully loaded ${moduleName}`);
        return module;
        
    } catch (error) {
        console.error(`Failed to load ${moduleName}:`, error);
        
        if (onError) {
            onError(error);
        }
        
        if (fallback) {
            console.log(`Using fallback for ${moduleName}`);
            return fallback;
        }
        
        throw error;
    }
};

/**
 * Load module when element becomes visible (intersection observer)
 * @param {string} selector - CSS selector for trigger elements
 * @param {Function} importFn - Dynamic import function
 * @param {string} moduleName - Name of the module
 * @param {Object} options - Loading options
 */
export const lazyLoadOnVisible = (selector, importFn, moduleName, options = {}) => {
    const elements = document.querySelectorAll(selector);
    
    if (elements.length === 0) {
        return;
    }
    
    const observer = new IntersectionObserver(
        async (entries) => {
            for (const entry of entries) {
                if (entry.isIntersecting) {
                    try {
                        await lazyLoad(importFn, moduleName, options);
                        observer.unobserve(entry.target);
                    } catch (error) {
                        console.error(`Failed to lazy load ${moduleName} on visibility:`, error);
                    }
                }
            }
        },
        { 
            rootMargin: '50px',
            threshold: 0.1 
        }
    );
    
    elements.forEach(element => observer.observe(element));
};

/**
 * Load module on user interaction (click, hover, focus)
 * @param {string} selector - CSS selector for trigger elements
 * @param {Function} importFn - Dynamic import function
 * @param {string} moduleName - Name of the module
 * @param {string} event - Event type to listen for
 * @param {Object} options - Loading options
 */
export const lazyLoadOnInteraction = (selector, importFn, moduleName, event = 'click', options = {}) => {
    const elements = document.querySelectorAll(selector);
    
    if (elements.length === 0) {
        return;
    }
    
    const loadHandler = async () => {
        try {
            await lazyLoad(importFn, moduleName, options);
            
            // Remove event listeners after loading
            elements.forEach(element => {
                element.removeEventListener(event, loadHandler);
            });
        } catch (error) {
            console.error(`Failed to lazy load ${moduleName} on ${event}:`, error);
        }
    };
    
    elements.forEach(element => {
        element.addEventListener(event, loadHandler, { once: true });
    });
};

/**
 * Preload module during idle time
 * @param {Function} importFn - Dynamic import function
 * @param {string} moduleName - Name of the module
 * @param {Object} options - Loading options
 */
export const preloadOnIdle = (importFn, moduleName, options = {}) => {
    if ('requestIdleCallback' in window) {
        requestIdleCallback(async () => {
            try {
                await lazyLoad(importFn, moduleName, options);
            } catch (error) {
                console.error(`Failed to preload ${moduleName}:`, error);
            }
        });
    } else {
        // Fallback for browsers without requestIdleCallback
        setTimeout(async () => {
            try {
                await lazyLoad(importFn, moduleName, options);
            } catch (error) {
                console.error(`Failed to preload ${moduleName}:`, error);
            }
        }, 2000);
    }
};

/**
 * Load critical modules immediately, non-critical modules lazily
 * @param {Object} modules - Object with critical and nonCritical arrays
 */
export const loadModules = async (modules) => {
    const { critical = [], nonCritical = [] } = modules;
    
    // Load critical modules immediately
    if (critical.length > 0) {
        try {
            await Promise.all(
                critical.map(({ importFn, name, options }) => 
                    lazyLoad(importFn, name, options)
                )
            );
        } catch (error) {
            console.error('Failed to load critical modules:', error);
        }
    }
    
    // Load non-critical modules with delay
    if (nonCritical.length > 0) {
        setTimeout(() => {
            nonCritical.forEach(({ importFn, name, options, trigger }) => {
                if (trigger) {
                    // Load based on trigger condition
                    if (trigger.type === 'visible') {
                        lazyLoadOnVisible(trigger.selector, importFn, name, options);
                    } else if (trigger.type === 'interaction') {
                        lazyLoadOnInteraction(trigger.selector, importFn, name, trigger.event, options);
                    }
                } else {
                    // Load immediately but with lower priority
                    preloadOnIdle(importFn, name, options);
                }
            });
        }, 100);
    }
};

// Initialize lazy loading cache
window._lazyLoadCache = window._lazyLoadCache || {};