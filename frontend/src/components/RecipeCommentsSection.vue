<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';

import { createRecipeComment, deleteRecipeComment, fetchRecipeComments, updateRecipeComment, type RecipeComment, type RecipePagination } from '@/utils/recipes';

const props = withDefaults(defineProps<{ recipeId: string; tokenType: string; accessToken: string; currentUserId?: number | null }>(), {
  currentUserId: null,
});
const comments = ref<RecipeComment[]>([]);
const pagination = ref<RecipePagination | null>(null);
const content = ref('');
const loading = ref(true);
const submitting = ref(false);
const errorMessage = ref('');
const fieldError = ref('');
const editingId = ref<string | null>(null);
const editingContent = ref('');
const editingError = ref('');
const editSubmitting = ref(false);
const deletingId = ref<string | null>(null);
const deleteError = ref('');
const replyToId = ref<string | null>(null);
const replyContent = ref('');
const replyError = ref('');
const replySubmitting = ref(false);
const MAX_VISUAL_DEPTH = 3;

const commentRows = computed(() => {
  const byParent = new Map<string | null, RecipeComment[]>();
  comments.value.forEach((comment) => {
    const parent = comment.parent_id && comments.value.some((item) => item.id === comment.parent_id) ? comment.parent_id : null;
    const siblings = byParent.get(parent) ?? [];
    siblings.push(comment);
    byParent.set(parent, siblings);
  });
  const rows: Array<{ comment: RecipeComment; depth: number }> = [];
  const visit = (parentId: string | null, depth: number): void => {
    (byParent.get(parentId) ?? []).forEach((comment) => {
      rows.push({ comment, depth: Math.min(depth, MAX_VISUAL_DEPTH) });
      visit(comment.id, depth + 1);
    });
  };
  visit(null, 0);
  return rows;
});

async function loadComments(page = 1): Promise<void> {
  loading.value = true;
  errorMessage.value = '';
  const result = await fetchRecipeComments(props.recipeId, props.tokenType, props.accessToken, page);
  if (result.ok) {
    comments.value = result.comments;
    pagination.value = result.pagination;
  } else {
    errorMessage.value = result.message;
  }
  loading.value = false;
}

async function submitComment(): Promise<void> {
  fieldError.value = '';
  errorMessage.value = '';
  const trimmed = content.value.trim();
  if (trimmed.length === 0) {
    fieldError.value = 'Le commentaire est requis.';
    return;
  }
  if (trimmed.length > 2000) {
    fieldError.value = 'Le commentaire ne peut pas dépasser 2000 caractères.';
    return;
  }

  submitting.value = true;
  const result = await createRecipeComment(props.recipeId, trimmed, props.tokenType, props.accessToken);
  if (result.ok) {
    content.value = '';
    await loadComments(1);
  } else {
    errorMessage.value = result.message;
    fieldError.value = result.fieldError ?? '';
  }
  submitting.value = false;
}

function startReply(comment: RecipeComment): void {
  replyToId.value = comment.id;
  replyContent.value = '';
  replyError.value = '';
  editingId.value = null;
}

function cancelReply(): void {
  replyToId.value = null;
  replyContent.value = '';
  replyError.value = '';
}

async function submitReply(comment: RecipeComment): Promise<void> {
  replyError.value = '';
  const trimmed = replyContent.value.trim();
  if (trimmed.length === 0) {
    replyError.value = 'La réponse est requise.';
    return;
  }
  if (trimmed.length > 2000) {
    replyError.value = 'La réponse ne peut pas dépasser 2000 caractères.';
    return;
  }
  replySubmitting.value = true;
  const result = await createRecipeComment(props.recipeId, trimmed, props.tokenType, props.accessToken, comment.id);
  if (result.ok) {
    cancelReply();
    await loadComments(1);
  } else {
    replyError.value = result.fieldError ?? result.message;
  }
  replySubmitting.value = false;
}

function canManage(comment: RecipeComment): boolean {
  return props.currentUserId !== null && comment.author.id === props.currentUserId;
}

function startEditing(comment: RecipeComment): void {
  editingId.value = comment.id;
  editingContent.value = comment.content;
  editingError.value = '';
  deleteError.value = '';
}

function cancelEditing(): void {
  editingId.value = null;
  editingContent.value = '';
  editingError.value = '';
}

async function saveEdit(comment: RecipeComment): Promise<void> {
  editingError.value = '';
  const trimmed = editingContent.value.trim();
  if (trimmed.length === 0) {
    editingError.value = 'Le commentaire est requis.';
    return;
  }
  if (trimmed.length > 2000) {
    editingError.value = 'Le commentaire ne peut pas dépasser 2000 caractères.';
    return;
  }

  editSubmitting.value = true;
  const result = await updateRecipeComment(props.recipeId, comment.id, trimmed, props.tokenType, props.accessToken);
  if (result.ok) {
    const index = comments.value.findIndex((item) => item.id === comment.id);
    if (index !== -1) comments.value[index] = result.comment;
    cancelEditing();
  } else {
    editingError.value = result.fieldError ?? result.message;
  }
  editSubmitting.value = false;
}

