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
    settings: Object,
    holidays: Array,
    holidayYear: Number,
    nationalHolidays: Array,
});

const form = useForm({
    check_in_time: props.settings.check_in_time ?? '07:00',
    check_out_time: props.settings.check_out_time ?? '15:00',
    whatsapp_number: props.settings.whatsapp_number ?? '',
    report_send_time: props.settings.report_send_time ?? '',
    weekly_off_days: [...(props.settings.weekly_off_days ?? [6, 7])],
});

const WEEKDAYS = [
    { value: 1, label: 'Sen' },
    { value: 2, label: 'Sel' },
    { value: 3, label: 'Rab' },
    { value: 4, label: 'Kam' },
    { value: 5, label: 'Jum' },
    { value: 6, label: 'Sab' },
    { value: 7, label: 'Min' },
];

const toggleOffDay = (day) => {
    const i = form.weekly_off_days.indexOf(day);
    if (i === -1) {
        form.weekly_off_days.push(day);
    } else {
        form.weekly_off_days.splice(i, 1);
    }
};

const holidayForm = useForm({ holiday_date: '', name: '' });
const generateForm = useForm({ year: props.holidayYear });

const save = () => form.put(route('settings.update'));
const addHoliday = () => {
    holidayForm.post(route('settings.holidays.store'), { onSuccess: () => holidayForm.reset() });
};
const removeHoliday = (id) => {
    if (confirm('Hapus tanggal libur ini?')) {
        router.delete(route('settings.holidays.destroy', id), { preserveScroll: true });
    }
};
const generate = () => {
    generateForm.post(route('settings.holidays.generate'), { preserveScroll: true });
};

const holidayDates = computed(() => new Set(props.holidays.map((h) => h.holiday_date)));
</script>

