<script setup lang="ts">
import { computed, nextTick, onMounted, reactive, ref } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';

import { useAuthStore } from '@/stores/auth';
import CookbookInvitationForm from '@/components/CookbookInvitationForm.vue';
import type { Cookbook, CookbookMember, Pagination, Recipe } from '@/utils/cookbooks';
import { addRecipeToCookbook, deleteCookbook, fetchCookbook, fetchCookbookMembers, fetchCookbookRecipes, leaveCookbook, removeCookbookMember, removeRecipeFromCookbook, updateCookbook, updateCookbookMemberRole } from '@/utils/cookbooks';
import { fetchRecipes, type Recipe as PublicRecipe } from '@/utils/recipes';

const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();
const cookbook = ref<Cookbook | null>(null);
const errorMessage = ref('');
const recipes = ref<Recipe[]>([]);
const recipesPagination = ref<Pagination | null>(null);
const recipesError = ref('');
const recipesLoading = ref(true);
const publicRecipes = ref<PublicRecipe[]>([]);
const isAddRecipeVisible = ref(false);
const selectedRecipeId = ref('');
const addRecipeError = ref('');
const isAddingRecipe = ref(false);
const removingRecipeId = ref<string | null>(null);
const members = ref<CookbookMember[]>([]);
const membersPagination = ref<Pagination | null>(null);
const membersError = ref('');
const membersLoading = ref(true);
const roleDrafts = reactive<Record<number, string>>({});
const pendingRoleChange = ref<{ member: CookbookMember; role: string } | null>(null);
const roleUpdateLoading = ref(false);
const roleUpdateError = ref('');
const roleDialog = ref<HTMLElement | null>(null);
const pendingMemberAction = ref<{ type: 'leave' | 'remove'; member: CookbookMember } | null>(null);
const memberActionLoading = ref(false);
const memberActionError = ref('');
const memberActionDialog = ref<HTMLElement | null>(null);
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
const canManageRoles = computed(() => cookbook.value?.member_role === 'owner');
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

async function openAddRecipeForm(): Promise<void> {
  isAddRecipeVisible.value = true;
  if (publicRecipes.value.length > 0) return;

  const result = await fetchRecipes(authStore.tokenType, authStore.accessToken);
  if (result.ok) publicRecipes.value = result.recipes;
  else addRecipeError.value = result.message;
}

async function addSelectedRecipe(): Promise<void> {
  if (!cookbook.value || selectedRecipeId.value === '') return;
  isAddingRecipe.value = true;
  addRecipeError.value = '';
  const result = await addRecipeToCookbook(cookbook.value.id, selectedRecipeId.value, authStore.tokenType, authStore.accessToken);
  if (result.ok) {
    selectedRecipeId.value = '';
    await loadRecipes(recipesPagination.value?.current_page ?? 1);
  } else {
    addRecipeError.value = result.message;
  }
  isAddingRecipe.value = false;
}

async function removeRecipe(recipeId: string): Promise<void> {
  if (!cookbook.value || removingRecipeId.value !== null) return;
  removingRecipeId.value = recipeId;
  addRecipeError.value = '';
  const result = await removeRecipeFromCookbook(cookbook.value.id, recipeId, authStore.tokenType, authStore.accessToken);
  if (result.ok) {
    await loadRecipes(recipesPagination.value?.current_page ?? 1);
  } else {
    addRecipeError.value = result.message;
  }
  removingRecipeId.value = null;
}

async function loadMembers(page = 1): Promise<void> {
  membersLoading.value = true;
  membersError.value = '';
  const result = await fetchCookbookMembers(String(route.params.id), authStore.tokenType, authStore.accessToken, page);

  if (result.ok) {
    members.value = result.data;
    membersPagination.value = result.pagination;
    result.data.forEach((member) => { roleDrafts[member.user.id] = member.role; });
  } else {
    membersError.value = result.message;
  }
  membersLoading.value = false;
}

async function goToMemberPage(page: number): Promise<void> {
  if (membersPagination.value === null || page < 1 || page > membersPagination.value.last_page) return;
  await loadMembers(page);
}

function isCurrentMember(member: CookbookMember): boolean {
  return member.user.id === authStore.user?.id;
}

function isProtectedOwner(member: CookbookMember): boolean {
  return cookbook.value?.owner.id === member.user.id;
}

