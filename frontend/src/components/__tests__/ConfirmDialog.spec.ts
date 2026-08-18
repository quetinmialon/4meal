import { nextTick } from 'vue';
import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';

import ConfirmDialog from '../ConfirmDialog.vue';

describe('ConfirmDialog', () => {
  it('announces its title, focuses the dialog, and restores focus on cancel', async () => {
    const opener = document.createElement('button');
    document.body.appendChild(opener);
    opener.focus();

    const wrapper = mount(ConfirmDialog, {
      props: { modelValue: false, title: 'Supprimer la recette', description: 'Cette action est définitive.', tone: 'danger' },
      attachTo: document.body,
    });

    await wrapper.setProps({ modelValue: true });
    await nextTick();
    await nextTick();

    const dialog = wrapper.get('[role="dialog"]');
    expect(dialog.attributes('aria-modal')).toBe('true');
    expect(dialog.text()).toContain('Cette action est définitive.');
    expect(document.activeElement).toBe(dialog.element);

    await dialog.get('button').trigger('click');
    expect(wrapper.emitted('cancel')).toHaveLength(1);
    expect(wrapper.emitted('update:modelValue')).toEqual([[false]]);

    await wrapper.setProps({ modelValue: false });
    await nextTick();
    expect(document.activeElement).toBe(opener);

    wrapper.unmount();
    opener.remove();
  });

  it('confirms without closing until the parent updates the model', async () => {
    const wrapper = mount(ConfirmDialog, { props: { modelValue: true, title: 'Confirmer' } });
    await nextTick();

    await wrapper.get('.confirm-default').trigger('click');

    expect(wrapper.emitted('confirm')).toHaveLength(1);
    expect(wrapper.find('[role="dialog"]').exists()).toBe(true);
    expect(wrapper.emitted('update:modelValue')).toBeUndefined();
    wrapper.unmount();
  });

  it('closes on Escape', async () => {
    const wrapper = mount(ConfirmDialog, { props: { modelValue: true, title: 'Confirmer' } });
    await nextTick();

    document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }));
    expect(wrapper.emitted('cancel')).toHaveLength(1);
    expect(wrapper.emitted('update:modelValue')).toEqual([[false]]);
    wrapper.unmount();
  });
});
