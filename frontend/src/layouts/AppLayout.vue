<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch, type Component } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';
import {
  ArrowDownUp,
  Bell,
  CalendarDays,
  ChefHat,
  HouseIcon as Home,
  LibraryIcon as Library,
  LogOut,
  Menu,
  Plus,
  Search,
  Settings,
  ShoppingCart,
  UserCircleIcon as UserCircle,
  X,
} from '@lucide/vue';

import { useAuthStore } from '@/stores/auth';

type NavigationItem = { label: string; name: string; icon: Component };

const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();
const isMobileNavigationOpen = ref(false);
const isQuickMenuOpen = ref(false);
const isUserMenuOpen = ref(false);
const quickMenu = ref<HTMLElement | null>(null);
const userMenu = ref<HTMLElement | null>(null);
const quickMenuTrigger = ref<HTMLButtonElement | null>(null);
const userMenuTrigger = ref<HTMLButtonElement | null>(null);

const navigationItems: NavigationItem[] = [
  { label: 'Accueil', name: 'dashboard', icon: Home },
  { label: 'Recettes', name: 'recipes', icon: ChefHat },
  { label: 'Cookbooks', name: 'cookbooks', icon: Library },
  { label: 'Planning', name: 'planning', icon: CalendarDays },
  { label: 'Courses', name: 'shopping-list', icon: ShoppingCart },
  { label: 'Import & Export', name: 'data', icon: ArrowDownUp },
  { label: 'Paramètres', name: 'settings', icon: Settings },
];

const cookbookId = computed(() => String(route.params.id ?? ''));
const isCookbookContext = computed(() => route.name === 'cookbook' || route.name === 'cookbook-messages');
const isSettingsContext = computed(() => route.name === 'profile' || route.name === 'change-password');

function isNavigationItemActive(item: NavigationItem): boolean {
  if (item.name === 'cookbooks') return isCookbookContext.value;
  if (item.name === 'settings') return isSettingsContext.value;
  if (item.name === 'data') return ['data', 'import', 'export'].includes(String(route.name));
  return route.name === item.name || (item.name === 'recipes' && ['recipe-create', 'recipe-detail', 'recipe-edit', 'recipe-history', 'public-recipes', 'search'].includes(String(route.name)));
}

function closeMobileNavigation(): void { isMobileNavigationOpen.value = false; }
function toggleMobileNavigation(): void { isMobileNavigationOpen.value = !isMobileNavigationOpen.value; }

function focusFirstMenuItem(menu: HTMLElement | null): void {
  void nextTick(() => menu?.querySelector<HTMLElement>('[role="menuitem"]')?.focus());
}

function toggleQuickMenu(): void {
  isUserMenuOpen.value = false;
  isQuickMenuOpen.value = !isQuickMenuOpen.value;
  if (isQuickMenuOpen.value) focusFirstMenuItem(quickMenu.value);
}

function toggleUserMenu(): void {
  isQuickMenuOpen.value = false;
  isUserMenuOpen.value = !isUserMenuOpen.value;
  if (isUserMenuOpen.value) focusFirstMenuItem(userMenu.value);
}

function closeQuickMenu(restoreFocus = false): void {
  isQuickMenuOpen.value = false;
  if (restoreFocus) void nextTick(() => quickMenuTrigger.value?.focus());
}

function closeUserMenu(restoreFocus = false): void {
  isUserMenuOpen.value = false;
  if (restoreFocus) void nextTick(() => userMenuTrigger.value?.focus());
}

function handleDocumentPointerDown(event: PointerEvent): void {
  const target = event.target as Node;
  if (!quickMenu.value?.contains(target)) closeQuickMenu();
  if (!userMenu.value?.contains(target)) closeUserMenu();
}

function handleDocumentKeydown(event: KeyboardEvent): void {
  if (event.key !== 'Escape') return;
  if (isQuickMenuOpen.value) { event.preventDefault(); closeQuickMenu(true); }
  if (isUserMenuOpen.value) { event.preventDefault(); closeUserMenu(true); }
}

async function logout(): Promise<void> {
  closeUserMenu();
  await authStore.logout();
  await router.push({ name: 'login' });
}

watch(() => route.fullPath, () => { closeMobileNavigation(); closeQuickMenu(); closeUserMenu(); });
onMounted(() => {
  document.addEventListener('pointerdown', handleDocumentPointerDown);
  document.addEventListener('keydown', handleDocumentKeydown);
});
onBeforeUnmount(() => {
  document.removeEventListener('pointerdown', handleDocumentPointerDown);
  document.removeEventListener('keydown', handleDocumentKeydown);
});
</script>

