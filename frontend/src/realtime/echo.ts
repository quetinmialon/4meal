import Echo from 'laravel-echo';
import Pusher from 'pusher-js';


type EchoConfig = {
  tokenType: string;
  accessToken: string;
};

export type RealtimeConnection = Echo<'reverb'>;

function isEnabled(): boolean {
  return import.meta.env.VITE_REALTIME_ENABLED !== 'false'
    && typeof import.meta.env.VITE_REVERB_APP_KEY === 'string'
    && import.meta.env.VITE_REVERB_APP_KEY.length > 0;
}

export function createRealtimeConnection(config: EchoConfig): RealtimeConnection | null {
  if (!isEnabled() || typeof window === 'undefined') return null;

  (window as unknown as { Pusher: typeof Pusher }).Pusher = Pusher;
  const key = import.meta.env.VITE_REVERB_APP_KEY ?? '';

  const scheme = import.meta.env.VITE_REVERB_SCHEME ?? 'http';
  const host = import.meta.env.VITE_REVERB_HOST ?? window.location.hostname;
  const port = Number(import.meta.env.VITE_REVERB_PORT ?? (scheme === 'https' ? 443 : 8080));
  const authEndpoint = import.meta.env.VITE_REVERB_AUTH_ENDPOINT ?? '/api/broadcasting/auth';
  const authHeaders = {
    Accept: 'application/json',
    ...(config.tokenType !== '' && config.accessToken !== ''
      ? { Authorization: `${config.tokenType} ${config.accessToken}` }
      : {}),
  };

  return new Echo({
    broadcaster: 'reverb',
    key,
    wsHost: host,
    wsPort: port,
    wssPort: port,
    forceTLS: scheme === 'https',
    enabledTransports: ['ws', 'wss'],
    authEndpoint,
    auth: {
      headers: authHeaders,
    },
  });
}
