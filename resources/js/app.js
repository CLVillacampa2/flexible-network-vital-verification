import './bootstrap';
import './app.jsx';

if ('serviceWorker' in navigator && import.meta.env.PROD && window.isSecureContext) {
    window.addEventListener('load', async () => {
        try {
            const registration = await navigator.serviceWorker.register('/service-worker.js', {
                updateViaCache: 'none',
            });

            // Check for a new worker on each application load so fixes are not
            // hidden behind an older browser-cached service-worker script.
            await registration.update();
        } catch (error) {
            // The app remains usable if service-worker registration is blocked.
            console.warn('HIM offline support could not be started.', error);
        }
    });
}
