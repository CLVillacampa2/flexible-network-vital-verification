import './bootstrap';
import './app.jsx';

if ('serviceWorker' in navigator) {
    window.addEventListener('load', async () => {
        // A worker from an earlier production build can keep serving stale
        // files while the developer runs Vite in development mode.
        if (import.meta.env.DEV) {
            try {
                const registrations = await navigator.serviceWorker.getRegistrations();
                await Promise.all(registrations.map((registration) => registration.unregister()));
            } catch (error) {
                console.warn('Could not clear the previous HIM service worker.', error);
            }
            return;
        }

        if (!window.isSecureContext) return;

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
