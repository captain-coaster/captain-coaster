// The routes marked `expose`, for Routing.generate() in the Stimulus
// controllers. The JSON is dumped by the build (vite.config.js), not in git.
import Routing from 'fos-router';
import routes from './fos_js_routes.json';

Routing.setRoutingData(routes);
window.Routing = Routing;
