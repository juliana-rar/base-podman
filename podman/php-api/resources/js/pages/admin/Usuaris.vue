<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { useI18n } from '@/lib/i18n';
import '../../../css/reserva/admin.css';

const { t, localeTag } = useI18n();

interface Role {
    id: number;
    name: string;
    screens: string[];
}

interface UserRow {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    is_admin: boolean;
    role_id: number | null;
    role_name: string | null;
    created_at: string;
}

const props = defineProps<{
    users: UserRow[];
    roles: Role[];
    screens: string[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Usuaris', href: '/admin/usuaris' }],
    },
});

// Cada clau de pantalla es mostra amb l'etiqueta del menú corresponent.
const SCREEN_LABELS: Record<string, string> = {
    hores: 'nav.hores',
    informacio: 'nav.horaris',
    serveis: 'nav.serveis',
    stock: 'nav.stock',
    empleats: 'nav.empleats',
    posts: 'nav.posts',
    imatges: 'nav.imatges',
    reserves: 'nav.historial',
    reviews: 'nav.reviews',
    cancellacions: 'nav.cancellacions',
    xat: 'nav.xat',
};

function screenLabel(key: string): string {
    return t(SCREEN_LABELS[key] ?? key);
}

function fmtDate(iso: string): string {
    return new Date(iso).toLocaleDateString(localeTag(), { day: 'numeric', month: 'short', year: 'numeric' });
}

function initials(name: string): string {
    return (
        name
            .split(/\s+/)
            .filter(Boolean)
            .slice(0, 2)
            .map((w) => w[0]?.toUpperCase() ?? '')
            .join('') || '?'
    );
}

function toggleScreen(list: string[], key: string): void {
    const i = list.indexOf(key);
    if (i === -1) {
        list.push(key);
    } else {
        list.splice(i, 1);
    }
}

// =========================================================================
//  Rols
// =========================================================================
const roleForm = useForm<{ name: string; screens: string[] }>({ name: '', screens: [] });

function createRole(): void {
    roleForm.post('/admin/rols', {
        preserveScroll: true,
        onSuccess: () => roleForm.reset(),
    });
}

const editRoleId = ref<number | null>(null);
const editRoleForm = useForm<{ name: string; screens: string[] }>({ name: '', screens: [] });

function startEditRole(role: Role): void {
    editRoleId.value = role.id;
    editRoleForm.clearErrors();
    editRoleForm.name = role.name;
    editRoleForm.screens = [...role.screens];
}

function saveRole(): void {
    if (editRoleId.value === null) return;
    editRoleForm.put(`/admin/rols/${editRoleId.value}`, {
        preserveScroll: true,
        onSuccess: () => (editRoleId.value = null),
    });
}

function deleteRole(id: number): void {
    router.delete(`/admin/rols/${id}`, { preserveScroll: true });
}

// =========================================================================
//  Usuaris
// =========================================================================
const search = ref('');

const filteredUsers = computed(() => {
    const q = search.value.trim().toLowerCase();
    if (!q) return props.users;
    return props.users.filter(
        (u) => u.name.toLowerCase().includes(q) || u.email.toLowerCase().includes(q),
    );
});

// Estat editable per usuari (admin + rol de personal), indexat per id.
const draft = ref<Record<number, { is_admin: boolean; role_id: number | null }>>({});

// Sembra l'esborrany amb els valors actuals (i per a usuaris nous que arribin).
watch(
    () => props.users,
    (users) => {
        for (const u of users) {
            if (!draft.value[u.id]) {
                draft.value[u.id] = { is_admin: u.is_admin, role_id: u.role_id };
            }
        }
    },
    { immediate: true },
);

function draftFor(u: UserRow): { is_admin: boolean; role_id: number | null } {
    return draft.value[u.id];
}

// Indica si l'usuari té canvis pendents respecte a la BD (per activar "Desar").
function isDirty(u: UserRow): boolean {
    const d = draft.value[u.id];
    if (!d) return false;
    const roleId = d.is_admin ? null : d.role_id;
    return d.is_admin !== u.is_admin || roleId !== u.role_id;
}

const savingId = ref<number | null>(null);

function saveUser(u: UserRow): void {
    const d = draftFor(u);
    savingId.value = u.id;
    router.put(
        `/admin/usuaris/${u.id}`,
        { is_admin: d.is_admin, role_id: d.is_admin ? null : d.role_id },
        {
            preserveScroll: true,
            onFinish: () => (savingId.value = null),
        },
    );
}

function deleteUser(id: number): void {
    router.delete(`/admin/usuaris/${id}`, { preserveScroll: true });
}
</script>

