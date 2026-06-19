<?php
declare(strict_types=1);

namespace App\Helpers;

class Jwt
{
    public static function getSecret(): string
    {
        $secret = getenv('JWT_SECRET') ?: ($_ENV['JWT_SECRET'] ?? null);
        if (empty($secret)) {
            $secret = '762f974b879d5fb8469032c78ede41f441da8f3837bee657541dfa369b541d38';
        }
        return $secret;
    }

    public static function encode(array $payload, string $secret, int $expSeconds = 3600): string
    {
        $header = ['alg' => 'HS256', 'typ' => 'JWT'];

        $now = time();
        $payload['iat'] = $now;
        $payload['exp'] = $now + $expSeconds;

        $base64url = function ($data) {
            return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
        };

        $segments = [];
        $segments[] = $base64url(json_encode($header));
        $segments[] = $base64url(json_encode($payload));

        $signingInput = implode('.', $segments);
        $signature = hash_hmac('sha256', $signingInput, $secret, true);
        $segments[] = $base64url($signature);

        return implode('.', $segments);
    }

    public static function decode(string $token, string $secret): array
    {
        $base64urlDecode = function ($data) {
            $remainder = strlen($data) % 4;
            if ($remainder) {
                $padlen = 4 - $remainder;
                $data .= str_repeat('=', $padlen);
            }
            return base64_decode(strtr($data, '-_', '+/'));
        };

        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new \UnexpectedValueException('Invalid token format');
        }

        [$headerB64, $payloadB64, $signatureB64] = $parts;

        $headerJson = $base64urlDecode($headerB64);
        $payloadJson = $base64urlDecode($payloadB64);
        $signature = $base64urlDecode($signatureB64);

        if ($headerJson === false || $payloadJson === false || $signature === false) {
            throw new \UnexpectedValueException('Invalid token encoding');
        }

        $header = json_decode($headerJson, true);
        $payload = json_decode($payloadJson, true);

        if (!is_array($header) || !is_array($payload)) {
            throw new \UnexpectedValueException('Invalid token payload');
        }

        if (empty($header['alg']) || strtoupper($header['alg']) !== 'HS256') {
            throw new \UnexpectedValueException('Unsupported token algorithm');
        }

        $signingInput = $headerB64 . '.' . $payloadB64;
        $expectedSig = hash_hmac('sha256', $signingInput, $secret, true);

        if (!hash_equals($expectedSig, $signature)) {
            throw new \UnexpectedValueException('Signature verification failed');
        }

        // check exp
        if (isset($payload['exp']) && time() >= (int)$payload['exp']) {
            throw new \UnexpectedValueException('Token has expired');
        }

        return $payload;
    }
}
