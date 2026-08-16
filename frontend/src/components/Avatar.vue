<script setup lang="ts">
import { computed } from 'vue';

const props = withDefaults(defineProps<{ src?: string | null; name: string; size?: 'small' | 'medium' | 'large' }>(), { src: null, size: 'medium' });
const initials = computed(() => props.name.trim().charAt(0).toUpperCase() || '?');
</script>

<template>
  <span class="avatar" :class="`avatar-${size}`">
    <img v-if="src" :src="src" :alt="`Photo de ${name}`" />
    <span v-else aria-hidden="true">{{ initials }}</span>
  </span>
</template>

<style scoped>
.avatar { display: inline-grid; flex: 0 0 auto; place-items: center; overflow: hidden; border-radius: 50%; background: color-mix(in srgb, var(--app-muted, #50634d) 18%, transparent); color: var(--app-text, #243127); font-weight: 700; }
.avatar img { width: 100%; height: 100%; object-fit: cover; }
.avatar-small { width: 1.75rem; height: 1.75rem; font-size: .75rem; }
.avatar-medium { width: 2.5rem; height: 2.5rem; font-size: .95rem; }
.avatar-large { width: 4rem; height: 4rem; font-size: 1.25rem; }
</style>
