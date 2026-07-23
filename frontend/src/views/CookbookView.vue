<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';

import { useAuthStore } from '@/stores/auth';
import type { Cookbook, Pagination, Recipe } from '@/utils/cookbooks';
import { deleteCookbook, fetchCookbook, fetchCookbookRecipes, updateCookbook } from '@/utils/cookbooks';

const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();
const cookbook = ref<Cookbook | null>(null);
const errorMessage = ref('');
const recipes = ref<Recipe[]>([]);
const recipesPagination = ref<Pagination | null>(null);
const recipesError = ref('');
const recipesLoading = ref(true);
const isEditingName = ref(false);
const isSavingName = ref(false);
const editName = reactive({ value: '', slug: '', description: '', image: null as File | null });
const editNameError = ref('');
const editSlugError = ref('');
const editDescriptionError = ref('');
const editImageError = ref('');
const editGlobalError = ref('');
const canEditName = computed(() => cookbook.value?.member_role === 'owner' || cookbook.value?.member_role === 'editor');
const canDelete = computed(() => cookbook.value?.member_role === 'owner');
const isDeleteConfirmationVisible = ref(false);
const isDeleting = ref(false);
const deleteConfirmation = ref('');
const deleteError = ref('');
const deleteFieldError = ref('');

function openDeleteConfirmation(): void {
  if (!canDelete.value) return;
  deleteConfirmation.value = '';
  deleteError.value = '';
  deleteFieldError.value = '';
  isDeleteConfirmationVisible.value = true;
}

function cancelDelete(): void {
  if (isDeleting.value) return;
  isDeleteConfirmationVisible.value = false;
  deleteConfirmation.value = '';
  deleteError.value = '';
  deleteFieldError.value = '';
}

async function confirmDelete(): Promise<void> {
  deleteError.value = '';
  deleteFieldError.value = '';

  if (!cookbook.value) return;
  if (deleteConfirmation.value.trim() !== cookbook.value.name) {
    deleteFieldError.value = 'Saisissez exactement le nom du cookbook pour confirmer.';
    return;
  }

  isDeleting.value = true;
  const result = await deleteCookbook(
    cookbook.value.id,
    deleteConfirmation.value,
    authStore.tokenType,
    authStore.accessToken,
  );

  if (result.ok) {
    await router.push({ name: 'dashboard' });
    return;
  }

  deleteError.value = result.message;
  deleteFieldError.value = result.fieldErrors.confirmation ?? '';
  isDeleting.value = false;
}

function startEditingName(): void {
  if (!cookbook.value || !canEditName.value) return;
  editName.value = cookbook.value.name;
  editName.slug = cookbook.value.slug ?? '';
  editName.description = cookbook.value.description ?? '';
  editName.image = null;
  editNameError.value = '';
  editSlugError.value = '';
  editDescriptionError.value = '';
  editImageError.value = '';
  editGlobalError.value = '';
  isEditingName.value = true;
}

function cancelEditingName(): void {
  isEditingName.value = false;
  editNameError.value = '';
  editSlugError.value = '';
  editDescriptionError.value = '';
  editImageError.value = '';
  editGlobalError.value = '';
}

async function saveName(): Promise<void> {
  editNameError.value = '';
  editGlobalError.value = '';

  if (editName.value.trim() === '') {
    editNameError.value = 'Le nom du cookbook est requis.';
    return;
  }

  if (!cookbook.value) return;
  isSavingName.value = true;
  const result = await updateCookbook(
    cookbook.value.id,
    {
      name: editName.value,
      slug: editName.slug,
      description: editName.description,
      image: editName.image,
    },
    authStore.tokenType,
    authStore.accessToken,
  );

  if (result.ok) {
    cookbook.value = result.cookbook;
    isEditingName.value = false;
  } else {
    editGlobalError.value = result.message;
    editNameError.value = result.fieldErrors.name ?? '';
    editSlugError.value = result.fieldErrors.slug ?? '';
    editDescriptionError.value = result.fieldErrors.description ?? '';
    editImageError.value = result.fieldErrors.image ?? '';
  }
  isSavingName.value = false;
}

function handleEditImageChange(event: Event): void {
  const input = event.target as HTMLInputElement;
  const file = input.files?.[0] ?? null;
  editName.image = file;
  editImageError.value = '';

  if (file === null) return;

  if (!['image/png', 'image/jpeg'].includes(file.type)) {
    editImageError.value = 'Le fichier doit être au format PNG ou JPEG.';
    input.value = '';
    editName.image = null;
  } else if (file.size > 5 * 1024 * 1024) {
    editImageError.value = 'L’image ne doit pas dépasser 5 Mo.';
    input.value = '';
    editName.image = null;
  }
}

async function loadRecipes(page = 1): Promise<void> {
  recipesLoading.value = true;
  recipesError.value = '';
  const result = await fetchCookbookRecipes(String(route.params.id), authStore.tokenType, authStore.accessToken, page);

  if (result.ok) {
    recipes.value = result.data;
    recipesPagination.value = result.pagination;
  } else {
    recipesError.value = result.message;
  }
  recipesLoading.value = false;
}

