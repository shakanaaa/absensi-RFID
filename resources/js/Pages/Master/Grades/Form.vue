<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({ grade: Object });
const isEdit = computed(() => !!props.grade);

const form = useForm({
    name: props.grade?.name ?? '',
    level: props.grade?.level ?? null,
});

const submit = () => {
    if (isEdit.value) {
        form.put(route('master.grades.update', props.grade.id));
    } else {
        form.post(route('master.grades.store'));
    }
};
</script>

<template>
    <Head :title="isEdit ? 'Ubah Tingkat' : 'Tambah Tingkat'" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold text-gray-800">
                {{ isEdit ? 'Ubah Tingkat' : 'Tambah Tingkat' }}
            </h2>
        </template>

        <div class="mx-auto max-w-2xl px-4 sm:px-6 lg:px-8">
            <form class="space-y-5 rounded-lg bg-white p-6 shadow" @submit.prevent="submit">
                <div>
                    <InputLabel for="name" value="Nama (mis. X, XI, XII)" />
                    <TextInput id="name" v-model="form.name" class="mt-1 w-full" required />
                    <InputError :message="form.errors.name" class="mt-1" />
                </div>

                <div>
                    <InputLabel for="level" value="Level Kelas (opsional)" />
                    <TextInput id="level" v-model="form.level" type="number" min="1" max="12" class="mt-1 w-full" />
                    <InputError :message="form.errors.level" class="mt-1" />
                </div>

                <div class="flex items-center justify-end gap-3 pt-2">
                    <Link :href="route('master.grades.index')">
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
