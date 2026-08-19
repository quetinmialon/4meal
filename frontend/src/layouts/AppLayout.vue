<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch, type Component } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';
import {
  ArrowDownUp,
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
import NotificationsPanel from '@/components/NotificationsPanel.vue';

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
const mobileNavigation = ref<HTMLElement | null>(null);
const mobileMenuTrigger = ref<HTMLButtonElement | null>(null);
let previousBodyOverflow = '';

const navigationItems: NavigationItem[] = [
  { label: 'Accueil', name: 'dashboard', icon: Home },
  { label: 'Recettes', name: 'recipes', icon: ChefHat },
  { label: 'Cookbooks', name: 'cookbooks', icon: Library },
  { label: 'Planning', name: 'planning', icon: CalendarDays },
  { label: 'Courses', name: 'shopping-list', icon: ShoppingCart },
  { label: 'Import & Export', name: 'data', icon: ArrowDownUp },
  { label: 'Paramètres', name: 'settings', icon: Settings },
];
const mobilePrimaryItems = navigationItems.filter((item) => ['dashboard', 'recipes', 'planning', 'shopping-list'].includes(item.name));

const cookbookId = computed(() => String(route.params.id ?? ''));
const cookbookRouteNames = ['cookbook', 'cookbook-recipes', 'cookbook-members', 'cookbook-planning', 'cookbook-settings', 'cookbook-messages'];
const isCookbookContext = computed(() => cookbookRouteNames.includes(String(route.name)));
const isSettingsContext = computed(() => ['profile', 'profile-food-preferences', 'profile-usage-preferences', 'change-password', 'security'].includes(String(route.name)));

function isCookbookTabActive(tab: 'recipes' | 'planning' | 'discussion' | 'members' | 'settings'): boolean {
  if (tab === 'discussion') return route.name === 'cookbook-messages';
  if (tab === 'planning') return route.name === 'cookbook-planning';
  if (tab === 'members') return route.name === 'cookbook-members';
  if (tab === 'settings') return route.name === 'cookbook-settings';
  return tab === 'recipes' ? route.name === 'cookbook-recipes' : route.name === 'cookbook';
}

function isNavigationItemActive(item: NavigationItem): boolean {
  if (item.name === 'cookbooks') return isCookbookContext.value;
  if (item.name === 'settings') return isSettingsContext.value;
  if (item.name === 'data') return ['data', 'import', 'export'].includes(String(route.name));
  return route.name === item.name || (item.name === 'recipes' && ['recipe-create', 'recipe-detail', 'recipe-edit', 'recipe-history', 'public-recipes', 'search'].includes(String(route.name)));
}

function closeMobileNavigation(restoreFocus = true): void {
  isMobileNavigationOpen.value = false;
  if (restoreFocus) void nextTick(() => mobileMenuTrigger.value?.focus());
}
function toggleMobileNavigation(): void {
  if (isMobileNavigationOpen.value) closeMobileNavigation(true);
  else isMobileNavigationOpen.value = true;
}

function focusMobileNavigation(): void {
  void nextTick(() => mobileNavigation.value?.querySelector<HTMLElement>('button, a')?.focus());
}

function trapMobileNavigationFocus(event: KeyboardEvent): void {
  if (!isMobileNavigationOpen.value || event.key !== 'Tab' || !mobileNavigation.value) return;
  const focusable = Array.from(mobileNavigation.value.querySelectorAll<HTMLElement>('button, a[href]')).filter((element) => !element.hasAttribute('disabled'));
  if (focusable.length === 0) return;
  const first = focusable.at(0);
  const last = focusable.at(-1);
  if (!first || !last) return;
  if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
  else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
}

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
  if (isMobileNavigationOpen.value) {
    if (event.key === 'Escape') { event.preventDefault(); closeMobileNavigation(true); return; }
    trapMobileNavigationFocus(event);
  }
  if (event.key !== 'Escape') return;
  if (isQuickMenuOpen.value) { event.preventDefault(); closeQuickMenu(true); }
  if (isUserMenuOpen.value) { event.preventDefault(); closeUserMenu(true); }
}

async function logout(): Promise<void> {
  closeUserMenu();
  await authStore.logout();
  await router.push({ name: 'login' });
}

