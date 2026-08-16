<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { RouterLink, useRoute } from 'vue-router';

import { useAuthStore } from '@/stores/auth';
import { fetchRecipeHistory, type RecipeAudit } from '@/utils/recipes';

const route = useRoute();
const authStore = useAuthStore();
const audits = ref<RecipeAudit[]>([]);
const nextCursor = ref<string | null>(null);
const previousCursor = ref<string | null>(null);
const isLoading = ref(true);
const errorMessage = ref('');

const fieldLabels: Record<string, string> = {
  title: 'du titre',
  description: 'de la description',
  prep_time_minutes: 'du temps de préparation',
  cook_time_minutes: 'du temps de cuisson',
  rest_time_minutes: 'du temps de repos',
  servings: 'du nombre de portions',
  visibility: 'de la visibilité',
  difficulty: 'de la difficulté',
  notes: 'des notes',
  source: 'de la source',
  ingredients_count: 'des ingrédients',
  steps_count: 'des étapes',
  tags_count: 'des tags',
};

async function loadHistory(cursor: string | null = null): Promise<void> {
  isLoading.value = true;
  errorMessage.value = '';
  const result = await fetchRecipeHistory(String(route.params.id), authStore.tokenType, authStore.accessToken, cursor);
  if (result.ok) {
    audits.value = result.audits;
    nextCursor.value = result.pagination.next_cursor;
    previousCursor.value = result.pagination.previous_cursor;
  } else {
    errorMessage.value = result.message;
  }
  isLoading.value = false;
}

function typeLabel(type: RecipeAudit['type']): string {
  return ({ created: 'Création', updated: 'Modification', deleted: 'Suppression' }[type] ?? 'Changement');
}

function typeDescription(type: RecipeAudit['type']): string {
  return ({ created: 'Recette créée', updated: 'Recette modifiée', deleted: 'Recette supprimée' }[type] ?? 'Recette modifiée');
}

function changeSummary(audit: RecipeAudit): string {
  if (audit.type !== 'updated') return typeDescription(audit.type);
  const fields = Object.keys({ ...audit.old_values, ...audit.new_values })
    .map((field) => fieldLabels[field] ?? field)
    .filter((field, index, all) => all.indexOf(field) === index);
  return fields.length > 0 ? `Modification ${fields.join(', ')}` : 'Modification sans détail disponible';
}

function formatDate(date: string | null): string {
  if (!date) return 'Date inconnue';
  return new Intl.DateTimeFormat('fr-FR', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(date));
}

onMounted(() => { void loadHistory(); });
</script>

<template>
  <main class="history-page">
    <RouterLink class="back-link" :to="{ name: 'recipe-detail', params: { id: route.params.id } }">Retour à la recette</RouterLink>
    <p class="kicker">Recette</p>
    <h2>Historique des modifications</h2>
    <p class="intro">Consultez les changements apportés à cette recette.</p>

    <p v-if="isLoading" class="state-message" role="status">Chargement de l’historique...</p>
    <section v-else-if="errorMessage" class="state-message error-summary" role="alert">
      {{ errorMessage }}
      <button type="button" @click="loadHistory()">Réessayer</button>
    </section>
    <section v-else-if="audits.length === 0" class="empty-state">
      <h3>Aucun changement enregistré</h3>
      <p>L’historique de cette recette est encore vide.</p>
    </section>
    <section v-else class="history-list" aria-label="Historique des modifications">
      <article v-for="audit in audits" :key="audit.id" class="history-item">
        <div class="history-heading">
          <span class="change-type">{{ typeLabel(audit.type) }}</span>
          <time :datetime="audit.created_at ?? undefined">{{ formatDate(audit.created_at) }}</time>
        </div>
        <p class="change-summary">{{ changeSummary(audit) }}</p>
        <p class="change-author">Par {{ audit.author?.name ?? 'Utilisateur supprimé' }}</p>
      </article>
    </section>
    <nav v-if="!isLoading && !errorMessage && (nextCursor || previousCursor)" class="history-pagination" aria-label="Pagination de l’historique">
      <button type="button" :disabled="isLoading || !previousCursor" @click="loadHistory(previousCursor)">Précédent</button>
      <button type="button" :disabled="isLoading || !nextCursor" @click="loadHistory(nextCursor)">Suivant</button>
    </nav>
  </main>
</template>

<style scoped>
.history-page { width: 100%; max-width: 76rem; margin: 0 auto; }
.back-link { color: #395330; font-weight: 700; }
.kicker { margin: 2rem 0 .35rem; color: #6b7b57; font-size: .8rem; font-weight: 700; letter-spacing: .14em; text-transform: uppercase; }
h2 { margin: 0; font-size: clamp(1.9rem, 4vw, 2.8rem); }
.intro, .state-message, .empty-state p { color: #50634d; line-height: 1.6; }
.state-message { margin-top: 1.5rem; }
.error-summary { padding: 1rem; border-radius: .8rem; color: #8f1e1e; background: #fff0ee; }
.error-summary button { display: block; margin-top: .75rem; padding: .5rem .7rem; border: 1px solid #8f1e1e; border-radius: .5rem; background: transparent; color: #8f1e1e; font: inherit; font-weight: 700; cursor: pointer; }
.empty-state { margin-top: 1.5rem; padding: 1.2rem; border: 1px solid rgba(86,112,79,.18); border-radius: .9rem; background: rgba(255,253,248,.92); }
.empty-state h3 { margin: 0; }
.history-list { display: grid; gap: .8rem; margin-top: 1.5rem; }
.history-item { padding: 1rem 1.1rem; border: 1px solid rgba(86,112,79,.2); border-radius: .9rem; background: rgba(255,253,248,.92); }
.history-heading { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: .6rem; color: #50634d; font-size: .9rem; }
.change-type { color: #395330; font-weight: 700; }
.change-summary { margin: .55rem 0 .25rem; color: #263b22; font-weight: 700; }
.change-author { margin: 0; color: #50634d; font-size: .9rem; }
.history-pagination { display: flex; justify-content: space-between; gap: .7rem; margin-top: 1rem; }
.history-pagination button { padding: .5rem .7rem; border: 1px solid #395330; border-radius: .5rem; background: transparent; color: #395330; font: inherit; font-weight: 700; cursor: pointer; }
button:disabled { cursor: not-allowed; opacity: .5; }
</style>
