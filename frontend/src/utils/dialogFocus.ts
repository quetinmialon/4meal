import { nextTick, onBeforeUnmount, watch, type Ref } from 'vue';

const focusableSelector = [
  'a[href]',
  'button:not([disabled])',
  'input:not([disabled])',
  'select:not([disabled])',
  'textarea:not([disabled])',
  '[tabindex]:not([tabindex="-1"])',
].join(',');

export function useDialogFocus(
  dialog: Ref<HTMLElement | null>,
  open: Ref<boolean>,
  onEscape: () => void,
): void {
  let previousFocus: HTMLElement | null = null;

  function handleKeydown(event: KeyboardEvent): void {
    if (event.key === 'Escape') {
      event.preventDefault();
      onEscape();
      return;
    }

    if (event.key !== 'Tab' || dialog.value === null) return;

    const focusable = Array.from(dialog.value.querySelectorAll<HTMLElement>(focusableSelector));
    if (focusable.length === 0) {
      event.preventDefault();
      dialog.value.focus();
      return;
    }

    const first = focusable[0]!;
    const last = focusable[focusable.length - 1]!;
    if (event.shiftKey && document.activeElement === first) {
      event.preventDefault();
      last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
      event.preventDefault();
      first.focus();
    }
  }

  watch(open, async (isOpen) => {
    if (isOpen) {
      previousFocus = document.activeElement instanceof HTMLElement ? document.activeElement : null;
      await nextTick();
      dialog.value?.focus();
      document.addEventListener('keydown', handleKeydown);
    } else {
      document.removeEventListener('keydown', handleKeydown);
      previousFocus?.focus();
      previousFocus = null;
    }
  }, { immediate: true });

  onBeforeUnmount(() => {
    document.removeEventListener('keydown', handleKeydown);
    previousFocus?.focus();
  });
}
