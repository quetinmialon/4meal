export type JsonExportResult =
  | { ok: true; filename: string }
  | { ok: false; message: string };

type ApiErrorPayload = {
  success?: false;
  error?: { message?: string };
};

function safeFilename(contentDisposition: string | null): string {
  const match = contentDisposition?.match(/filename\s*=\s*"?([^";]+)"?/i);
  const filename = match?.[1]?.trim() ?? '';

  return /^4meal-export-\d{8}-\d{6}\.json$/.test(filename)
    ? filename
    : '4meal-export.json';
}

export async function downloadJsonExport(tokenType: string, accessToken: string): Promise<JsonExportResult> {
  try {
    const response = await fetch('/api/export', {
      headers: {
        Accept: 'application/json',
        Authorization: `${tokenType} ${accessToken}`,
      },
    });

    if (!response.ok) {
      const payload = (await response.json().catch(() => null)) as ApiErrorPayload | null;

      return {
        ok: false,
        message: payload?.error?.message ?? 'Impossible de préparer l’export JSON.',
      };
    }

    const blob = await response.blob();
    if (blob.size === 0) return { ok: false, message: 'Le fichier exporté est vide.' };

    const filename = safeFilename(response.headers.get('Content-Disposition'));
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = filename;
    link.click();
    URL.revokeObjectURL(url);

    return { ok: true, filename };
  } catch {
    return { ok: false, message: 'Impossible de joindre le serveur. Réessayez dans un instant.' };
  }
}