watch(() => route.fullPath, () => { closeMobileNavigation(false); closeQuickMenu(); closeUserMenu(); });
watch(isMobileNavigationOpen, (isOpen) => {
  if (isOpen) {
    previousBodyOverflow = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    focusMobileNavigation();
  } else {
    document.body.style.overflow = previousBodyOverflow;
  }
});
onMounted(() => {
  document.addEventListener('pointerdown', handleDocumentPointerDown);
  document.addEventListener('keydown', handleDocumentKeydown);
});
onBeforeUnmount(() => {
  document.removeEventListener('pointerdown', handleDocumentPointerDown);
  document.removeEventListener('keydown', handleDocumentKeydown);
  document.body.style.overflow = previousBodyOverflow;
});
</script>

<template>
  <div class="app-shell">
    <button v-if="isMobileNavigationOpen" class="navigation-scrim" type="button" aria-label="Fermer le menu" @click="closeMobileNavigation()" />

    <aside id="main-navigation" ref="mobileNavigation" class="sidebar" :class="{ 'is-open': isMobileNavigationOpen }" aria-label="Navigation principale">
      <div class="sidebar-header">
        <RouterLink class="brand" :to="{ name: 'dashboard' }" @click="closeMobileNavigation">SUPMEAL</RouterLink>
        <button class="icon-button sidebar-close" type="button" aria-label="Fermer le menu" @click="closeMobileNavigation()"><X :size="20" aria-hidden="true" /></button>
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
        <button ref="mobileMenuTrigger" class="icon-button mobile-menu-button" type="button" :aria-expanded="isMobileNavigationOpen" aria-controls="main-navigation" :aria-label="isMobileNavigationOpen ? 'Fermer le menu' : 'Ouvrir le menu'" @click="toggleMobileNavigation">
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

          <NotificationsPanel compact :token-type="authStore.tokenType" :access-token="authStore.accessToken" />

          <div ref="userMenu" class="header-popover">
            <button ref="userMenuTrigger" class="user-trigger" type="button" aria-haspopup="menu" :aria-expanded="isUserMenuOpen" aria-controls="user-menu" aria-label="Ouvrir le menu utilisateur" @click="toggleUserMenu">
              <img v-if="authStore.user?.avatar_url" class="user-avatar" :src="authStore.user.avatar_url" alt="" />
              <UserCircle v-else :size="22" aria-hidden="true" />
              <span class="user-name">{{ authStore.user?.name ?? 'Mon compte' }}</span>
            </button>
            <div v-if="isUserMenuOpen" id="user-menu" class="popover-menu user-popover" role="menu" aria-label="Menu utilisateur">
              <RouterLink role="menuitem" :to="{ name: 'profile' }" @click="closeUserMenu(false)">Profil</RouterLink>
              <RouterLink role="menuitem" :to="{ name: 'security' }" @click="closeUserMenu(false)">Paramètres</RouterLink>
              <button role="menuitem" type="button" @click="logout"><LogOut :size="17" aria-hidden="true" /><span>Déconnexion</span></button>
            </div>
          </div>
        </nav>
      </header>

      <nav v-if="isCookbookContext" class="context-navigation" aria-label="Navigation du cookbook">
        <RouterLink :class="{ 'is-active': isCookbookTabActive('recipes') }" :aria-current="isCookbookTabActive('recipes') ? 'page' : undefined" :to="{ name: 'cookbook-recipes', params: { id: cookbookId } }">Recettes</RouterLink>
        <RouterLink :class="{ 'is-active': isCookbookTabActive('planning') }" :aria-current="isCookbookTabActive('planning') ? 'page' : undefined" :to="{ name: 'cookbook-planning', params: { id: cookbookId } }">Planning</RouterLink>
        <RouterLink :class="{ 'is-active': isCookbookTabActive('discussion') }" :aria-current="isCookbookTabActive('discussion') ? 'page' : undefined" :to="{ name: 'cookbook-messages', params: { id: cookbookId } }">Discussion</RouterLink>
        <RouterLink :class="{ 'is-active': isCookbookTabActive('members') }" :aria-current="isCookbookTabActive('members') ? 'page' : undefined" :to="{ name: 'cookbook-members', params: { id: cookbookId } }">Membres</RouterLink>
        <RouterLink :class="{ 'is-active': isCookbookTabActive('settings') }" :aria-current="isCookbookTabActive('settings') ? 'page' : undefined" :to="{ name: 'cookbook-settings', params: { id: cookbookId } }">Paramètres</RouterLink>
      </nav>
      <nav v-if="isSettingsContext" class="context-navigation" aria-label="Navigation des paramètres">
        <RouterLink :to="{ name: 'profile' }">Profil</RouterLink><RouterLink :to="{ name: 'profile-food-preferences' }">Préférences alimentaires</RouterLink><RouterLink :to="{ name: 'profile-usage-preferences' }">Préférences d’utilisation</RouterLink><RouterLink :to="{ name: 'security' }">Sécurité</RouterLink>
      </nav>
      <main id="main-content" class="main-content"><slot /></main>
      <nav class="mobile-primary-navigation" aria-label="Accès rapide mobile">
        <RouterLink v-for="item in mobilePrimaryItems" :key="item.name" :to="{ name: item.name }" :class="{ 'is-active': isNavigationItemActive(item) }" :aria-current="isNavigationItemActive(item) ? 'page' : undefined">
          <component :is="item.icon" :size="19" aria-hidden="true" /><span>{{ item.label }}</span>
        </RouterLink>
      </nav>
    </div>
  </div>
