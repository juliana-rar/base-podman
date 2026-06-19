<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import Calendar from '@/components/Calendar.vue';
import { useI18n } from '@/lib/i18n';
import '../../../css/reserva/admin.css';

const { t, localeTag } = useI18n();

interface Reservation {
    id: number;
    note: string | null;
    created_at: string;
    service_id: number | null;
    service_option_id: number | null;
    employee_id: number | null;
    user: { id: number; name: string; email: string; phone: string | null } | null;
    slot: { id: number; starts_at: string; notes: string | null } | null;
    service: {
        id: number;
        name: string;
        price: string | number;
        vat_rate: string | number;
        category: { id: number; name: string } | null;
    } | null;
    service_option: { id: number; name: string; price: string | number } | null;
    stocks: { id: number; name: string; price: string | number; vat_rate: string | number; pivot: { quantity: number } }[];
}

interface ServiceOption {
    id: number;
    name: string;
    price: string | number;
}

interface Service {
    id: number;
    name: string;
    price: string | number;
    options: ServiceOption[];
}

interface Employee {
    id: number;
    name: string;
}

interface Product {
    id: number;
    name: string;
    price: string | number;
    quantity: number;
}

interface StockCategory {
    id: number;
    name: string;
    products: Product[];
}

const props = defineProps<{
    reservations: Reservation[];
    services: Service[];
    employees: Employee[];
    stockCategories: StockCategory[];
    uncategorizedStock: Product[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Historial', href: '/admin/reserves' }],
    },
});

const pad = (n: number) => String(n).padStart(2, '0');

function dayKeyOf(iso: string): string {
    const d = new Date(iso);
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
}

// Format de data fix: 15/06/2026. 01:17
function dateLabel(iso: string): string {
    const d = new Date(iso);
    return `${pad(d.getDate())}/${pad(d.getMonth() + 1)}/${d.getFullYear()}. ${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

const search = ref('');
const selectedDate = ref('');

// Dies (de creació de la reserva) que tenen alguna reserva, per al calendari.
const reservationDayKeys = computed(() => [
    ...new Set(props.reservations.map((r) => dayKeyOf(r.created_at))),
]);

const filtered = computed(() => {
    const q = search.value.trim().toLowerCase();

    return props.reservations.filter((r) => {
        if (selectedDate.value && dayKeyOf(r.created_at) !== selectedDate.value) {
            return false;
        }
        if (!q) {
            return true;
        }
        const haystack = [
            r.user?.name,
            r.user?.email,
            r.user?.phone,
            dateLabel(r.created_at),
        ]
            .join(' ')
            .toLowerCase();
        return haystack.includes(q);
    });
});

const selectedDateLabel = computed(() =>
    selectedDate.value
        ? new Date(selectedDate.value + 'T00:00:00').toLocaleDateString(localeTag(), {
              day: 'numeric',
              month: 'long',
              year: 'numeric',
          })
        : '',
);

function clearDate(): void {
    selectedDate.value = '';
}

// Paginació: màxim 20 reserves per pàgina.
const PER_PAGE = 20;
const currentPage = ref(1);
const totalPages = computed(() => Math.max(1, Math.ceil(filtered.value.length / PER_PAGE)));
const paged = computed(() =>
    filtered.value.slice((currentPage.value - 1) * PER_PAGE, currentPage.value * PER_PAGE),
);

watch([search, selectedDate], () => {
    currentPage.value = 1;
});
watch(totalPages, () => {
    if (currentPage.value > totalPages.value) {
        currentPage.value = totalPages.value;
    }
});

function goToPage(page: number): void {
    currentPage.value = page;
}

// --- Facturació mensual ---
const now = new Date();
const billingMonth = ref(`${now.getFullYear()}-${pad(now.getMonth() + 1)}`);

// Data que compta per a la facturació: la de la cita (slot) si en té, si no la de creació.
function billingDate(r: Reservation): string {
    return r.slot?.starts_at ?? r.created_at;
}

function monthKeyOf(iso: string): string {
    const d = new Date(iso);
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}`;
}

// Import d'una reserva: el de l'opció si en té (>0), si no el del servei.
function priceOf(r: Reservation): number {
    const opt = r.service_option ? Number(r.service_option.price) : 0;
    if (opt > 0) {
        return opt;
    }
    return r.service ? Number(r.service.price) : 0;
}

// Llista de productes d'una reserva en text ("2× Xampú, 1× Crema").
function productsLabel(r: Reservation): string {
    return r.stocks.map((s) => `${s.pivot.quantity}× ${s.name}`).join(', ');
}

// Categoria del servei de la reserva (buit si el servei no en té o ja no existeix).
function categoryLabel(r: Reservation): string {
    return r.service?.category?.name ?? '';
}

// Nom de l'opció triada del servei (buit si no en té).
function optionLabel(r: Reservation): string {
    return r.service_option?.name ?? '';
}

