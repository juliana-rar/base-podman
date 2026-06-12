<script setup lang="ts">
import { Clock, Mail, MapPin, Phone } from '@lucide/vue';
import { computed } from 'vue';
import { useI18n } from '@/lib/i18n';
import '../../css/reserva/footer.css';

const { t } = useI18n();
const year = computed(() => new Date().getFullYear());
</script>

<template>
    <footer class="rsv-footer">
        <div class="rsv-footer-inner">
            <div class="rsv-footer-brand">
                <span class="rsv-footer-logo">ReservaHores</span>
                <p>{{ t('footer.tagline') }}</p>
                <div class="rsv-footer-social">
                    <a href="https://instagram.com" target="_blank" rel="noopener">Instagram</a>
                    <a href="https://facebook.com" target="_blank" rel="noopener">Facebook</a>
                    <a href="https://linkedin.com" target="_blank" rel="noopener">LinkedIn</a>
                </div>
            </div>

            <div class="rsv-footer-col">
                <h4>{{ t('footer.contact') }}</h4>
                <a href="mailto:info@reservahores.com">
                    <Mail />
                    <span>info@reservahores.com</span>
                </a>
                <a href="tel:+34931234567">
                    <Phone />
                    <span>+34 931 234 567</span>
                </a>
                <span class="rsv-footer-static">
                    <MapPin />
                    <span>Carrer Exemple 123, Barcelona</span>
                </span>
            </div>

            <div class="rsv-footer-col">
                <h4>{{ t('footer.hours') }}</h4>
                <span class="rsv-footer-static">
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
