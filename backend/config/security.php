<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Access code
    |--------------------------------------------------------------------------
    | A single shared code that gates every endpoint which costs money.
    |
    | This is deliberately not a user system. It exists for one reason: without
    | it, anyone who finds the deployed URL can spend the owner's Gemini quota,
    | and per-IP rate limiting does not stop a script.
    |
    | Leave EMPTY to disable the gate entirely — that is the local-development
    | default and keeps the app open. Set it before deploying anywhere public.
    |
    | Being a shared secret, it identifies no one and is only as strong as its
    | length. Use something long and random:
    |     php -r "echo bin2hex(random_bytes(16));"
    */
    'access_code' => env('APP_ACCESS_CODE', ''),

];