// Import total dels productes d'una reserva.
function productsTotalOf(r: Reservation): number {
    return r.stocks.reduce((sum, s) => sum + Number(s.price) * s.pivot.quantity, 0);
}

// Tipus d'IVA (%) del servei d'una reserva (per defecte 21 si no consta).
function serviceVatRate(r: Reservation): number {
    return r.service ? Number(r.service.vat_rate) : 21;
}

// Conceptes facturables d'una reserva (el servei + cada producte), cadascun amb el
// seu import (IVA inclòs) i el seu tipus d'IVA. Permet desglossar per tipus.
function conceptsOf(r: Reservation): { rate: number; amount: number }[] {
    const list: { rate: number; amount: number }[] = [];
    const service = priceOf(r);
    if (service > 0) {
        list.push({ rate: serviceVatRate(r), amount: service });
    }
    for (const s of r.stocks) {
        list.push({ rate: Number(s.vat_rate), amount: Number(s.price) * s.pivot.quantity });
    }
    return list;
}

// Base imposable i quota d'IVA d'una reserva, sumant els seus conceptes (que poden
// portar tipus diferents). Els preus inclouen IVA: base = import / (1 + tipus/100).
function fiscalOf(r: Reservation): { base: number; vat: number; total: number } {
    let base = 0;
    let vat = 0;
    let total = 0;
    for (const c of conceptsOf(r)) {
        const conceptBase = c.amount / (1 + c.rate / 100);
        base += conceptBase;
        vat += c.amount - conceptBase;
        total += c.amount;
    }
    return { base, vat, total };
}

const monthReservations = computed(() =>
    props.reservations.filter((r) => monthKeyOf(billingDate(r)) === billingMonth.value),
);

const billingTotal = computed(() => monthReservations.value.reduce((sum, r) => sum + priceOf(r), 0));
const billingCount = computed(() => monthReservations.value.length);
const billingAvg = computed(() => (billingCount.value ? billingTotal.value / billingCount.value : 0));

// Desglossament per servei (import i nombre de reserves).
const billingByService = computed(() => {
    const map = new Map<string, { total: number; count: number }>();
    for (const r of monthReservations.value) {
        const name = r.service?.name ?? t('bill.noService');
        const cur = map.get(name) ?? { total: 0, count: 0 };
        cur.total += priceOf(r);
        cur.count += 1;
        map.set(name, cur);
    }
    return [...map.entries()]
        .map(([name, v]) => ({ name, ...v }))
        .sort((a, b) => b.total - a.total);
});
const billingMax = computed(() => Math.max(1, ...billingByService.value.map((s) => s.total)));

const currencyFmt = computed(() =>
    new Intl.NumberFormat(localeTag(), { style: 'currency', currency: 'EUR' }),
);
function money(n: number): string {
    return currencyFmt.value.format(n);
}

const billingMonthLabel = computed(() =>
    new Date(billingMonth.value + '-01T00:00:00').toLocaleDateString(localeTag(), {
        month: 'long',
        year: 'numeric',
    }),
);

// --- Selector de mes modern (popover amb graella de mesos) ---
const pickerOpen = ref(false);
const monthPickEl = ref<HTMLElement | null>(null);
const selectedYear = computed(() => Number(billingMonth.value.slice(0, 4)));
const selectedMonthIdx = computed(() => Number(billingMonth.value.slice(5, 7)) - 1);
const pickerYear = ref(selectedYear.value);

const monthNames = computed(() =>
    Array.from({ length: 12 }, (_, i) =>
        new Date(2024, i, 1).toLocaleDateString(localeTag(), { month: 'short' }),
    ),
);

function togglePicker(): void {
    if (!pickerOpen.value) {
        pickerYear.value = selectedYear.value;
    }
    pickerOpen.value = !pickerOpen.value;
}

function selectMonth(i: number): void {
    billingMonth.value = `${pickerYear.value}-${pad(i + 1)}`;
    pickerOpen.value = false;
}

function shiftMonth(delta: number): void {
    const d = new Date(selectedYear.value, selectedMonthIdx.value + delta, 1);
    billingMonth.value = `${d.getFullYear()}-${pad(d.getMonth() + 1)}`;
}

function isSelectedMonth(i: number): boolean {
    return pickerYear.value === selectedYear.value && i === selectedMonthIdx.value;
}

function onDocClick(event: MouseEvent): void {
    if (pickerOpen.value && monthPickEl.value && !monthPickEl.value.contains(event.target as Node)) {
        pickerOpen.value = false;
    }
}

onMounted(() => document.addEventListener('click', onDocClick));
onBeforeUnmount(() => document.removeEventListener('click', onDocClick));

