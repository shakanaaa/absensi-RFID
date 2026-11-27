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
    account: Object,
    roles: Array,
});
const isEdit = computed(() => !!props.account);

const form = useForm({
    name: props.account?.name ?? '',
    email: props.account?.email ?? '',
    password: '',
    password_confirmation: '',
    role: props.account?.role ?? 'orang_tua',
    phone: props.account?.phone ?? '',
    status: props.account?.status ?? 'active',
});

const submit = () => {
    if (isEdit.value) {
        form.put(route('users.update', props.account.id));
    } else {
        form.post(route('users.store'));
    }
};
</script>

<template>
    <Head :title="isEdit ? 'Ubah Pengguna' : 'Tambah Pengguna'" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold text-gray-800">
                {{ isEdit ? 'Ubah Pengguna' : 'Tambah Pengguna' }}
            </h2>
        </template>

        <div class="mx-auto max-w-2xl px-4 sm:px-6 lg:px-8">
            <form class="space-y-5 rounded-lg bg-white p-6 shadow" @submit.prevent="submit">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <InputLabel for="name" value="Nama" />
                        <TextInput id="name" v-model="form.name" class="mt-1 w-full" required />
                        <InputError :message="form.errors.name" class="mt-1" />
                    </div>
                    <div>
                        <InputLabel for="email" value="Email" />
                        <TextInput id="email" v-model="form.email" type="email" class="mt-1 w-full" :disabled="isEdit" required />
                        <InputError :message="form.errors.email" class="mt-1" />
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-3">
                    <div>
                        <InputLabel for="role" value="Peran" />
                        <select
                            id="role"
                            v-model="form.role"
                            class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            required
                        >
                            <option v-for="r in roles" :key="r.value" :value="r.value">{{ r.label }}</option>
                        </select>
                        <InputError :message="form.errors.role" class="mt-1" />
                    </div>
                    <div>
                        <InputLabel for="phone" value="Telepon" />
                        <TextInput id="phone" v-model="form.phone" class="mt-1 w-full" />
                        <InputError :message="form.errors.phone" class="mt-1" />
                    </div>
                    <div>
                        <InputLabel for="status" value="Status" />
                        <select
                            id="status"
                            v-model="form.status"
                            class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            required
                        >
                            <option value="active">Aktif</option>
                            <option value="inactive">Nonaktif</option>
                            <option value="suspended">Ditangguhkan</option>
                        </select>
                        <InputError :message="form.errors.status" class="mt-1" />
                    </div>
                </div>

                <div v-if="!isEdit" class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <InputLabel for="password" value="Kata Sandi" />
                        <TextInput id="password" v-model="form.password" type="password" class="mt-1 w-full" required autocomplete="new-password" />
                        <InputError :message="form.errors.password" class="mt-1" />
                    </div>
                    <div>
                        <InputLabel for="password_confirmation" value="Ulangi Kata Sandi" />
                        <TextInput id="password_confirmation" v-model="form.password_confirmation" type="password" class="mt-1 w-full" required autocomplete="new-password" />
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 pt-2">
                    <Link :href="route('users.index')">
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
