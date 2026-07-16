import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';

import AppStatus from '../AppStatus.vue';

describe('AppStatus', () => {
  it('renders the provided label', () => {
    const wrapper = mount(AppStatus, {
      props: {
        label: 'Tooling configured',
      },
    });

    expect(wrapper.text()).toContain('Tooling configured');
  });
});