function roleLabel(role: string): string {
  return {
    owner: 'Propriétaire',
    editor: 'Éditeur',
    reader: 'Lecteur',
    commenter: 'Commentateur',
  }[role] ?? role;
}

async function requestRoleChange(member: CookbookMember): Promise<void> {
  const role = roleDrafts[member.user.id] ?? member.role;
  if (role === member.role) return;

  roleUpdateError.value = '';
  pendingRoleChange.value = { member, role };
  await nextTick();
  roleDialog.value?.focus();
}

function cancelRoleChange(): void {
  if (roleUpdateLoading.value) return;
  if (pendingRoleChange.value) {
    roleDrafts[pendingRoleChange.value.member.user.id] = pendingRoleChange.value.member.role;
  }
  pendingRoleChange.value = null;
  roleUpdateError.value = '';
}

async function confirmRoleChange(): Promise<void> {
  if (!pendingRoleChange.value || !cookbook.value) return;

  roleUpdateLoading.value = true;
  roleUpdateError.value = '';
  const { member, role } = pendingRoleChange.value;
  const result = await updateCookbookMemberRole(
    cookbook.value.id,
    member.user.id,
    role,
    authStore.tokenType,
    authStore.accessToken,
  );

  if (result.ok) {
    const page = membersPagination.value?.current_page ?? 1;
    pendingRoleChange.value = null;
    await loadMembers(page);
    const refreshedCookbook = await fetchCookbook(String(route.params.id), authStore.tokenType, authStore.accessToken);
    if (refreshedCookbook.ok) cookbook.value = refreshedCookbook.cookbook;
  } else {
    roleUpdateError.value = result.message;
  }
  roleUpdateLoading.value = false;
}

function canLeaveMember(member: CookbookMember): boolean {
  return isCurrentMember(member) && cookbook.value?.member_role !== 'owner';
}

function canRemoveMember(member: CookbookMember): boolean {
  return canManageRoles.value && !isCurrentMember(member) && !isProtectedOwner(member);
}

async function requestMemberAction(type: 'leave' | 'remove', member: CookbookMember): Promise<void> {
  if ((type === 'leave' && !canLeaveMember(member)) || (type === 'remove' && !canRemoveMember(member))) return;

  memberActionError.value = '';
  pendingMemberAction.value = { type, member };
  await nextTick();
  memberActionDialog.value?.focus();
}

function cancelMemberAction(): void {
  if (memberActionLoading.value) return;
  pendingMemberAction.value = null;
  memberActionError.value = '';
}

async function confirmMemberAction(): Promise<void> {
  if (!pendingMemberAction.value || !cookbook.value) return;

  memberActionLoading.value = true;
  memberActionError.value = '';
  const { type, member } = pendingMemberAction.value;
  const result = type === 'leave'
    ? await leaveCookbook(cookbook.value.id, authStore.tokenType, authStore.accessToken)
    : await removeCookbookMember(cookbook.value.id, member.user.id, authStore.tokenType, authStore.accessToken);

  if (result.ok) {
    if (type === 'leave') {
      await router.push({ name: 'dashboard' });
      return;
    }

    const page = membersPagination.value?.current_page ?? 1;
    pendingMemberAction.value = null;
    await loadMembers(page);
  } else {
    memberActionError.value = result.message;
  }
  memberActionLoading.value = false;
}

