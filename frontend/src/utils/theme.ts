export type ThemeMode = 'light' | 'dark';
export type ThemePreference = ThemeMode | 'system';

export const THEME_STORAGE_KEY = '4meal.theme.preference';

function isThemePreference(value: unknown): value is ThemePreference {
  return value === 'light' || value === 'dark' || value === 'system';
}

function systemTheme(): ThemeMode {
  return typeof window !== 'undefined' && typeof window.matchMedia === 'function' && window.matchMedia('(prefers-color-scheme: dark)').matches
    ? 'dark'
    : 'light';
}

export function readThemePreference(): ThemePreference | null {
  if (typeof window === 'undefined') return null;
  const value = window.localStorage.getItem(THEME_STORAGE_KEY);
  return isThemePreference(value) ? value : null;
}

export function resolveTheme(preference: ThemePreference): ThemeMode {
  return preference === 'system' ? systemTheme() : preference;
}

let mediaQuery: MediaQueryList | null = null;
let mediaQueryListener: ((event: MediaQueryListEvent) => void) | null = null;

function watchSystemTheme(preference: ThemePreference): void {
  if (mediaQuery !== null && mediaQueryListener !== null) {
    mediaQuery.removeEventListener('change', mediaQueryListener);
    mediaQuery = null;
    mediaQueryListener = null;
  }

  if (preference !== 'system' || typeof window === 'undefined' || typeof window.matchMedia !== 'function') return;

  mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
  mediaQueryListener = () => applyThemePreference('system', false);
  mediaQuery.addEventListener('change', mediaQueryListener);
}

export function applyThemePreference(preference: ThemePreference, persist = true): ThemeMode {
  const resolved = resolveTheme(preference);

  if (typeof document !== 'undefined') {
    document.documentElement.dataset.theme = resolved;
    document.documentElement.style.colorScheme = resolved;
  }

  if (persist && typeof window !== 'undefined') {
    window.localStorage.setItem(THEME_STORAGE_KEY, preference);
  }

  watchSystemTheme(preference);
  return resolved;
}

export function initializeTheme(): ThemeMode {
  return applyThemePreference(readThemePreference() ?? 'system', false);
}

export function initialThemePreference(serverTheme?: ThemeMode | null): ThemePreference {
  return readThemePreference() ?? serverTheme ?? 'system';
}

export function applyUserTheme(serverTheme?: ThemeMode | null): ThemeMode {
  return applyThemePreference(readThemePreference() ?? serverTheme ?? 'system', false);
}
