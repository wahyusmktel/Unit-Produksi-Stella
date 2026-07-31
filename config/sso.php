<?php

return [
    'base_url' => rtrim((string) env('SISFO_SSO_URL', 'https://sso.smktelkom-lpg.id'), '/'),
    'client_id' => env('SISFO_SSO_CLIENT_ID'),
    'redirect_uri' => env('SISFO_SSO_REDIRECT_URI', env('APP_URL').'/auth/sisfo/callback'),
    'scope' => env('SISFO_SSO_SCOPE', 'profile:read'),
];
