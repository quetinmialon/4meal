<template>
  <main class="data-page">
    <section class="data-card" aria-labelledby="data-page-title">
      <img class="data-hero" src="@/assets/importexport.png" alt="Cuisine lumineuse avec des ingrédients frais" />
    <header class="data-header">
      <div>
        <p class="kicker">Mes données</p>
        <h1 id="data-page-title">Import &amp; Export</h1>
        <p class="intro">Gérez les échanges de données de votre espace SUPMEAL depuis un même endroit.</p>
      </div>
      <span class="data-safety">Vos données restent sous votre contrôle</span>
    </header>

    <div class="data-sections">
      <section class="data-panel import-card" aria-labelledby="import-title">
        <div class="card-heading">
          <div><span class="step-badge">1</span><div><p class="section-kicker">Entrer des données</p><h2 id="import-title">Import</h2></div></div>
          <span class="card-icon" aria-hidden="true">↓</span>
        </div>
        <p>Ajoutez vos recettes en choisissant le format correspondant à votre fichier.</p>
        <ul class="format-list">
          <li><strong>JSON SUPMEAL</strong><span>Import complet et prévisualisation des objets reconnus.</span></li>
          <li><strong>CSV recettes</strong><span>Import des recettes, ingrédients, étapes et tags.</span></li>
          <li><strong>Mealie</strong><span>Import adapté aux exports Mealie compatibles.</span></li>
        </ul>
        <div class="trust-note"><strong>Avant l’import :</strong> validation, erreurs détaillées, avertissements et prévisualisation avant confirmation.</div>
        <RouterLink class="primary-link" :to="{ name: 'import' }">Ouvrir l’import</RouterLink>
      </section>

      <section class="data-panel export-card" aria-labelledby="export-title">
        <div class="card-heading">
          <div><span class="step-badge">2</span><div><p class="section-kicker">Sortir vos données</p><h2 id="export-title">Export</h2></div></div>
          <span class="card-icon" aria-hidden="true">↑</span>
        </div>
        <p>Téléchargez une copie de vos données exportables dans les formats disponibles.</p>
        <ul class="format-list">
          <li><strong>Recettes</strong><span>Descriptions, durées, portions, ingrédients, étapes, tags, notes et sources.</span></li>
          <li><strong>Cookbooks</strong><span>Noms, descriptions et références de recettes accessibles.</span></li>
          <li><strong>Formats existants</strong><span>JSON SUPMEAL versionné et CSV recettes.</span></li>
        </ul>
        <div class="trust-note"><strong>Avant le téléchargement :</strong> vérification de confirmation et rappel des limites de chaque format.</div>
        <RouterLink class="primary-link" :to="{ name: 'export' }">Ouvrir l’export</RouterLink>
      </section>
    </div>
    </section>
  </main>
</template>

<script setup lang="ts">
import { RouterLink } from 'vue-router';
</script>

<style scoped>
.data-page { width: 100%; max-width: 76rem; margin: 0 auto; padding: 1.5rem; box-sizing: border-box; }
.data-card { overflow: hidden; border: 1px solid rgba(86,112,79,.18); border-radius: 1.5rem; background: rgba(255,253,248,.94); box-shadow: 0 20px 60px rgba(54,68,35,.1); }
.data-hero { display: block; width: 100%; height: clamp(12rem, 25vw, 20rem); object-fit: cover; object-position: center; }
.data-header { display: flex; align-items: end; justify-content: space-between; gap: 1rem; padding: 1rem 0 1.5rem; }
.kicker { margin: 0 0 .35rem; color: var(--app-muted); font-size: .8rem; font-weight: 700; letter-spacing: .14em; text-transform: uppercase; }
h1 { margin: 0 0 .75rem; font-size: clamp(1.9rem, 4vw, 2.8rem); }
.intro { color: var(--app-muted); }
.data-safety { padding: .55rem .75rem; border: 1px solid #b9c5af; border-radius: 999px; color: #395330; font-size: .85rem; font-weight: 700; }
.data-header, .data-sections { padding-inline: 1.5rem; }
.data-header { padding-top: 1.5rem; }
.data-sections { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 1rem; padding-bottom: 1.5rem; }
.data-panel { display: flex; flex-direction: column; min-width: 0; padding: 1.25rem 0; }
.data-panel + .data-panel { padding-left: 1.25rem; border-left: 1px solid rgba(86,112,79,.18); }
.card-heading { display: flex; align-items: start; justify-content: space-between; gap: 1rem; }.card-heading > div { display: flex; gap: .7rem; align-items: start; }
.step-badge { display: grid; flex: 0 0 1.7rem; width: 1.7rem; height: 1.7rem; place-items: center; border-radius: 50%; background: #edf4e8; color: #395330; font-weight: 800; }
.section-kicker { margin: 0 0 .25rem; color: #6b7b57; font-size: .75rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; }.data-card h2 { margin: 0; color: #243127; }
.card-icon { color: #395330; font-size: 1.8rem; font-weight: 800; line-height: 1; }.data-panel > p { color: #50634d; line-height: 1.5; }
.format-list { display: grid; gap: .75rem; margin: .6rem 0 1rem; padding: 0; list-style: none; }.format-list li { display: grid; gap: .15rem; padding: .7rem; border-radius: .6rem; background: #f3f7ef; }.format-list span { color: #50634d; font-size: .9rem; line-height: 1.4; }
.trust-note { margin-top: auto; padding: .75rem; border: 1px solid #d8e1d2; border-radius: .6rem; background: #fafcf8; color: #50634d; font-size: .88rem; line-height: 1.45; }.primary-link { display: inline-block; margin-top: 1rem; padding: .7rem .85rem; border-radius: .55rem; background: #395330; color: #fffdf8; font-weight: 700; text-align: center; text-decoration: none; }
@media (max-width: 680px) { .data-page { padding: 1rem .6rem; }.data-hero { height: 11rem; }.data-header { align-items: start; flex-direction: column; padding-inline: 1rem; }.data-safety { border-radius: .55rem; }.data-sections { grid-template-columns: 1fr; padding-inline: 1rem; padding-bottom: 1rem; }.data-panel + .data-panel { padding-left: 0; border-top: 1px solid rgba(86,112,79,.18); border-left: 0; } }
</style>