async function goToRecipePage(page: number): Promise<void> {
  if (recipesPagination.value === null || page < 1 || page > recipesPagination.value.last_page) return;
  await loadRecipes(page);
}

onMounted(async () => {
  const result = await fetchCookbook(String(route.params.id), authStore.tokenType, authStore.accessToken);

  if (result.ok) {
    cookbook.value = result.cookbook;
    await loadRecipes();
    return;
  }

  errorMessage.value = result.message;
});
</script>

<template>
  <main class="cookbook-card">
    <RouterLink class="back-link" :to="{ name: 'dashboard' }">Retour aux cookbooks</RouterLink>
    <p v-if="errorMessage" class="error-summary" role="alert">{{ errorMessage }}</p>
    <template v-else-if="cookbook">
      <p class="kicker">Cookbook</p>
      <img
        v-if="cookbook.image_url"
        class="cookbook-image"
        :src="cookbook.image_url"
        :alt="`Image de ${cookbook.name}`"
      />
      <div v-if="!isEditingName" class="name-heading">
        <h2>{{ cookbook.name }}</h2>
        <button v-if="canEditName" type="button" class="edit-button" @click="startEditingName">
          Modifier le cookbook
        </button>
      </div>
      <form v-else class="edit-name-form" novalidate @submit.prevent="saveName">
        <label for="cookbook-name-edit-input">Nom du cookbook</label>
        <input
          id="cookbook-name-edit-input"
          v-model="editName.value"
          maxlength="255"
          :aria-invalid="editNameError ? 'true' : 'false'"
          :aria-describedby="editNameError ? 'cookbook-name-edit-error' : undefined"
        />
        <p v-if="editNameError" id="cookbook-name-edit-error" class="field-error" role="alert">{{ editNameError }}</p>
        <label for="cookbook-slug-edit-input">Slug</label>
        <input id="cookbook-slug-edit-input" v-model="editName.slug" maxlength="255" />
        <p v-if="editSlugError" class="field-error" role="alert">{{ editSlugError }}</p>
        <label for="cookbook-description-edit-input">Description</label>
        <textarea id="cookbook-description-edit-input" v-model="editName.description" rows="4" />
        <p v-if="editDescriptionError" class="field-error" role="alert">{{ editDescriptionError }}</p>
        <label for="cookbook-image-edit-input">Nouvelle image (PNG/JPEG, 5 Mo maximum)</label>
        <input id="cookbook-image-edit-input" type="file" accept="image/png,image/jpeg" @change="handleEditImageChange" />
        <p v-if="editImageError" class="field-error" role="alert">{{ editImageError }}</p>
        <p v-if="editGlobalError" class="error-summary" role="alert">{{ editGlobalError }}</p>
        <div class="edit-actions">
          <button type="submit" :disabled="isSavingName">{{ isSavingName ? 'Enregistrement...' : 'Enregistrer' }}</button>
          <button type="button" class="cancel-button" :disabled="isSavingName" @click="cancelEditingName">Annuler</button>
        </div>
      </form>
      <p class="detail">Proprietaire : {{ cookbook.owner.name }}</p>
      <p v-if="cookbook.description" class="detail">{{ cookbook.description }}</p>
      <p class="role-line">Votre rôle : <strong>{{ cookbook.member_role ?? 'membre' }}</strong></p>
      <section v-if="canDelete" class="danger-section" aria-labelledby="delete-title">
        <h3 id="delete-title">Zone dangereuse</h3>
        <button v-if="!isDeleteConfirmationVisible" type="button" class="delete-button" @click="openDeleteConfirmation">
          Supprimer ce cookbook
        </button>
        <form v-else class="delete-form" novalidate @submit.prevent="confirmDelete">
          <p class="warning">Cette action supprimera définitivement le cookbook, ses recettes et ses membres.</p>
          <label for="delete-confirmation-input">Saisissez le nom « {{ cookbook.name }} » pour confirmer</label>
          <input
            id="delete-confirmation-input"
            v-model="deleteConfirmation"
            type="text"
            autocomplete="off"
            :disabled="isDeleting"
            :aria-invalid="deleteFieldError ? 'true' : 'false'"
            :aria-describedby="deleteFieldError ? 'delete-confirmation-error' : undefined"
          />
          <p v-if="deleteFieldError" id="delete-confirmation-error" class="field-error" role="alert">{{ deleteFieldError }}</p>
          <p v-if="deleteError" class="error-summary" role="alert">{{ deleteError }}</p>
          <div class="delete-actions">
            <button type="submit" class="delete-button" :disabled="isDeleting">
              {{ isDeleting ? 'Suppression...' : 'Confirmer la suppression' }}
            </button>
            <button type="button" class="cancel-button" :disabled="isDeleting" @click="cancelDelete">Annuler</button>
          </div>
        </form>
      </section>
      <section class="recipes-section" aria-labelledby="recipes-title">
        <h3 id="recipes-title">Recettes</h3>
        <p v-if="recipesLoading" role="status">Chargement des recettes...</p>
        <p v-else-if="recipesError" class="error-summary" role="alert">{{ recipesError }}</p>
        <p v-else-if="recipes.length === 0" class="empty-state">Aucune recette dans ce cookbook.</p>
        <div v-else class="recipe-list">
          <article v-for="recipe in recipes" :key="recipe.id" class="recipe-item">
            <h4>{{ recipe.title }}</h4>
            <p v-if="recipe.description">{{ recipe.description }}</p>
          </article>
          <nav v-if="recipesPagination && recipesPagination.last_page > 1" class="pagination" aria-label="Pagination des recettes">
            <button type="button" :disabled="recipesPagination.current_page === 1" @click="goToRecipePage(recipesPagination.current_page - 1)">
              Precedent
            </button>
            <span>Page {{ recipesPagination.current_page }} / {{ recipesPagination.last_page }}</span>
            <button type="button" :disabled="!recipesPagination.has_more_pages" @click="goToRecipePage(recipesPagination.current_page + 1)">
              Suivant
            </button>
          </nav>
        </div>
      </section>
    </template>
    <p v-else class="loading" role="status">Chargement...</p>
  </main>
