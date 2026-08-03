import { readFile } from 'node:fs/promises';

import { expect, test, type APIRequestContext, type Page } from '@playwright/test';

const password = 'E2ePassword123!';

function account(): { name: string; email: string; password: string } {
  const id = `${Date.now()}-${Math.random().toString(36).slice(2, 8)}`;
  return { name: `Utilisateur E2E ${id}`, email: `e2e-${id}@example.test`, password };
}

async function registerWithApi(request: APIRequestContext, user: ReturnType<typeof account>): Promise<void> {
  const response = await request.post('/api/auth/register', { data: {
    name: user.name,
    email: user.email,
    password: user.password,
    password_confirmation: user.password,
  } });
  expect(response.ok()).toBeTruthy();
}

async function login(page: Page, user: ReturnType<typeof account>): Promise<void> {
  await page.goto('/connexion');
  await page.locator('#email-input').fill(user.email);
  await page.locator('#password-input').fill(user.password);
  await page.getByRole('button', { name: 'Se connecter' }).click();
  await expect(page).toHaveURL(/\/dashboard$/);
}

async function createRecipe(page: Page, title: string): Promise<void> {
  await page.goto('/recettes/nouvelle');
  await page.locator('#recipe-title-input').fill(title);
  await page.locator('#ingredient-name-0').fill('Tomate');
  await page.locator('#step-instruction-0').fill('Préparer la recette.');
  await page.locator('form button[type="submit"]').click();
  await expect(page).toHaveURL(/\/dashboard$/);
}

test('inscription puis connexion', async ({ page }) => {
  const user = account();
  await page.goto('/inscription');
  await page.locator('#name-input').fill(user.name);
  await page.locator('#email-input').fill(user.email);
  await page.locator('#password-input').fill(user.password);
  await page.locator('#passwordConfirmation-input').fill(user.password);
  await page.locator('button[type="submit"]').click();
  await expect(page).toHaveURL(/\/inscription\/confirmation/);
  await login(page, user);
  await expect(page.getByRole('heading', { name: 'Mes cookbooks' })).toBeVisible();
});

test('création d’un cookbook', async ({ page, request }) => {
  const user = account();
  await registerWithApi(request, user);
  await login(page, user);
  await page.getByRole('button', { name: 'Nouveau cookbook' }).click();
  await page.locator('#cookbook-name-input').fill('Cookbook E2E');
  await page.locator('#cookbook-description-input').fill('Recettes de test');
  await page.locator('form button[type="submit"]').click();
  await expect(page).toHaveURL(/\/cookbooks\/[0-9a-f-]+$/);
  await expect(page.getByRole('heading', { name: 'Cookbook E2E' })).toBeVisible();
});

test('invitation d’un membre simulé', async ({ page, request }) => {
  const user = account();
  const memberEmail = `membre-simule-${Date.now()}@example.test`;
  await registerWithApi(request, user);
  await login(page, user);
  await page.getByRole('button', { name: 'Nouveau cookbook' }).click();
  await page.locator('#cookbook-name-input').fill('Cookbook membres E2E');
  await page.locator('form button[type="submit"]').click();
  await expect(page).toHaveURL(/\/cookbooks\//);
  await page.locator('#invitation-email').fill(memberEmail);
  await page.getByRole('button', { name: /Envoyer l.invitation/ }).click();
  await expect(page.getByRole('status').filter({ hasText: memberEmail })).toBeVisible();
});

test('création d’une recette', async ({ page, request }) => {
  const user = account();
  await registerWithApi(request, user);
  await login(page, user);
  const title = 'Recette E2E';
  await createRecipe(page, title);
  await page.goto('/recettes');
  await expect(page.getByRole('heading', { name: title })).toBeVisible();
});

test('ajout d’une recette au planning', async ({ page, request }) => {
  const user = account();
  await registerWithApi(request, user);
  await login(page, user);
  const title = 'Recette planning E2E';
  await createRecipe(page, title);
  await page.goto('/recettes');
  await page.getByRole('link', { name: 'Voir la recette' }).first().click();
  await page.getByRole('button', { name: 'Ajouter au planning' }).click();
  await page.locator('#planning-date').fill('2030-01-15');
  await page.getByRole('dialog').locator('button[type="submit"]').click();
  await expect(page.getByRole('status').filter({ hasText: /ajout.*planning/ })).toBeVisible();
});

test('recherche d’une recette', async ({ page, request }) => {
  const user = account();
  await registerWithApi(request, user);
  await login(page, user);
  const title = `Recette recherche E2E ${Date.now()}`;
  await createRecipe(page, title);
  await page.goto('/recherche');
  await page.locator('#recipe-search').fill(title);
  await page.getByRole('button', { name: 'Rechercher' }).click();
  await expect(page.getByRole('heading', { name: title }).last()).toBeVisible();
});

test('export puis import', async ({ page, request }) => {
  const user = account();
  await registerWithApi(request, user);
  await login(page, user);
  await page.goto('/export');
  await page.getByRole('checkbox').check();
  const downloadPromise = page.waitForEvent('download');
  await page.getByRole('button', { name: /T.l.charger l.export JSON/ }).click();
  const download = await downloadPromise;
  const path = await download.path();
  expect(path).not.toBeNull();
  await page.goto('/import');
  await page.locator('#import-file').setInputFiles({
    name: download.suggestedFilename(),
    mimeType: 'application/json',
    buffer: await readFile(path!),
  });
  await page.getByRole('button', { name: /Importer ce fichier/ }).click();
  await expect(page.getByRole('heading', { name: /Import termin/ })).toBeVisible();
});
