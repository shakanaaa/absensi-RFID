<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    cards: Object,
    students: Array,
    filters: Object,
});
const page = usePage();
const canWrite = computed(() => ['admin', 'petugas_piket'].includes(page.props.auth.user?.role));

const statusLabel = {
    active: 'Aktif', unassigned: 'Belum Ditautkan', suspended: 'Ditangguhkan',
    lost: 'Hilang', damaged: 'Rusak', replaced: 'Diganti', revoked: 'Dicabut', archived: 'Arsip',
};
const statusOptions = Object.keys(statusLabel);

const search = ref(props.filters?.status ?? '');
const q = ref(props.filters?.q ?? '');
const showForm = ref(false);
const assignTarget = ref(null); // card yang sedang dipilih untuk assign

const form = useForm({ uid_text: '', card_type: 'em125k', issued_at: '' });
const assignForm = useForm({ student_id: '', reason: '' });
const unassignForm = useForm({ reason: '' });
const statusForm = useForm({ status: '' });

const applyFilters = () => {
    router.get(route('master.cards.index'), { status: search.value || undefined, q: q.value || undefined }, { preserveState: true });
};

const register = () => {
    form.post(route('master.cards.store'), { onSuccess: () => { form.reset(); showForm.value = false; } });
};

const openAssign = (card) => {
    assignTarget.value = card;
    assignForm.reset();
};

const doAssign = () => {
    assignForm.post(route('master.cards.assign', assignTarget.value.id), {
        preserveScroll: true,
        onSuccess: () => { assignTarget.value = null; },
    });
};

const doUnassign = (assignment) => {
    const reason = prompt('Alasan melepas kartu (opsional):') ?? '';
    unassignForm.reason = reason;
    unassignForm.post(route('master.assignments.unassign', assignment.id), { preserveScroll: true });
};

const changeStatus = (card, status) => {
    statusForm.status = status;
    statusForm.patch(route('master.cards.update', card.id), { preserveScroll: true });
};

const remove = (card) => {
    if (confirm(`Hapus kartu ${card.uid_text}? Hanya kartu tanpa histori yang bisa dihapus.`)) {
        router.delete(route('master.cards.destroy', card.id));
    }
};
</script>

