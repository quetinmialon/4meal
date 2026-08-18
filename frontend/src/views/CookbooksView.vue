<script setup lang="ts">
import { onMounted, ref } from 'vue';

import CookbookCard from '@/components/CookbookCard.vue';
import CookbookCreateForm from '@/components/CookbookCreateForm.vue';
import EmptyState from '@/components/EmptyState.vue';
import ErrorState from '@/components/ErrorState.vue';
import LoadingState from '@/components/LoadingState.vue';
import PageHeader from '@/components/PageHeader.vue';
import PaginationControls from '@/components/PaginationControls.vue';
import { useAuthStore } from '@/stores/auth';
import { fetchCookbooks, type Cookbook, type Pagination } from '@/utils/cookbooks';
import { useDialogFocus } from '@/utils/dialogFocus';

const authStore = useAuthStore();
const cookbooks = ref<Cookbook[]>([]);
const pagination = ref<Pagination | null>(null);
const isLoading = ref(true);
const errorMessage = ref('');
const isCreateFormVisible = ref(false);
const createDialog = ref<HTMLElement | null>(null);

async function loadCookbooks(page = 1): Promise<void> {
  isLoading.value = true;
  errorMessage.value = '';
  const result = await fetchCookbooks(authStore.tokenType, authStore.accessToken, page);
  if (result.ok) {
    cookbooks.value = result.data;
    pagination.value = result.pagination;
  } else errorMessage.value = result.message;
  isLoading.value = false;
}

function retry(): void { void loadCookbooks(pagination.value?.current_page ?? 1); }
function toggleCreateForm(): void { isCreateFormVisible.value = !isCreateFormVisible.value; }
function closeCreateForm(): void { isCreateFormVisible.value = false; }

useDialogFocus(createDialog, isCreateFormVisible, closeCreateForm);

onMounted(() => { void loadCookbooks(); });
</script>

<template>
  <main class="cookbooks-page">
    <PageHeader title="Mes cookbooks" description="Vos espaces de recettes partagés." eyebrow="Organisation">
      <template #primary>
        <button type="button" class="primary-action" @click="toggleCreateForm">{{ isCreateFormVisible ? 'Fermer' : 'Nouveau cookbook' }}</button>
      </template>
    </PageHeader>

    <div v-if="isCreateFormVisible" class="dialog-backdrop" role="presentation" @click.self="closeCreateForm">
      <section ref="createDialog" class="create-dialog" role="dialog" aria-modal="true" aria-labelledby="create-cookbook-title" tabindex="-1">
        <div class="dialog-heading">
          <h2 id="create-cookbook-title">Nouveau cookbook</h2>
          <button type="button" class="dialog-close" aria-label="Fermer la création du cookbook" @click="closeCreateForm">Fermer</button>
        </div>
        <CookbookCreateForm />
      </section>
    </div>

    <LoadingState v-if="isLoading" label="Chargement des cookbooks..." />
    <ErrorState v-else-if="errorMessage" :message="errorMessage" show-retry @retry="retry" />
    <EmptyState v-else-if="cookbooks.length === 0" title="Aucun cookbook pour le moment." description="Créez un espace pour organiser vos recettes partagées.">
      <template #actions><button type="button" class="primary-action" @click="isCreateFormVisible = true">Nouveau cookbook</button></template>
    </EmptyState>
    <template v-else>
      <section aria-labelledby="cookbooks-list-title">
        <h2 id="cookbooks-list-title" class="sr-only">Cookbooks accessibles</h2>
        <div class="cookbook-grid">
          <CookbookCard v-for="cookbook in cookbooks" :key="cookbook.id" :cookbook="cookbook" />
        </div>
      </section>
      <PaginationControls v-if="pagination && pagination.last_page > 1" :pagination="pagination" :disabled="isLoading" @change="loadCookbooks" />
    </template>
  </main>
</template>

<style scoped>
.cookbooks-page { width: 100%; max-width: 76rem; margin: 0 auto; }
.primary-action { display: inline-flex; align-items: center; justify-content: center; min-height: 2.7rem; padding: .65rem .85rem; border: 1px solid #395330; border-radius: .65rem; background: #395330; color: #fffdf8; font: inherit; font-weight: 700; cursor: pointer; }
.dialog-backdrop { position: fixed; inset: 0; z-index: 30; display: grid; place-items: center; padding: 1rem; overflow-y: auto; background: rgba(36,49,39,.48); }
.create-dialog { width: min(100%, 38rem); max-height: calc(100vh - 2rem); overflow-y: auto; padding: 1.25rem; border: 1px solid rgba(86,112,79,.18); border-radius: 1rem; background: #fffdf8; box-shadow: 0 20px 60px rgba(36,49,39,.25); }
.dialog-heading { display: flex; align-items: center; justify-content: space-between; gap: 1rem; }.dialog-heading h2 { margin: 0; font-size: 1.3rem; }.dialog-close { padding: .55rem .7rem; border: 1px solid #395330; border-radius: .5rem; background: transparent; color: #395330; font: inherit; font-weight: 700; cursor: pointer; }
.cookbook-grid { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 1rem; }
.sr-only { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0,0,0,0); white-space: nowrap; border: 0; }
@media (max-width: 72rem) { .cookbook-grid { grid-template-columns: repeat(4, minmax(0, 1fr)); } }
@media (max-width: 58rem) { .cookbook-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
@media (max-width: 46rem) { .cookbook-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
@media (max-width: 38rem) { .cookbook-grid { grid-template-columns: 1fr; } }
</style>
