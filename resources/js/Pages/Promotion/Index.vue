<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    years: Array,
    activeYear: Object,
    sourceYear: Object,
    sourceClasses: Array,
    lulusCount: Number,
    canPromote: Boolean,
    nextYearPreview: Object,
});

const yearForm = useForm({ name: '' });

const prepare = () => {
    yearForm.post(route('promotion.prepare-year'), { preserveScroll: true });
};

const promote = () => {
    if (confirm('Promosikan semua siswa ke tahun ajaran aktif? Siswa kelas tertinggi akan diluluskan.')) {
        router.post(route('promotion.promote'), {}, { preserveScroll: true });
    }
};

const totalStudents = computed(() =>
    props.sourceClasses.reduce((sum, c) => sum + (c.students_count ?? 0), 0)
);
</script>

<template>
    <Head title="Kenaikan Kelas" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold text-gray-800">Kenaikan Kelas</h2>
        </template>

        <div class="mx-auto max-w-4xl space-y-6 px-4 sm:px-6 lg:px-8">
            <!-- Status tahun ajaran -->
            <div class="grid gap-4 rounded-lg bg-white p-6 shadow sm:grid-cols-2">
                <div>
                    <p class="text-xs font-medium uppercase text-gray-500">Tahun Ajaran Aktif (tujuan)</p>
                    <p class="mt-1 text-sm font-medium text-gray-900">
                        {{ activeYear ? `${activeYear.name} (${activeYear.start_date} s.d. ${activeYear.end_date})` : 'Belum ada' }}
                    </p>
                </div>
                <div>
                    <p class="text-xs font-medium uppercase text-gray-500">Tahun Ajaran Sumber (closed)</p>
                    <p class="mt-1 text-sm font-medium text-gray-900">
                        {{ sourceYear ? `${sourceYear.name} (${sourceYear.start_date} s.d. ${sourceYear.end_date})` : 'Belum ada' }}
                    </p>
                </div>
            </div>

            <!-- Siapkan tahun ajaran baru -->
            <form class="space-y-4 rounded-lg bg-white p-6 shadow" @submit.prevent="prepare">
                <h3 class="text-sm font-semibold text-gray-800">1. Siapkan Tahun Ajaran Baru</h3>
                <p class="text-xs text-gray-500">
                    Membuat tahun ajaran baru (otomatis aktif) dan menyalin struktur kelas dari tahun aktif —
                    kelas tujuan sudah digeser satu tingkat (X → XI, XI → XII). Tahun sebelumnya ditutup.
                </p>
                <div>
                    <InputLabel for="year_name" :value="`Nama (kosongkan untuk otomatis: ${nextYearPreview?.name ?? '-'})`" />
                    <TextInput id="year_name" v-model="yearForm.name" class="mt-1 w-full" placeholder="cth. 2027/2028" />
                    <InputError :message="yearForm.errors.name" class="mt-1" />
                </div>
                <p class="text-sm text-gray-600">
                    Periode otomatis:
                    <span class="font-medium">
                        {{ nextYearPreview ? `${nextYearPreview.start_date} s.d. ${nextYearPreview.end_date}` : '-' }}
                    </span>
                    (10 Juli – 9 Juni)
                </p>
                <div class="flex justify-end">
                    <PrimaryButton :disabled="yearForm.processing">Buat Tahun Ajaran Baru</PrimaryButton>
                </div>
            </form>

            <!-- Promosi -->
            <div class="space-y-4 rounded-lg bg-white p-6 shadow">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-semibold text-gray-800">2. Jalankan Promosi</h3>
                        <p class="mt-1 text-xs text-gray-500">
                            Memindahkan {{ totalStudents }} siswa dari {{ sourceYear?.name ?? '-' }} ke kelas setara di
                            {{ activeYear?.name ?? '-' }}. Siswa XII → lulus (sudah {{ lulusCount }}).
                        </p>
                    </div>
                    <SecondaryButton type="button" :disabled="!canPromote" @click="promote">
                        Naikkan Semua Siswa
                    </SecondaryButton>
                </div>
                <p v-if="!canPromote" class="text-xs text-amber-600">
                    Promosi butuh tahun ajaran aktif baru DAN tahun ajaran closed sebelumnya.
                </p>

                <table class="min-w-full divide-y divide-gray-100">
                    <thead>
                        <tr>
                            <th class="py-2 text-left text-xs font-medium uppercase text-gray-500">Kelas Sumber</th>
                            <th class="py-2 text-left text-xs font-medium uppercase text-gray-500">Tingkat</th>
                            <th class="py-2 text-right text-xs font-medium uppercase text-gray-500">Siswa</th>
                            <th class="py-2 text-left text-xs font-medium uppercase text-gray-500">Tujuan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <tr v-for="c in sourceClasses" :key="c.id">
                            <td class="py-2 text-sm font-medium text-gray-900">{{ c.name }}</td>
                            <td class="py-2 text-sm text-gray-600">{{ c.grade?.name ?? '-' }}</td>
                            <td class="py-2 text-right text-sm text-gray-600">{{ c.students_count }}</td>
                            <td class="py-2 text-sm text-gray-600">
                                {{ c.grade?.level === 12 ? 'Lulus' : `Kelas ${c.grade?.level + 1} (urutan sama)` }}
                            </td>
                        </tr>
                        <tr v-if="!sourceClasses.length">
                            <td colspan="4" class="py-4 text-center text-sm text-gray-500">Tidak ada kelas di tahun sumber.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