<template>
    <Head title="Kartu RFID" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold text-gray-800">Kartu RFID</h2>
                <div class="flex gap-2">
                    <Link :href="route('master.registrations.index')" class="rounded-md bg-white px-4 py-2 text-sm font-medium text-gray-700 ring-1 ring-gray-300 hover:bg-gray-50">
                        Registrasi Massal
                    </Link>
                    <button
                        v-if="canWrite"
                        class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"
                        @click="showForm = !showForm"
                    >
                        {{ showForm ? 'Batal' : 'Daftarkan Kartu' }}
                    </button>
                </div>
            </div>
        </template>

        <div class="mx-auto max-w-7xl space-y-4 px-4 sm:px-6 lg:px-8">
            <!-- Form kartu baru -->
            <form v-if="showForm" class="grid gap-3 rounded-lg bg-white p-4 shadow sm:grid-cols-4 sm:items-end" @submit.prevent="register">
                <div>
                    <InputLabel for="uid_text" value="UID Kartu" />
                    <TextInput id="uid_text" v-model="form.uid_text" class="mt-1 w-full font-mono" required />
                    <InputError :message="form.errors.uid_text" class="mt-1" />
                </div>
                <div>
                    <InputLabel for="card_type" value="Tipe" />
                    <TextInput id="card_type" v-model="form.card_type" class="mt-1 w-full" />
                </div>
                <div>
                    <InputLabel for="issued_at" value="Tanggal Terbit" />
                    <TextInput id="issued_at" v-model="form.issued_at" type="date" class="mt-1 w-full" />
                </div>
                <PrimaryButton :disabled="form.processing">Simpan</PrimaryButton>
            </form>

            <!-- Filter -->
            <div class="flex gap-2">
                <select v-model="search" class="rounded-md border-gray-300 text-sm shadow-sm" @change="applyFilters">
                    <option value="">Semua status</option>
                    <option v-for="s in statusOptions" :key="s" :value="s">{{ statusLabel[s] }}</option>
                </select>
                <input
                    v-model="q"
                    type="search"
                    placeholder="Cari UID..."
                    class="w-64 rounded-md border-gray-300 text-sm shadow-sm"
                    @keyup.enter="applyFilters"
                />
            </div>

            <!-- Tabel -->
            <div class="overflow-hidden rounded-lg bg-white shadow">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">UID</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Tipe</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Status</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Pemegang</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Sejak</th>
                            <th class="px-4 py-3 text-right text-xs font-medium uppercase text-gray-500">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <tr v-for="card in cards.data" :key="card.id">
                            <td class="px-4 py-3 font-mono text-sm text-gray-900">{{ card.uid_text }}</td>
                            <td class="px-4 py-3 text-sm text-gray-600">{{ card.card_type }}</td>
                            <td class="px-4 py-3 text-sm text-gray-600">{{ statusLabel[card.status] ?? card.status }}</td>
                            <td class="px-4 py-3 text-sm text-gray-600">
                                <template v-if="card.active_assignment">
                                    <Link :href="route('master.students.show', card.active_assignment.student_id)" class="text-indigo-600 hover:underline">
                                        {{ card.active_assignment.student?.name }}
                                    </Link>
                                </template>
                                <span v-else class="text-gray-400">—</span>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-600">
                                {{ card.active_assignment?.assigned_at?.slice(0, 10) ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-right text-sm">
                                <template v-if="canWrite">
                                    <button
                                        v-if="!card.active_assignment && ['active', 'unassigned'].includes(card.status)"
                                        class="text-indigo-600 hover:underline"
                                        @click="openAssign(card)"
                                    >Tautkan</button>
                                    <button
                                        v-if="card.active_assignment"
                                        class="text-amber-600 hover:underline"
                                        @click="doUnassign(card.active_assignment)"
                                    >Lepas</button>
                                    <select
                                        :value="card.status"
                                        class="ml-2 rounded border-gray-300 py-0.5 text-xs"
                                        @change="changeStatus(card, $event.target.value)"
                                    >
                                        <option v-for="s in statusOptions" :key="s" :value="s">{{ statusLabel[s] }}</option>
                                    </select>
                                    <button
                                        v-if="!card.assignments_count"
                                        class="ml-2 text-red-600 hover:underline"
                                        @click="remove(card)"
                                    >Hapus</button>
                                </template>
                            </td>
                        </tr>
                        <tr v-if="!cards.data.length">
                            <td colspan="6" class="px-4 py-8 text-center text-sm text-gray-500">Belum ada kartu terdaftar.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <nav v-if="cards.last_page > 1" class="flex justify-center gap-1" aria-label="Navigasi halaman">
                <Link
                    v-for="link in cards.links"
                    :key="link.label"
                    :href="link.url ?? '#'"
                    class="rounded-md px-3 py-1.5 text-sm"
                    :class="link.active ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 ring-1 ring-gray-200 hover:bg-gray-50'"
                    v-html="link.label"
                />
            </nav>
        </div>

        <!-- Modal assign -->
        <div v-if="assignTarget" class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 p-4" @click.self="assignTarget = null">
            <div class="w-full max-w-md rounded-lg bg-white p-6 shadow-xl">
                <h3 class="text-lg font-semibold text-gray-900">Tautkan Kartu</h3>
                <p class="mt-1 font-mono text-sm text-gray-600">{{ assignTarget.uid_text }}</p>
                <form class="mt-4 space-y-4" @submit.prevent="doAssign">
                    <div>
                        <InputLabel for="assign_student" value="Siswa" />
                        <select
                            id="assign_student"
                            v-model="assignForm.student_id"
                            class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            required
                        >
                            <option value="" disabled>Pilih siswa</option>
                            <option v-for="s in students" :key="s.id" :value="s.id">{{ s.name }}</option>
                        </select>
                        <InputError :message="assignForm.errors.student_id" class="mt-1" />
                    </div>
                    <div>
                        <InputLabel for="assign_reason" value="Alasan (opsional)" />
                        <TextInput id="assign_reason" v-model="assignForm.reason" class="mt-1 w-full" />
                    </div>
                    <InputError :message="assignForm.errors.uid_text" class="mt-1" />
                    <div class="flex justify-end gap-3">
                        <SecondaryButton type="button" @click="assignTarget = null">Batal</SecondaryButton>
                        <PrimaryButton :disabled="assignForm.processing">Tautkan</PrimaryButton>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
