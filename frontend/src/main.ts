import { createApp } from 'vue';

import App from './App.vue';
import { pinia } from './pinia';
import { router } from './router';
import { useAuthStore } from './stores/auth';

const app = createApp(App);

app.use(pinia);

await useAuthStore(pinia).restoreSession();

app.use(router);
await router.isReady();

app.mount('#app');