// --- Exportació Excel (.xlsx) amb estil de la facturació del mes ---
const page = usePage();
// El nom que es desa a /admin/informacio (site_name), no el config('app.name') estàtic.
const companyName = computed(() => String(page.props.siteName ?? page.props.name ?? 'Empresa'));
// Logo desat a /admin/informacio (URL pública), per estampar-lo a dalt de l'Excel.
const logoUrl = computed(() => (page.props.logoUrl ? String(page.props.logoUrl) : null));

// Dades fiscals de l'empresa (desades a /admin/informacio i compartides globalment).
const fiscal = computed(
    () => (page.props.fiscal ?? {}) as { legalName?: string | null; taxId?: string | null; fiscalAddress?: string | null },
);

// Carrega el logo i el passa a PNG (via canvas) perquè ExcelJS l'accepti sigui quin
// sigui el format original (webp, svg…). Retorna null si no es pot carregar.
async function logoAsPng(url: string): Promise<{ base64: string; width: number; height: number } | null> {
    return new Promise((resolve) => {
        const img = new Image();
        img.crossOrigin = 'anonymous';
        img.onload = () => {
            const canvas = document.createElement('canvas');
            canvas.width = img.naturalWidth;
            canvas.height = img.naturalHeight;
            const ctx = canvas.getContext('2d');
            if (!ctx) {
                resolve(null);
                return;
            }
            ctx.drawImage(img, 0, 0);
            try {
                resolve({ base64: canvas.toDataURL('image/png'), width: img.naturalWidth, height: img.naturalHeight });
            } catch {
                resolve(null);
            }
        };
        img.onerror = () => resolve(null);
        img.src = url;
    });
}

