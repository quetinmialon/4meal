import { apiFetch } from '@/utils/api';

export type ImportErrorDetail = {
  path: string;
  code: string;
  message: string;
};

export type ImportReport = {
  cookbooks?: number;
  recipes: number;
  duplicates: Array<{ path: string; type: string; reason: string }>;
};

export type JsonImportResult =
  | { ok: true; report: ImportReport }
  | { ok: false; message: string; errors: ImportErrorDetail[] };

export type CsvImportResult =
  | { ok: true; report: Pick<ImportReport, 'recipes' | 'duplicates'> }
  | { ok: false; message: string; errors: ImportErrorDetail[] };

type ImportPayload = {
  success?: boolean;
  data?: { report?: ImportReport };
  error?: {
    message?: string;
    details?: { errors?: ImportErrorDetail[]; fields?: Record<string, string[]> };
  };
};

const MAX_IMPORT_SIZE = 10 * 1024 * 1024;

function clientError(message: string, code: string): JsonImportResult {
  return { ok: false, message, errors: [{ path: 'file', code, message }] };
}

function normalizeErrors(payload: ImportPayload | null): ImportErrorDetail[] {
  const errors = payload?.error?.details?.errors;
  if (Array.isArray(errors)) return errors;

  return Object.entries(payload?.error?.details?.fields ?? {}).flatMap(([path, messages]) =>
    messages.map((message) => ({ path, code: 'validation_error', message })),
  );
}

export async function importJsonFile(file: File, tokenType: string, accessToken: string): Promise<JsonImportResult> {
  if (!file.name.toLowerCase().endsWith('.json')) return clientError('Sélectionnez un fichier avec l’extension .json.', 'invalid_extension');
  if (file.size > MAX_IMPORT_SIZE) return clientError('Le fichier ne doit pas dépasser 10 Mo.', 'file_too_large');
  if (file.type !== '' && !['application/json', 'application/ld+json', 'text/json'].includes(file.type)) {
    return clientError('Le fichier sélectionné doit être un document JSON.', 'invalid_mime');
  }

  try {
    const formData = new FormData();
    formData.append('file', file);
    const response = await apiFetch('/api/import', {
      method: 'POST',
      headers: { Accept: 'application/json', Authorization: `${tokenType} ${accessToken}` },
      body: formData,
    });
    const payload = (await response.json().catch(() => null)) as ImportPayload | null;

    if (response.ok && payload?.success === true && payload.data?.report) return { ok: true, report: payload.data.report };

    return {
      ok: false,
      message: payload?.error?.message ?? 'Impossible d’importer ce fichier JSON.',
      errors: normalizeErrors(payload),
    };
  } catch {
    return {
      ok: false,
      message: 'Impossible de joindre le serveur. Réessayez dans un instant.',
      errors: [{ path: '', code: 'network_error', message: 'Le serveur est momentanément indisponible.' }],
    };
  }
}

export async function importMealieFile(file: File, tokenType: string, accessToken: string): Promise<JsonImportResult> {
  if (!file.name.toLowerCase().endsWith('.json')) return clientError('Sélectionnez un fichier avec l’extension .json.', 'invalid_extension');
  if (file.size > MAX_IMPORT_SIZE) return clientError('Le fichier ne doit pas dépasser 10 Mo.', 'file_too_large');
  if (file.type !== '' && !['application/json', 'application/ld+json', 'text/json'].includes(file.type)) {
    return clientError('Le fichier sélectionné doit être un document JSON.', 'invalid_mime');
  }

  try {
    const formData = new FormData();
    formData.append('file', file);
    const response = await apiFetch('/api/import/mealie', {
      method: 'POST',
      headers: { Accept: 'application/json', Authorization: `${tokenType} ${accessToken}` },
      body: formData,
    });
    const payload = (await response.json().catch(() => null)) as ImportPayload | null;

    if (response.ok && payload?.success === true && payload.data?.report) return { ok: true, report: payload.data.report };
    return { ok: false, message: payload?.error?.message ?? 'Impossible d’importer ce fichier Mealie.', errors: normalizeErrors(payload) };
  } catch {
    return { ok: false, message: 'Impossible de joindre le serveur. Réessayez dans un instant.', errors: [{ path: '', code: 'network_error', message: 'Le serveur est momentanément indisponible.' }] };
  }
}

export async function importCsvFile(file: File, tokenType: string, accessToken: string): Promise<CsvImportResult> {
  if (!file.name.toLowerCase().endsWith('.csv')) return clientError('Sélectionnez un fichier avec l’extension .csv.', 'invalid_extension');
  if (file.size > MAX_IMPORT_SIZE) return clientError('Le fichier ne doit pas dépasser 10 Mo.', 'file_too_large');
  if (file.type !== '' && !['text/csv', 'text/plain', 'application/csv'].includes(file.type)) {
    return clientError('Le fichier sélectionné doit être un document CSV.', 'invalid_mime');
  }

  try {
    const formData = new FormData();
    formData.append('file', file);
    const response = await apiFetch('/api/import/csv', {
      method: 'POST',
      headers: { Accept: 'application/json', Authorization: `${tokenType} ${accessToken}` },
      body: formData,
    });
    const payload = (await response.json().catch(() => null)) as ImportPayload | null;

    if (response.ok && payload?.success === true && payload.data?.report) return { ok: true, report: payload.data.report };
    return { ok: false, message: payload?.error?.message ?? 'Impossible d’importer ce fichier CSV.', errors: normalizeErrors(payload) };
  } catch {
    return { ok: false, message: 'Impossible de joindre le serveur. Réessayez dans un instant.', errors: [{ path: '', code: 'network_error', message: 'Le serveur est momentanément indisponible.' }] };
  }
}
