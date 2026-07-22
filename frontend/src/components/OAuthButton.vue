<script setup lang="ts">
withDefaults(
  defineProps<{
    provider: 'google' | 'microsoft';
    endpoint: string;
    label: string;
  }>(),
  {},
);
</script>

<template>
  <a
    class="oauth-button"
    :class="`oauth-button--${provider}`"
    :href="endpoint"
    :aria-label="label"
  >
    <span class="oauth-mark" aria-hidden="true">{{ provider === 'google' ? 'G' : 'M' }}</span>
    <span>{{ label }}</span>
  </a>
</template>

<style scoped>
.oauth-button {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.7rem;
  width: 100%;
  min-height: 3rem;
  padding: 0.75rem 1.25rem;
  border: 1px solid #b4bead;
  border-radius: 999px;
  background: #fffdf9;
  color: #243127;
  font: inherit;
  font-weight: 700;
  text-decoration: none;
  transition: background-color 0.2s ease, box-shadow 0.2s ease;
}

.oauth-button:hover { background: #f5f8f1; }

.oauth-button:focus-visible {
  outline: 3px solid rgba(116, 144, 88, 0.32);
  outline-offset: 3px;
}

.oauth-mark {
  font-family: Arial, sans-serif;
  font-size: 1.15rem;
  font-weight: 800;
}

.oauth-button--google .oauth-mark { color: #4285f4; }
.oauth-button--microsoft .oauth-mark { color: #0078d4; }
</style>
