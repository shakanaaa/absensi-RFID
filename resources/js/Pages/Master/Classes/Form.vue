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
    classData: Object,
    grades: Array,
    concentrations: Array,
});
const isEdit = computed(() => !!props.classData);

const form = useForm({
    grade_id: props.classData?.grade_id ?? '',
    concentration_id: props.classData?.concentration_id ?? '',
    code: props.classData?.code ?? '',
    name: props.classData?.name ?? '',
    status: props.classData?.status ?? 'active',
});

const submit = () => {
    if (isEdit.value) {
        form.put(route('master.classes.update', props.classData.id));
    } else {
        form.post(route('master.classes.store'));
    }
};
</script>

<template>
    <Head :title="isEdit ? 'Ubah Kelas' : 'Tambah Kelas'" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold text-gray-800">
                {{ isEdit ? 'Ubah Kelas' : 'Tambah Kelas' }}
            </h2>
        </template>

        <div class="mx-auto max-w-2xl px-4 sm:px-6 lg:px-8">
            <form class="space-y-5 rounded-lg bg-white p-6 shadow" @submit.prevent="submit">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <InputLabel for="grade_id" value="Tingkat" />
                        <select
                            id="grade_id"
                            v-model="form.grade_id"
                            class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            <option value="">-</option>
                            <option v-for="g in grades" :key="g.id" :value="g.id">{{ g.name }}</option>
                        </select>
                        <InputError :message="form.errors.grade_id" class="mt-1" />
                    </div>
                    <div>
                        <InputLabel for="concentration_id" value="Konsentrasi" />
                        <select
                            id="concentration_id"
                            v-model="form.concentration_id"
                            class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            required
                        >
                            <option value="" disabled>Pilih konsentrasi</option>
                            <option v-for="c in concentrations" :key="c.id" :value="c.id">{{ c.name }}</option>
                        </select>
                        <InputError :message="form.errors.concentration_id" class="mt-1" />
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <InputLabel for="code" value="Kode Kelas" />
                        <TextInput id="code" v-model="form.code" class="mt-1 w-full" placeholder="mis. X-1" required />
                        <InputError :message="form.errors.code" class="mt-1" />
                    </div>
                    <div>
                        <InputLabel for="name" value="Nama Kelas" />
                        <TextInput id="name" v-model="form.name" class="mt-1 w-full" placeholder="mis. X IPA 1" required />
                        <InputError :message="form.errors.name" class="mt-1" />
                    </div>
                </div>

                <div v-if="isEdit">
                    <InputLabel for="status" value="Status" />
                    <select
                        id="status"
                        v-model="form.status"
                        class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    >
                        <option value="active">Aktif</option>
                        <option value="inactive">Nonaktif</option>
                        <option value="archived">Arsip</option>
                    </select>
                    <InputError :message="form.errors.status" class="mt-1" />
                </div>

                <div class="flex items-center justify-end gap-3 pt-2">
                    <Link :href="route('master.classes.index')">
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
