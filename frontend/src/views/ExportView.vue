<script setup lang="ts">
import { ref } from 'vue';
import { RouterLink } from 'vue-router';

import { useAuthStore } from '@/stores/auth';
import { downloadCsvExport, downloadJsonExport } from '@/utils/export';

const authStore = useAuthStore();
const confirmed = ref(false);
const includeCookbooks = ref(true);
const isDownloading = ref(false);
const errorMessage = ref('');
const successMessage = ref('');

async function handleDownload(): Promise<void> {
  if (!confirmed.value || isDownloading.value) return;

  isDownloading.value = true;
  errorMessage.value = '';
  successMessage.value = '';

  const result = await downloadJsonExport(authStore.tokenType, authStore.accessToken, includeCookbooks.value);
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
    <img class="data-hero" src="@/assets/importexport.png" alt="Cuisine lumineuse avec des ingrédients frais" />
    <RouterLink class="back-link" :to="{ name: 'dashboard' }">← Retour à mon espace</RouterLink>
    <p class="kicker">Mes données</p>
    <h1>Exporter mes données</h1>
    <p class="intro">Choisissez un format pour télécharger vos données exportables.</p>

    <ol class="export-workflow" aria-label="Etapes de l’export">
      <li class="active"><span>1</span><strong>Contenu</strong><small>Ce qui sera exporté</small></li>
      <li class="active"><span>2</span><strong>Format</strong><small>JSON ou CSV</small></li>
      <li><span>3</span><strong>Confirmation</strong><small>Fichier lisible en clair</small></li>
      <li><span>4</span><strong>Téléchargement</strong><small>Résultat</small></li>
    </ol>

    <section class="data-section" aria-labelledby="data-title">
      <div class="section-heading"><span class="step-badge">1</span><div><p class="section-kicker">Périmètre</p>
      <h2 id="data-title">Ce qui sera exporté</h2>
      </div>
      </div>
      <div class="export-scope">
        <div class="scope-option selected"><span class="scope-check" aria-hidden="true">✓</span><div><strong>Recettes</strong><span>Recettes, ingrédients, étapes, tags, notes et sources.</span></div></div>
        <div class="scope-option selected"><span class="scope-check" aria-hidden="true">✓</span><div><strong>Cookbooks</strong><span>Noms, descriptions et références dans le JSON SUPMEAL.</span></div></div>
      </div>
      <label class="scope-toggle" for="export-scope">
        <span>Contenu JSON</span>
        <select id="export-scope" v-model="includeCookbooks" :disabled="isDownloading">
          <option :value="true">Recettes et cookbooks</option>
          <option :value="false">Recettes uniquement</option>
        </select>
      </label>
      <p v-if="!includeCookbooks" class="scope-note">Mode recettes uniquement : les cookbooks et les références de cookbook seront retirés du fichier.</p>
      <ul>
        <li>Les titres, descriptions, durées, portions, ingrédients, étapes, tags, notes et sources des recettes.</li>
        <li>Les noms, descriptions et références de recettes des cookbooks accessibles.</li>
        <li>Le format JSON versionné SUPMEAL 1.0.0, pour faciliter la conservation ou la réutilisation.</li>
      </ul>
      <p class="not-exported">Les mots de passe, membres, commentaires, favoris, planning et images ne sont pas exportés.</p>
    </section>

    <section class="format-section" aria-labelledby="format-title">
      <div class="section-heading"><span class="step-badge">2</span><div><p class="section-kicker">Fichier généré</p>
      <h2 id="format-title">Format d’export</h2>
      </div>
      </div>
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

    <section class="export-note">
      <p class="unsupported-format"><strong>Mealie :</strong> ce format est disponible à l’import, mais aucun export Mealie n’est disponible actuellement.</p>
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

    <section class="confirmation-panel" aria-labelledby="confirmation-title">
      <div class="section-heading"><span class="step-badge">3</span><div><p class="section-kicker">Avant de continuer</p><h2 id="confirmation-title">Confirmer le téléchargement</h2></div></div>
    <label class="confirmation">
      <input v-model="confirmed" type="checkbox" :disabled="isDownloading">
      <span>Je comprends que ce fichier sera lisible en clair et je confirme son téléchargement.</span>
    </label>
    </section>

  </main>
</template>

<style scoped>
.export-card {
  box-sizing: border-box;
  width: min(100% - 2rem, 76rem);
  margin: 2rem auto;
  padding: 2rem;
  border: 1px solid rgba(86, 112, 79, 0.18);
  border-radius: 1.5rem;
  background: rgba(255, 253, 248, 0.94);
  box-shadow: 0 20px 60px rgba(54, 68, 35, 0.1);
}
.data-hero { display: block; width: calc(100% + 4rem); height: clamp(12rem, 24vw, 19rem); margin: -2rem -2rem 1.5rem; object-fit: cover; object-position: center; }

.back-link { color: #395330; font-weight: 700; }
.kicker { margin: 2rem 0 0.35rem; color: #6b7b57; font-size: 0.8rem; font-weight: 700; letter-spacing: 0.14em; text-transform: uppercase; }
h1 { margin: 0 0 1rem; font-size: clamp(1.9rem, 4vw, 2.8rem); }
h2 { margin: 0; font-size: 1.25rem; }
.intro { color: #50634d; line-height: 1.6; }
.export-workflow { display: grid; grid-template-columns: repeat(4, 1fr); gap: .5rem; margin: 1.75rem 0; padding: 0; list-style: none; }
.export-workflow li { display: grid; gap: .3rem; justify-items: center; color: #71806b; font-size: .78rem; text-align: center; }
.export-workflow li span { display: grid; place-items: center; width: 2rem; height: 2rem; border: 1px solid #b9c5af; border-radius: 50%; background: #fffdf8; font-weight: 800; }
.export-workflow li.active { color: #395330; font-weight: 700; }.export-workflow li.active span { border-color: #395330; background: #395330; color: #fffdf8; }
.export-workflow small { font-size: .75rem; font-weight: 400; }
.section-heading { display: flex; align-items: center; gap: .7rem; margin-bottom: 1rem; }.section-heading h2 { margin: 0; }.section-kicker { margin: 0 0 .2rem; color: #6b7b57; font-size: .75rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; }
.export-scope { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .75rem; margin: 1rem 0; }.scope-option, .format-option { display: flex; align-items: flex-start; gap: .7rem; padding: .8rem; border: 1px solid #d8e1d2; border-radius: .65rem; background: #f3f7ef; }.scope-option > div, .format-option > div { display: grid; gap: .2rem; }.scope-option span:last-child, .format-option span { color: #50634d; font-size: .9rem; line-height: 1.4; }.scope-check { color: #395330; font-weight: 800; }
.unsupported-format { margin-bottom: 0; color: #50634d; font-size: .92rem; line-height: 1.5; }
.scope-toggle { display: flex; align-items: center; justify-content: space-between; gap: .8rem; margin: .9rem 0; color: #243127; font-weight: 700; line-height: 1.4; }.scope-toggle select { min-width: 13rem; padding: .55rem .7rem; border: 1px solid #b9c5af; border-radius: .55rem; background: #fffdf8; color: #243127; font: inherit; }.scope-note { margin: .5rem 0 1rem; padding: .7rem; border-radius: .55rem; background: #eef5ea; color: #395330; font-size: .9rem; line-height: 1.4; }
.confirmation-panel { margin-top: 1.5rem; padding: 1rem; border: 1px solid #8ca17b; border-radius: .8rem; background: #f8fcf5; }
.data-section { margin-top: 1.75rem; padding-top: 1.5rem; border-top: 1px solid rgba(86, 112, 79, 0.18); }
.format-section { margin-top: 1.75rem; padding-top: 1.5rem; border-top: 1px solid rgba(86, 112, 79, 0.18); }
.format-options { display: grid; gap: 0.75rem; margin-top: 1rem; }.format-option { align-items: center; justify-content: space-between; }.format-option .download-button { width: auto; min-width: 12rem; margin-top: 0; }
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
@media (max-width: 640px) { .export-card { width: min(100% - 1rem, 76rem); padding: 1rem; }.data-hero { width: calc(100% + 2rem); height: 11rem; margin: -1rem -1rem 1.25rem; }.export-workflow li { font-size: .68rem; }.export-workflow small { display: none; }.export-scope { grid-template-columns: 1fr; }.format-option { align-items: stretch; flex-direction: column; }.format-option .download-button { width: 100%; } }
</style>
