<script setup lang="ts">
import { ref } from 'vue';
import { RouterLink } from 'vue-router';

import { useAuthStore } from '@/stores/auth';
import type { ImportErrorDetail, ImportPreview, ImportReport } from '@/utils/import';
import { importCsvFile, importJsonFile, importMealieFile, previewJsonFile } from '@/utils/import';

const authStore = useAuthStore();
const selectedFile = ref<File | null>(null);
const isUploading = ref(false);
const errorMessage = ref('');
const errors = ref<ImportErrorDetail[]>([]);
const report = ref<ImportReport | null>(null);
const preview = ref<ImportPreview | null>(null);
const format = ref<'json' | 'csv' | 'mealie'>('json');

function selectFile(event: Event): void {
  const input = event.target as HTMLInputElement;
  selectedFile.value = input.files?.[0] ?? null;
  errorMessage.value = '';
  errors.value = [];
  report.value = null;
  preview.value = null;
}

function clearFile(input?: HTMLInputElement): void {
  selectedFile.value = null;
  errorMessage.value = '';
  errors.value = [];
  report.value = null;
  preview.value = null;
  if (input) input.value = '';
}

async function handleImport(): Promise<void> {
  if (selectedFile.value === null || isUploading.value) return;

  isUploading.value = true;
  errorMessage.value = '';
  errors.value = [];
  report.value = null;
  preview.value = null;
  if (format.value === 'json') {
    const result = await previewJsonFile(selectedFile.value, authStore.tokenType, authStore.accessToken);
    if (result.ok) preview.value = result.analysis;
    else {
      errorMessage.value = result.message;
      errors.value = result.errors;
    }
  } else {
    const result = format.value === 'mealie'
      ? await importMealieFile(selectedFile.value, authStore.tokenType, authStore.accessToken)
      : await importCsvFile(selectedFile.value, authStore.tokenType, authStore.accessToken);
    if (result.ok) report.value = result.report;
    else {
      errorMessage.value = result.message;
      errors.value = result.errors;
    }
  }
  isUploading.value = false;
}

async function confirmImport(): Promise<void> {
  if (selectedFile.value === null || isUploading.value || (format.value === 'json' && preview.value === null)) return;

  isUploading.value = true;
  errorMessage.value = '';
  errors.value = [];
  const result = format.value === 'json'
    ? await importJsonFile(selectedFile.value, authStore.tokenType, authStore.accessToken)
    : null;
  if (result?.ok) report.value = result.report;
  else if (result) {
    errorMessage.value = result.message;
    errors.value = result.errors;
  }
  isUploading.value = false;
}

function cancelPreview(): void {
  preview.value = null;
  errorMessage.value = '';
  errors.value = [];
}
</script>

