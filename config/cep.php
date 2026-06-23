<?php

// config for JeffersonGoncalves/Cep
return [
    /*
    |--------------------------------------------------------------------------
    | HTTP Timeout
    |--------------------------------------------------------------------------
    |
    | The maximum number of seconds to wait for each provider HTTP request
    | before giving up and trying the next provider.
    |
    */
    'timeout' => 5,

    /*
    |--------------------------------------------------------------------------
    | SSL Verification
    |--------------------------------------------------------------------------
    |
    | Whether the TLS certificate of each provider should be verified. This
    | should always remain true in production. Guzzle ships with a CA bundle
    | (composer/ca-bundle), so disabling this is almost never required.
    |
    */
    'verify_ssl' => true,

    /*
    |--------------------------------------------------------------------------
    | Database Cache TTL
    |--------------------------------------------------------------------------
    |
    | The number of seconds a CEP stored in the database is considered fresh.
    | When the stored record is older than this value it will be fetched again
    | from the providers. Use null to cache the records forever.
    |
    */
    'cache_ttl' => null,
];
