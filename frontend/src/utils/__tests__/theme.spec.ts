import { beforeEach, describe, expect, it, vi } from 'vitest';

import {
  applyThemePreference,
  initializeTheme,
  readThemePreference,
  resolveTheme,
  THEME_STORAGE_KEY,
} from '../theme';

describe('theme preference', () => {
  let matchesDark = false;
  let changeListener: ((event: MediaQueryListEvent) => void) | null = null;

  beforeEach(() => {
    window.localStorage.clear();
    document.documentElement.dataset.theme = '';
    matchesDark = false;
    changeListener = null;
    vi.stubGlobal('matchMedia', vi.fn(() => ({
      matches: matchesDark,
      media: '(prefers-color-scheme: dark)',
      addEventListener: (_type: string, listener: (event: MediaQueryListEvent) => void) => { changeListener = listener; },
      removeEventListener: vi.fn(),
    })));
  });

  it('applies an explicit preference before the application mounts', () => {
    window.localStorage.setItem(THEME_STORAGE_KEY, 'dark');

    expect(initializeTheme()).toBe('dark');
    expect(document.documentElement.dataset.theme).toBe('dark');
    expect(document.documentElement.style.colorScheme).toBe('dark');
    expect(readThemePreference()).toBe('dark');
  });

  it('resolves system preference from prefers-color-scheme and reacts to changes', () => {
    matchesDark = true;
    expect(resolveTheme('system')).toBe('dark');
    applyThemePreference('system');
    expect(document.documentElement.dataset.theme).toBe('dark');

    matchesDark = false;
    changeListener?.({ matches: false } as MediaQueryListEvent);
    expect(document.documentElement.dataset.theme).toBe('light');
    expect(readThemePreference()).toBe('system');
  });
});
