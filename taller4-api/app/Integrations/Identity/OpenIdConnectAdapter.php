<?php

namespace App\Integrations\Identity;

use App\Models\IdentityFlow;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/** Real protocol adapter; requires an institution-provisioned client and explicit trusted endpoints. */
class OpenIdConnectAdapter implements ProveedorIdentidad
{
    private function settings(): array
    {
        $cfg = config('integrations.identity');
        foreach (['issuer', 'authorization_endpoint', 'token_endpoint', 'jwks_uri', 'redirect_uri'] as $key) {
            abort_unless(is_string($cfg[$key]) && filter_var($cfg[$key], FILTER_VALIDATE_URL) && parse_url($cfg[$key], PHP_URL_SCHEME) === 'https', 503, 'OIDC requiere endpoints HTTPS configurados');
        }
        abort_unless($cfg['client_id'], 503, 'OIDC requiere un cliente institucional configurado');

        return $cfg;
    }

    public function authorizationUrl(string $state, string $nonce, string $challenge): ?string
    {
        $cfg = $this->settings();

        return $cfg['authorization_endpoint'].'?'.http_build_query([
            'client_id' => $cfg['client_id'], 'redirect_uri' => $cfg['redirect_uri'],
            'response_type' => 'code', 'scope' => 'openid', 'state' => $state, 'nonce' => $nonce,
            'code_challenge' => $challenge, 'code_challenge_method' => 'S256',
        ]);
    }

    public function validar(string $code, string $verifier, IdentityFlow $flow): array
    {
        $cfg = $this->settings();
        try {
            $args = ['grant_type' => 'authorization_code', 'client_id' => $cfg['client_id'],
                'redirect_uri' => $cfg['redirect_uri'], 'code' => $code, 'code_verifier' => $verifier];
            // Confidential clients may use client_secret_post; never expose this secret to JS.
            if ($cfg['client_secret']) {
                $args['client_secret'] = $cfg['client_secret'];
            }
            $response = Http::asForm()->acceptJson()->connectTimeout(3)->timeout(10)->withoutRedirecting()->post($cfg['token_endpoint'], $args);
            abort_if($response->serverError(), 503, 'Proveedor de identidad temporalmente no disponible');
            abort_unless($response->successful() && is_string($response->json('id_token')), 400, 'El proveedor rechazó el código de identidad');
            $keys = Http::acceptJson()->connectTimeout(3)->timeout(10)->withoutRedirecting()->get($cfg['jwks_uri']);
            abort_unless($keys->successful() && is_array($keys->json('keys')), 503, 'No se pudo validar la identidad institucional');
            $trustedKeys = array_values(array_filter($keys->json('keys'), fn ($key) => ($key['kty'] ?? '') === 'RSA'
                && ($key['alg'] ?? 'RS256') === 'RS256' && ($key['use'] ?? 'sig') === 'sig'));
            $payload = JWT::decode($response->json('id_token'), JWK::parseKeySet(['keys' => $trustedKeys], 'RS256'));
            $audiences = (array) ($payload->aud ?? []);
            abort_unless(($payload->iss ?? null) === $cfg['issuer'] && in_array($cfg['client_id'], $audiences, true)
                && (count($audiences) <= 1 || ($payload->azp ?? null) === $cfg['client_id'])
                && ($payload->nonce ?? null) === $flow->nonce && is_string($payload->sub ?? null) && strlen($payload->sub) <= 255
                && strlen($payload->sub) > 0 && isset($payload->exp, $payload->iat) && $payload->exp > time()
                && $payload->iat <= time(), 400, 'La identidad institucional no es válida');

            return ['issuer' => $payload->iss, 'subject' => $payload->sub];
        } catch (HttpExceptionInterface $e) {
            throw $e;
        } catch (ConnectionException $e) {
            abort(503, 'Proveedor de identidad temporalmente no disponible');
        } catch (\Throwable $e) {
            // JWT errors must not include raw tokens, key material or provider responses.
            abort(400, 'La identidad institucional no es válida');
        }
    }
}
