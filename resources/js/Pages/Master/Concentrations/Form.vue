<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({ concentration: Object });
const isEdit = computed(() => !!props.concentration);

const form = useForm({
    name: props.concentration?.name ?? '',
});

const submit = () => {
    if (isEdit.value) {
        form.put(route('master.concentrations.update', props.concentration.id));
    } else {
        form.post(route('master.concentrations.store'));
    }
};
</script>

<template>
    <Head :title="isEdit ? 'Ubah Konsentrasi' : 'Tambah Konsentrasi'" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold text-gray-800">
                {{ isEdit ? 'Ubah Konsentrasi' : 'Tambah Konsentrasi' }}
            </h2>
        </template>

        <div class="mx-auto max-w-2xl px-4 sm:px-6 lg:px-8">
            <form class="space-y-5 rounded-lg bg-white p-6 shadow" @submit.prevent="submit">
                <div>
                    <InputLabel for="name" value="Nama (mis. IPA, IPS, Bahasa)" />
                    <TextInput id="name" v-model="form.name" class="mt-1 w-full" required />
                    <InputError :message="form.errors.name" class="mt-1" />
                </div>

                <div class="flex items-center justify-end gap-3 pt-2">
                    <Link :href="route('master.concentrations.index')">
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