<template>
  <main class="import-card">
    <RouterLink class="back-link" :to="{ name: 'dashboard' }">← Retour à mon espace</RouterLink>
    <p class="kicker">Mes données</p>
    <h1>Importer un fichier</h1>
    <p class="intro">Choisissez le format correspondant au fichier à importer.</p>

    <fieldset class="format-choice">
      <legend>Format du fichier</legend>
      <label><input v-model="format" type="radio" value="json" :disabled="isUploading"> JSON SUPMEAL</label>
      <label><input v-model="format" type="radio" value="csv" :disabled="isUploading"> CSV recettes</label>
      <label><input v-model="format" type="radio" value="mealie" :disabled="isUploading"> Mealie</label>
    </fieldset>

    <aside class="warning" role="note" aria-label="Avertissements d’import">
      <strong>Avant de commencer</strong>
      <ul>
        <template v-if="format === 'mealie'">
          <li><strong>Compatibilité :</strong> Mealie Recipe JSON API v1, avec les champs actuels <code>recipeIngredient</code> et <code>recipeInstructions</code>.</li>
          <li><strong>Limites :</strong> les recettes, ingrédients, étapes, tags/catégories, durées et portions sont importés. Cookbooks, images, nutrition, réglages et identifiants Mealie sont ignorés.</li>
        </template>
        <li>Les fichiers sont limités à 10 Mo.</li>
        <li>Les données seront attribuées à votre compte ; les identifiants externes ne sont pas conservés comme identifiants internes.</li>
        <li>Les doublons détectés sont ignorés et listés dans le résultat.</li>
        <li v-if="format === 'csv'">Le CSV ne contient que des recettes : pas de cookbooks, images, favoris, commentaires ou planning. Il utilise des lignes séparées pour les ingrédients, étapes et tags et n’est pas compatible avec le JSON.</li>
      </ul>
    </aside>

    <div class="file-picker">
      <label for="import-file">Fichier {{ format.toUpperCase() }}</label>
      <input id="import-file" ref="fileInput" type="file" :accept="format === 'csv' ? '.csv,text/csv' : '.json,application/json'" :disabled="isUploading" @change="selectFile">
      <div v-if="selectedFile" class="selected-file">
        <span><strong>{{ selectedFile.name }}</strong> · {{ (selectedFile.size / 1024 / 1024).toFixed(2) }} Mo</span>
        <button type="button" :disabled="isUploading" @click="clearFile($refs.fileInput as HTMLInputElement)">Retirer</button>
      </div>
    </div>

    <div v-if="errorMessage" class="error-summary" role="alert" aria-live="assertive">
      <strong>{{ errorMessage }}</strong>
      <ul v-if="errors.length" class="error-list">
        <li v-for="(error, index) in errors" :key="`${error.path}-${error.code}-${index}`">
          <code>{{ error.path || 'document' }}</code>
          <span>{{ error.message }}</span>
          <small>{{ error.code }}</small>
        </li>
      </ul>
    </div>

    <div v-if="isUploading" class="upload-status" role="status" aria-live="polite">
      <span class="spinner" aria-hidden="true" />
      Validation et import du fichier en cours…
    </div>

    <section v-if="preview" class="preview" aria-labelledby="preview-title">
      <h2 id="preview-title">Prévisualisation avant import</h2>
      <p>Vérifiez les éléments détectés avant de confirmer l’écriture dans votre compte.</p>
      <table>
        <caption>Objets reconnus</caption>
        <thead><tr><th>Type</th><th>Nom</th><th>Chemin</th></tr></thead>
        <tbody>
          <tr v-for="object in preview.objects" :key="object.path">
            <td>{{ object.type }}</td><td>{{ object.title ?? object.name ?? object.id }}</td><td><code>{{ object.path }}</code></td>
          </tr>
          <tr v-if="preview.objects.length === 0"><td colspan="3">Aucun objet reconnu.</td></tr>
        </tbody>
      </table>
      <div v-if="preview.warnings.length" class="preview-warnings" role="note">
        <strong>Avertissements</strong>
        <ul><li v-for="warning in preview.warnings" :key="`${warning.path}-${warning.code}`"><code>{{ warning.path || 'document' }}</code> — {{ warning.message }}</li></ul>
      </div>
      <div v-if="preview.errors.length" class="error-summary" role="alert">
        <strong>Import impossible tant que ces erreurs ne sont pas corrigées</strong>
        <ul class="error-list"><li v-for="error in preview.errors" :key="`${error.path}-${error.code}`"><code>{{ error.path || 'document' }}</code> — {{ error.message }}</li></ul>
      </div>
      <div v-if="preview.duplicates.length" class="preview-duplicates" role="note">
        <strong>Doublons potentiels</strong>
        <ul class="duplicate-list"><li v-for="duplicate in preview.duplicates" :key="`${duplicate.path}-${duplicate.type}`"><code>{{ duplicate.path }}</code> — {{ duplicate.reason }}</li></ul>
      </div>
      <div class="preview-actions">
        <button type="button" class="secondary-button" :disabled="isUploading" @click="cancelPreview">Modifier le fichier</button>
        <button type="button" class="import-button" :disabled="isUploading || preview.errors.length > 0" @click="confirmImport">Confirmer et importer</button>
      </div>
    </section>

    <section v-if="report" class="result" aria-labelledby="result-title" role="status">
      <h2 id="result-title">Import terminé</h2>
      <dl>
        <div v-if="report.cookbooks !== undefined"><dt>Cookbooks importés</dt><dd>{{ report.cookbooks }}</dd></div>
        <div><dt>Recettes importées</dt><dd>{{ report.recipes }}</dd></div>
        <div><dt>Doublons ignorés</dt><dd>{{ report.duplicates.length }}</dd></div>
      </dl>
      <ul v-if="report.duplicates.length" class="duplicate-list">
        <li v-for="duplicate in report.duplicates" :key="`${duplicate.path}-${duplicate.type}`">
          <code>{{ duplicate.path }}</code> — {{ duplicate.reason }}
        </li>
      </ul>
    </section>

    <button v-if="!preview" class="import-button" type="button" :disabled="selectedFile === null || isUploading" @click="handleImport">
      {{ isUploading ? 'Analyse en cours…' : format === 'json' ? 'Prévisualiser avant import' : 'Importer ce fichier' }}
    </button>
  </main>
</template>

