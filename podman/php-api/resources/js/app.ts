import { createInertiaApp, router } from '@inertiajs/vue3';
import { initializeTheme } from '@/composables/useAppearance';
import AuthLayout from '@/layouts/AuthLayout.vue';
import NavbarLayout from '@/layouts/NavbarLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { initializeFlashToast } from '@/lib/flashToast';

// Nom de la marca per al títol de la pestanya (es pot canviar a /admin/informacio).
let appName = import.meta.env.VITE_APP_NAME || 'ReservaHores';

function applyBranding(page: { props?: Record<string, unknown> } | null | undefined): void {
    const props = page?.props ?? {};

    if (typeof props.siteName === 'string' && props.siteName) {
        appName = props.siteName;
    }

    const logo = props.logoUrl;
    if (typeof logo === 'string' && logo) {
        // Esborrem els favicons existents i n'afegim un de nou (alguns navegadors no
        // refresquen si només es canvia l'href).
        document.querySelectorAll("link[rel~='icon']").forEach((l) => l.remove());
        const link = document.createElement('link');
        link.setAttribute('rel', 'icon');
        link.setAttribute('href', logo);
        document.head.appendChild(link);
    }
}

// Marca inicial (de la primera càrrega), abans que Inertia apliqui el títol.
try {
    const el = document.getElementById('app');
    if (el?.dataset.page) {
        applyBranding(JSON.parse(el.dataset.page));
    }
} catch {
    // Ignorem errors de parseig del payload inicial.
}

// En cada navegació, actualitzem el nom i el favicon segons les dades compartides.
router.on('navigate', (event) => applyBranding(event.detail.page));

createInertiaApp({
    // La pestanya mostra només el nom de la marca (configurable a /admin/informacio).
    title: () => appName,
    layout: (name) => {
        switch (true) {
            case name === 'Welcome':
            case name === 'PostDetail':
                return null;
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('settings/'):
                return [NavbarLayout, SettingsLayout];
            default:
                return NavbarLayout;
        }
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on page load...
initializeTheme();

// This will listen for flash toast data from the server...
initializeFlashToast();