async function exportBillingExcel(): Promise<void> {
    // Carrega la llibreria només en clicar (chunk a banda, no infla la pàgina).
    const ExcelJS = (await import('exceljs')).default;

    const rows = [...monthReservations.value].sort((a, b) =>
        billingDate(a).localeCompare(billingDate(b)),
    );

    // Paleta corporativa (ARGB) i format moneda d'Excel.
    const ACCENT = 'FF4F46E5';
    const ACCENT_SOFT = 'FFEEF0FD';
    const INK = 'FF1F2430';
    const MUTED = 'FF6B7280';
    const ZEBRA = 'FFF6F7FB';
    const LINE = 'FFD1D5DB';
    const WHITE = 'FFFFFFFF';
    const CURRENCY = '#,##0.00" €"';
    const LAST_COL = 10;

    type Fill = { type: 'pattern'; pattern: 'solid'; fgColor: { argb: string } };
    type Border = { style: 'thin'; color: { argb: string } };
    const fillOf = (argb: string): Fill => ({ type: 'pattern', pattern: 'solid', fgColor: { argb } });
    const thin = (argb = LINE): Border => ({ style: 'thin', color: { argb } });
    const box = (argb = LINE) => ({ top: thin(argb), bottom: thin(argb), left: thin(argb), right: thin(argb) });

    const wb = new ExcelJS.Workbook();
    const ws = wb.addWorksheet(t('bill.title'));
    ws.columns = [
        { width: 20 }, { width: 22 }, { width: 26 }, { width: 16 }, { width: 20 },
        { width: 16 }, { width: 30 }, { width: 14 }, { width: 13 }, { width: 15 },
    ];

    let R = 1;

    // Capçalera: el logo i el nom de l'empresa a la MATEIXA línia, sobre la banda d'accent.
    const logo = logoUrl.value ? await logoAsPng(logoUrl.value) : null;

    ws.mergeCells(R, 1, R, LAST_COL);
    const brand = ws.getCell(R, 1);
    brand.value = companyName.value;
    brand.font = { bold: true, size: 18, color: { argb: WHITE } };
    brand.fill = fillOf(ACCENT);

    if (logo) {
        // El logo s'encaixa (contain) dins d'una caixa a l'esquerra, centrat
        // verticalment a la banda; el text comença just després amb sagnat.
        const bandHeightPt = 50;
        const bandHeightPx = bandHeightPt * (4 / 3); // pt → px a 96 dpi
        const scale = Math.min(220 / logo.width, 44 / logo.height, 1);
        const displayWidth = Math.round(logo.width * scale);
        const displayHeight = Math.round(logo.height * scale);

        const leftPad = 16; // px des de l'esquerra de la cel·la
        const gap = 16; // px entre el logo i el text
        const colAWidthPx = 145; // amplada aproximada de la columna A (width 20)
        const topFraction = (bandHeightPx - displayHeight) / 2 / bandHeightPx;

        const imageId = wb.addImage({ base64: logo.base64, extension: 'png' });
        ws.addImage(imageId, {
            tl: { col: leftPad / colAWidthPx, row: R - 1 + topFraction },
            ext: { width: displayWidth, height: displayHeight },
        });

        // 1 nivell de sagnat ≈ 7 px (amplada d'un caràcter de la font normal d'Excel).
        const indent = Math.ceil((leftPad + displayWidth + gap) / 7);
        brand.alignment = { vertical: 'middle', horizontal: 'left', indent };
        ws.getRow(R).height = bandHeightPt;
    } else {
        brand.alignment = { vertical: 'middle', horizontal: 'left', indent: 1 };
        ws.getRow(R).height = 36;
    }
    R++;

    // Línia amb les dades fiscals de l'empresa (raó social · NIF/CIF · adreça fiscal).
    const fiscalParts = [
        fiscal.value.legalName,
        fiscal.value.taxId ? `NIF/CIF: ${fiscal.value.taxId}` : '',
        fiscal.value.fiscalAddress,
    ].filter(Boolean);
    if (fiscalParts.length) {
        ws.mergeCells(R, 1, R, LAST_COL);
        const fiscalCell = ws.getCell(R, 1);
        fiscalCell.value = fiscalParts.join('    ·    ');
        fiscalCell.font = { size: 10, color: { argb: INK } };
        fiscalCell.alignment = { vertical: 'middle', horizontal: 'left', indent: 1 };
        fiscalCell.border = { bottom: thin() };
        ws.getRow(R).height = 18;
        R++;
    }

    ws.mergeCells(R, 1, R, LAST_COL);
    const subtitle = ws.getCell(R, 1);
    subtitle.value = `${t('bill.title')} · ${billingMonthLabel.value}`;
    subtitle.font = { bold: true, size: 11, color: { argb: INK } };
    subtitle.fill = fillOf(ACCENT_SOFT);
    subtitle.alignment = { vertical: 'middle', horizontal: 'left', indent: 1 };
    ws.getRow(R).height = 20;
    R++;

    ws.mergeCells(R, 1, R, LAST_COL);
    const generated = ws.getCell(R, 1);
    generated.value = `${t('bill.generated')} ${dateLabel(new Date().toISOString())}    ·    ${t('bill.taxNote')}`;
    generated.font = { italic: true, size: 9, color: { argb: MUTED } };
    generated.alignment = { horizontal: 'left', indent: 1 };
    R += 2;

    // Desglossament fiscal per tipus d'IVA. Cada concepte (servei o producte) pot
    // portar un tipus diferent; els preus inclouen IVA → base = import / (1 + tipus).
    const round2 = (n: number): number => Math.round(n * 100) / 100;
    const byRate = new Map<number, { base: number; vat: number }>();
    for (const r of rows) {
        for (const c of conceptsOf(r)) {
            const conceptBase = c.amount / (1 + c.rate / 100);
            const cur = byRate.get(c.rate) ?? { base: 0, vat: 0 };
            cur.base += conceptBase;
            cur.vat += c.amount - conceptBase;
            byRate.set(c.rate, cur);
        }
    }
    const rateBreakdown = [...byRate.entries()]
        .map(([rate, v]) => ({ rate, base: round2(v.base), vat: round2(v.vat) }))
        .sort((a, b) => b.rate - a.rate);
    const monthBase = round2(rateBreakdown.reduce((s, b) => s + b.base, 0));
    const monthVat = round2(rateBreakdown.reduce((s, b) => s + b.vat, 0));
    const monthTotal = round2(monthBase + monthVat);
    const avgTicket = billingCount.value ? round2(monthTotal / billingCount.value) : 0;

    // Resum (KPIs) en format etiqueta · valor.
    const summary = [
        { label: t('bill.totalWithTax'), value: monthTotal, fmt: CURRENCY, em: true },
        { label: t('bill.count'), value: billingCount.value, fmt: '0', em: false },
        { label: t('bill.avg'), value: avgTicket, fmt: CURRENCY, em: false },
    ];
    for (const row of summary) {
        const color = row.em ? ACCENT : INK;
        const labelCell = ws.getCell(R, 1);
        labelCell.value = row.label;
        labelCell.font = { bold: true, size: 10, color: { argb: color } };
        labelCell.alignment = { horizontal: 'left', indent: 1 };
        labelCell.border = { bottom: thin() };

        ws.mergeCells(R, 2, R, 3);
        const valueCell = ws.getCell(R, 2);
        valueCell.value = row.value;
        valueCell.numFmt = row.fmt;
        valueCell.font = { bold: true, size: 10, color: { argb: color } };
        valueCell.alignment = { horizontal: 'right' };
        valueCell.border = { bottom: thin() };

        if (row.em) {
            labelCell.fill = fillOf(ACCENT_SOFT);
            valueCell.fill = fillOf(ACCENT_SOFT);
        }
        R++;
    }
    R++;

    // Capçalera de la taula de detall (base, IVA i total per reserva).
    const headers = [
        t('bill.csvDate'), t('bill.csvClient'), t('bill.csvEmail'),
        t('bill.csvCategory'), t('bill.csvService'), t('bill.csvOption'),
        t('bill.csvProducts'), t('bill.csvBase'), t('bill.csvVat'), t('bill.csvTotal'),
    ];
    headers.forEach((h, i) => {
        const c = i + 1;
        const headerCell = ws.getCell(R, c);
        headerCell.value = h;
        headerCell.font = { bold: true, size: 10, color: { argb: WHITE } };
        headerCell.fill = fillOf(ACCENT);
        headerCell.alignment = { horizontal: c >= 8 ? 'right' : 'left', vertical: 'middle' };
        headerCell.border = box(ACCENT);
    });
    ws.getRow(R).height = 22;
    R++;

    // Files de detall (amb ratlla zebra i imports com a nombres amb format moneda).
    rows.forEach((r, i) => {
        const zebra = i % 2 === 1;
        const rowNum = R;
        const put = (c: number, value: string | number, opts: { align?: 'left' | 'right'; numFmt?: string; wrap?: boolean } = {}): void => {
            const detailCell = ws.getCell(rowNum, c);
            detailCell.value = value;
            detailCell.font = { size: 10, color: { argb: INK } };
            detailCell.alignment = { horizontal: opts.align ?? 'left', vertical: 'middle', wrapText: opts.wrap ?? false };
            detailCell.border = box();
            if (zebra) {
                detailCell.fill = fillOf(ZEBRA);
            }
            if (opts.numFmt) {
                detailCell.numFmt = opts.numFmt;
            }
        };
        const f = fiscalOf(r);
        put(1, dateLabel(billingDate(r)));
        put(2, r.user?.name ?? '');
        put(3, r.user?.email ?? '');
        put(4, categoryLabel(r));
        put(5, r.service?.name ?? '');
        put(6, optionLabel(r));
        put(7, productsLabel(r), { wrap: true });
        put(8, round2(f.base), { align: 'right', numFmt: CURRENCY });
        put(9, round2(f.vat), { align: 'right', numFmt: CURRENCY });
        put(10, round2(f.total), { align: 'right', numFmt: CURRENCY });
        R++;
    });

    // Fila de totals (serveis i productes).
    for (let c = 1; c <= LAST_COL; c++) {
        const totalCell = ws.getCell(R, c);
        totalCell.font = { bold: true, size: 10, color: { argb: INK } };
        totalCell.fill = fillOf(ACCENT_SOFT);
        totalCell.alignment = { horizontal: 'left', vertical: 'middle' };
        totalCell.border = { top: thin(ACCENT), bottom: thin(ACCENT) };
    }
    const setTotal = (c: number, value: string | number, numFmt?: string): void => {
        const totalCell = ws.getCell(R, c);
        totalCell.value = value;
        totalCell.alignment = { horizontal: 'right', vertical: 'middle' };
        if (numFmt) {
            totalCell.numFmt = numFmt;
        }
    };
    setTotal(7, t('bill.total'));
    setTotal(8, monthBase, CURRENCY);
    setTotal(9, monthVat, CURRENCY);
    setTotal(10, monthTotal, CURRENCY);
    R += 2;

    // Desglossament d'IVA per tipus (base imposable i quota de cada %).
    ws.mergeCells(R, 8, R, 10);
    const brkHead = ws.getCell(R, 8);
    brkHead.value = t('bill.taxBreakdown');
    brkHead.font = { bold: true, size: 10, color: { argb: WHITE } };
    brkHead.fill = fillOf(ACCENT);
    brkHead.alignment = { horizontal: 'left', vertical: 'middle', indent: 1 };
    ws.getRow(R).height = 20;
    R++;

    const brkCols = [t('bill.csvVatRate'), t('bill.taxBase'), t('bill.vat')];
    brkCols.forEach((h, i) => {
        const cell = ws.getCell(R, i + 8);
        cell.value = h;
        cell.font = { bold: true, size: 9, color: { argb: INK } };
        cell.fill = fillOf(ACCENT_SOFT);
        cell.alignment = { horizontal: i === 0 ? 'left' : 'right', vertical: 'middle' };
        cell.border = box();
    });
    R++;

    for (const b of rateBreakdown) {
        const cells: [number, string | number, 'left' | 'right', boolean][] = [
            [8, `${b.rate}%`, 'left', false],
            [9, b.base, 'right', true],
            [10, b.vat, 'right', true],
        ];
        for (const [c, value, align, money] of cells) {
            const cell = ws.getCell(R, c);
            cell.value = value;
            cell.font = { size: 10, color: { argb: INK } };
            cell.alignment = { horizontal: align, vertical: 'middle' };
            cell.border = box();
            if (money) {
                cell.numFmt = CURRENCY;
            }
        }
        R++;
    }

    // Total general destacat (IVA inclòs).
    for (const c of [8, 9, 10]) {
        ws.getCell(R, c).fill = fillOf(ACCENT);
    }
    ws.mergeCells(R, 8, R, 9);
    const grandLabel = ws.getCell(R, 8);
    grandLabel.value = t('bill.totalWithTax');
    grandLabel.font = { bold: true, size: 11, color: { argb: WHITE } };
    grandLabel.alignment = { horizontal: 'right', vertical: 'middle' };
    const grandValue = ws.getCell(R, 10);
    grandValue.value = monthTotal;
    grandValue.numFmt = CURRENCY;
    grandValue.font = { bold: true, size: 11, color: { argb: WHITE } };
    grandValue.alignment = { horizontal: 'right', vertical: 'middle' };
    ws.getRow(R).height = 22;

    const buffer = await wb.xlsx.writeBuffer();
    const blob = new Blob([buffer], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `facturacio-${billingMonth.value}.xlsx`;
    document.body.appendChild(a);
    a.click();
    a.remove();
    URL.revokeObjectURL(url);
}

// --- Edició d'una reserva (servei, opció, empleat o nota) ---
const editing = ref<Reservation | null>(null);
const formServiceId = ref<number | null>(null);
const formOptionId = ref<number | null>(null);
const formEmployeeId = ref<number | null>(null);
const formNote = ref('');
const saving = ref(false);

// Quantitat triada per article a editar (id -> unitats). Absència o 0 = no inclòs.
const formProducts = ref<Record<number, number>>({});

// Opcions del servei seleccionat al formulari (per al desplegable d'opcions).
const editOptions = computed(() => {
    const svc = props.services.find((s) => s.id === formServiceId.value);
    return svc ? svc.options : [];
});

// Catàleg de productes a mostrar al modal: categories + grup «sense categoria».
const productGroups = computed(() => {
    const groups = props.stockCategories.map((c) => ({ id: c.id, name: c.name, products: c.products }));
    if (props.uncategorizedStock.length) {
        groups.push({ id: -1, name: t('stk.noCategory'), products: props.uncategorizedStock });
    }
    return groups;
});

const hasProducts = computed(() => productGroups.value.some((g) => g.products.length));

function qtyOf(product: Product): number {
    return formProducts.value[product.id] ?? 0;
}

// Màxim editable: l'stock disponible, o la quantitat ja reservada si era més gran.
function maxQtyOf(product: Product): number {
    return Math.max(product.quantity, qtyOf(product));
}

function setQty(product: Product, value: number): void {
    const clamped = Math.max(0, Math.min(value, maxQtyOf(product)));
    if (clamped === 0) {
        delete formProducts.value[product.id];
    } else {
        formProducts.value[product.id] = clamped;
    }
}

// Productes triats per al desat (quantitat > 0).
const editProducts = computed(() =>
    productGroups.value
        .flatMap((g) => g.products)
        .filter((p) => qtyOf(p) > 0)
        .map((p) => ({ stock_id: p.id, quantity: qtyOf(p) })),
);

// Preu del servei escollit al formulari: el de l'opció si en té (>0), si no el del servei.
const editServicePrice = computed(() => {
    const option = editOptions.value.find((o) => o.id === formOptionId.value);
    const optionPrice = option ? Number(option.price) : 0;
    if (optionPrice > 0) {
        return optionPrice;
    }
    const service = props.services.find((s) => s.id === formServiceId.value);
    return service ? Number(service.price) : 0;
});

// Import total dels productes triats al formulari.
const editProductsTotal = computed(() =>
    productGroups.value
        .flatMap((g) => g.products)
        .reduce((sum, p) => sum + Number(p.price) * qtyOf(p), 0),
);

function openEdit(r: Reservation): void {
    editing.value = r;
    formServiceId.value = r.service_id;
    formOptionId.value = r.service_option_id;
    formEmployeeId.value = r.employee_id;
    formNote.value = r.note ?? '';
    formProducts.value = {};
    for (const s of r.stocks) {
        formProducts.value[s.id] = s.pivot.quantity;
    }
}

function closeEdit(): void {
    editing.value = null;
}

// En canviar de servei, si l'opció actual no pertany al nou servei, es reinicia.
watch(formServiceId, () => {
    if (!editOptions.value.some((o) => o.id === formOptionId.value)) {
        formOptionId.value = null;
    }
});

const canSave = computed(
    () => formServiceId.value !== null && formEmployeeId.value !== null && formNote.value.trim() !== '',
);

function saveEdit(): void {
    if (editing.value === null || !canSave.value) {
        return;
    }
    saving.value = true;
    router.put(
        `/admin/reserves/${editing.value.id}`,
        {
            service_id: formServiceId.value,
            service_option_id: formOptionId.value,
            employee_id: formEmployeeId.value,
            note: formNote.value,
            products: editProducts.value,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                editing.value = null;
            },
            onFinish: () => {
                saving.value = false;
            },
        },
    );
}