<style scoped>
.import-card { width: min(100% - 2rem, 46rem); margin: 2rem auto; padding: 2rem; border: 1px solid rgba(86, 112, 79, 0.18); border-radius: 1.5rem; background: rgba(255, 253, 248, 0.94); box-shadow: 0 20px 60px rgba(54, 68, 35, 0.1); }
.back-link { color: #395330; font-weight: 700; }
.kicker { margin: 2rem 0 0.35rem; color: #6b7b57; font-size: 0.8rem; font-weight: 700; letter-spacing: 0.14em; text-transform: uppercase; }
h1 { margin: 0 0 1rem; font-size: clamp(1.9rem, 4vw, 2.8rem); }
h2 { margin: 0 0 1rem; font-size: 1.25rem; }
.intro { color: #50634d; line-height: 1.6; }
.warning { margin-top: 1.5rem; padding: 1rem 1.1rem; border: 1px solid #d89b43; border-radius: 0.8rem; background: #fff5df; color: #704414; line-height: 1.5; }
.warning ul { margin-bottom: 0; padding-left: 1.2rem; }
.warning li + li { margin-top: 0.35rem; }
.format-choice { display: flex; gap: 1rem; margin: 1.5rem 0 0; padding: 0; border: 0; color: #243127; font-weight: 700; }
.format-choice legend { margin-bottom: 0.6rem; }
.format-choice label { font-weight: 600; cursor: pointer; }
.file-picker { display: grid; gap: 0.6rem; margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid rgba(86, 112, 79, 0.18); }
.file-picker label { color: #243127; font-weight: 700; }
.file-picker input { width: 100%; padding: 0.65rem; border: 1px solid #b9c5af; border-radius: 0.6rem; background: #fffdf8; font: inherit; }
.selected-file { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: 0.75rem; border-radius: 0.6rem; background: #f1f8ed; color: #395330; }
.selected-file button { border: 0; background: transparent; color: #8f1e1e; font: inherit; font-weight: 700; cursor: pointer; }
.error-summary, .upload-status, .result { margin-top: 1.25rem; padding: 1rem; border-radius: 0.7rem; line-height: 1.45; }
.error-summary { border: 1px solid #d58b8b; background: #fff0f0; color: #8f1e1e; }
.error-list, .duplicate-list { margin: 0.75rem 0 0; padding-left: 1.1rem; }
.error-list li + li, .duplicate-list li + li { margin-top: 0.45rem; }
.error-list small { display: block; opacity: 0.8; }
code { padding: 0.1rem 0.25rem; border-radius: 0.25rem; background: rgba(36, 49, 39, 0.08); }
.upload-status { display: flex; align-items: center; gap: 0.65rem; background: #f1f8ed; color: #395330; }
.spinner { width: 1rem; height: 1rem; border: 2px solid #b9c5af; border-top-color: #395330; border-radius: 50%; animation: spin 0.8s linear infinite; }
.result { border: 1px solid #8ca17b; background: #f1f8ed; color: #395330; }
.preview { margin-top: 1.25rem; padding: 1rem; border: 1px solid #8ca17b; border-radius: 0.7rem; background: #f8fcf5; color: #395330; }
.preview p { margin-top: -0.35rem; }
.preview table { width: 100%; border-collapse: collapse; background: #fffdf8; }
.preview caption { padding: 0.7rem; text-align: left; font-weight: 800; }
.preview th, .preview td { padding: 0.6rem; border: 1px solid #d8e1d2; text-align: left; }
.preview th { background: #eef5ea; }
.preview-warnings, .preview-duplicates { margin-top: 1rem; padding: 0.75rem; border-radius: 0.55rem; background: #fff5df; color: #704414; }
.preview-actions { display: flex; justify-content: flex-end; gap: 0.75rem; margin-top: 1rem; }
.preview-actions .import-button { width: auto; margin-top: 0; }
.secondary-button { padding: 0.85rem 1rem; border: 1px solid #395330; border-radius: 0.65rem; background: transparent; color: #395330; font: inherit; font-weight: 700; cursor: pointer; }
.secondary-button:disabled { cursor: not-allowed; opacity: 0.5; }
.result dl { display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.75rem; margin: 0; }
.result dl div { padding: 0.7rem; border-radius: 0.55rem; background: rgba(255, 253, 248, 0.7); text-align: center; }
.result dt { font-size: 0.85rem; }
.result dd { margin: 0.25rem 0 0; font-size: 1.5rem; font-weight: 800; }
.duplicate-list { margin-bottom: 0; }
.import-button { width: 100%; margin-top: 1.25rem; padding: 0.85rem 1rem; border: 1px solid #395330; border-radius: 0.65rem; background: #395330; color: #fffdf8; font: inherit; font-weight: 700; cursor: pointer; }
.import-button:disabled { cursor: not-allowed; opacity: 0.5; }
@keyframes spin { to { transform: rotate(360deg); } }
</style>
