<script setup lang="ts">
import { RouterLink } from 'vue-router';

import RecipeFavoriteButton from '@/components/RecipeFavoriteButton.vue';
import type { Recipe } from '@/utils/recipes';

const props = defineProps<{ recipe: Recipe; compact?: boolean }>();
</script>

<template>
  <article class="recipe-card" :class="{ compact: props.compact }">
    <img v-if="recipe.image_url && !props.compact" class="recipe-image" :src="recipe.image_url" :alt="'Photo de ' + recipe.title" />
    <img v-else-if="!props.compact" class="recipe-image recipe-image-placeholder" src="@/assets/recipe-no-image.svg" alt="Aucune photo pour cette recette" />
    <RouterLink class="card-link" :to="{ name: 'recipe-detail', params: { id: recipe.id } }" :aria-label="`Voir la fiche de ${recipe.title}`"><span class="visually-hidden">Voir la fiche de {{ recipe.title }}</span></RouterLink>
    <RecipeFavoriteButton :recipe-id="recipe.id" :is-favorite="recipe.is_favorite ?? false" icon-only />
    <div>
      <div class="recipe-card-heading">
        <p class="recipe-kicker">Recette</p>
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
        <span v-for="tag in recipe.tags.slice(0, 3)" :key="tag.id" class="tag">{{ tag.name }}</span>
      </div>
    </div>
  </article>
</template>

<style scoped>
.recipe-card { position: relative; display: grid; grid-template-rows: minmax(0, 1fr) minmax(0, 1fr); min-height: 25rem; overflow: hidden; border: 1px solid var(--color-border); border-radius: var(--radius-lg); background: var(--color-surface); color: var(--color-text); box-shadow: var(--shadow-sm); transition: border-color .16s ease, box-shadow .16s ease, transform .16s ease; }
.recipe-card:hover, .recipe-card:focus-within { border-color: var(--color-primary); box-shadow: var(--shadow-md); }
.recipe-card:hover { transform: translateY(-2px); }
.recipe-card.compact { display: flex; min-height: 0; gap: .75rem; padding: .8rem; }
.recipe-card > div { position: relative; z-index: 2; display: flex; min-width: 0; flex-direction: column; padding: 1rem; pointer-events: none; }
.recipe-card.compact > div { padding: 0; pointer-events: none; }
.recipe-card-heading { display: flex; align-items: flex-start; justify-content: space-between; gap: .5rem; }
.recipe-card-heading .recipe-kicker { margin-bottom: 0; }
.recipe-card > :deep(.favorite-control) { position: absolute; z-index: 3; top: .75rem; right: .75rem; pointer-events: auto; }
.recipe-image, .recipe-image-placeholder { position: relative; z-index: 0; display: block; width: 100%; height: 100%; min-height: 0; object-fit: cover; background: var(--color-surface-subtle); }
.recipe-kicker { display: none; }
h3 { margin: 0; color: var(--color-text); font-size: 1.25rem; line-height: 1.25; }
.description { display: none; }
.recipe-author { display: flex; min-width: 0; align-items: center; gap: .5rem; margin-top: .65rem; color: var(--color-text-secondary); font-size: .78rem; }
.recipe-author > span:last-child { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.recipe-author img, .author-fallback { width: 1.85rem; height: 1.85rem; border-radius: 50%; object-fit: cover; }
.author-fallback { display: grid; place-items: center; background: var(--color-primary-soft); color: var(--color-primary); font-weight: 700; }
.recipe-meta { display: flex; flex-wrap: wrap; gap: .25rem .6rem; margin-top: .7rem; color: var(--color-text-muted); font-size: .78rem; line-height: 1.35; }
.recipe-rating { margin: .7rem 0 0; color: var(--color-accent); font-size: .9rem; font-weight: 700; }
.recipe-rating span { color: var(--color-text-muted); font-size: .8rem; font-weight: 400; }
.tags { display: flex; flex-wrap: wrap; gap: .35rem; margin-top: auto; padding-top: .75rem; }
.tag { padding: .22rem .48rem; border: 1px solid var(--color-border); border-radius: var(--radius-pill); background: var(--color-surface-subtle); color: var(--color-text-secondary); font-size: .75rem; }
.card-link { position: absolute; z-index: 1; inset: 0; border-radius: inherit; }
.card-link:focus-visible { outline: 3px solid var(--color-focus); outline-offset: -3px; }
.visually-hidden { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0, 0, 0, 0); white-space: nowrap; border: 0; }
@media (prefers-reduced-motion: reduce) { .recipe-card { transition: none; } .recipe-card:hover { transform: none; } }
</style>
