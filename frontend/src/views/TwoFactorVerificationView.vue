<script setup lang="ts">
import { nextTick, onMounted, ref } from 'vue';
import { RouterLink, useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';

const router = useRouter();
const authStore = useAuthStore();
const code = ref('');
const error = ref('');
const submitted = ref(false);
const formError = ref<HTMLElement | null>(null);

function validate(): boolean {
  if (!/^\d{6}$/.test(code.value)) {
    error.value = 'Saisissez le code a 6 chiffres recu par e-mail.';
    return false;
  }
  return true;
}

async function submit(): Promise<void> {
  submitted.value = true;
  error.value = '';
  if (!validate()) { await nextTick(); formError.value?.focus(); return; }
  const result = await authStore.verifyTwoFactor(code.value);
  if (!result.ok) { error.value = result.message; await nextTick(); formError.value?.focus(); return; }
  await router.push({ name: authStore.user?.email_verified === false ? 'email-verification-pending' : 'dashboard' });
}

function restart(): void {
  authStore.clearPendingTwoFactor();
  void router.push({ name: 'login' });
}

onMounted(() => {
  if (authStore.pendingTwoFactor === null) error.value = 'Votre demande de connexion a expire. Recommencez la connexion.';
});
</script>

<template>
  <main class="two-factor-card">
    <p class="kicker">Verification en deux etapes</p>
    <h2>Confirmez votre connexion</h2>
    <p class="intro">Un code temporaire a ete envoye par e-mail<span v-if="authStore.pendingTwoFactor?.email"> a {{ authStore.pendingTwoFactor.email }}</span>.</p>
    <form novalidate @submit.prevent="submit">
      <div v-if="error" ref="formError" class="error-summary" role="alert" tabindex="-1">{{ error }}</div>
      <label for="two-factor-code">Code de verification</label>
      <input id="two-factor-code" v-model="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" placeholder="000000" :aria-invalid="submitted && !!error" @input="code = code.replace(/\D/g, '').slice(0, 6); error = ''" />
      <button type="submit" :disabled="authStore.status === 'loading'">{{ authStore.status === 'loading' ? 'Verification...' : 'Valider le code' }}</button>
    </form>
    <p class="help">Le code expire rapidement et ne peut etre utilise qu’une seule fois.</p>
    <button type="button" class="link-button" @click="restart">Recommencer la connexion</button>
    <RouterLink class="back-link" :to="{ name: 'login' }">Retour a la connexion</RouterLink>
  </main>
</template>

<style scoped>
.two-factor-card { margin: 0 auto; max-width: 34rem; padding: 2rem; border: 1px solid rgba(86,112,79,.18); border-radius: 1.5rem; background: rgba(255,253,248,.92); box-shadow: 0 20px 60px rgba(54,68,35,.1); }
.kicker { margin: 0 0 .35rem; color: #6b7b57; font-size: .8rem; font-weight: 700; letter-spacing: .14em; text-transform: uppercase; }
h2 { margin: 0; font-size: clamp(1.9rem,4vw,2.8rem); line-height: 1; }
.intro, .help { color: #50634d; line-height: 1.6; }
form { display: grid; gap: .65rem; margin-top: 1.75rem; }
label { font-weight: 700; }
input { width: 100%; padding: .9rem 1rem; border: 1px solid #c4cfb8; border-radius: .95rem; font: inherit; letter-spacing: .2em; text-align: center; }
input:focus-visible, button:focus-visible, a:focus-visible { outline: 3px solid rgba(116,144,88,.32); outline-offset: 2px; }
button { margin-top: .75rem; padding: .95rem 1.25rem; border: 0; border-radius: 999px; background: #2f4520; color: #fff; font: inherit; font-weight: 700; cursor: pointer; }
button:disabled { cursor: wait; opacity: .8; }
.error-summary { padding: .95rem 1rem; border: 1px solid rgba(185,72,72,.26); border-radius: 1rem; background: #fff3f0; color: #8d2727; line-height: 1.5; }
.link-button, .back-link { display: block; width: fit-content; margin: 1rem auto 0; padding: 0; background: transparent; color: #2f4520; font-weight: 700; }
.back-link { margin-top: .75rem; }
</style>