</template>

<style scoped>
.cookbook-card { margin: 0 auto; max-width: 42rem; padding: 2rem; border: 1px solid rgba(86, 112, 79, 0.18); border-radius: 1.5rem; background: rgba(255, 253, 248, 0.92); box-shadow: 0 20px 60px rgba(54, 68, 35, 0.1); }
.cookbook-image { display: block; width: 100%; max-height: 15rem; margin: 1rem 0; object-fit: cover; border-radius: 0.8rem; }
.back-link { color: #395330; font-weight: 700; }
.kicker { margin: 2rem 0 0.35rem; color: #6b7b57; font-size: 0.8rem; font-weight: 700; letter-spacing: 0.14em; text-transform: uppercase; }
h2 { margin: 0; font-size: clamp(1.9rem, 4vw, 2.8rem); }
.detail, .loading { margin-top: 1rem; color: #50634d; }
.error-summary { margin-top: 2rem; color: #8f1e1e; }
.name-heading { display: flex; align-items: center; justify-content: space-between; gap: 1rem; }
.name-heading h2 { margin: 0; }
.edit-button, .edit-actions button { padding: 0.55rem 0.75rem; border: 1px solid #395330; border-radius: 0.5rem; background: #395330; color: #fffdf8; font: inherit; font-weight: 700; cursor: pointer; }
.edit-name-form { display: grid; gap: 0.6rem; }
.edit-name-form label { font-weight: 700; }
.edit-name-form input { padding: 0.7rem; border: 1px solid #b9c5af; border-radius: 0.5rem; font: inherit; }
.field-error { margin: 0; color: #8f1e1e; }
.edit-name-form .error-summary { margin: 0; }
.edit-actions { display: flex; gap: 0.6rem; }
.edit-actions button:disabled { cursor: wait; opacity: 0.6; }
.edit-actions .cancel-button { background: transparent; color: #395330; }
.danger-section { margin-top: 2rem; padding-top: 1.5rem; border-top: 1px solid #e2b3ad; }
.danger-section h3 { color: #8f1e1e; }
.delete-button { padding: 0.6rem 0.8rem; border: 1px solid #8f1e1e; border-radius: 0.5rem; background: #8f1e1e; color: #fffdf8; font: inherit; font-weight: 700; cursor: pointer; }
.delete-form { display: grid; gap: 0.6rem; }
.warning { margin: 0; color: #8f1e1e; line-height: 1.5; }
.delete-form label { font-weight: 700; }
.delete-form input { padding: 0.7rem; border: 1px solid #d49b93; border-radius: 0.5rem; font: inherit; }
.delete-form .error-summary { margin: 0; }
.delete-actions { display: flex; gap: 0.6rem; }
.delete-actions button:disabled { cursor: wait; opacity: 0.6; }
.delete-actions .cancel-button { padding: 0.6rem 0.8rem; border: 1px solid #395330; border-radius: 0.5rem; background: transparent; color: #395330; font: inherit; font-weight: 700; cursor: pointer; }
.role-line { margin-top: 0.5rem; color: #395330; }
.recipes-section { margin-top: 2rem; padding-top: 1.5rem; border-top: 1px solid rgba(86, 112, 79, 0.18); }
h3 { margin: 0 0 1rem; font-size: 1.5rem; }
.empty-state { color: #50634d; }
.recipe-list { display: grid; gap: 0.7rem; }
.recipe-item { padding: 1rem; border: 1px solid rgba(86, 112, 79, 0.2); border-radius: 0.8rem; }
.recipe-item h4, .recipe-item p { margin: 0; }
.recipe-item p { margin-top: 0.4rem; color: #50634d; line-height: 1.5; }
.pagination { display: flex; align-items: center; justify-content: space-between; gap: 0.75rem; margin-top: 1rem; color: #50634d; font-size: 0.9rem; }
.pagination button { padding: 0.5rem 0.7rem; border: 1px solid #b9c5af; border-radius: 0.5rem; background: transparent; color: #395330; cursor: pointer; }
.pagination button:disabled { cursor: not-allowed; opacity: 0.45; }
</style>
