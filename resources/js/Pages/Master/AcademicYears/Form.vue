<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({ year: Object });
const isEdit = computed(() => !!props.year);

const form = useForm({
    name: props.year?.name ?? '',
    start_date: props.year?.start_date ?? '',
    end_date: props.year?.end_date ?? '',
    status: props.year?.status ?? 'draft',
});

const submit = () => {
    if (isEdit.value) {
        form.put(route('master.academic-years.update', props.year.id));
    } else {
        form.post(route('master.academic-years.store'));
    }
};
</script>

<template>
    <Head :title="isEdit ? 'Ubah Tahun Ajaran' : 'Tambah Tahun Ajaran'" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold text-gray-800">
                {{ isEdit ? 'Ubah Tahun Ajaran' : 'Tambah Tahun Ajaran' }}
            </h2>
        </template>

        <div class="mx-auto max-w-2xl px-4 sm:px-6 lg:px-8">
            <form class="space-y-5 rounded-lg bg-white p-6 shadow" @submit.prevent="submit">
                <div>
                    <InputLabel for="name" value="Nama (mis. 2026/2027)" />
                    <TextInput id="name" v-model="form.name" class="mt-1 w-full" required />
                    <InputError :message="form.errors.name" class="mt-1" />
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <InputLabel for="start_date" value="Tanggal Mulai" />
                        <TextInput id="start_date" v-model="form.start_date" type="date" class="mt-1 w-full" required />
                        <InputError :message="form.errors.start_date" class="mt-1" />
                    </div>
                    <div>
                        <InputLabel for="end_date" value="Tanggal Selesai" />
                        <TextInput id="end_date" v-model="form.end_date" type="date" class="mt-1 w-full" required />
                        <InputError :message="form.errors.end_date" class="mt-1" />
                    </div>
                </div>

                <div>
                    <InputLabel for="status" value="Status" />
                    <select
                        id="status"
                        v-model="form.status"
                        class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    >
                        <option value="draft">Draft</option>
                        <option value="active">Aktif</option>
                        <option value="closed">Ditutup</option>
                        <option v-if="isEdit" value="archived">Arsip</option>
                    </select>
                    <InputError :message="form.errors.status" class="mt-1" />
                </div>

                <div class="flex items-center justify-end gap-3 pt-2">
                    <Link :href="route('master.academic-years.index')">
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