function askDelete(comment: RecipeComment): void {
  deletingId.value = comment.id;
  deleteError.value = '';
  editingId.value = null;
}

function cancelDelete(): void {
  if (deletingId.value !== null) {
    deletingId.value = null;
    deleteError.value = '';
  }
}

async function confirmDelete(comment: RecipeComment): Promise<void> {
  deleteError.value = '';
  const result = await deleteRecipeComment(props.recipeId, comment.id, props.tokenType, props.accessToken);
  if (result.ok) {
    comments.value = comments.value.filter((item) => item.id !== comment.id);
    deletingId.value = null;
    if (pagination.value) pagination.value = { ...pagination.value, total: Math.max(0, pagination.value.total - 1) };
  } else {
    deleteError.value = result.message;
  }
}

function roleLabel(role: string | null): string {
  return ({ owner: 'Propriétaire', editor: 'Éditeur', commenter: 'Commentateur' }[role ?? ''] ?? role ?? 'Membre');
}

onMounted(() => { void loadComments(); });
</script>

<template>
  <section class="comments-section" aria-labelledby="comments-heading">
    <h3 id="comments-heading">Commentaires</h3>
    <form class="comment-form" @submit.prevent="submitComment">
      <label for="recipe-comment-content">Ajouter un commentaire</label>
      <textarea id="recipe-comment-content" v-model="content" rows="3" maxlength="2000" :disabled="submitting" placeholder="Votre commentaire..." />
      <p v-if="fieldError" class="field-error" role="alert">{{ fieldError }}</p>
      <p v-if="errorMessage" class="comment-error" role="alert">{{ errorMessage }}</p>
      <div class="comment-form-actions">
        <span>{{ content.length }}/2000</span>
        <button type="submit" :disabled="submitting || content.trim().length === 0">{{ submitting ? 'Ajout...' : 'Commenter' }}</button>
      </div>
    </form>
    <p v-if="loading" role="status">Chargement des commentaires...</p>
    <p v-else-if="errorMessage" class="comment-error" role="alert">{{ errorMessage }}</p>
    <p v-else-if="comments.length === 0" class="muted">Aucun commentaire pour le moment.</p>
    <div v-else class="comment-list" role="tree" aria-label="Fil des commentaires">
      <article v-for="row in commentRows" :key="row.comment.id" class="comment-item" role="treeitem" :aria-level="row.depth + 1" :style="{ '--comment-depth': row.depth }">
        <span class="sr-only">{{ row.depth ? `Réponse, niveau ${row.depth}` : 'Commentaire principal' }}</span>
        <img v-if="row.comment.author.avatar_url" class="comment-avatar" :src="row.comment.author.avatar_url" :alt="`Avatar de ${row.comment.author.name}`" />
        <div v-else class="comment-avatar avatar-fallback" aria-hidden="true">{{ row.comment.author.name.charAt(0).toUpperCase() }}</div>
        <div class="comment-content">
          <div class="comment-meta"><strong>{{ row.comment.author.name }}</strong><span>{{ roleLabel(row.comment.author.role) }}</span><time :datetime="row.comment.created_at ?? undefined">{{ row.comment.created_at ? new Date(row.comment.created_at).toLocaleString() : '' }}</time></div>
          <template v-if="editingId === row.comment.id">
            <form class="comment-edit-form" @submit.prevent="saveEdit(row.comment)">
              <label :for="`edit-comment-${row.comment.id}`">Modifier le commentaire</label>
              <textarea :id="`edit-comment-${row.comment.id}`" v-model="editingContent" rows="3" maxlength="2000" :disabled="editSubmitting" />
              <p v-if="editingError" class="field-error" role="alert">{{ editingError }}</p>
              <div class="comment-actions"><button type="submit" :disabled="editSubmitting">{{ editSubmitting ? 'Enregistrement...' : 'Enregistrer' }}</button><button type="button" :disabled="editSubmitting" @click="cancelEditing">Annuler</button></div>
            </form>
          </template>
          <template v-else>
            <p>{{ row.comment.content }} <small v-if="row.comment.edited_at" class="edited-label">(modifié)</small></p>
            <div class="comment-actions">
              <button type="button" @click="startReply(row.comment)">Répondre</button>
              <template v-if="canManage(row.comment)">
                <button type="button" @click="startEditing(row.comment)">Modifier</button>
                <button type="button" @click="askDelete(row.comment)">Supprimer</button>
              </template>
            </div>
            <form v-if="replyToId === row.comment.id" class="comment-reply-form" @submit.prevent="submitReply(row.comment)">
              <label :for="`reply-comment-${row.comment.id}`">Répondre à {{ row.comment.author.name }}</label>
              <textarea :id="`reply-comment-${row.comment.id}`" v-model="replyContent" rows="3" maxlength="2000" :disabled="replySubmitting" />
              <p v-if="replyError" class="field-error" role="alert">{{ replyError }}</p>
              <div class="comment-actions"><button type="submit" :disabled="replySubmitting">{{ replySubmitting ? 'Envoi...' : 'Envoyer la réponse' }}</button><button type="button" :disabled="replySubmitting" @click="cancelReply">Annuler</button></div>
            </form>
            <div v-if="deletingId === row.comment.id" class="delete-comment-confirmation">
              <span>Supprimer ce commentaire ?</span>
              <button type="button" @click="confirmDelete(row.comment)">Confirmer</button>
              <button type="button" @click="cancelDelete">Annuler</button>
              <p v-if="deleteError" class="comment-error" role="alert">{{ deleteError }}</p>
            </div>
          </template>
        </div>
      </article>
    </div>
    <nav v-if="pagination && pagination.last_page > 1" class="comment-pagination" aria-label="Pagination des commentaires">
      <button type="button" :disabled="loading || pagination.current_page === 1" @click="loadComments(pagination!.current_page - 1)">Précédent</button>
      <span>Page {{ pagination.current_page }} / {{ pagination.last_page }}</span>
      <button type="button" :disabled="loading || !pagination.has_more_pages" @click="loadComments(pagination!.current_page + 1)">Suivant</button>
    </nav>
  </section>