<template>
    <Head :title="t('usr.title')" />

    <div id="rsv-usuaris">
        <header>
            <h1>{{ t('usr.title') }}</h1>
            <p>{{ t('usr.subtitle') }}</p>
        </header>

        <!-- ===================== Rols ===================== -->
        <section>
            <h2>{{ t('usr.rolesTitle') }}</h2>

            <div class="usr-rolebar">
                <input
                    v-model="roleForm.name"
                    type="text"
                    maxlength="50"
                    class="usr-roleinput"
                    :placeholder="t('usr.roleNamePh')"
                    @keydown.enter.prevent="createRole"
                />
                <div>
                    <span class="usr-fieldlabel">{{ t('usr.screens') }}</span>
                    <div class="usr-chips">
                        <button
                            v-for="s in screens"
                            :key="s"
                            type="button"
                            class="usr-chip"
                            :class="{ 'is-on': roleForm.screens.includes(s) }"
                            @click="toggleScreen(roleForm.screens, s)"
                        >
                            {{ screenLabel(s) }}
                        </button>
                    </div>
                </div>
                <div>
                    <button
                        type="button"
                        class="usr-btn usr-btn-primary"
                        :disabled="roleForm.processing || !roleForm.name.trim()"
                        @click="createRole"
                    >
                        ＋ {{ t('usr.addRole') }}
                    </button>
                </div>
            </div>
            <p v-if="roleForm.errors.name" class="rsv-error">{{ roleForm.errors.name }}</p>

            <div v-if="roles.length" class="usr-rolegrid">
                <div v-for="role in roles" :key="role.id" class="usr-rolecard">
                    <template v-if="editRoleId !== role.id">
                        <div class="usr-rolecard-head">
                            <span class="usr-rolename">{{ role.name }}</span>
                            <div class="usr-rolecard-actions">
                                <button type="button" class="usr-iconbtn" :title="t('usr.edit')" @click="startEditRole(role)">✎</button>
                                <button type="button" class="usr-iconbtn usr-iconbtn-del" :title="t('usr.delete')" @click="deleteRole(role.id)">×</button>
                            </div>
                        </div>
                        <div class="usr-chips">
                            <span v-for="s in role.screens" :key="s" class="usr-chip is-static">{{ screenLabel(s) }}</span>
                            <span v-if="!role.screens.length" class="usr-muted">{{ t('usr.noScreens') }}</span>
                        </div>
                    </template>
                    <template v-else>
                        <input v-model="editRoleForm.name" type="text" maxlength="50" class="usr-roleinput" />
                        <div class="usr-chips">
                            <button
                                v-for="s in screens"
                                :key="s"
                                type="button"
                                class="usr-chip"
                                :class="{ 'is-on': editRoleForm.screens.includes(s) }"
                                @click="toggleScreen(editRoleForm.screens, s)"
                            >
                                {{ screenLabel(s) }}
                            </button>
                        </div>
                        <div class="usr-editactions">
                            <button type="button" class="usr-btn usr-btn-primary" :disabled="editRoleForm.processing" @click="saveRole">{{ t('usr.save') }}</button>
                            <button type="button" class="usr-btn usr-btn-ghost" @click="editRoleId = null">{{ t('usr.cancel') }}</button>
                        </div>
                    </template>
                </div>
            </div>
            <div v-else class="rsv-empty">{{ t('usr.rolesEmpty') }}</div>
        </section>

        <!-- ===================== Usuaris ===================== -->
        <section>
            <h2>{{ t('usr.usersTitle') }}</h2>

            <div class="usr-toolbar">
                <div class="usr-search">
                    <span class="usr-search-icon">🔍</span>
                    <input v-model="search" type="search" :placeholder="t('usr.searchPh')" />
                </div>
                <span class="usr-count">{{ filteredUsers.length }} / {{ users.length }}</span>
            </div>

            <div v-if="filteredUsers.length" class="usr-tablecard">
                <table class="usr-table">
                    <thead>
                        <tr>
                            <th>{{ t('usr.colUser') }}</th>
                            <th>{{ t('usr.colPhone') }}</th>
                            <th>{{ t('usr.admin') }}</th>
                            <th>{{ t('usr.staffRole') }}</th>
                            <th>{{ t('usr.colCreated') }}</th>
                            <th class="usr-th-actions">{{ t('usr.colActions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="u in filteredUsers" :key="u.id" :class="{ 'is-dirty': isDirty(u) }">
                            <td>
                                <div class="usr-userc">
                                    <span class="usr-avatar" :class="{ 'is-admin': draftFor(u).is_admin }">{{ initials(u.name) }}</span>
                                    <span class="usr-userc-text">
                                        <span class="usr-userc-name">
                                            {{ u.name }}
                                            <span v-if="draftFor(u).is_admin" class="usr-badge">{{ t('usr.admin') }}</span>
                                        </span>
                                        <span class="usr-userc-email">{{ u.email }}</span>
                                    </span>
                                </div>
                            </td>
                            <td class="usr-date">{{ u.phone ?? '—' }}</td>
                            <td>
                                <label class="usr-switch">
                                    <input v-model="draftFor(u).is_admin" type="checkbox" />
                                    <span class="usr-switch-track"></span>
                                </label>
                            </td>
                            <td>
                                <select v-model="draftFor(u).role_id" class="usr-select" :disabled="draftFor(u).is_admin">
                                    <option :value="null">{{ draftFor(u).is_admin ? t('usr.adminAll') : t('usr.noRole') }}</option>
                                    <option v-for="r in roles" :key="r.id" :value="r.id">{{ r.name }}</option>
                                </select>
                            </td>
                            <td class="usr-date">{{ fmtDate(u.created_at) }}</td>
                            <td>
                                <div class="usr-actions">
                                    <button
                                        type="button"
                                        class="usr-btn usr-btn-primary"
                                        :disabled="savingId === u.id || !isDirty(u)"
                                        @click="saveUser(u)"
                                    >
                                        {{ t('usr.save') }}
                                    </button>
                                    <button type="button" class="usr-btn usr-btn-ghost" @click="deleteUser(u.id)">{{ t('usr.delete') }}</button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div v-else class="rsv-empty">{{ t('usr.empty') }}</div>
        </section>
    </div>
</template>