<template>
    <Head title="Pengaturan" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold text-gray-800">Pengaturan</h2>
        </template>

        <div class="mx-auto max-w-4xl space-y-6 px-4 sm:px-6 lg:px-8">
            <!-- Jam & WhatsApp -->
            <form class="space-y-5 rounded-lg bg-white p-6 shadow" @submit.prevent="save">
                <h3 class="text-sm font-semibold text-gray-800">Jam Absensi &amp; Laporan</h3>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <InputLabel for="check_in_time" value="Jam Masuk (batas terlambat)" />
                        <TextInput id="check_in_time" v-model="form.check_in_time" type="time" class="mt-1 w-full" required />
                        <InputError :message="form.errors.check_in_time" class="mt-1" />
                    </div>
                    <div>
                        <InputLabel for="check_out_time" value="Jam Pulang" />
                        <TextInput id="check_out_time" v-model="form.check_out_time" type="time" class="mt-1 w-full" required />
                        <InputError :message="form.errors.check_out_time" class="mt-1" />
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <InputLabel for="whatsapp_number" value="Nomor WhatsApp Laporan (format 62xxx)" />
                        <TextInput id="whatsapp_number" v-model="form.whatsapp_number" class="mt-1 w-full" placeholder="6281234567890" />
                        <InputError :message="form.errors.whatsapp_number" class="mt-1" />
                        <p class="mt-1 text-xs text-gray-500">Dipakai untuk tombol "Kirim ke WhatsApp" di halaman Laporan.</p>
                    </div>
                    <div>
                        <InputLabel for="report_send_time" value="Jam Kirim Laporan (opsional)" />
                        <TextInput id="report_send_time" v-model="form.report_send_time" type="time" class="mt-1 w-full" />
                        <InputError :message="form.errors.report_send_time" class="mt-1" />
                    </div>
                </div>

                <div>
                    <InputLabel value="Hari Libur Mingguan" />
                    <div class="mt-2 flex flex-wrap gap-2">
                        <label
                            v-for="d in WEEKDAYS"
                            :key="d.value"
                            class="flex cursor-pointer items-center gap-1.5 rounded-md border px-3 py-1.5 text-sm"
                            :class="form.weekly_off_days.includes(d.value) ? 'border-indigo-500 bg-indigo-50 text-indigo-700' : 'border-gray-300 text-gray-600'"
                        >
                            <input
                                type="checkbox"
                                class="sr-only"
                                :checked="form.weekly_off_days.includes(d.value)"
                                @change="toggleOffDay(d.value)"
                            />
                            {{ d.label }}
                        </label>
                    </div>
                    <InputError :message="form.errors.weekly_off_days" class="mt-1" />
                    <p class="mt-1 text-xs text-gray-500">Hari yang dipilih tidak dihitung sebagai hari sekolah (tidak ada status tidak hadir).</p>
                </div>

                <div class="flex justify-end">
                    <PrimaryButton :disabled="form.processing">Simpan</PrimaryButton>
                </div>
            </form>

            <!-- Kalender libur -->
            <div class="space-y-4 rounded-lg bg-white p-6 shadow">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-gray-800">Hari Libur</h3>
                    <div class="flex items-center gap-2">
                        <TextInput v-model="generateForm.year" type="number" class="w-24" min="2000" max="2100" />
                        <SecondaryButton type="button" :disabled="generateForm.processing" @click="generate">
                            Generate Libur Nasional
                        </SecondaryButton>
                    </div>
                </div>
                <p class="text-xs text-gray-500">
                    Sabtu &amp; Minggu otomatis non-efektif. Generate mengisi tanggal tetap + hari besar nasional;
                    tanggal tabulasi (Imlek, Idul Fitri, dll) tersedia untuk tahun yang datanya sudah ditetapkan —
                    sisanya bisa ditambah manual.
                </p>

                <!-- Preview nasional -->
                <details class="rounded-md border border-gray-200 p-3">
                    <summary class="cursor-pointer text-sm font-medium text-gray-700">
                        Pratinjau libur nasional {{ generateForm.year }} ({{ nationalHolidays.length }} tanggal)
                    </summary>
                    <ul class="mt-2 grid gap-1 text-sm text-gray-600 sm:grid-cols-2">
                        <li v-for="h in nationalHolidays" :key="h.date" class="flex justify-between">
                            <span class="font-mono">{{ h.date }}</span>
                            <span>{{ h.name }}</span>
                            <span v-if="holidayDates.has(h.date)" class="text-green-600">✓ tersimpan</span>
                        </li>
                    </ul>
                </details>

                <!-- Tambah manual -->
                <form class="flex flex-wrap items-end gap-3 border-t border-gray-100 pt-4" @submit.prevent="addHoliday">
                    <div>
                        <InputLabel for="holiday_date" value="Tanggal" />
                        <TextInput id="holiday_date" v-model="holidayForm.holiday_date" type="date" class="mt-1" required />
                        <InputError :message="holidayForm.errors.holiday_date" class="mt-1" />
                    </div>
                    <div class="flex-1">
                        <InputLabel for="holiday_name" value="Nama Libur" />
                        <TextInput id="holiday_name" v-model="holidayForm.name" class="mt-1 w-full" placeholder="mis. Study Tour" required />
                        <InputError :message="holidayForm.errors.name" class="mt-1" />
                    </div>
                    <PrimaryButton :disabled="holidayForm.processing">Tambah</PrimaryButton>
                </form>

                <!-- Daftar libur tersimpan -->
                <table class="min-w-full divide-y divide-gray-100">
                    <tbody class="divide-y divide-gray-50">
                        <tr v-for="h in holidays" :key="h.id">
                            <td class="py-2 font-mono text-sm text-gray-500">{{ h.holiday_date }}</td>
                            <td class="py-2 text-sm text-gray-900">{{ h.name }}</td>
                            <td class="py-2 text-right">
                                <button type="button" class="text-sm text-red-600 hover:underline" @click="removeHoliday(h.id)">
                                    Hapus
                                </button>
                            </td>
                        </tr>
                        <tr v-if="!holidays.length">
                            <td colspan="3" class="py-4 text-center text-sm text-gray-500">Belum ada libur tersimpan.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
