<script setup lang="ts">
import { RouterLink } from 'vue-router';

import RecipeCreateForm from '@/components/RecipeCreateForm.vue';
</script>

<template>
  <main>
    <RouterLink class="back-link" :to="{ name: 'dashboard' }">Retour au tableau de bord</RouterLink>
    <p class="kicker">Nouvelle recette</p>
    <h2>Créer une recette</h2>
    <p class="intro">Ajoutez les ingrédients et les étapes, puis choisissez où partager cette recette.</p>
    <RecipeCreateForm />
  </main>
</template>

<style scoped>
main { width: 100%; max-width: 76rem; margin: 0 auto; }
.back-link { color: #395330; font-weight: 700; }
.kicker { margin: 2rem 0 .35rem; color: #6b7b57; font-size: .8rem; font-weight: 700; letter-spacing: .14em; text-transform: uppercase; }
h2 { margin: 0; font-size: clamp(1.9rem, 4vw, 2.8rem); }
.intro { color: #50634d; line-height: 1.6; }
</style>
