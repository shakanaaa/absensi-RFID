<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    student: Object,
    classes: Array,
    years: Array,
    defaults: Object,
});
const isEdit = computed(() => !!props.student);
const membership = props.student?.memberships?.[0] ?? null;

const form = useForm({
    student_no: props.student?.student_no ?? '',
    name: props.student?.name ?? '',
    gender: props.student?.gender ?? '',
    birth_date: props.student?.birth_date ?? '',
    status: props.student?.status ?? 'active',
    class_id: membership?.class_id ?? '',
    academic_year_id: membership?.academic_year_id ?? props.defaults?.academic_year_id ?? '',
    from_date: membership?.from_date ?? props.defaults?.from_date ?? '',
    to_date: membership?.to_date ?? '',
    attendance_number: membership?.attendance_number ?? '',
});

const submit = () => {
    if (isEdit.value) {
        form.put(route('master.students.update', props.student.id));
    } else {
        form.post(route('master.students.store'));
    }
};
</script>

<template>
    <Head :title="isEdit ? 'Ubah Siswa' : 'Tambah Siswa'" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold text-gray-800">
                {{ isEdit ? 'Ubah Siswa' : 'Tambah Siswa' }}
            </h2>
        </template>

        <div class="mx-auto max-w-2xl px-4 sm:px-6 lg:px-8">
            <form class="space-y-5 rounded-lg bg-white p-6 shadow" @submit.prevent="submit">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <InputLabel for="student_no" value="NIS / NISN" />
                        <TextInput id="student_no" v-model="form.student_no" class="mt-1 w-full" required />
                        <InputError :message="form.errors.student_no" class="mt-1" />
                    </div>
                    <div>
                        <InputLabel for="name" value="Nama Lengkap" />
                        <TextInput id="name" v-model="form.name" class="mt-1 w-full" required />
                        <InputError :message="form.errors.name" class="mt-1" />
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-3">
                    <div>
                        <InputLabel for="gender" value="Jenis Kelamin" />
                        <select
                            id="gender"
                            v-model="form.gender"
                            class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            <option value="">-</option>
                            <option value="male">Laki-laki</option>
                            <option value="female">Perempuan</option>
                        </select>
                        <InputError :message="form.errors.gender" class="mt-1" />
                    </div>
                    <div>
                        <InputLabel for="birth_date" value="Tanggal Lahir" />
                        <TextInput id="birth_date" v-model="form.birth_date" type="date" class="mt-1 w-full" />
                        <InputError :message="form.errors.birth_date" class="mt-1" />
                    </div>
                    <div>
                        <InputLabel for="status" value="Status" />
                        <select
                            id="status"
                            v-model="form.status"
                            class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            <option value="active">Aktif</option>
                            <option value="inactive">Nonaktif</option>
                            <option value="lulus">Lulus</option>
                        </select>
                        <InputError :message="form.errors.status" class="mt-1" />
                    </div>
                </div>

                <fieldset class="rounded-md border border-gray-200 p-4">
                    <legend class="px-1 text-sm font-medium text-gray-700">Kelas Efektif</legend>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <InputLabel for="class_id" value="Kelas" />
                            <select
                                id="class_id"
                                v-model="form.class_id"
                                class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                required
                            >
                                <option value="" disabled>Pilih kelas</option>
                                <option v-for="c in classes" :key="c.id" :value="c.id">
                                    {{ c.name }} ({{ c.code }})
                                </option>
                            </select>
                            <InputError :message="form.errors.class_id" class="mt-1" />
                        </div>
                        <div>
                            <InputLabel for="attendance_number" value="Nomor Absen" />
                            <TextInput id="attendance_number" v-model="form.attendance_number" type="number" min="1" class="mt-1 w-full" />
                            <InputError :message="form.errors.attendance_number" class="mt-1" />
                        </div>
                    </div>
                    <div class="mt-4 grid gap-4 sm:grid-cols-3">
                        <div>
                            <InputLabel for="academic_year_id" value="Tahun Ajaran" />
                            <select
                                id="academic_year_id"
                                v-model="form.academic_year_id"
                                class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                required
                            >
                                <option value="" disabled>Pilih tahun ajaran</option>
                                <option v-for="y in years" :key="y.id" :value="y.id">{{ y.name }}</option>
                            </select>
                            <InputError :message="form.errors.academic_year_id" class="mt-1" />
                        </div>
                        <div>
                            <InputLabel for="from_date" value="Berlaku Mulai" />
                            <TextInput id="from_date" v-model="form.from_date" type="date" class="mt-1 w-full" required />
                            <InputError :message="form.errors.from_date" class="mt-1" />
                        </div>
                        <div>
                            <InputLabel for="to_date" value="Berlaku Sampai (opsional)" />
                            <TextInput id="to_date" v-model="form.to_date" type="date" class="mt-1 w-full" />
                            <InputError :message="form.errors.to_date" class="mt-1" />
                        </div>
                    </div>
                </fieldset>

                <div class="flex items-center justify-end gap-3 pt-2">
                    <Link :href="isEdit ? route('master.students.show', props.student.id) : route('master.students.index')">
                        <SecondaryButton type="button">Batal</SecondaryButton>
                    </Link>
                    <PrimaryButton :disabled="form.processing">
                        {{ isEdit ? 'Simpan Perubahan' : 'Simpan' }}
                    </PrimaryButton>
                </div>
            </form>
        </div>
    </AuthenticatedLayout>
</template>
