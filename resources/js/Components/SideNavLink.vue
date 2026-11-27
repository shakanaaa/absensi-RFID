<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    href: {
        type: String,
        required: true,
    },
    active: {
        type: Boolean,
        default: false,
    },
    method: {
        type: String,
        default: 'get',
    },
    as: {
        type: String,
        default: 'a',
    },
});

const classes = computed(() =>
    props.active
        ? 'flex items-center rounded-md bg-gray-100 px-3 py-2 text-sm font-medium text-gray-900'
        : 'flex items-center rounded-md px-3 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50 hover:text-gray-900',
);
</script>

<template>
    <Link :href="href" :method="method" :as="as" :class="classes">
        <slot />
    </Link>
</template>