</template>

<style scoped>
.comments-section { margin-top: 2rem; padding-top: 1.2rem; border-top: 1px solid rgba(86,112,79,.18); }
.comment-form { display: grid; gap: .55rem; margin: 1rem 0; padding: 1rem; border: 1px solid rgba(86,112,79,.2); border-radius: .8rem; }
.comment-form label { font-weight: 700; }
.comment-form textarea { resize: vertical; padding: .7rem; border: 1px solid #b9c5af; border-radius: .5rem; font: inherit; }
.comment-form-actions, .comment-pagination { display: flex; align-items: center; justify-content: space-between; gap: .75rem; color: #50634d; font-size: .85rem; }
.comment-form-actions button, .comment-pagination button { padding: .5rem .7rem; border: 1px solid #395330; border-radius: .5rem; background: #395330; color: #fffdf8; font: inherit; font-weight: 700; cursor: pointer; }
.comment-pagination button { background: transparent; color: #395330; }
button:disabled { cursor: not-allowed; opacity: .5; }
.comment-list { display: grid; gap: .7rem; }
.comment-item { display: flex; gap: .75rem; padding: 1rem; border: 1px solid rgba(86,112,79,.2); border-radius: .8rem; }
.comment-item { margin-inline-start: min(calc(var(--comment-depth, 0) * 2rem), 6rem); }
.comment-avatar { flex: 0 0 2.5rem; width: 2.5rem; height: 2.5rem; border-radius: 50%; object-fit: cover; }
.avatar-fallback { display: grid; place-items: center; background: #edf4e8; color: #395330; font-weight: 700; }
.comment-content { min-width: 0; flex: 1; }
.comment-meta { display: flex; flex-wrap: wrap; align-items: baseline; gap: .5rem; color: #50634d; font-size: .85rem; }
.comment-meta strong { color: #263b22; }
.comment-meta time { margin-left: auto; }
.comment-content p { margin: .4rem 0 0; white-space: pre-wrap; overflow-wrap: anywhere; line-height: 1.5; }
.comment-edit-form { display: grid; gap: .5rem; margin-top: .5rem; }
.comment-reply-form { display: grid; gap: .5rem; margin-top: .6rem; padding: .7rem; border-inline-start: 3px solid #b9c5af; }
.comment-edit-form textarea { resize: vertical; padding: .7rem; border: 1px solid #b9c5af; border-radius: .5rem; font: inherit; }
.comment-actions { display: flex; flex-wrap: wrap; gap: .5rem; margin-top: .5rem; }
.comment-actions button, .delete-comment-confirmation button { padding: .4rem .6rem; border: 1px solid #395330; border-radius: .4rem; background: transparent; color: #395330; font: inherit; cursor: pointer; }
.delete-comment-confirmation { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem; margin-top: .6rem; padding: .6rem; border: 1px solid #e2b3ad; border-radius: .5rem; color: #6d4140; }
.edited-label { color: #50634d; }
.comment-error, .field-error { margin: 0; color: #8f1e1e; }
.muted { color: #50634d; }
.sr-only { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0, 0, 0, 0); white-space: nowrap; border: 0; }
</style>
