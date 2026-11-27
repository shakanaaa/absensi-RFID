<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    users: Object,
    roles: Array,
    filters: Object,
});
const page = usePage();
const isAdmin = computed(() => page.props.auth.user?.role === 'admin');
const roleFilter = ref(props.filters?.role ?? '');
const roleLabel = Object.fromEntries(props.roles.map((r) => [r.value, r.label]));

const suspend = (id) => {
    if (confirm('Tangguhkan akun ini?')) {
        router.delete(route('users.destroy', id));
    }
};
</script>

<template>
    <Head title="Pengguna" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold text-gray-800">Pengguna</h2>
                <Link
                    v-if="isAdmin"
                    :href="route('users.create')"
                    class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"
                >
                    Tambah Pengguna
                </Link>
            </div>
        </template>

        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="mb-4">
                <select
                    v-model="roleFilter"
                    class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    @change="router.get(route('users.index'), { role: roleFilter || undefined }, { preserveState: true })"
                >
                    <option value="">Semua peran</option>
                    <option v-for="r in roles" :key="r.value" :value="r.value">{{ r.label }}</option>
                </select>
            </div>

            <div class="overflow-hidden rounded-lg bg-white shadow">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Nama</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Email</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Peran</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Status</th>
                            <th class="px-4 py-3 text-right text-xs font-medium uppercase text-gray-500">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <tr v-for="u in users.data" :key="u.id">
                            <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ u.name }}</td>
                            <td class="px-4 py-3 text-sm text-gray-600">{{ u.email }}</td>
                            <td class="px-4 py-3 text-sm text-gray-600">{{ roleLabel[u.role] ?? u.role }}</td>
                            <td class="px-4 py-3 text-sm text-gray-600">{{ u.status }}</td>
                            <td class="px-4 py-3 text-right text-sm">
                                <template v-if="isAdmin">
                                    <Link :href="route('users.edit', u.id)" class="text-indigo-600 hover:underline">Ubah</Link>
                                    <button
                                        v-if="u.status !== 'suspended'"
                                        class="ml-2 text-red-600 hover:underline"
                                        @click="suspend(u.id)"
                                    >
                                        Tangguhkan
                                    </button>
                                </template>
                            </td>
                        </tr>
                        <tr v-if="!users.data.length">
                            <td colspan="5" class="px-4 py-8 text-center text-sm text-gray-500">
                                Tidak ada pengguna.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <nav v-if="users.last_page > 1" class="mt-4 flex justify-center gap-1" aria-label="Navigasi halaman">
                <Link
                    v-for="link in users.links"
                    :key="link.label"
                    :href="link.url ?? '#'"
                    class="rounded-md px-3 py-1.5 text-sm"
                    :class="link.active ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 ring-1 ring-gray-200 hover:bg-gray-50'"
                    v-html="link.label"
                />
            </nav>
        </div>
    </AuthenticatedLayout>
</template>
