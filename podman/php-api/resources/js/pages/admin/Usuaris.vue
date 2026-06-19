<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
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

function draftFor(u: UserRow): { is_admin: boolean; role_id: number | null } {
    if (!draft.value[u.id]) {
        draft.value[u.id] = { is_admin: u.is_admin, role_id: u.role_id };
    }
    return draft.value[u.id];
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

            <div class="rsv-role-new">
                <input
                    v-model="roleForm.name"
                    type="text"
                    maxlength="50"
                    :placeholder="t('usr.roleNamePh')"
                    @keydown.enter.prevent="createRole"
                />
                <div class="rsv-role-screens">
                    <span class="rsv-emp-assign-label">{{ t('usr.screens') }}</span>
                    <div class="rsv-role-checks">
                        <label v-for="s in screens" :key="s" class="rsv-role-check">
                            <input
                                type="checkbox"
                                :checked="roleForm.screens.includes(s)"
                                @change="toggleScreen(roleForm.screens, s)"
                            />
                            <span>{{ screenLabel(s) }}</span>
                        </label>
                    </div>
                </div>
                <button type="button" class="rsv-edit" :disabled="roleForm.processing" @click="createRole">
                    {{ t('usr.addRole') }}
                </button>
            </div>
            <p v-if="roleForm.errors.name" class="rsv-error">{{ roleForm.errors.name }}</p>

            <div v-if="roles.length" class="rsv-emp-rows">
                <div v-for="role in roles" :key="role.id" class="rsv-emp-row">
                    <template v-if="editRoleId !== role.id">
                        <div class="rsv-emp-info">
                            <span class="rsv-emp-name">{{ role.name }}</span>
                            <span class="rsv-emp-tags">
                                <template v-if="role.screens.length">
                                    <span v-for="s in role.screens" :key="s" class="rsv-emp-tag">{{ screenLabel(s) }}</span>
                                </template>
                                <span v-else class="rsv-emp-none">{{ t('usr.noScreens') }}</span>
                            </span>
                        </div>
                        <button type="button" class="rsv-edit" @click="startEditRole(role)">{{ t('usr.edit') }}</button>
                        <button type="button" class="rsv-del" @click="deleteRole(role.id)">{{ t('usr.delete') }}</button>
                    </template>
                    <template v-else>
                        <div class="rsv-role-edit">
                            <input v-model="editRoleForm.name" type="text" maxlength="50" />
                            <div class="rsv-role-checks">
                                <label v-for="s in screens" :key="s" class="rsv-role-check">
                                    <input
                                        type="checkbox"
                                        :checked="editRoleForm.screens.includes(s)"
                                        @change="toggleScreen(editRoleForm.screens, s)"
                                    />
                                    <span>{{ screenLabel(s) }}</span>
                                </label>
                            </div>
                            <div class="rsv-emp-editactions">
                                <button type="button" class="rsv-edit" :disabled="editRoleForm.processing" @click="saveRole">{{ t('usr.save') }}</button>
                                <button type="button" class="rsv-del" @click="editRoleId = null">{{ t('usr.cancel') }}</button>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
            <div v-else class="rsv-empty">{{ t('usr.rolesEmpty') }}</div>
        </section>

        <!-- ===================== Usuaris ===================== -->
        <section>
            <h2>{{ t('usr.usersTitle') }}</h2>

            <div class="rsv-toolbar">
                <input v-model="search" type="search" :placeholder="t('usr.searchPh')" />
                <span class="rsv-count">{{ filteredUsers.length }} {{ t('adm.of') }} {{ users.length }}</span>
            </div>

            <div v-if="filteredUsers.length" class="rsv-tablewrap">
                <table>
                    <thead>
                        <tr>
                            <th>{{ t('usr.colUser') }}</th>
                            <th>{{ t('usr.colEmail') }}</th>
                            <th>{{ t('usr.colPhone') }}</th>
                            <th>{{ t('usr.admin') }}</th>
                            <th>{{ t('usr.staffRole') }}</th>
                            <th>{{ t('usr.colCreated') }}</th>
                            <th>{{ t('usr.colActions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="u in filteredUsers" :key="u.id">
                            <td>{{ u.name }}</td>
                            <td>{{ u.email }}</td>
                            <td>{{ u.phone ?? '—' }}</td>
                            <td>
                                <input type="checkbox" v-model="draftFor(u).is_admin" />
                            </td>
                            <td>
                                <select v-model="draftFor(u).role_id" :disabled="draftFor(u).is_admin">
                                    <option :value="null">{{ draftFor(u).is_admin ? t('usr.adminAll') : t('usr.noRole') }}</option>
                                    <option v-for="r in roles" :key="r.id" :value="r.id">{{ r.name }}</option>
                                </select>
                            </td>
                            <td>{{ fmtDate(u.created_at) }}</td>
                            <td class="rsv-usr-actions">
                                <button type="button" class="rsv-edit" :disabled="savingId === u.id" @click="saveUser(u)">{{ t('usr.save') }}</button>
                                <button type="button" class="rsv-del" @click="deleteUser(u.id)">{{ t('usr.delete') }}</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div v-else class="rsv-empty">{{ t('usr.empty') }}</div>
        </section>
    </div>
</template>
