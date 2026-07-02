<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'efact' => [
        'env'                => env('EFACT_ENV', 'prueba'),
        // Produccion
        'url'                => env('EFACT_URL', 'https://ose-gw1.efact.pe/api-efact-ose'),
        'client_id'          => env('EFACT_CLIENT_ID', 'client'),
        'client_secret'      => env('EFACT_CLIENT_SECRET', 'secret'),
        'username'           => env('EFACT_USERNAME'),
        'password'           => env('EFACT_PASSWORD'),
        // Si está definido, se usa tal cual en Authorization: Bearer (mismo uso que Postman) y no se llama a /oauth/token
        'bearer_token'       => env('EFACT_BEARER_TOKEN'),
        // Prueba
        'url_test'           => env('EFACT_URL_TEST', 'https://ose-gw1.efact.pe/api-efact-ose'),
        'client_id_test'     => env('EFACT_CLIENT_ID_TEST', 'client'),
        'client_secret_test' => env('EFACT_CLIENT_SECRET_TEST', 'secret'),
        'username_test'      => env('EFACT_USERNAME_TEST'),
        'password_test'      => env('EFACT_PASSWORD_TEST'),
        'bearer_token_test'  => env('EFACT_BEARER_TOKEN_TEST'),
        // URL web del proveedor OSE para la leyenda del PDF (reemplaza www.sunat.gob.pe).
        'web_url'            => env('EFACT_WEB_URL', 'www.efact.pe'),
    ],

];