</template>

<style scoped>
.app-shell { min-height: 100vh; background: var(--color-background); color: var(--color-text); }
.sidebar { position: fixed; inset: 0 auto 0 0; z-index: 20; display: flex; width: 16rem; flex-direction: column; border-right: 1px solid var(--color-border); background: var(--color-surface); box-shadow: 4px 0 18px rgb(38 48 42 / 3%); }
.sidebar-header { display: flex; align-items: center; justify-content: space-between; min-height: 4.25rem; padding: 1rem 1.15rem; border-bottom: 1px solid var(--color-border); }
.brand { color: var(--color-text); font-size: .95rem; font-weight: 800; letter-spacing: .16em; text-decoration: none; }
.brand:hover { color: var(--color-primary); }
.sidebar-navigation { display: grid; gap: .25rem; padding: 1.25rem .7rem; }
.navigation-link, .account-link { position: relative; display: flex; align-items: center; gap: .7rem; min-height: 2.75rem; padding: .65rem .8rem; border-radius: var(--radius-md); color: var(--color-text-secondary); font-size: .9rem; font-weight: 700; text-decoration: none; transition: color .16s ease, background-color .16s ease; }
.navigation-link svg, .account-link svg { flex: 0 0 auto; color: var(--color-text-muted); transition: color .16s ease; }
.navigation-link:hover, .navigation-link:focus-visible, .account-link:hover, .account-link:focus-visible { color: var(--color-text); background: var(--color-surface-subtle); outline-offset: 1px; }
.navigation-link:hover svg, .navigation-link:focus-visible svg, .account-link:hover svg, .account-link:focus-visible svg { color: var(--color-primary); }
.navigation-link.is-active { color: var(--color-primary); background: var(--color-primary-soft); }
.navigation-link.is-active::before { position: absolute; inset: .55rem auto .55rem 0; width: .2rem; border-radius: var(--radius-pill); background: var(--color-primary); content: ''; }
.navigation-link.is-active svg { color: var(--color-primary); }
.sidebar-footer { margin-top: auto; padding: .8rem .7rem; border-top: 1px solid var(--color-border); }
.shell-body { min-height: 100vh; margin-left: 16rem; }
.global-header { position: sticky; top: 0; z-index: 10; display: flex; align-items: center; gap: 1rem; min-height: 4.25rem; padding: .7rem 2rem; border-bottom: 1px solid var(--color-border); background: color-mix(in srgb, var(--color-surface) 94%, transparent); backdrop-filter: blur(12px); }
.header-search { display: flex; align-items: center; gap: .6rem; min-width: 17rem; color: var(--color-text-muted); }
.header-search a { overflow: hidden; color: var(--color-text-secondary); font-size: .9rem; text-decoration: none; text-overflow: ellipsis; white-space: nowrap; }
.header-search a:hover { color: var(--color-primary); }
.header-actions { display: flex; align-items: center; gap: .5rem; margin-left: auto; }
.header-action, .icon-button, .user-trigger { display: inline-flex; align-items: center; justify-content: center; gap: .45rem; min-height: 2.4rem; border: 1px solid var(--color-border); border-radius: var(--radius-md); background: transparent; color: var(--color-text-secondary); font: inherit; font-size: .875rem; font-weight: 700; text-decoration: none; cursor: pointer; transition: border-color .16s ease, background-color .16s ease, color .16s ease; }
.header-action:hover, .icon-button:hover, .user-trigger:hover { border-color: var(--color-border-strong); background: var(--color-surface-subtle); color: var(--color-text); }
.header-action { padding: .4rem .7rem; }
.icon-button { width: 2.4rem; padding: 0; }
.user-trigger { padding: .2rem .5rem .2rem .3rem; }
.user-avatar { width: 1.85rem; height: 1.85rem; border: 1px solid var(--color-border); border-radius: 50%; object-fit: cover; }
.header-action:focus-visible, .icon-button:focus-visible, .user-trigger:focus-visible, .popover-menu a:focus-visible, .popover-menu button:focus-visible, .context-navigation a:focus-visible { outline: 3px solid color-mix(in srgb, var(--color-focus) 72%, transparent); outline-offset: 2px; }
.header-popover { position: relative; }
.popover-menu { position: absolute; right: 0; display: grid; gap: .25rem; min-width: 12rem; margin-top: .5rem; padding: .45rem; border: 1px solid var(--color-border); border-radius: var(--radius-lg); background: var(--color-surface-raised); box-shadow: var(--shadow-md); }
.popover-menu a, .popover-menu button { display: flex; align-items: center; gap: .5rem; padding: .6rem .65rem; border: 0; border-radius: var(--radius-sm); background: transparent; color: var(--color-text); font: inherit; font-size: .875rem; text-align: left; text-decoration: none; cursor: pointer; }
.popover-menu a:hover, .popover-menu button:hover { background: var(--color-surface-subtle); color: var(--color-primary); }
.context-navigation { display: flex; gap: 1.25rem; padding: .7rem 2rem; overflow-x: auto; border-bottom: 1px solid var(--color-border); background: var(--color-surface); }
.context-navigation a { position: relative; flex: 0 0 auto; padding: .2rem 0; color: var(--color-text-secondary); font-size: .875rem; font-weight: 700; text-decoration: none; }
.context-navigation a.router-link-active, .context-navigation a.is-active { color: var(--color-primary); }
.context-navigation a.router-link-active::after, .context-navigation a.is-active::after { position: absolute; right: 0; bottom: -.7rem; left: 0; height: 2px; border-radius: var(--radius-pill); background: var(--color-primary); content: ''; }
.main-content { width: min(100% - 4rem, 76rem); margin: 0 auto; padding: 2rem 0 3rem; }
.mobile-menu-button, .sidebar-close, .navigation-scrim, .mobile-primary-navigation { display: none; }
@media (max-width: 52rem) {
  .sidebar { transform: translateX(-100%); transition: transform .2s ease; box-shadow: var(--shadow-lg); }
  .sidebar.is-open { transform: translateX(0); }
  .sidebar-close, .mobile-menu-button { display: inline-flex; }
  .navigation-scrim { position: fixed; inset: 0; z-index: 15; display: block; border: 0; background: rgb(38 48 42 / 42%); cursor: pointer; }
  .shell-body { margin-left: 0; padding-bottom: 4.75rem; }
  .global-header { padding: .7rem 1rem; }
  .header-search { flex: 1; min-width: 0; }
  .header-search a { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  .header-action span, .user-name { display: none; }
  .header-action { width: 2.5rem; padding: 0; }
  .main-content { width: min(100% - 2rem, 76rem); }
  .mobile-primary-navigation { position: fixed; inset: auto 0 0; z-index: 10; display: grid; grid-template-columns: repeat(4, 1fr); min-height: 4.25rem; padding: .35rem .5rem calc(.35rem + env(safe-area-inset-bottom)); border-top: 1px solid var(--app-border); background: var(--app-surface); }
  .mobile-primary-navigation a { display: flex; align-items: center; justify-content: center; gap: .2rem; min-height: 3.5rem; flex-direction: column; border-radius: var(--radius-md); color: var(--color-text-secondary); font-size: .72rem; font-weight: 700; text-decoration: none; }
  .mobile-primary-navigation a.is-active { color: var(--color-primary); background: var(--color-primary-soft); }
  .mobile-primary-navigation a:focus-visible { outline: 3px solid color-mix(in srgb, var(--color-focus) 72%, transparent); outline-offset: -3px; }
}
@media (prefers-reduced-motion: reduce) { .sidebar { transition: none; } }
</style>
