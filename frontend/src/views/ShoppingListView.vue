<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';

import { useAuthStore } from '@/stores/auth';
import { fetchShoppingList } from '@/utils/planning';
import { groupShoppingItems, type EditableShoppingListItem } from '@/utils/shoppingList';

const authStore = useAuthStore();
const today = new Date();
const from = ref(toDateKey(today));
const to = ref(toDateKey(addDays(today, 6)));
const items = ref<EditableShoppingListItem[]>([]);
const isLoading = ref(true);
const errorMessage = ref('');
const hasLoaded = ref(false);

function toDateKey(date: Date): string {
  return [date.getFullYear(), date.getMonth() + 1, date.getDate()]
    .map((part, index) => index === 0 ? String(part) : String(part).padStart(2, '0')).join('-');
}
function addDays(date: Date, days: number): Date {
  const result = new Date(date);
  result.setDate(result.getDate() + days);
  return result;
}
function periodLabel(value: string): string {
  return new Date(`${value}T12:00:00`).toLocaleDateString('fr-FR', { day: 'numeric', month: 'long', year: 'numeric' });
}
function setCurrentWeek(): void {
  const date = new Date();
  const day = date.getDay() || 7;
  date.setDate(date.getDate() - day + 1);
  from.value = toDateKey(date);
  to.value = toDateKey(addDays(date, 6));
  void loadList();
}
async function loadList(): Promise<void> {
  errorMessage.value = '';
  if (!from.value || !to.value || from.value > to.value) {
    errorMessage.value = 'La période sélectionnée est invalide.';
    return;
  }
  isLoading.value = true;
  const result = await fetchShoppingList(from.value, to.value, authStore.tokenType, authStore.accessToken);
  if (result.ok) {
    items.value = groupShoppingItems(result.items);
    hasLoaded.value = true;
  } else {
    items.value = [];
    errorMessage.value = result.message;
  }
  isLoading.value = false;
}
function printList(): void { window.print(); }
const remainingCount = computed(() => items.value.filter((item) => !item.checked).length);
const checkedCount = computed(() => items.value.length - remainingCount.value);
const generateLabel = computed(() => hasLoaded.value ? 'Régénérer la liste' : 'Générer la liste');

onMounted(() => { void loadList(); });
</script>

<template>
  <main class="shopping-page">
    <header class="shopping-header">
      <div>
        <p class="kicker">Organisation</p>
        <h2>Liste de courses</h2>
        <p class="period-label">{{ periodLabel(from) }} — {{ periodLabel(to) }}</p>
      </div>
      <div class="header-actions">
        <RouterLink class="planning-link" :to="{ name: 'planning' }">Voir le planning</RouterLink>
        <button type="button" class="print-button" :disabled="items.length === 0" @click="printList">Imprimer</button>
      </div>
    </header>

    <form class="period-form" aria-label="Sélection de la période" @submit.prevent="loadList">
      <label for="shopping-from">Du</label>
      <input id="shopping-from" v-model="from" type="date" />
      <label for="shopping-to">au</label>
      <input id="shopping-to" v-model="to" type="date" />
      <button type="button" class="secondary-button" @click="setCurrentWeek">Cette semaine</button>
      <button type="submit" class="primary-button">{{ generateLabel }}</button>
    </form>

    <p v-if="isLoading" class="state-message" role="status">Génération de la liste...</p>
    <p v-else-if="errorMessage" class="state-message error-state" role="alert">{{ errorMessage }}</p>
    <section v-else-if="hasLoaded && items.length === 0" class="state-message empty-state">
      <h3>Aucun ingrédient sur cette période.</h3>
      <p>Ajoutez un repas au planning ou choisissez une autre période.</p>
      <RouterLink class="planning-link" :to="{ name: 'planning' }">Retour au planning</RouterLink>
    </section>
    <section v-else class="shopping-list" aria-labelledby="shopping-list-title">
      <div class="list-summary">
        <div><p class="list-kicker">Ingrédients agrégés</p><h3 id="shopping-list-title">À acheter</h3></div>
        <div class="list-counts"><strong>{{ remainingCount }}</strong> à acheter<span aria-hidden="true"> · </span><span>{{ checkedCount }} cochés</span></div>
      </div>
      <p class="list-help">Cochez les ingrédients au fur et à mesure. Les quantités sont regroupées par ingrédient et unité.</p>
      <ul>
        <li v-for="item in items" :key="item.id" class="shopping-item" :class="{ checked: item.checked }">
          <input :id="item.id" v-model="item.checked" type="checkbox" :aria-label="`${item.checked ? 'Désélectionner' : 'Marquer'} ${item.name}`" />
          <div class="item-fields">
            <label :for="`${item.id}-name`" class="sr-only">Nom</label>
            <input :id="`${item.id}-name`" v-model="item.name" class="item-name" aria-label="Nom de l'ingrédient" />
            <div class="quantity-fields">
              <label :for="`${item.id}-quantity`" class="sr-only">Quantité</label>
              <input :id="`${item.id}-quantity`" v-model.number="item.quantity" type="number" min="0" step="0.001" aria-label="Quantité" />
              <label :for="`${item.id}-unit`" class="sr-only">Unité</label>
              <input :id="`${item.id}-unit`" v-model="item.unit" placeholder="unité" aria-label="Unité" />
            </div>
            <small v-if="item.preparation || item.is_optional" class="item-meta">{{ item.preparation }}<span v-if="item.is_optional"> · facultatif</span></small>
          </div>
        </li>
      </ul>
    </section>
  </main>