onMounted(async () => {
  const result = await fetchCookbook(String(route.params.id), authStore.tokenType, authStore.accessToken);

  if (result.ok) {
    cookbook.value = result.cookbook;
    await loadRecipes();
    await loadMembers();
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
      <CookbookInvitationForm v-if="canEditName" :cookbook-id="cookbook.id" />
      <section class="members-section" aria-labelledby="members-title">
        <div class="section-heading">
          <h3 id="members-title">Membres</h3>
          <span v-if="membersPagination" class="section-count">{{ membersPagination.total }} membre<span v-if="membersPagination.total !== 1">s</span></span>
        </div>
        <p v-if="membersLoading" role="status">Chargement des membres...</p>
        <p v-else-if="membersError" class="error-summary" role="alert">{{ membersError }}</p>
        <p v-else-if="members.length === 0" class="empty-state">Aucun membre dans ce cookbook.</p>
        <div v-else class="member-list">
          <article v-for="member in members" :key="member.user.id" class="member-item">
            <div class="member-identity">
              <strong>{{ member.user.name }}</strong>
              <span v-if="member.user.email" class="member-email">{{ member.user.email }}</span>
            </div>
            <span class="role-badge">{{ roleLabel(member.role) }}</span>
            <div class="member-actions" aria-label="Actions disponibles">
              <span v-if="isProtectedOwner(member)" class="member-no-action">Propriétaire protégé</span>
              <button v-else-if="canLeaveMember(member)" type="button" class="member-action-button member-leave-button" @click="requestMemberAction('leave', member)">
                Quitter
              </button>
              <span v-else-if="isCurrentMember(member)" class="member-self">Vous</span>
              <template v-else-if="canRemoveMember(member)">
                <button type="button" class="member-action-button member-remove-button" @click="requestMemberAction('remove', member)">
                  Retirer
                </button>
                <form class="member-role-form" @submit.prevent="requestRoleChange(member)">
                  <label class="sr-only" :for="`member-role-${member.user.id}`">Rôle de {{ member.user.name }}</label>
                  <select :id="`member-role-${member.user.id}`" v-model="roleDrafts[member.user.id]" :disabled="roleUpdateLoading">
                    <option v-for="role in ['owner', 'editor', 'reader', 'commenter']" :key="role" :value="role">
                      {{ roleLabel(role) }}
                    </option>
                  </select>
                  <button type="submit" :disabled="roleUpdateLoading || roleDrafts[member.user.id] === member.role">
                    Modifier le rôle
                  </button>
                </form>
              </template>
              <form v-else-if="canManageRoles" class="member-role-form" @submit.prevent="requestRoleChange(member)">
                <label class="sr-only" :for="`member-role-${member.user.id}`">Rôle de {{ member.user.name }}</label>
                <select :id="`member-role-${member.user.id}`" v-model="roleDrafts[member.user.id]" :disabled="roleUpdateLoading">
                  <option v-for="role in ['owner', 'editor', 'reader', 'commenter']" :key="role" :value="role">
                    {{ roleLabel(role) }}
                  </option>
                </select>
                <button type="submit" :disabled="roleUpdateLoading || roleDrafts[member.user.id] === member.role">
                  Modifier le rôle
                </button>
              </form>
              <span v-else class="member-no-action">Aucune action</span>
            </div>
          </article>
          <nav v-if="membersPagination && membersPagination.last_page > 1" class="pagination" aria-label="Pagination des membres">
            <button type="button" :disabled="membersPagination.current_page === 1 || membersLoading" @click="goToMemberPage(membersPagination.current_page - 1)">
              Précédent
            </button>
            <span>Page {{ membersPagination.current_page }} / {{ membersPagination.last_page }}</span>
            <button type="button" :disabled="!membersPagination.has_more_pages || membersLoading" @click="goToMemberPage(membersPagination.current_page + 1)">
              Suivant
            </button>
          </nav>
        </div>
      </section>
      <div v-if="pendingRoleChange" class="role-dialog-backdrop" @click.self="cancelRoleChange">
        <section
          ref="roleDialog"
          class="role-dialog"
          role="dialog"
          aria-modal="true"
          aria-labelledby="role-dialog-title"
          aria-describedby="role-dialog-description"
          tabindex="-1"
          @keydown.esc="cancelRoleChange"
        >
          <h3 id="role-dialog-title">Confirmer le changement de rôle</h3>
          <p id="role-dialog-description">
            Modifier le rôle de {{ pendingRoleChange.member.user.name }} en {{ roleLabel(pendingRoleChange.role) }} ?
          </p>
          <p v-if="roleUpdateError" class="error-summary" role="alert">{{ roleUpdateError }}</p>
          <div class="role-dialog-actions">
            <button type="button" class="edit-button" :disabled="roleUpdateLoading" @click="confirmRoleChange">
              {{ roleUpdateLoading ? 'Enregistrement...' : 'Confirmer' }}
            </button>
            <button type="button" class="cancel-button" :disabled="roleUpdateLoading" @click="cancelRoleChange">Annuler</button>
          </div>
        </section>
      </div>
      <div v-if="pendingMemberAction" class="role-dialog-backdrop" @click.self="cancelMemberAction">
        <section
          ref="memberActionDialog"
          class="role-dialog"
          role="dialog"
          aria-modal="true"
          aria-labelledby="member-action-dialog-title"
          aria-describedby="member-action-dialog-description"
          tabindex="-1"
          @keydown.esc="cancelMemberAction"
        >
          <h3 id="member-action-dialog-title">
            {{ pendingMemberAction.type === 'leave' ? 'Confirmer votre départ' : 'Confirmer le retrait' }}
          </h3>
          <p id="member-action-dialog-description">
            {{ pendingMemberAction.type === 'leave'
              ? 'Vous ne pourrez plus accéder à ce cookbook après votre départ.'
              : `Retirer ${pendingMemberAction.member.user.name} de ce cookbook ?` }}
          </p>
          <p v-if="memberActionError" class="error-summary" role="alert">{{ memberActionError }}</p>
          <div class="role-dialog-actions">
            <button type="button" class="edit-button" :disabled="memberActionLoading" @click="confirmMemberAction">
              {{ memberActionLoading ? 'Traitement...' : 'Confirmer' }}
            </button>
            <button type="button" class="cancel-button" :disabled="memberActionLoading" @click="cancelMemberAction">Annuler</button>
          </div>
        </section>
      </div>
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
        <button v-if="canEditName && !isAddRecipeVisible" type="button" class="add-recipe-button" @click="openAddRecipeForm">
          Ajouter une recette existante
        </button>
        <form v-if="canEditName && isAddRecipeVisible" class="add-recipe-form" @submit.prevent="addSelectedRecipe">
          <label for="recipe-to-add">Ajouter une recette existante</label>
          <select id="recipe-to-add" v-model="selectedRecipeId" :disabled="isAddingRecipe">
            <option value="">Choisir une recette</option>
            <option v-for="recipe in publicRecipes" :key="recipe.id" :value="recipe.id">{{ recipe.title }}</option>
          </select>
          <button type="submit" :disabled="isAddingRecipe || selectedRecipeId === ''">Ajouter</button>
          <p v-if="addRecipeError" class="error-summary" role="alert">{{ addRecipeError }}</p>
        </form>
        <p v-if="recipesLoading" role="status">Chargement des recettes...</p>
        <p v-else-if="recipesError" class="error-summary" role="alert">{{ recipesError }}</p>
        <p v-else-if="recipes.length === 0" class="empty-state">Aucune recette dans ce cookbook.</p>
        <div v-else class="recipe-list">
          <article v-for="recipe in recipes" :key="recipe.id" class="recipe-item">
            <h4><RouterLink :to="{ name: 'recipe-detail', params: { id: recipe.id } }">{{ recipe.title }}</RouterLink></h4>
            <img v-if="recipe.image_url" class="recipe-item-image" :src="recipe.image_url" :alt="'Photo de ' + recipe.title" />
            <h4>{{ recipe.title }}</h4>
            <p v-if="recipe.description">{{ recipe.description }}</p>
            <button v-if="canEditName" type="button" class="remove-recipe-button" :disabled="removingRecipeId !== null" @click="removeRecipe(recipe.id)">
              {{ removingRecipeId === recipe.id ? 'Retrait...' : 'Retirer du cookbook' }}
            </button>
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
.add-recipe-form { display: grid; gap: .5rem; margin: 1rem 0; padding: 1rem; border: 1px solid rgba(86, 112, 79, .18); border-radius: .7rem; }
.add-recipe-button { margin: 1rem 0; padding: .5rem .7rem; border: 1px solid #395330; border-radius: .5rem; background: #395330; color: #fffdf8; font: inherit; font-weight: 700; cursor: pointer; }
.remove-recipe-button { margin-top: .45rem; padding: .4rem .6rem; border: 1px solid #8f1e1e; border-radius: .45rem; background: transparent; color: #8f1e1e; font: inherit; font-size: .85rem; font-weight: 700; cursor: pointer; }
.add-recipe-form label { font-weight: 700; }
.add-recipe-form select, .add-recipe-form button { width: fit-content; padding: .5rem .7rem; border: 1px solid #395330; border-radius: .5rem; font: inherit; }
.add-recipe-form button { background: #395330; color: #fffdf8; font-weight: 700; cursor: pointer; }
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
.members-section { margin-top: 2rem; padding-top: 1.5rem; border-top: 1px solid rgba(86, 112, 79, 0.18); }
.section-heading { display: flex; align-items: baseline; justify-content: space-between; gap: 1rem; }
.section-count { color: #50634d; font-size: 0.9rem; }
.member-list { display: grid; gap: 0.7rem; }
.member-item { display: grid; grid-template-columns: minmax(0, 1fr) auto auto; align-items: center; gap: 1rem; padding: 1rem; border: 1px solid rgba(86, 112, 79, 0.2); border-radius: 0.8rem; }
.member-identity { display: grid; gap: 0.25rem; min-width: 0; }
.member-email { overflow: hidden; color: #50634d; text-overflow: ellipsis; white-space: nowrap; }
.role-badge, .member-self { padding: 0.3rem 0.55rem; border-radius: 999px; background: #edf4e8; color: #395330; font-size: 0.85rem; font-weight: 700; }
.member-actions { color: #50634d; font-size: 0.85rem; text-align: right; }
.member-no-action { white-space: nowrap; }
.member-role-form { display: flex; align-items: center; gap: 0.45rem; }
.member-role-form select { padding: 0.35rem; border: 1px solid #b9c5af; border-radius: 0.45rem; background: #fffdf8; color: #395330; font: inherit; }
.member-role-form button { padding: 0.4rem 0.55rem; border: 1px solid #395330; border-radius: 0.45rem; background: transparent; color: #395330; font: inherit; font-size: 0.8rem; cursor: pointer; }
.member-role-form button:disabled { cursor: not-allowed; opacity: 0.45; }
.member-action-button { padding: 0.4rem 0.55rem; border: 1px solid #395330; border-radius: 0.45rem; background: transparent; color: #395330; font: inherit; font-size: 0.8rem; cursor: pointer; }
.member-action-button:focus-visible, .member-role-form button:focus-visible, .role-dialog button:focus-visible { outline: 3px solid #d98b35; outline-offset: 2px; }
.sr-only { position: absolute; width: 1px; height: 1px; padding: 0; overflow: hidden; clip: rect(0, 0, 0, 0); white-space: nowrap; border: 0; }
.role-dialog-backdrop { position: fixed; inset: 0; z-index: 10; display: grid; place-items: center; padding: 1rem; background: rgba(29, 39, 24, 0.45); }
.role-dialog { width: min(100%, 28rem); padding: 1.5rem; border: 1px solid rgba(86, 112, 79, 0.25); border-radius: 1rem; background: #fffdf8; box-shadow: 0 20px 60px rgba(54, 68, 35, 0.2); }
.role-dialog h3 { margin-top: 0; }
.role-dialog-actions { display: flex; gap: 0.6rem; margin-top: 1rem; }
.role-dialog .cancel-button { padding: 0.55rem 0.75rem; border: 1px solid #395330; border-radius: 0.5rem; background: transparent; color: #395330; font: inherit; cursor: pointer; }
.role-dialog button:disabled { cursor: wait; opacity: 0.6; }
.recipes-section { margin-top: 2rem; padding-top: 1.5rem; border-top: 1px solid rgba(86, 112, 79, 0.18); }
h3 { margin: 0 0 1rem; font-size: 1.5rem; }
.empty-state { color: #50634d; }
.recipe-list { display: grid; gap: 0.7rem; }
.recipe-item { padding: 1rem; border: 1px solid rgba(86, 112, 79, 0.2); border-radius: 0.8rem; }
.recipe-item-image { display: block; width: 100%; max-height: 12rem; margin-bottom: .8rem; object-fit: cover; border-radius: .6rem; }
.recipe-item h4, .recipe-item p { margin: 0; }
.recipe-item p { margin-top: 0.4rem; color: #50634d; line-height: 1.5; }
.pagination { display: flex; align-items: center; justify-content: space-between; gap: 0.75rem; margin-top: 1rem; color: #50634d; font-size: 0.9rem; }
.pagination button { padding: 0.5rem 0.7rem; border: 1px solid #b9c5af; border-radius: 0.5rem; background: transparent; color: #395330; cursor: pointer; }
.pagination button:disabled { cursor: not-allowed; opacity: 0.45; }
@media (max-width: 36rem) {
  .member-item { grid-template-columns: 1fr auto; }
  .member-actions { grid-column: 1 / -1; text-align: left; }
  .member-role-form { flex-wrap: wrap; }
}
</style>
