<script setup lang="ts">
import { RouterLink } from 'vue-router';

import RecipeFavoriteButton from '@/components/RecipeFavoriteButton.vue';
import RecipeRating from '@/components/RecipeRating.vue';
import type { Recipe } from '@/utils/recipes';

const props = defineProps<{ recipe: Recipe; compact?: boolean }>();
</script>

<template>
  <article class="recipe-card" :class="{ compact: props.compact }">
    <img v-if="recipe.image_url && !props.compact" class="recipe-image" :src="recipe.image_url" :alt="'Photo de ' + recipe.title" />
    <div>
      <div class="recipe-card-heading">
        <p class="recipe-kicker">Recette</p>
        <RecipeFavoriteButton v-if="props.compact" :recipe-id="recipe.id" :is-favorite="recipe.is_favorite ?? false" icon-only />
      </div>
      <h3>{{ recipe.title }}</h3>
      <div v-if="recipe.author" class="recipe-author">
        <img v-if="recipe.author.avatar_url" :src="recipe.author.avatar_url" :alt="`Photo de ${recipe.author.name}`" />
        <span v-else class="author-fallback" aria-hidden="true">{{ recipe.author.name.charAt(0).toUpperCase() }}</span>
        <span>Par {{ recipe.author.name }}</span>
      </div>
      <p v-if="recipe.description && !props.compact" class="description">{{ recipe.description }}</p>
      <div v-if="!props.compact" class="recipe-meta">
        <span v-if="recipe.prep_time_minutes !== null">Préparation : {{ recipe.prep_time_minutes }} min</span>
        <span v-if="recipe.cook_time_minutes !== null">Cuisson : {{ recipe.cook_time_minutes }} min</span>
        <span v-if="recipe.servings !== null">{{ recipe.servings }} portion<span v-if="recipe.servings > 1">s</span></span>
      </div>
      <p class="recipe-rating" aria-label="Note moyenne">
        ★ {{ (recipe.average_rating ?? 0).toFixed(1) }}/5 <span>({{ recipe.rating_count ?? 0 }} vote{{ (recipe.rating_count ?? 0) > 1 ? 's' : '' }})</span>
      </p>
      <div v-if="recipe.tags?.length && !props.compact" class="tags" aria-label="Tags">
        <span v-for="tag in recipe.tags" :key="tag.id" class="tag">{{ tag.name }}</span>
      </div>
      <RecipeFavoriteButton v-if="!props.compact" :recipe-id="recipe.id" :is-favorite="recipe.is_favorite ?? false" />
      <RecipeRating :recipe-id="recipe.id" :personal-rating="recipe.personal_rating ?? null" />
    </div>
    <RouterLink class="details-link" :to="{ name: 'recipe-detail', params: { id: recipe.id } }">Voir la recette</RouterLink>
  </article>
</template>

<style scoped>
.recipe-card { position: relative; display: flex; flex-direction: column; justify-content: space-between; gap: 1rem; padding: 1.2rem; border: 1px solid rgba(86,112,79,.2); border-radius: 1rem; background: rgba(255,253,248,.92); box-shadow: 0 10px 30px rgba(54,68,35,.06); }
.recipe-card.compact { gap: .75rem; padding: .9rem; }
.recipe-card-heading { display: flex; align-items: center; justify-content: space-between; gap: .5rem; }
.recipe-card-heading .recipe-kicker { margin-bottom: 0; }
.recipe-card-heading :deep(.favorite-control) { flex: 0 0 auto; }
.recipe-image { display: block; width: 100%; aspect-ratio: 16 / 10; object-fit: cover; border-radius: .75rem; }
.recipe-kicker { margin: 0 0 .3rem; color: #6b7b57; font-size: .75rem; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; }
h3 { margin: 0; color: #243127; font-size: 1.3rem; }
.description { color: #50634d; line-height: 1.5; }
.recipe-author { display: flex; align-items: center; gap: .5rem; margin-top: .7rem; color: #50634d; font-size: .9rem; }
.recipe-author img, .author-fallback { width: 2rem; height: 2rem; border-radius: 50%; object-fit: cover; }
.author-fallback { display: grid; place-items: center; background: #edf4e8; color: #395330; font-weight: 700; }
.recipe-meta { display: flex; flex-wrap: wrap; gap: .45rem .8rem; color: #50634d; font-size: .9rem; }
.recipe-rating { margin: .8rem 0 0; color: #a46114; font-weight: 700; }
.recipe-rating span { color: #50634d; font-size: .85rem; font-weight: 400; }
.tags { display: flex; flex-wrap: wrap; gap: .4rem; margin-top: .8rem; }
.tag { padding: .25rem .5rem; border-radius: 999px; background: #edf4e8; color: #395330; font-size: .8rem; }
.details-link { width: fit-content; color: #395330; font-weight: 700; text-decoration: none; }
.details-link:hover { text-decoration: underline; }
</style>
