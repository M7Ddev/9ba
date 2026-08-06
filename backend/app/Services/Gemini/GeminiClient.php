<?php

namespace App\Services\Gemini;

use App\Exceptions\AgentException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Transport layer: one HTTP call to Gemini's generateContent endpoint.
 *
 * This class knows nothing about coffee. Its only jobs are to send a request
 * with the API key attached and to turn every possible failure into an
 * AgentException carrying a short, stable code.
 *
 * Kept separate from GeminiAgent so the agent's reasoning loop can be read (and
 * presented) without HTTP noise in the way.
 */
class GeminiClient
{
    /**
     * Send one generateContent request.
     *
     * @param  array<string, mixed>  $payload  The full request body.
     * @return array<string, mixed> The decoded response body.
     *
     * @throws AgentException
     */
    public function generateContent(array $payload): array
    {
        $apiKey = config('gemini.api_key');

        // Fail fast and clearly rather than sending an empty key to Google.
        if (blank($apiKey)) {
            throw new AgentException('MISSING_KEY', 'GEMINI_API_KEY is not set in backend/.env', 500);
        }

        $model = config('gemini.model');
        $url = rtrim(config('gemini.base_url'), '/')."/models/{$model}:generateContent";

        try {
            $response = Http::withHeaders([
                // Header auth keeps the key out of the URL, so it never lands
                // in access logs or proxy history.
                'x-goog-api-key' => $apiKey,
                'Content-Type' => 'application/json',
            ])
                ->withOptions($this->transportOptions())
                ->timeout(config('gemini.timeout'))
                // Retry only transient failures; a 400/404 will never succeed.
                ->retry(config('gemini.retries'), 400, function (Throwable $e) {
                    return $e instanceof ConnectionException
                        || ($e instanceof RequestException
                            && $e->response->serverError());
                }, throw: false)
                ->post($url, $payload);
        } catch (ConnectionException $e) {
            // Covers DNS failure, refused connection and read timeout.
            $timedOut = str_contains(strtolower($e->getMessage()), 'timed out');

            throw new AgentException(
                $timedOut ? 'TIMEOUT' : 'NETWORK',
                'Could not reach Gemini: '.$e->getMessage(),
                504,
                $e,
            );
        }

        if ($response->failed()) {
            throw $this->mapHttpError($response->status(), (string) $response->body());
        }

        $decoded = $response->json();

        if (! is_array($decoded)) {
            throw new AgentException('BAD_JSON', 'Gemini returned a non-JSON body.', 502);
        }

        return $decoded;
    }

    /**
     * Guzzle transport options.
     *
     * Forcing IPv4 matters on networks that advertise IPv6 but black-hole it:
     * cURL picks the AAAA record, the connection opens, and then nothing ever
     * arrives — every call dies at the timeout with "0 bytes received".
     *
     * @return array<string, mixed>
     */
    private function transportOptions(): array
    {
        if (! config('gemini.force_ipv4')) {
            return [];
        }

        // CURL_IPRESOLVE_V4 is only defined when the cURL extension is loaded.
        if (! defined('CURL_IPRESOLVE_V4')) {
            return [];
        }

        return ['curl' => [CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4]];
    }

    /**
     * Map an HTTP status onto one of our error codes.
     *
     * Classification is driven by the status code, not by searching the message
     * text — substring matching on error text is unreliable (searching for
     * "rate" matches the "rate" inside "generateContent", which would make every
     * 404 look like a rate limit).
     */
    private function mapHttpError(int $status, string $body): AgentException
    {
        // The full body goes to the Laravel log, never to the browser.
        Log::warning('Gemini API error', ['status' => $status, 'body' => mb_substr($body, 0, 2000)]);

        [$code, $httpStatus] = match (true) {
            $status === 400 => [
                // A 400 is usually a malformed request, but an invalid key also
                // lands here with an explicit "API key not valid" message.
                preg_match('/api[ _-]?key not valid|api_key_invalid/i', $body) === 1
                    ? 'INVALID_KEY'
                    : 'UNKNOWN',
                preg_match('/api[ _-]?key not valid|api_key_invalid/i', $body) === 1 ? 401 : 502,
            ],
            $status === 401, $status === 403 => ['INVALID_KEY', 401],
            $status === 404 => ['MODEL_NOT_FOUND', 502],
            $status === 429 => ['RATE_LIMIT', 429],
            $status >= 500 => ['SERVER', 502],
            default => ['UNKNOWN', 502],
        };

        return new AgentException($code, "Gemini returned HTTP {$status}", $httpStatus);
    }
}
