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
  box-sizing: border-box;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.65rem;
  width: 100%;
  min-height: 2.45rem;
  margin-inline: auto;
  padding: 0.45rem 0.75rem;
  border: 1px solid #c4cfb8;
  border-radius: 1.1rem;
  background: #fff;
  color: #243127;
  font: inherit;
  font-size: 0.92rem;
  font-weight: 600;
  text-decoration: none;
  transition: background-color 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease, transform 0.2s ease;
}

.oauth-button:hover { border-color: #8fa481; background: #f5f8f1; transform: translateY(-1px); }
.oauth-button + .oauth-button { margin-top: 0.55rem; }

.oauth-button:focus-visible {
  outline: 3px solid rgba(116, 144, 88, 0.32);
  outline-offset: 3px;
}

.oauth-mark {
  display: grid;
  width: 1.7rem;
  height: 1.7rem;
  flex: 0 0 1.7rem;
  place-items: center;
  border-radius: 0.45rem;
  background: #edf4e6;
  font-family: Arial, sans-serif;
  font-size: 1rem;
  font-weight: 800;
}

.oauth-button--google .oauth-mark { color: #4285f4; }
.oauth-button--microsoft .oauth-mark { color: #0078d4; }
</style>
