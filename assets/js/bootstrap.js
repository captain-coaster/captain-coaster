import {
    startStimulusApp,
    registerControllers,
} from 'vite-plugin-symfony/stimulus/helpers';

// Start the Stimulus application
const app = startStimulusApp();

// Register all controllers using Vite's import.meta.glob
registerControllers(
    app,
    import.meta.glob('../controllers/*_controller.js', {
        query: '?stimulus',
        eager: true,
    })
);

// Export for HMR support
export { app };
