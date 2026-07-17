import { createRouter, createWebHistory } from 'vue-router';

import RegisterSuccessView from '@/views/RegisterSuccessView.vue';
import RegisterView from '@/views/RegisterView.vue';

export const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/',
      redirect: {
        name: 'register',
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
