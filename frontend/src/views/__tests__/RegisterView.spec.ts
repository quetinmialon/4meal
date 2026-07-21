import { createPinia, setActivePinia } from 'pinia';
import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import RegisterView from '../RegisterView.vue';

const pushMock = vi.fn();
const replaceMock = vi.fn();

vi.mock('vue-router', () => ({
  useRouter: () => ({
    push: pushMock,
    replace: replaceMock,
  }),
}));

describe('RegisterView', () => {
  const fetchMock = vi.fn<typeof fetch>();

  beforeEach(() => {
    setActivePinia(createPinia());
    pushMock.mockReset();
    replaceMock.mockReset();
    fetchMock.mockReset();
    vi.stubGlobal('fetch', fetchMock);
    window.history.replaceState({}, '', '/inscription');
  });

  it('shows ergonomic client-side validation errors before submitting', async () => {
    const wrapper = mount(RegisterView);

    await wrapper.get('form').trigger('submit.prevent');

    expect(wrapper.text()).toContain('Le nom est requis.');
    expect(wrapper.text()).toContain("L'adresse e-mail est requise.");
    expect(wrapper.text()).toContain('Le mot de passe est requis.');
    expect(wrapper.text()).toContain('La confirmation du mot de passe est requise.');
    expect(fetchMock).not.toHaveBeenCalled();
  });

  it('submits the form, shows a loading state and redirects after success', async () => {
    let resolveRequest: ((value: Response | PromiseLike<Response>) => void) | undefined;

    fetchMock.mockImplementation(
      () =>
        new Promise<Response>((resolve) => {
          resolveRequest = resolve;
        }),
    );

    const wrapper = mount(RegisterView);

    await wrapper.get('#name-input').setValue('  Jane Doe  ');
    await wrapper.get('#email-input').setValue('Jane.DOE@example.com');
    await wrapper.get('#password-input').setValue('password123');
    await wrapper.get('#passwordConfirmation-input').setValue('password123');

    await wrapper.get('form').trigger('submit.prevent');

    expect(fetchMock).toHaveBeenCalledTimes(1);
    expect(wrapper.get('button[type="submit"]').text()).toBe('Creation du compte...');
    expect(wrapper.get('fieldset').attributes('disabled')).toBeDefined();

    resolveRequest?.({
      ok: true,
      json: async () => ({
        success: true,
        data: {
          id: 1,
          name: 'Jane Doe',
          email: 'jane.doe@example.com',
        },
      }),
    } as Response);

    await flushPromises();

    expect(fetchMock).toHaveBeenCalledWith('/api/auth/register', {
      method: 'POST',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({
        name: 'Jane Doe',
        email: 'jane.doe@example.com',
        password: 'password123',
        password_confirmation: 'password123',
      }),
    });

    expect(pushMock).toHaveBeenCalledWith({
      name: 'register-success',
      query: {
        email: 'jane.doe@example.com',
      },
    });
  });

  it('renders API validation errors returned by the backend', async () => {
    fetchMock.mockResolvedValue({
      ok: false,
      json: async () => ({
        success: false,
        error: {
          code: 'validation_error',
          message: 'Les donnees fournies sont invalides.',
          details: {
            fields: {
              email: ["Cette valeur pour l'adresse e-mail est deja utilisee."],
            },
          },
        },
      }),
    } as Response);

    const wrapper = mount(RegisterView);

    await wrapper.get('#name-input').setValue('Jane Doe');
    await wrapper.get('#email-input').setValue('jane@example.com');
    await wrapper.get('#password-input').setValue('password123');
    await wrapper.get('#passwordConfirmation-input').setValue('password123');

    await wrapper.get('form').trigger('submit.prevent');
    await flushPromises();

    expect(wrapper.text()).toContain('Les donnees fournies sont invalides.');
    expect(wrapper.text()).toContain("Cette valeur pour l'adresse e-mail est deja utilisee.");
    expect(pushMock).not.toHaveBeenCalled();
  });
});