<template>
  <div class="app-shell">
    <button v-if="isMobileNavigationOpen" class="navigation-scrim" type="button" aria-label="Fermer le menu" @click="closeMobileNavigation" />

    <aside id="main-navigation" class="sidebar" :class="{ 'is-open': isMobileNavigationOpen }" aria-label="Navigation principale">
      <div class="sidebar-header">
        <RouterLink class="brand" :to="{ name: 'dashboard' }" @click="closeMobileNavigation">SUPMEAL</RouterLink>
        <button class="icon-button sidebar-close" type="button" aria-label="Fermer le menu" @click="closeMobileNavigation"><X :size="20" aria-hidden="true" /></button>
      </div>
      <nav class="sidebar-navigation">
        <RouterLink v-for="item in navigationItems" :key="item.name" class="navigation-link" :class="{ 'is-active': isNavigationItemActive(item) }" :to="{ name: item.name }" :aria-current="isNavigationItemActive(item) ? 'page' : undefined" @click="closeMobileNavigation">
          <component :is="item.icon" :size="19" stroke-width="1.8" aria-hidden="true" /><span>{{ item.label }}</span>
        </RouterLink>
      </nav>
      <div class="sidebar-footer">
        <RouterLink class="account-link" :to="{ name: 'profile' }" @click="closeMobileNavigation"><UserCircle :size="19" aria-hidden="true" /><span>Mon compte</span></RouterLink>
      </div>
    </aside>

    <div class="shell-body">
      <header class="global-header">
        <button class="icon-button mobile-menu-button" type="button" :aria-expanded="isMobileNavigationOpen" aria-controls="main-navigation" aria-label="Ouvrir le menu" @click="toggleMobileNavigation">
          <Menu v-if="!isMobileNavigationOpen" :size="22" aria-hidden="true" /><X v-else :size="22" aria-hidden="true" />
        </button>
        <div class="header-search"><Search :size="18" aria-hidden="true" /><RouterLink :to="{ name: 'search' }">Rechercher une recette</RouterLink></div>

        <nav class="header-actions" aria-label="Actions globales">
          <div ref="quickMenu" class="header-popover">
            <button ref="quickMenuTrigger" class="header-action" type="button" aria-haspopup="menu" :aria-expanded="isQuickMenuOpen" aria-controls="quick-create-menu" @click="toggleQuickMenu">
              <Plus :size="18" aria-hidden="true" /><span>Créer</span>
            </button>
            <div v-if="isQuickMenuOpen" id="quick-create-menu" class="popover-menu" role="menu" aria-label="Création rapide">
              <RouterLink role="menuitem" :to="{ name: 'recipe-create' }" @click="closeQuickMenu(false)">Nouvelle recette</RouterLink>
            </div>
          </div>

          <RouterLink class="icon-button" :to="{ name: 'dashboard', hash: '#notifications' }" aria-label="Notifications"><Bell :size="20" aria-hidden="true" /></RouterLink>

          <div ref="userMenu" class="header-popover">
            <button ref="userMenuTrigger" class="user-trigger" type="button" aria-haspopup="menu" :aria-expanded="isUserMenuOpen" aria-controls="user-menu" aria-label="Ouvrir le menu utilisateur" @click="toggleUserMenu">
              <img v-if="authStore.user?.avatar_url" class="user-avatar" :src="authStore.user.avatar_url" alt="" />
              <UserCircle v-else :size="22" aria-hidden="true" />
              <span class="user-name">{{ authStore.user?.name ?? 'Mon compte' }}</span>
            </button>
            <div v-if="isUserMenuOpen" id="user-menu" class="popover-menu user-popover" role="menu" aria-label="Menu utilisateur">
              <RouterLink role="menuitem" :to="{ name: 'profile' }" @click="closeUserMenu(false)">Profil</RouterLink>
              <RouterLink role="menuitem" :to="{ name: 'settings' }" @click="closeUserMenu(false)">Paramètres</RouterLink>
              <button role="menuitem" type="button" @click="logout"><LogOut :size="17" aria-hidden="true" /><span>Déconnexion</span></button>
            </div>
          </div>
        </nav>
      </header>

      <nav v-if="isCookbookContext" class="context-navigation" aria-label="Navigation du cookbook">
        <RouterLink :to="{ name: 'cookbook', params: { id: cookbookId } }">Recettes</RouterLink><RouterLink :to="{ name: 'planning' }">Planning</RouterLink><RouterLink :to="{ name: 'cookbook-messages', params: { id: cookbookId } }">Discussion</RouterLink><RouterLink :to="{ name: 'cookbook', params: { id: cookbookId }, hash: '#cookbook-members' }">Membres</RouterLink><RouterLink :to="{ name: 'cookbook', params: { id: cookbookId }, hash: '#cookbook-settings' }">Paramètres</RouterLink>
      </nav>
      <nav v-if="isSettingsContext" class="context-navigation" aria-label="Navigation des paramètres">
        <RouterLink :to="{ name: 'profile' }">Profil</RouterLink><RouterLink :to="{ name: 'profile', hash: '#food-preferences' }">Préférences alimentaires</RouterLink><RouterLink :to="{ name: 'change-password' }">Sécurité</RouterLink><RouterLink :to="{ name: 'profile', hash: '#connected-accounts' }">Comptes connectés</RouterLink><RouterLink :to="{ name: 'profile', hash: '#notification-preferences' }">Notifications</RouterLink><RouterLink :to="{ name: 'profile', hash: '#theme-preferences' }">Apparence</RouterLink>
      </nav>
      <main id="main-content" class="main-content"><slot /></main>
    </div>
  </div>
