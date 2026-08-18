import { createRouter, createWebHistory, type RouteRecordRaw, type Router, type RouterHistory } from 'vue-router';
import type { Pinia } from 'pinia';

import { pinia } from '@/pinia';
import { useAuthStore } from '@/stores/auth';

import DashboardView from '@/views/DashboardView.vue';
import CookbooksView from '@/views/CookbooksView.vue';
import DataView from '@/views/DataView.vue';
import ExportView from '@/views/ExportView.vue';
import ImportView from '@/views/ImportView.vue';
import ChangePasswordView from '@/views/ChangePasswordView.vue';
import EmailVerificationView from '@/views/EmailVerificationView.vue';
import CookbookInvitationView from '@/views/CookbookInvitationView.vue';
import CookbookHomeView from '@/views/CookbookHomeView.vue';
import CookbookRecipesView from '@/views/CookbookRecipesView.vue';
import CookbookMembersView from '@/views/CookbookMembersView.vue';
import CookbookSettingsView from '@/views/CookbookSettingsView.vue';
import CookbookMessagesView from '@/views/CookbookMessagesView.vue';
import LoginView from '@/views/LoginView.vue';
import ForgotPasswordView from '@/views/ForgotPasswordView.vue';
import ResetPasswordView from '@/views/ResetPasswordView.vue';
import RegisterSuccessView from '@/views/RegisterSuccessView.vue';
import RegisterView from '@/views/RegisterView.vue';
import RecipeCreateView from '@/views/RecipeCreateView.vue';
import RecipeDetailView from '@/views/RecipeDetailView.vue';
import RecipeHistoryView from '@/views/RecipeHistoryView.vue';
import RecipeEditView from '@/views/RecipeEditView.vue';
import PublicRecipesView from '@/views/PublicRecipesView.vue';
import RecipesView from '@/views/RecipesView.vue';
import SearchView from '@/views/SearchView.vue';
import PlanningView from '@/views/PlanningView.vue';
import ShoppingListView from '@/views/ShoppingListView.vue';
import ProfileView from '@/views/ProfileView.vue';
import TwoFactorVerificationView from '@/views/TwoFactorVerificationView.vue';
import NotFoundView from '@/views/NotFoundView.vue';

