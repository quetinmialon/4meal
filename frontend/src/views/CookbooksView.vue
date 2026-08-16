<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { RouterLink } from 'vue-router';

import Avatar from '@/components/Avatar.vue';
import Badge from '@/components/Badge.vue';
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
          <RouterLink v-for="cookbook in cookbooks" :key="cookbook.id" class="cookbook-card" :to="{ name: 'cookbook', params: { id: cookbook.id } }">
            <div class="cookbook-card-top">
              <img v-if="cookbook.image_url" class="cookbook-image" :src="cookbook.image_url" :alt="`Image de ${cookbook.name}`" />
              <Avatar v-else :src="cookbook.owner.avatar_url ?? null" :name="cookbook.name" size="large" />
              <Badge>{{ cookbook.member_role ?? 'membre' }}</Badge>
            </div>
            <h3>{{ cookbook.name }}</h3>
            <p v-if="cookbook.description" class="cookbook-description">{{ cookbook.description }}</p>
            <p class="cookbook-owner">Géré par {{ cookbook.owner.name }}</p>
            <span class="open-cookbook">Ouvrir le cookbook</span>
          </RouterLink>
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
.cookbook-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 1rem; }
.cookbook-card { display: flex; min-height: 15rem; flex-direction: column; gap: .7rem; padding: 1.1rem; border: 1px solid rgba(86,112,79,.2); border-radius: 1rem; background: rgba(255,253,248,.92); color: #243127; text-decoration: none; box-shadow: 0 10px 30px rgba(54,68,35,.06); }.cookbook-card:hover, .cookbook-card:focus-visible { border-color: #6b7b57; }.cookbook-card:focus-visible { outline: 3px solid rgba(86,112,79,.3); outline-offset: 3px; }
.cookbook-card-top { display: flex; align-items: flex-start; justify-content: space-between; gap: .75rem; }.cookbook-image { width: 5rem; height: 5rem; border-radius: .8rem; object-fit: cover; }.cookbook-card h3 { margin: .3rem 0 0; font-size: 1.25rem; }.cookbook-description { display: -webkit-box; margin: 0; overflow: hidden; color: #50634d; line-height: 1.5; -webkit-box-orient: vertical; -webkit-line-clamp: 3; }.cookbook-owner { margin: auto 0 0; color: #50634d; font-size: .9rem; }.open-cookbook { color: #395330; font-weight: 700; }.sr-only { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0,0,0,0); white-space: nowrap; border: 0; }
@media (max-width: 58rem) { .cookbook-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
@media (max-width: 38rem) { .cookbook-grid { grid-template-columns: 1fr; } }
</style>