</template>

<style scoped>
.shopping-page { width: 100%; max-width: 76rem; margin: 0 auto; padding: 1.5rem; box-sizing: border-box; border: 1px solid rgba(86,112,79,.18); border-radius: 1.5rem; background: rgba(255,253,248,.92); box-shadow: 0 20px 60px rgba(54,68,35,.1); }
.shopping-header, .list-summary { display: flex; align-items: center; justify-content: space-between; gap: 1rem; }
.header-actions { display: flex; flex-wrap: wrap; align-items: center; justify-content: end; gap: .7rem; }
.planning-link { color: #395330; font-weight: 700; }
.kicker { margin: 0 0 .3rem; color: #6b7b57; font-size: .75rem; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; }
h2, h3 { margin: 0; color: #243127; }
.period-label { margin: .4rem 0 0; color: #50634d; text-transform: capitalize; }
.period-form { display: flex; flex-wrap: wrap; align-items: end; gap: .55rem; margin: 1.5rem 0; padding: 1rem; border-radius: .8rem; background: #edf4e8; color: #395330; font-weight: 700; }
.period-form label { font-size: .85rem; }
.period-form input { padding: .55rem; border: 1px solid #b9c5af; border-radius: .5rem; background: #fffdf8; font: inherit; }
button { padding: .55rem .75rem; border-radius: .5rem; font: inherit; font-weight: 700; cursor: pointer; }
.primary-button, .print-button { border: 1px solid #395330; background: #395330; color: #fffdf8; }
.secondary-button { margin-left: auto; border: 1px solid #b9c5af; background: transparent; color: #395330; }
button:disabled { cursor: not-allowed; opacity: .5; }
.state-message { margin: 2rem 0; padding: 2rem 1rem; border-radius: .8rem; color: #50634d; text-align: center; }
.state-message h3 { margin: 0 0 .4rem; }
.state-message p { margin: .4rem 0 1rem; }
.empty-state { background: #f3f7ef; }
.error-state { background: #fff0ee; color: #8f1e1e; }
.shopping-list { margin-top: 1rem; }
.list-summary { padding-bottom: .7rem; border-bottom: 1px solid #dce5d6; }
.list-kicker { margin: 0 0 .2rem; color: #6b7b57; font-size: .75rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; }
.list-counts { color: #50634d; font-size: .9rem; text-align: right; }
.list-counts strong { color: #243127; font-size: 1.1rem; }
.list-help { margin: .8rem 0; color: #50634d; font-size: .9rem; }
.shopping-list ul { display: grid; gap: .6rem; margin: 1rem 0 0; padding: 0; list-style: none; }
.shopping-item { display: flex; align-items: start; gap: .75rem; padding: .75rem; border: 1px solid #dce5d6; border-radius: .65rem; }
.shopping-item > input { width: 1.2rem; height: 1.2rem; margin-top: .55rem; accent-color: #395330; }
.item-fields { display: grid; flex: 1; gap: .35rem; }
.item-fields input { min-width: 0; padding: .4rem .5rem; border: 1px solid transparent; border-radius: .4rem; background: transparent; font: inherit; color: #243127; }
.item-fields input:focus { border-color: #b9c5af; background: #fffdf8; outline: none; }
.item-name { font-weight: 700; }
.quantity-fields { display: flex; gap: .4rem; }
.quantity-fields input:first-child { width: 7rem; }
.quantity-fields input:last-child { width: 8rem; }
.item-meta { color: #6d7768; }
.shopping-item.checked .item-fields { opacity: .55; }
.shopping-item.checked .item-name { text-decoration: line-through; }
.sr-only { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0, 0, 0, 0); white-space: nowrap; }
@media print {
  .shopping-page { max-width: none; padding: 0; border: 0; box-shadow: none; }
  .period-form, .print-button { display: none; }
  .shopping-item { break-inside: avoid; }
  .item-fields input { border: 0; }
}
@media (max-width: 640px) {
  .shopping-page { padding: 1rem .6rem; border-radius: 1rem; }
  .shopping-header { align-items: flex-start; flex-direction: column; }
  .header-actions { width: 100%; justify-content: space-between; }
  .print-button { padding: .45rem .55rem; }
  .secondary-button { margin-left: 0; }
  .period-form input { flex: 1; min-width: 8rem; }
  .list-summary { align-items: end; }
  .list-counts { max-width: 8rem; }
  .quantity-fields { width: 100%; }
  .quantity-fields input:first-child,
  .quantity-fields input:last-child { width: auto; min-width: 0; flex: 1; }
}
</style>
