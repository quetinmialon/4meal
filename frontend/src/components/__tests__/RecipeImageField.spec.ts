import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import RecipeImageField from '../RecipeImageField.vue';

describe('RecipeImageField', () => {
  const createObjectURL = vi.fn((file: File) => `blob:${file.name}`);
  const revokeObjectURL = vi.fn();

  beforeEach(() => {
    vi.stubGlobal('URL', { ...URL, createObjectURL, revokeObjectURL });
    vi.stubGlobal('Image', class {
      width = 800;
      height = 600;
      onload: (() => void) | null = null;
      onerror: (() => void) | null = null;
      set src(_value: string) { queueMicrotask(() => this.onload?.()); }
    });
    createObjectURL.mockClear();
    revokeObjectURL.mockClear();
  });

  afterEach(() => {
    vi.unstubAllGlobals();
  });

  async function selectFile(wrapper: ReturnType<typeof mount>, file: File): Promise<void> {
    const input = wrapper.get('input[type="file"]');
    Object.defineProperty(input.element, 'files', { configurable: true, value: [file] });
    await input.trigger('change');
    await flushPromises();
  }

  it('validates the MIME type and size before accepting a file', async () => {
    const wrapper = mount(RecipeImageField, { props: { modelValue: null } });

    await selectFile(wrapper, new File(['unsafe'], 'payload.exe', { type: 'application/x-msdownload' }));
    expect(wrapper.text()).toContain('JPEG, PNG ou WebP');
    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual([null]);

    await selectFile(wrapper, new File([new Uint8Array(5 * 1024 * 1024 + 1)], 'large.png', { type: 'image/png' }));
    expect(wrapper.text()).toContain('ne doit pas dépasser 5 Mo');
  });

  it('shows a local preview and allows removing the selected replacement', async () => {
    const wrapper = mount(RecipeImageField, { props: { modelValue: null } });
    const file = new File(['image'], 'recipe.png', { type: 'image/png' });

    await selectFile(wrapper, file);

    expect(wrapper.get('img').attributes('src')).toBe('blob:recipe.png');
    expect(wrapper.get('img').attributes('alt')).toContain('Aperçu');
    await wrapper.get('button').trigger('click');
    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual([null]);
    expect(wrapper.find('img').exists()).toBe(false);
  });

  it('keeps the existing image visible until a valid replacement is selected', async () => {
    const wrapper = mount(RecipeImageField, {
      props: { modelValue: null, existingImageUrl: '/storage/recipes/current.jpg' },
    });

    expect(wrapper.get('img').attributes('src')).toBe('/storage/recipes/current.jpg');
    expect(wrapper.find('button').exists()).toBe(false);
  });
});