const routes: RouteRecordRaw[] = [
  {
    path: '/',
    redirect: {
      name: 'login',
    },
  },
  {
    path: '/connexion',
    name: 'login',
    component: LoginView,
    meta: {
      guestOnly: true,
    },
  },
  {
    path: '/verification-2fa',
    name: 'two-factor-verification',
    component: TwoFactorVerificationView,
  },
  {
    path: '/mot-de-passe-oublie',
    name: 'forgot-password',
    component: ForgotPasswordView,
  },
  {
    path: '/nouveau-mot-de-passe',
    name: 'reset-password',
    component: ResetPasswordView,
  },
  {
    path: '/verification-email',
    name: 'email-verification-pending',
    component: EmailVerificationView,
  },
  {
    path: '/verification-email/:id/:token',
    name: 'email-verification',
    component: EmailVerificationView,
  },
  {
    path: '/dashboard',
    name: 'dashboard',
    component: DashboardView,
    meta: {
      requiresAuth: true,
    },
  },
  {
    path: '/cookbooks',
    name: 'cookbooks',
    component: CookbooksView,
    meta: { requiresAuth: true },
  },
  {
    path: '/donnees',
    name: 'data',
    component: DataView,
    meta: { requiresAuth: true },
  },
  {
    path: '/parametres',
    name: 'settings',
    redirect: { name: 'profile' },
    meta: { requiresAuth: true },
  },
  {
    path: '/export',
    name: 'export',
    component: ExportView,
    meta: {
      requiresAuth: true,
    },
  },
  {
    path: '/import',
    name: 'import',
    component: ImportView,
    meta: {
      requiresAuth: true,
    },
  },
  {
    path: '/cookbooks/:id',
    name: 'cookbook',
    component: CookbookHomeView,
    meta: {
      requiresAuth: true,
    },
  },
  {
    path: '/cookbooks/:id/messages',
    name: 'cookbook-messages',
    component: CookbookMessagesView,
    meta: {
      requiresAuth: true,
    },
  },
  {
    path: '/cookbooks/:id/recettes',
    name: 'cookbook-recipes',
    component: CookbookRecipesView,
    meta: { requiresAuth: true },
  },
  {
    path: '/cookbooks/:id/membres',
    name: 'cookbook-members',
    component: CookbookMembersView,
    meta: { requiresAuth: true },
  },
  {
    path: '/cookbooks/:id/planning',
    name: 'cookbook-planning',
    component: PlanningView,
    meta: { requiresAuth: true },
  },
  {
    path: '/cookbooks/:id/parametres',
    name: 'cookbook-settings',
    component: CookbookSettingsView,
    meta: { requiresAuth: true },
  },
  {
    path: '/recettes/nouvelle',
    name: 'recipe-create',
    component: RecipeCreateView,
    meta: {
      requiresAuth: true,
    },
  },
  {
    path: '/decouvrir/recettes',
    name: 'public-recipes',
    component: PublicRecipesView,
    meta: {
      requiresAuth: true,
    },
  },
  {
    path: '/recettes',
    name: 'recipes',
    component: RecipesView,
    meta: {
      requiresAuth: true,
    },
  },
  {
    path: '/recherche',
    name: 'search',
    component: SearchView,
    meta: {
      requiresAuth: true,
    },
  },
  {
    path: '/planning',
    name: 'planning',
    component: PlanningView,
    meta: {
      requiresAuth: true,
    },
  },
  {
    path: '/liste-de-courses',
    name: 'shopping-list',
    component: ShoppingListView,
    meta: {
      requiresAuth: true,
    },
  },
  {
    path: '/recettes/:id',
    name: 'recipe-detail',
    component: RecipeDetailView,
    meta: {
      requiresAuth: true,
    },
  },
  {
    path: '/recettes/:id/historique',
    name: 'recipe-history',
    component: RecipeHistoryView,
    meta: {
      requiresAuth: true,
    },
  },
  {
    path: '/recettes/:id/modifier',
    name: 'recipe-edit',
    component: RecipeEditView,
    meta: {
      requiresAuth: true,
    },
  },
  {
    path: '/invitations/:token',
    name: 'cookbook-invitation',
    component: CookbookInvitationView,
  },
  {
    path: '/mot-de-passe',
    name: 'change-password',
    component: ChangePasswordView,
    meta: {
      requiresAuth: true,
    },
  },
  {
    path: '/profil',
    name: 'profile',
    component: ProfileView,
    meta: {
      requiresAuth: true,
    },
  },
  {
    path: '/profil/preferences-alimentaires',
    name: 'profile-food-preferences',
    component: ProfileView,
    meta: { requiresAuth: true },
  },
  {
    path: '/profil/preferences-utilisation',
    name: 'profile-usage-preferences',
    component: ProfileView,
    meta: { requiresAuth: true },
  },
  {
    path: '/securite',
    name: 'security',
    component: ChangePasswordView,
    meta: { requiresAuth: true },
  },
  {
    path: '/inscription',
    name: 'register',
    component: RegisterView,
  },
  {
    path: '/inscription/confirmation',
    name: 'register-success',
    component: RegisterSuccessView,
  },
  {
    path: '/:pathMatch(.*)*',
    name: 'not-found',
    component: NotFoundView,
  },
];

export function createAppRouter(history: RouterHistory = createWebHistory(import.meta.env.BASE_URL)): Router {
  return createRouter({
    history,
    routes,
  });
}

export function installAuthGuard(router: Router, storePinia: Pinia): void {
  router.beforeEach(async (to) => {
    const authStore = useAuthStore(storePinia);

    await authStore.restoreSession();

    if (to.meta.requiresAuth && !authStore.isAuthenticated) {
      return {
        name: 'login',
      };
    }

    if (
      to.meta.requiresAuth &&
      authStore.user?.email_verified === false &&
      to.name !== 'email-verification-pending' &&
      to.name !== 'email-verification'
    ) {
      return { name: 'email-verification-pending' };
    }

    if (to.meta.guestOnly && authStore.isAuthenticated) {
      return {
        name: 'dashboard',
      };
    }

    return true;
  });
}

export const router = createAppRouter();

installAuthGuard(router, pinia);
