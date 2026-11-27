<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    classes: Array,
    readerProfiles: Array,
});

const form = useForm({ class_id: '', reader_profile_id: '' });

const submit = () => form.post(route('master.registrations.store'));
</script>

<template>
    <Head title="Sesi Registrasi Baru" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold text-gray-800">Sesi Registrasi Baru</h2>
        </template>

        <div class="mx-auto max-w-xl px-4 sm:px-6 lg:px-8">
            <form class="space-y-5 rounded-lg bg-white p-6 shadow" @submit.prevent="submit">
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
                    <p class="mt-1 text-xs text-gray-500">
                        Daftar siswa diambil dari keanggotaan aktif kelas saat sesi dibuat.
                    </p>
                </div>

                <div>
                    <InputLabel for="reader_profile_id" value="Profil Reader (opsional)" />
                    <select
                        id="reader_profile_id"
                        v-model="form.reader_profile_id"
                        class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    >
                        <option value="">Tanpa validasi pola</option>
                        <option v-for="p in readerProfiles" :key="p.id" :value="p.id">
                            {{ p.name }}{{ p.pattern ? ` — ${p.pattern}` : '' }}
                        </option>
                    </select>
                    <InputError :message="form.errors.reader_profile_id" class="mt-1" />
                </div>

                <div class="flex items-center justify-end gap-3 pt-2">
                    <Link :href="route('master.registrations.index')">
                        <SecondaryButton type="button">Batal</SecondaryButton>
                    </Link>
                    <PrimaryButton :disabled="form.processing">Mulai Sesi</PrimaryButton>
                </div>
            </form>
        </div>
    </AuthenticatedLayout>
</template>
