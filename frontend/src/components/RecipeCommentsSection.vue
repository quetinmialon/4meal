<script setup lang="ts">
import { onMounted, ref } from 'vue';

import { createRecipeComment, fetchRecipeComments, type RecipeComment, type RecipePagination } from '@/utils/recipes';

const props = defineProps<{ recipeId: string; tokenType: string; accessToken: string }>();
const comments = ref<RecipeComment[]>([]);
const pagination = ref<RecipePagination | null>(null);
const content = ref('');
const loading = ref(true);
const submitting = ref(false);
const errorMessage = ref('');
const fieldError = ref('');

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
    <div v-else class="comment-list">
      <article v-for="comment in comments" :key="comment.id" class="comment-item">
        <img v-if="comment.author.avatar_url" class="comment-avatar" :src="comment.author.avatar_url" :alt="`Avatar de ${comment.author.name}`" />
        <div v-else class="comment-avatar avatar-fallback" aria-hidden="true">{{ comment.author.name.charAt(0).toUpperCase() }}</div>
        <div class="comment-content">
          <div class="comment-meta"><strong>{{ comment.author.name }}</strong><span>{{ roleLabel(comment.author.role) }}</span><time :datetime="comment.created_at ?? undefined">{{ comment.created_at ? new Date(comment.created_at).toLocaleString() : '' }}</time></div>
          <p>{{ comment.content }}</p>
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
.comment-avatar { flex: 0 0 2.5rem; width: 2.5rem; height: 2.5rem; border-radius: 50%; object-fit: cover; }
.avatar-fallback { display: grid; place-items: center; background: #edf4e8; color: #395330; font-weight: 700; }
.comment-content { min-width: 0; flex: 1; }
.comment-meta { display: flex; flex-wrap: wrap; align-items: baseline; gap: .5rem; color: #50634d; font-size: .85rem; }
.comment-meta strong { color: #263b22; }
.comment-meta time { margin-left: auto; }
.comment-content p { margin: .4rem 0 0; white-space: pre-wrap; overflow-wrap: anywhere; line-height: 1.5; }
.comment-error, .field-error { margin: 0; color: #8f1e1e; }
.muted { color: #50634d; }
</style>
