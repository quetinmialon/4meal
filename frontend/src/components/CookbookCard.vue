<script setup lang="ts">
import { computed } from 'vue';
import { RouterLink } from 'vue-router';

import Avatar from '@/components/Avatar.vue';
import type { Cookbook } from '@/utils/cookbooks';

const props = defineProps<{ cookbook: Cookbook }>();
const description = computed(() => {
  const value = props.cookbook.description?.trim() ?? '';
  return value.length > 150 ? `${value.slice(0, 150)}...` : value;
});
</script>

<template>
  <RouterLink class="cookbook-card" :to="{ name: 'cookbook', params: { id: cookbook.id } }">
    <div class="cookbook-card-image">
      <img v-if="cookbook.image_url" :src="cookbook.image_url" :alt="`Image de ${cookbook.name}`" />
      <img v-else src="@/assets/recipe-no-image.svg" alt="Aucune photo pour ce cookbook" />
    </div>
    <div class="cookbook-card-content">
      <div class="cookbook-card-owner">
        <Avatar :src="cookbook.owner.avatar_url ?? null" :name="cookbook.owner.name" size="small" />
        <span>{{ cookbook.owner.name }}</span>
      </div>
      <span
        class="cookbook-card-role role-badge"
        :aria-label="'Rôle : ' + (cookbook.member_role ?? 'membre')"
      >
        {{ cookbook.member_role ?? 'membre' }}
      </span>
      <h3>{{ cookbook.name }}</h3>
      <p v-if="description" class="cookbook-card-description">{{ description }}</p>
      <div v-if="cookbook.members?.length" class="cookbook-card-members" aria-label="Membres du cookbook">
        <span v-for="member in cookbook.members.slice(0, 10)" :key="member.id" class="cookbook-member-avatar" :title="member.name" :aria-label="member.name">
          <Avatar :src="member.avatar_url ?? null" :name="member.name" size="small" />
        </span>
      </div>
    </div>
  </RouterLink>
</template>

<style scoped>
.cookbook-card { display: grid; min-width: 0; min-height: 24rem; grid-template-rows: minmax(0, 1fr) minmax(0, 1fr); overflow: hidden; border: 1px solid var(--color-border); border-radius: var(--radius-lg); background: var(--color-surface); color: var(--color-text); text-decoration: none; box-shadow: var(--shadow-sm); transition: border-color .16s ease, box-shadow .16s ease, transform .16s ease; }
.cookbook-card:hover, .cookbook-card:focus-visible { border-color: var(--color-primary); box-shadow: var(--shadow-md); }
.cookbook-card:hover { transform: translateY(-2px); }
.cookbook-card-image { min-height: 0; background: var(--color-surface-subtle); }
.cookbook-card-image img { display: block; width: 100%; height: 100%; min-height: 0; object-fit: cover; }
.cookbook-card-content { display: flex; min-width: 0; flex-direction: column; gap: .45rem; padding: .9rem; }
.cookbook-card-owner { display: flex; min-width: 0; align-items: center; gap: .5rem; color: var(--color-text-secondary); font-size: .8rem; }
.cookbook-card-owner > span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.cookbook-card-role { color: var(--color-primary); font-size: .75rem; font-weight: 700; text-transform: capitalize; max-width:fit-content; padding-inline: .25rem .5rem; border-radius: var(--radius-sm); background: var(--color-primary-subtle); }
.cookbook-card h3 { margin: .15rem 0 0; color: var(--color-text); font-size: 1.15rem; line-height: 1.2; }
.cookbook-card-description { display: -webkit-box; margin: 0; overflow: hidden; color: var(--color-text-secondary); font-size: .875rem; line-height: 1.45; -webkit-box-orient: vertical; -webkit-line-clamp: 4; line-clamp: 4; }
.cookbook-card-members { display: flex; flex-wrap: wrap; max-height: 3.5rem; gap: .25rem; margin-top: auto; overflow: hidden; padding-top: .35rem; }
.cookbook-member-avatar { display: inline-flex; border: 2px solid var(--color-surface); border-radius: 50%; }
.cookbook-member-avatar + .cookbook-member-avatar { margin-left: -.35rem; }
@media (prefers-reduced-motion: reduce) { .cookbook-card { transition: none; } .cookbook-card:hover { transform: none; } }
</style>