</template>

<style scoped>
.app-shell { min-height: 100vh; background: var(--app-background); color: var(--app-text); font-family: "Avenir Next", Avenir, "Segoe UI", sans-serif; }
.sidebar { position: fixed; inset: 0 auto 0 0; z-index: 20; display: flex; width: 16rem; flex-direction: column; border-right: 1px solid var(--app-border); background: var(--app-surface); }
.sidebar-header { display: flex; align-items: center; justify-content: space-between; min-height: 4.5rem; padding: 1rem 1.25rem; border-bottom: 1px solid var(--app-border); }
.brand { color: var(--app-text); font-size: 1rem; font-weight: 800; letter-spacing: .14em; text-decoration: none; }
.sidebar-navigation { display: grid; gap: .35rem; padding: 1.25rem .8rem; }
.navigation-link, .account-link { display: flex; align-items: center; gap: .75rem; min-height: 2.75rem; padding: .65rem .8rem; border-radius: .65rem; color: var(--app-muted); font-weight: 700; text-decoration: none; }
.navigation-link:hover, .navigation-link:focus-visible, .account-link:hover, .account-link:focus-visible { color: var(--app-text); background: color-mix(in srgb, var(--app-border) 45%, transparent); }
.navigation-link.is-active { color: var(--app-text); background: color-mix(in srgb, var(--app-border) 70%, transparent); }
.sidebar-footer { margin-top: auto; padding: .8rem; border-top: 1px solid var(--app-border); }
.shell-body { min-height: 100vh; margin-left: 16rem; }
.global-header { position: sticky; top: 0; z-index: 10; display: flex; align-items: center; gap: 1.25rem; min-height: 4.5rem; padding: .8rem 2rem; border-bottom: 1px solid var(--app-border); background: var(--app-surface); }
.header-search { display: flex; align-items: center; gap: .55rem; min-width: 15rem; color: var(--app-muted); }
.header-search a { color: var(--app-muted); text-decoration: none; }
.header-actions { display: flex; align-items: center; gap: .7rem; margin-left: auto; }
.header-action, .icon-button, .user-trigger { display: inline-flex; align-items: center; justify-content: center; gap: .45rem; min-height: 2.5rem; border: 1px solid var(--app-border); border-radius: .65rem; background: transparent; color: var(--app-text); font: inherit; font-weight: 700; text-decoration: none; cursor: pointer; }
.header-action { padding: .45rem .75rem; }
.icon-button { width: 2.5rem; padding: 0; }
.user-trigger { padding: .25rem .55rem; }
.user-avatar { width: 1.9rem; height: 1.9rem; border-radius: 50%; object-fit: cover; }
.header-action:focus-visible, .icon-button:focus-visible, .user-trigger:focus-visible, .popover-menu a:focus-visible, .popover-menu button:focus-visible, .context-navigation a:focus-visible { outline: 3px solid color-mix(in srgb, var(--app-muted) 45%, transparent); outline-offset: 2px; }
.header-popover { position: relative; }
.popover-menu { position: absolute; right: 0; display: grid; gap: .35rem; min-width: 12rem; margin-top: .55rem; padding: .6rem; border: 1px solid var(--app-border); border-radius: .75rem; background: var(--app-surface); box-shadow: 0 12px 30px rgba(36, 49, 39, .12); }
.popover-menu a, .popover-menu button { display: flex; align-items: center; gap: .5rem; padding: .55rem .6rem; border: 0; border-radius: .45rem; background: transparent; color: var(--app-text); font: inherit; text-align: left; text-decoration: none; cursor: pointer; }
.popover-menu a:hover, .popover-menu button:hover { background: color-mix(in srgb, var(--app-border) 45%, transparent); }
.context-navigation { display: flex; gap: 1rem; padding: .75rem 2rem; overflow-x: auto; border-bottom: 1px solid var(--app-border); background: var(--app-surface); }
.context-navigation a { flex: 0 0 auto; color: var(--app-muted); font-weight: 700; text-decoration: none; }
.context-navigation a.router-link-active { color: var(--app-text); }
.main-content { width: min(100% - 4rem, 76rem); margin: 0 auto; padding: 2rem 0 3rem; }
.mobile-menu-button, .sidebar-close, .navigation-scrim { display: none; }
@media (max-width: 52rem) { .sidebar { transform: translateX(-100%); transition: transform .2s ease; box-shadow: 0 18px 45px rgba(36, 49, 39, .2); } .sidebar.is-open { transform: translateX(0); } .sidebar-close, .mobile-menu-button { display: inline-flex; } .navigation-scrim { position: fixed; inset: 0; z-index: 15; display: block; border: 0; background: rgba(36, 49, 39, .38); cursor: pointer; } .shell-body { margin-left: 0; } .global-header { padding: .75rem 1rem; } .header-search { flex: 1; min-width: 0; } .header-search a { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; } .header-action span, .user-name { display: none; } .header-action { width: 2.5rem; padding: 0; } .main-content { width: min(100% - 2rem, 76rem); } }
@media (prefers-reduced-motion: reduce) { .sidebar { transition: none; } }
</style>
