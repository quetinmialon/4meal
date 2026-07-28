import { createRouter, createWebHistory, type RouteRecordRaw, type Router, type RouterHistory } from 'vue-router';
import type { Pinia } from 'pinia';

import { pinia } from '@/pinia';
import { useAuthStore } from '@/stores/auth';

import DashboardView from '@/views/DashboardView.vue';
import ChangePasswordView from '@/views/ChangePasswordView.vue';
import CookbookInvitationView from '@/views/CookbookInvitationView.vue';
import CookbookView from '@/views/CookbookView.vue';
import LoginView from '@/views/LoginView.vue';
import RegisterSuccessView from '@/views/RegisterSuccessView.vue';
import RegisterView from '@/views/RegisterView.vue';
import RecipeCreateView from '@/views/RecipeCreateView.vue';
import RecipeDetailView from '@/views/RecipeDetailView.vue';
import RecipeEditView from '@/views/RecipeEditView.vue';
import PublicRecipesView from '@/views/PublicRecipesView.vue';
import RecipesView from '@/views/RecipesView.vue';
import SearchView from '@/views/SearchView.vue';
import PlanningView from '@/views/PlanningView.vue';

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
    path: '/dashboard',
    name: 'dashboard',
    component: DashboardView,
    meta: {
      requiresAuth: true,
    },
  },
  {
    path: '/cookbooks/:id',
    name: 'cookbook',
    component: CookbookView,
    meta: {
      requiresAuth: true,
    },
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
    path: '/recettes/:id',
    name: 'recipe-detail',
    component: RecipeDetailView,
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
    path: '/inscription',
    name: 'register',
    component: RegisterView,
  },
  {
    path: '/inscription/confirmation',
    name: 'register-success',
    component: RegisterSuccessView,
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
