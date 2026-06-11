import { ref } from 'vue';

export type Locale = 'ca' | 'es' | 'en';

export const locales: { code: Locale; label: string }[] = [
    { code: 'ca', label: 'Català' },
    { code: 'es', label: 'Español' },
    { code: 'en', label: 'English' },
];

const intlTag: Record<Locale, string> = {
    ca: 'ca-ES',
    es: 'es-ES',
    en: 'en-GB',
};

const messages: Record<Locale, Record<string, string>> = {
    ca: {
        'nav.inici': 'Inici',
        'nav.reservar': 'Reservar',
        'nav.perfil': 'Perfil',
        'nav.hores': 'Hores',
        'nav.posts': 'Posts',
        'nav.etiquetes': 'Etiquetes',
        'nav.historial': 'Historial',
        'nav.sortir': 'Sortir',
        'nav.dashboard': 'Tauler',
        'nav.imatges': 'Imatges',
        'nav.serveis': 'Serveis',
        'lang.label': 'Idioma',
        'welcome.panell': 'Panell',
        'welcome.entrar': 'Entrar',
        'welcome.crear': 'Crear compte',
        'welcome.heroA': 'Reserva la teva hora',
        'welcome.heroB': 'en segons',
        'welcome.heroText':
            "Tria el dia, gira el rellotge i escull l'hora que més t'encaixi. Senzill, ràpid i sense trucades.",
        'welcome.ctaUser': 'Reservar ara',
        'welcome.ctaGuest': 'Comença ara',
        'welcome.novetats': 'Novetats',
        'welcome.empty': 'Encara no hi ha novetats publicades.',
        'welcome.footer': "Sistema de reserva d'hores",
        'footer.tagline': "Reserva les teves hores de manera fàcil i ràpida, des de qualsevol dispositiu.",
        'footer.contact': 'Contacte',
        'footer.hours': 'Horari',
        'footer.hoursVal': 'Dilluns a divendres · 9:00 – 20:00',
        'footer.rights': 'Tots els drets reservats.',
        'dash.hello': 'Hola',
        'dash.where': 'On vols anar?',
        'dash.reservarD': 'Reserva una hora',
        'dash.horesD': 'Obre i gestiona franges',
        'dash.postsD': 'Publica i edita posts',
        'dash.etiquetesD': 'Gestiona les etiquetes',
        'dash.imatgesD': 'Gestiona les imatges del carrusel',
        'dash.serveisD': 'Crea i gestiona els serveis',
        'dash.historialD': 'Totes les reserves fetes',
        'dash.iniciD': 'Pàgina pública',
        'res.title': 'Reserva la teva hora',
        'res.subtitle': 'Tria un dia al calendari, escull una hora disponible al rellotge i confirma.',
        'res.triaDia': 'Tria el dia',
        'res.horesDisp': 'Hores disponibles',
        'res.capHora': 'Cap hora disponible aquest dia.',
        'res.triaHora': "Tria l'hora",
        'res.oEscriu': "O escriu l'hora:",
        'res.note': 'Motiu de la reserva',
        'res.notePlaceholder': 'Explica breument per què reserves…',
        'res.service': 'Tipus de servei',
        'res.pickService': 'Tria un servei',
        'res.pickAvailable': 'Tria una hora disponible',
        'res.book': 'Reservar el',
        'res.at': 'a les',
        'res.meves': 'Les meves reserves',
        'res.capReserva': 'Encara no tens cap reserva.',
        'res.cancelar': 'Cancel·lar',
        'res.confirmed': 'Reserva confirmada!',
        'res.err': "Aquesta hora ja no està disponible. Tria'n una altra.",
        'post.tornar': "← Tornar a l'inici",
    },
    es: {
        'nav.inici': 'Inicio',
        'nav.reservar': 'Reservar',
        'nav.perfil': 'Perfil',
        'nav.hores': 'Horas',
        'nav.posts': 'Posts',
        'nav.etiquetes': 'Etiquetas',
        'nav.historial': 'Historial',
        'nav.sortir': 'Salir',
        'nav.dashboard': 'Panel',
        'nav.imatges': 'Imágenes',
        'nav.serveis': 'Servicios',
        'lang.label': 'Idioma',
        'welcome.panell': 'Panel',
        'welcome.entrar': 'Entrar',
        'welcome.crear': 'Crear cuenta',
        'welcome.heroA': 'Reserva tu hora',
        'welcome.heroB': 'en segundos',
        'welcome.heroText':
            'Elige el día, gira el reloj y escoge la hora que mejor te venga. Sencillo, rápido y sin llamadas.',
        'welcome.ctaUser': 'Reservar ahora',
        'welcome.ctaGuest': 'Empieza ahora',
        'welcome.novetats': 'Novedades',
        'welcome.empty': 'Todavía no hay novedades publicadas.',
        'welcome.footer': 'Sistema de reserva de horas',
        'footer.tagline': 'Reserva tus horas de manera fácil y rápida, desde cualquier dispositivo.',
        'footer.contact': 'Contacto',
        'footer.hours': 'Horario',
        'footer.hoursVal': 'Lunes a viernes · 9:00 – 20:00',
        'footer.rights': 'Todos los derechos reservados.',
        'dash.hello': 'Hola',
        'dash.where': '¿A dónde quieres ir?',
        'dash.reservarD': 'Reserva una hora',
        'dash.horesD': 'Abre y gestiona franjas',
        'dash.postsD': 'Publica y edita posts',
        'dash.etiquetesD': 'Gestiona las etiquetas',
        'dash.imatgesD': 'Gestiona las imágenes del carrusel',
        'dash.serveisD': 'Crea y gestiona los servicios',
        'dash.historialD': 'Todas las reservas hechas',
        'dash.iniciD': 'Página pública',
        'res.title': 'Reserva tu hora',
        'res.subtitle': 'Elige un día en el calendario, escoge una hora disponible en el reloj y confirma.',
        'res.triaDia': 'Elige el día',
        'res.horesDisp': 'Horas disponibles',
        'res.capHora': 'No hay horas disponibles este día.',
        'res.triaHora': 'Elige la hora',
        'res.oEscriu': 'O escríbela:',
        'res.note': 'Motivo de la reserva',
        'res.notePlaceholder': 'Explica brevemente por qué reservas…',
        'res.service': 'Tipo de servicio',
        'res.pickService': 'Elige un servicio',
        'res.pickAvailable': 'Elige una hora disponible',
        'res.book': 'Reservar el',
        'res.at': 'a las',
        'res.meves': 'Mis reservas',
        'res.capReserva': 'Todavía no tienes ninguna reserva.',
        'res.cancelar': 'Cancelar',
        'res.confirmed': '¡Reserva confirmada!',
        'res.err': 'Esta hora ya no está disponible. Elige otra.',
        'post.tornar': '← Volver al inicio',
    },
    en: {
        'nav.inici': 'Home',
        'nav.reservar': 'Book',
        'nav.perfil': 'Profile',
        'nav.hores': 'Hours',
        'nav.posts': 'Posts',
        'nav.etiquetes': 'Tags',
        'nav.historial': 'History',
        'nav.sortir': 'Log out',
        'nav.dashboard': 'Dashboard',
        'nav.imatges': 'Images',
        'nav.serveis': 'Services',
        'lang.label': 'Language',
        'welcome.panell': 'Dashboard',
        'welcome.entrar': 'Log in',
        'welcome.crear': 'Sign up',
        'welcome.heroA': 'Book your slot',
        'welcome.heroB': 'in seconds',
        'welcome.heroText':
            'Pick the day, spin the clock and choose the time that suits you best. Simple, fast and no phone calls.',
        'welcome.ctaUser': 'Book now',
        'welcome.ctaGuest': 'Get started',
        'welcome.novetats': 'News',
        'welcome.empty': 'No news published yet.',
        'welcome.footer': 'Slot booking system',
        'footer.tagline': 'Book your time slots easily and quickly, from any device.',
        'footer.contact': 'Contact',
        'footer.hours': 'Hours',
        'footer.hoursVal': 'Monday to Friday · 9:00 – 20:00',
        'footer.rights': 'All rights reserved.',
        'dash.hello': 'Hi',
        'dash.where': 'Where to?',
        'dash.reservarD': 'Book a slot',
        'dash.horesD': 'Open and manage slots',
        'dash.postsD': 'Publish and edit posts',
        'dash.etiquetesD': 'Manage tags',
        'dash.imatgesD': 'Manage carousel images',
        'dash.serveisD': 'Create and manage services',
        'dash.historialD': 'All bookings made',
        'dash.iniciD': 'Public page',
        'res.title': 'Book your slot',
        'res.subtitle': 'Pick a day on the calendar, choose an available time on the clock and confirm.',
        'res.triaDia': 'Pick the day',
        'res.horesDisp': 'Available times',
        'res.capHora': 'No times available this day.',
        'res.triaHora': 'Pick the time',
        'res.oEscriu': 'Or type it:',
        'res.note': 'Reason for the booking',
        'res.notePlaceholder': 'Briefly explain why you are booking…',
        'res.service': 'Service type',
        'res.pickService': 'Pick a service',
        'res.pickAvailable': 'Pick an available time',
        'res.book': 'Book',
        'res.at': 'at',
        'res.meves': 'My bookings',
        'res.capReserva': "You don't have any bookings yet.",
        'res.cancelar': 'Cancel',
        'res.confirmed': 'Booking confirmed!',
        'res.err': 'This time is no longer available. Pick another one.',
        'post.tornar': '← Back to home',
    },
};

function getInitial(): Locale {
    if (typeof window === 'undefined') {
        return 'ca';
    }
    const saved = localStorage.getItem('locale');
    return saved === 'ca' || saved === 'es' || saved === 'en' ? saved : 'ca';
}

export const locale = ref<Locale>(getInitial());

export function setLocale(value: Locale): void {
    locale.value = value;
    if (typeof window !== 'undefined') {
        localStorage.setItem('locale', value);
        document.documentElement.lang = value;
    }
}

/**
 * Etiqueta Intl per a formatar dates segons l'idioma actual.
 * Llegir locale.value durant el render fa que sigui reactiu.
 */
export function localeTag(): string {
    return intlTag[locale.value];
}

export function useI18n() {
    const t = (key: string): string => messages[locale.value][key] ?? messages.ca[key] ?? key;
    return { t, locale, setLocale, locales, localeTag };
}
