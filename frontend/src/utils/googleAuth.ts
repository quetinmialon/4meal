import type { LoginResult } from '@/stores/auth';
import { handleOAuthCallback, type OAuthCallbackResult } from './oauth';

type GoogleCallbackResult = OAuthCallbackResult;

export async function handleGoogleAuthCallback(
  params: URLSearchParams,
  completeLogin: (params: URLSearchParams) => Promise<LoginResult>,
): Promise<GoogleCallbackResult> {
  return handleOAuthCallback(params, completeLogin);
}