function onEditKey(event: KeyboardEvent): void {
    if (editing.value !== null && event.key === 'Escape') {
        closeEdit();
    }
}

onMounted(() => window.addEventListener('keydown', onEditKey));
onBeforeUnmount(() => window.removeEventListener('keydown', onEditKey));
</script>

<template>
    <Head :title="t('hist.title')" />

    <div id="rsv-history">
        <header>
            <h1>{{ t('hist.title') }}</h1>
            <p>{{ t('hist.subtitle') }}</p>
        </header>

        <section class="rsv-billing">
            <div class="rsv-billing-head">
                <h2>{{ t('bill.title') }}</h2>
                <div class="rsv-billing-controls">
                    <div ref="monthPickEl" class="rsv-monthpick">
                        <button type="button" class="rsv-month-arrow" aria-label="‹" @click="shiftMonth(-1)">‹</button>
                        <button type="button" class="rsv-month-trigger" :aria-expanded="pickerOpen" @click="togglePicker">
                            {{ billingMonthLabel }}
                        </button>
                        <button type="button" class="rsv-month-arrow" aria-label="›" @click="shiftMonth(1)">›</button>

                        <div v-if="pickerOpen" class="rsv-month-pop">
                            <div class="rsv-month-pop-head">
                                <button type="button" aria-label="‹" @click="pickerYear--">‹</button>
                                <span>{{ pickerYear }}</span>
                                <button type="button" aria-label="›" @click="pickerYear++">›</button>
                            </div>
                            <div class="rsv-month-grid">
                                <button
                                    v-for="(name, i) in monthNames"
                                    :key="i"
                                    type="button"
                                    :class="{ on: isSelectedMonth(i) }"
                                    @click="selectMonth(i)"
                                >{{ name }}</button>
                            </div>
                        </div>
                    </div>

                    <button type="button" class="rsv-billing-export" :disabled="!billingCount" @click="exportBillingExcel">
                        {{ t('bill.export') }}
                    </button>
                </div>
            </div>

            <div class="rsv-billing-stats">
                <div class="rsv-bill-stat is-primary">
                    <span class="rsv-bill-num">{{ money(billingTotal) }}</span>
                    <span class="rsv-bill-label">{{ t('bill.total') }}</span>
                </div>
                <div class="rsv-bill-stat">
                    <span class="rsv-bill-num">{{ billingCount }}</span>
                    <span class="rsv-bill-label">{{ t('bill.count') }}</span>
                </div>
                <div class="rsv-bill-stat">
                    <span class="rsv-bill-num">{{ money(billingAvg) }}</span>
                    <span class="rsv-bill-label">{{ t('bill.avg') }}</span>
                </div>
            </div>

            <div v-if="billingByService.length" class="rsv-bill-bars">
                <h3>{{ t('bill.byService') }}</h3>
                <div v-for="s in billingByService" :key="s.name" class="rsv-bill-bar">
                    <span class="rsv-bill-bar-label">{{ s.name }}</span>
                    <div class="rsv-bill-bar-track">
                        <div class="rsv-bill-bar-fill" :style="{ width: Math.round((s.total / billingMax) * 100) + '%' }"></div>
                    </div>
                    <span class="rsv-bill-bar-val">{{ money(s.total) }} · {{ s.count }}</span>
                </div>
            </div>
            <p v-else class="rsv-bill-empty">{{ t('bill.empty') }} {{ billingMonthLabel }}.</p>
        </section>

        <section class="rsv-history-grid">
            <aside>
                <h2>{{ t('adm.filterByDate') }}</h2>
                <Calendar v-model="selectedDate" :highlight-dates="reservationDayKeys" />
                <button v-if="selectedDate" type="button" class="rsv-clear" @click="clearDate">
                    {{ t('adm.allDates') }}
                </button>
            </aside>

            <div>
                <div class="rsv-toolbar">
                    <input v-model="search" type="search" :placeholder="t('hist.searchPh')" />
                    <span class="rsv-count">{{ filtered.length }} {{ t('adm.of') }} {{ reservations.length }}</span>
                </div>

                <div v-if="selectedDate" class="rsv-chip">
                    {{ t('hist.madeOn') }} {{ selectedDateLabel }}
                    <button type="button" aria-label="×" @click="clearDate">×</button>
                </div>

                <div v-if="filtered.length" class="rsv-tablewrap">
                    <table>
                        <thead>
                            <tr>
                                <th class="rsv-when-cell">{{ t('hist.colMadeOn') }}</th>
                                <th>{{ t('adm.user') }}</th>
                                <th>{{ t('adm.email') }}</th>
                                <th>{{ t('adm.phone') }}</th>
                                <th class="rsv-service-cell">{{ t('adm.service') }}</th>
                                <th>{{ t('hist.colProducts') }}</th>
                                <th class="rsv-note-cell">{{ t('hist.colReason') }}</th>
                                <th class="rsv-actions-cell">{{ t('hist.colActions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="r in paged" :key="r.id">
                                <td class="rsv-when-cell">{{ dateLabel(r.created_at) }}</td>
                                <td>{{ r.user?.name ?? t('adm.userDeleted') }}</td>
                                <td>{{ r.user?.email ?? '—' }}</td>
                                <td>{{ r.user?.phone ?? '—' }}</td>
                                <td class="rsv-service-cell">{{ r.service?.name ?? '—' }}</td>
                                <td>
                                    <template v-if="r.stocks.length">
                                        <span v-for="s in r.stocks" :key="s.id" class="rsv-prod-tag">
                                            {{ s.pivot.quantity }}× {{ s.name }}
                                        </span>
                                    </template>
                                    <template v-else>—</template>
                                </td>
                                <td class="rsv-note-cell">{{ r.note ?? '—' }}</td>
                                <td class="rsv-actions-cell">
                                    <button type="button" class="rsv-edit" @click="openEdit(r)">{{ t('hist.edit') }}</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <div v-if="totalPages > 1" class="rsv-pagination">
                        <button type="button" :disabled="currentPage === 1" @click="goToPage(currentPage - 1)">‹</button>
                        <button
                            v-for="page in totalPages"
                            :key="page"
                            type="button"
                            :class="{ 'is-active': page === currentPage }"
                            @click="goToPage(page)"
                        >
                            {{ page }}
                        </button>
                        <button type="button" :disabled="currentPage === totalPages" @click="goToPage(currentPage + 1)">›</button>
                    </div>
                </div>
                <div v-else class="rsv-empty">{{ t('hist.empty') }}</div>
            </div>
        </section>

        <Teleport to="body">
            <transition name="rsv-fade">
                <div v-if="editing !== null" class="rsv-edit-overlay" @click.self="closeEdit">
                    <div class="rsv-edit-modal">
                        <h3>{{ t('hist.editTitle') }}</h3>

                        <label for="edit-service">{{ t('adm.service') }} *</label>
                        <select id="edit-service" v-model="formServiceId">
                            <option v-for="s in services" :key="s.id" :value="s.id">{{ s.name }}</option>
                        </select>

                        <label v-if="editOptions.length" for="edit-option">{{ t('hist.option') }}</label>
                        <select v-if="editOptions.length" id="edit-option" v-model="formOptionId">
                            <option :value="null">{{ t('hist.noOption') }}</option>
                            <option v-for="o in editOptions" :key="o.id" :value="o.id">{{ o.name }}</option>
                        </select>

                        <label for="edit-employee">{{ t('hist.employee') }} *</label>
                        <select id="edit-employee" v-model="formEmployeeId">
                            <option v-for="e in employees" :key="e.id" :value="e.id">{{ e.name }}</option>
                        </select>

                        <label for="edit-note">{{ t('hist.colReason') }} *</label>
                        <textarea id="edit-note" v-model="formNote" maxlength="1000" :placeholder="t('hist.notePh')"></textarea>

                        <template v-if="hasProducts">
                            <label>{{ t('hist.colProducts') }}</label>
                            <div class="rsv-edit-products">
                                <div v-for="group in productGroups" :key="group.id" class="rsv-edit-prodgroup">
                                    <span class="rsv-edit-prodcat">{{ group.name }}</span>
                                    <div
                                        v-for="product in group.products"
                                        :key="product.id"
                                        class="rsv-edit-proditem"
                                        :class="{ 'is-on': qtyOf(product) > 0 }"
                                    >
                                        <span class="rsv-edit-prodname">{{ product.name }}</span>
                                        <div class="rsv-edit-prodqty">
                                            <button type="button" aria-label="−" :disabled="qtyOf(product) === 0" @click="setQty(product, qtyOf(product) - 1)">−</button>
                                            <span>{{ qtyOf(product) }}</span>
                                            <button type="button" aria-label="+" :disabled="qtyOf(product) >= maxQtyOf(product)" @click="setQty(product, qtyOf(product) + 1)">+</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </template>

                        <div class="rsv-edit-money">
                            <div class="rsv-edit-money-row">
                                <span>{{ t('res.serviceType') }}</span>
                                <span>{{ money(editServicePrice) }}</span>
                            </div>
                            <div v-if="editProductsTotal > 0" class="rsv-edit-money-row">
                                <span>{{ t('hist.colProducts') }}</span>
                                <span>{{ money(editProductsTotal) }}</span>
                            </div>
                            <div class="rsv-edit-money-row rsv-edit-money-total">
                                <span>{{ t('res.total') }}</span>
                                <span>{{ money(editServicePrice + editProductsTotal) }}</span>
                            </div>
                        </div>

                        <div class="rsv-edit-actions">
                            <button type="button" class="rsv-edit-back" @click="closeEdit">{{ t('hist.editBack') }}</button>
                            <button type="button" class="rsv-edit-go" :disabled="!canSave || saving" @click="saveEdit">
                                {{ t('hist.editSave') }}
                            </button>
                        </div>
                    </div>
                </div>
            </transition>
        </Teleport>
    </div>
</template>
