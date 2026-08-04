<script setup lang="ts">
import { ref } from 'vue';
import { RouterLink } from 'vue-router';

import { useAuthStore } from '@/stores/auth';
import { downloadCsvExport, downloadJsonExport } from '@/utils/export';

const authStore = useAuthStore();
const confirmed = ref(false);
const isDownloading = ref(false);
const errorMessage = ref('');
const successMessage = ref('');

async function handleDownload(): Promise<void> {
  if (!confirmed.value || isDownloading.value) return;

  isDownloading.value = true;
  errorMessage.value = '';
  successMessage.value = '';

  const result = await downloadJsonExport(authStore.tokenType, authStore.accessToken);
  if (result.ok) {
    successMessage.value = `Le fichier ${result.filename} a été téléchargé.`;
  } else {
    errorMessage.value = result.message;
  }

  isDownloading.value = false;
}

async function handleCsvDownload(): Promise<void> {
  if (!confirmed.value || isDownloading.value) return;
  isDownloading.value = true;
  errorMessage.value = '';
  successMessage.value = '';
  const result = await downloadCsvExport(authStore.tokenType, authStore.accessToken);
  if (result.ok) successMessage.value = `Le fichier ${result.filename} a été téléchargé.`;
  else errorMessage.value = result.message;
  isDownloading.value = false;
}
</script>

<template>
  <main class="export-card">
    <RouterLink class="back-link" :to="{ name: 'dashboard' }">← Retour à mon espace</RouterLink>
    <p class="kicker">Mes données</p>
    <h1>Exporter mes données</h1>
    <p class="intro">Choisissez un format pour télécharger vos données exportables.</p>

    <section class="data-section" aria-labelledby="data-title">
      <h2 id="data-title">Ce qui sera exporté</h2>
      <ul>
        <li>Les titres, descriptions, durées, portions, ingrédients, étapes, tags, notes et sources des recettes.</li>
        <li>Les noms, descriptions et références de recettes des cookbooks accessibles.</li>
        <li>Le format JSON versionné SUPMEAL 1.0.0, pour faciliter la conservation ou la réutilisation.</li>
      </ul>
      <p class="not-exported">Les mots de passe, membres, commentaires, favoris, planning et images ne sont pas exportés.</p>
    </section>

    <section class="format-section" aria-labelledby="format-title">
      <h2 id="format-title">Format d’export</h2>
      <div class="format-options">
        <button class="download-button" type="button" :disabled="!confirmed || isDownloading" @click="handleDownload">
          {{ isDownloading ? 'Préparation du téléchargement…' : 'Télécharger l’export JSON' }}
        </button>
        <button class="download-button secondary-button" type="button" :disabled="!confirmed || isDownloading" @click="handleCsvDownload">
          {{ isDownloading ? 'Préparation du téléchargement…' : 'Télécharger l’export CSV' }}
        </button>
      </div>
      <p class="format-limit"><strong>Limites CSV :</strong> seules les recettes sont exportées ; les cookbooks, images, favoris, commentaires et planning ne le sont pas. Les ingrédients, étapes et tags sont conservés dans des lignes structurées, sans compatibilité avec le JSON SUPMEAL.</p>
    </section>

    <aside class="warning" role="note" aria-label="Avertissement de sécurité">
      <strong>Attention : fichier lisible en clair</strong>
      <p>
        Le fichier n’est pas chiffré. Il peut contenir des informations personnelles ou sensibles : conservez-le dans un endroit sûr et ne le partagez pas publiquement.
      </p>
    </aside>

    <div
      v-if="errorMessage"
      class="error-summary"
      role="alert"
      aria-live="assertive"
    >
      {{ errorMessage }}
    </div>
    <div
      v-if="successMessage"
      class="success-message"
      role="status"
      aria-live="polite"
    >
      {{ successMessage }}
    </div>

    <label class="confirmation">
      <input v-model="confirmed" type="checkbox" :disabled="isDownloading">
      <span>Je comprends que ce fichier sera lisible en clair et je confirme son téléchargement.</span>
    </label>

  </main>
</template>

<style scoped>
.export-card {
  width: min(100% - 2rem, 46rem);
  margin: 2rem auto;
  padding: 2rem;
  border: 1px solid rgba(86, 112, 79, 0.18);
  border-radius: 1.5rem;
  background: rgba(255, 253, 248, 0.94);
  box-shadow: 0 20px 60px rgba(54, 68, 35, 0.1);
}

.back-link { color: #395330; font-weight: 700; }
.kicker { margin: 2rem 0 0.35rem; color: #6b7b57; font-size: 0.8rem; font-weight: 700; letter-spacing: 0.14em; text-transform: uppercase; }
h1 { margin: 0 0 1rem; font-size: clamp(1.9rem, 4vw, 2.8rem); }
h2 { margin: 0; font-size: 1.25rem; }
.intro { color: #50634d; line-height: 1.6; }
.data-section { margin-top: 1.75rem; padding-top: 1.5rem; border-top: 1px solid rgba(86, 112, 79, 0.18); }
.format-section { margin-top: 1.75rem; padding-top: 1.5rem; border-top: 1px solid rgba(86, 112, 79, 0.18); }
.format-options { display: grid; gap: 0.75rem; margin-top: 1rem; }
.secondary-button { background: #6b7b57; border-color: #6b7b57; }
.format-limit { color: #50634d; line-height: 1.5; font-size: 0.92rem; }
li, .not-exported { color: #50634d; line-height: 1.55; }
li + li { margin-top: 0.55rem; }
.not-exported { margin-bottom: 0; font-size: 0.92rem; }
.warning { margin-top: 1.5rem; padding: 1rem 1.1rem; border: 1px solid #d89b43; border-radius: 0.8rem; background: #fff5df; color: #704414; }
.warning p { margin: 0.45rem 0 0; line-height: 1.5; }
.error-summary, .success-message { margin-top: 1.25rem; padding: 0.8rem 1rem; border-radius: 0.65rem; line-height: 1.45; }
.error-summary { border: 1px solid #d58b8b; background: #fff0f0; color: #8f1e1e; }
.success-message { border: 1px solid #8ca17b; background: #f1f8ed; color: #395330; }
.confirmation { display: flex; align-items: flex-start; gap: 0.7rem; margin-top: 1.5rem; color: #243127; font-weight: 700; line-height: 1.45; cursor: pointer; }
.confirmation input { width: 1.1rem; height: 1.1rem; margin-top: 0.15rem; accent-color: #395330; }
.download-button { width: 100%; margin-top: 1.25rem; padding: 0.85rem 1rem; border: 1px solid #395330; border-radius: 0.65rem; background: #395330; color: #fffdf8; font: inherit; font-weight: 700; cursor: pointer; }
.download-button:disabled { cursor: not-allowed; opacity: 0.5; }
</style>
