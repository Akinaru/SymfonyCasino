import './stimulus_bootstrap.js';
import './bootstrap.js';
import '@symfony/ux-live-component';

// Import des controlleurs mercures
import './mercure/games/slots-lastgames.js';
import './mercure/games/dice-lastgames.js';
import './mercure/games/mines-lastgames.js';
import './mercure/chat/chat-bus.js'

/*
 * Welcome to your app's main JavaScript file!
 *
 * This file will be included onto the page via the importmap() Twig function,
 * which should already be in your base.html.twig.
 */
import 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js';

console.log('This log comes from assets/app.js - welcome to AssetMapper! 🎉');
