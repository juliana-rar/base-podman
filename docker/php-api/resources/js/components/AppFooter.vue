<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { Clock, Mail, MapPin, Phone } from '@lucide/vue';
import { computed } from 'vue';
import { useI18n } from '@/lib/i18n';
import '../../css/reserva/footer.css';

interface BusinessHour {
    weekday: number;
    closed: boolean;
    opens: string | null;
    closes: string | null;
}

const page = usePage();
const { t, localeTag } = useI18n();
const year = computed(() => new Date().getFullYear());

const hours = computed<BusinessHour[]>(() => (page.props.businessHours as BusinessHour[] | undefined) ?? []);
const address = computed<string>(() => (page.props.businessAddress as string | null | undefined) ?? '');
const mapSrc = computed(
    () => `https://maps.google.com/maps?q=${encodeURIComponent(address.value)}&z=15&output=embed`,
);

interface Contact {
    email?: string | null;
    phone?: string | null;
    instagram?: string | null;
    facebook?: string | null;
    linkedin?: string | null;
}
const contact = computed<Contact>(() => (page.props.siteContact as Contact | undefined) ?? {});
const email = computed(() => contact.value.email || 'info@reservahores.com');
const phone = computed(() => contact.value.phone || '+34 931 234 567');
// Icones de marca (SVG inline; lucide no inclou logos de marca).
const SOCIAL_ICONS: Record<string, string> = {
    Instagram:
        'M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z',
    Facebook:
        'M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z',
    LinkedIn:
        'M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z',
};

const socials = computed(() =>
    [
        { name: 'Instagram', url: contact.value.instagram },
        { name: 'Facebook', url: contact.value.facebook },
        { name: 'LinkedIn', url: contact.value.linkedin },
    ].filter((s): s is { name: string; url: string } => Boolean(s.url)),
);

function dayName(weekday: number): string {
    // 2024-01-01 va ser dilluns; sumem el dia per al nom localitzat.
    return new Date(2024, 0, 1 + weekday).toLocaleDateString(localeTag(), { weekday: 'long' });
}

function hourRange(h: BusinessHour): string {
    if (h.closed || !h.opens || !h.closes) {
        return t('footer.closed');
    }
    return `${h.opens.slice(0, 5)} – ${h.closes.slice(0, 5)}`;
}
</script>

<template>
    <footer class="rsv-footer">
        <div class="rsv-footer-inner">
            <div class="rsv-footer-brand">
                <div v-if="address" class="rsv-footer-map">
                    <iframe
                        :src="mapSrc"
                        title="Mapa"
                        loading="lazy"
                        referrerpolicy="no-referrer-when-downgrade"
                    ></iframe>
                    <span class="rsv-footer-addr">
                        <MapPin />
                        <span>{{ address }}</span>
                    </span>
                </div>
            </div>

            <div class="rsv-footer-col rsv-footer-contact">
                <h4>{{ t('footer.contact') }}</h4>
                <a :href="`mailto:${email}`">
                    <Mail />
                    <span>{{ email }}</span>
                </a>
                <a :href="`tel:${phone.replace(/\s+/g, '')}`">
                    <Phone />
                    <span>{{ phone }}</span>
                </a>
                <div v-if="socials.length" class="rsv-footer-social">
                    <a
                        v-for="s in socials"
                        :key="s.name"
                        :href="s.url"
                        target="_blank"
                        rel="noopener"
                        :aria-label="s.name"
                        :title="s.name"
                    >
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path :d="SOCIAL_ICONS[s.name]" /></svg>
                    </a>
                </div>
            </div>

            <div class="rsv-footer-col">
                <h4>{{ t('footer.hours') }}</h4>
                <div v-if="hours.length" class="rsv-footer-hours">
                    <div v-for="h in hours" :key="h.weekday" class="rsv-footer-hrow">
                        <span class="rsv-footer-hday">{{ dayName(h.weekday) }}</span>
                        <span class="rsv-footer-hval" :class="{ closed: h.closed }">{{ hourRange(h) }}</span>
                    </div>
                </div>
                <span v-else class="rsv-footer-static">
                    <Clock />
                    <span>{{ t('footer.hoursVal') }}</span>
                </span>
            </div>
        </div>

        <div class="rsv-footer-bottom">
            <span>© {{ year }} ReservaHores · {{ t('footer.rights') }}</span>
        </div>
    </footer>
</template>
