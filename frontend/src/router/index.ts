import { createRouter, createWebHistory } from 'vue-router';

import { useAuthStore } from '@/stores/auth';

import DashboardView from '@/views/DashboardView.vue';
import LoginView from '@/views/LoginView.vue';
import RegisterSuccessView from '@/views/RegisterSuccessView.vue';
import RegisterView from '@/views/RegisterView.vue';

export const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
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
      path: '/inscription',
      name: 'register',
      component: RegisterView,
    },
    {
      path: '/inscription/confirmation',
      name: 'register-success',
      component: RegisterSuccessView,
    },
  ],
});

router.beforeEach((to) => {
  const authStore = useAuthStore();

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
