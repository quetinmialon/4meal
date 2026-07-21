import type { LoginResult } from '@/stores/auth';

type GoogleCallbackResult = {
  handled: boolean;
  result?: LoginResult;
};

export async function handleGoogleAuthCallback(
  params: URLSearchParams,
  completeLogin: (params: URLSearchParams) => Promise<LoginResult>,
): Promise<GoogleCallbackResult> {
  const hasCallbackData = ['access_token', 'token', 'code', 'error', 'error_description', 'oauth_error'].some((key) =>
    params.has(key),
  );

  if (!hasCallbackData) {
    return { handled: false };
  }

  return {
    handled: true,
    result: await completeLogin(params),
  };
}
