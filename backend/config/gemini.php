<?php

/**
 * Gemini configuration.
 *
 * The API key is read from the environment and never leaves the server. Nothing
 * in this file is exposed to the browser — that is the whole point of moving the
 * integration into Laravel.
 */
return [

    /*
    |--------------------------------------------------------------------------
    | Credentials
    |--------------------------------------------------------------------------
    | Set GEMINI_API_KEY in backend/.env. Get a key at:
    | https://aistudio.google.com/app/apikey
    */
    'api_key' => env('GEMINI_API_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Model
    |--------------------------------------------------------------------------
    | Any Gemini model id that supports function calling. Change it in .env
    | rather than here — no code change is needed to switch models.
    |
    | Note: gemini-1.5-flash is retired for API keys created after Sept 2025.
    | Newer keys should use gemini-flash-lite-latest or gemini-2.0-flash.
    */
    'model' => env('GEMINI_MODEL', 'gemini-flash-lite-latest'),

    'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),

    /*
     | Seconds to wait for a single Gemini call before giving up.
     |
     | Keep this comfortably under PHP's max_execution_time (30s by default on
     | the `artisan serve` dev server). If the HTTP timeout is the longer of the
     | two, PHP kills the process first and you get an opaque fatal error
     | instead of our clean TIMEOUT response.
     */
    'timeout' => (int) env('GEMINI_TIMEOUT', 25),

    /*
     | Force cURL to connect over IPv4.
     |
     | On networks where IPv6 is advertised but black-holed, cURL picks the
     | AAAA record, connects, and then receives nothing — every request dies at
     | the timeout with "0 bytes received". Forcing IPv4 avoids that, and is
     | harmless where IPv6 works. Set GEMINI_FORCE_IPV4=false to disable.
     */
    'force_ipv4' => filter_var(env('GEMINI_FORCE_IPV4', true), FILTER_VALIDATE_BOOL),

    /** Retries on transient (5xx / connection) failures. */
    'retries' => (int) env('GEMINI_RETRIES', 2),

    /*
    |--------------------------------------------------------------------------
    | Agent behaviour
    |--------------------------------------------------------------------------
    | max_tool_rounds is a safety valve: the model can call tools at most this
    | many times before we stop the loop and fail cleanly. There are four tools,
    | so a recipe can legitimately need several rounds.
    */
    'max_tool_rounds' => (int) env('GEMINI_MAX_TOOL_ROUNDS', 6),

    'temperature' => (float) env('GEMINI_TEMPERATURE', 0.4),

    /*
     | Wall-clock budget, in seconds, for one whole agent request.
     |
     | A single recipe involves several sequential Gemini calls (get_bean_profile,
     | then calculate_brew_ratio, then the recipe itself), so the total easily
     | exceeds PHP's default max_execution_time of 30s. Without raising the limit
     | PHP kills the process mid-request and the clean error handling never runs —
     | you get an opaque FatalError instead of a TIMEOUT response.
     |
     | Set to 0 to disable the limit entirely.
     */
    'request_time_limit' => (int) env('GEMINI_REQUEST_TIME_LIMIT', 150),

];
