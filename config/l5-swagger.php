<?php

return [
    'default' => 'default',
    'documentations' => [
        'default' => [
            'api' => [
                'title' => 'SIAKAD UIKA Satria Work API',
                'version' => '1.0.0',
            ],
            'routes' => [
                'api' => 'api/documentation',
                'docs' => 'docs',
                'oauth2_callback' => 'api/oauth2-callback',
                'middleware' => [
                    'api' => [],
                    'assets' => [],
                    'docs' => [],
                    'oauth2_callback' => [],
                ],
            ],
            'paths' => [
                'docs' => storage_path('api-docs'),
                'docs_json' => 'api-docs.json',
                'docs_yaml' => 'api-docs.yaml',
                'format_to_use_for_docs' => env('L5_FORMAT_TO_USE_FOR_DOCS', 'json'),
                'annotations' => [
                    base_path('app/Http/Controllers'),
                    base_path('app/Http/Requests'),
                    base_path('app/Models'),
                ],
                'base' => env('L5_SWAGGER_BASE_PATH', null),
                'swagger_ui_assets_path' => env('L5_SWAGGER_UI_ASSETS_PATH', 'vendor/swagger-api/swagger-ui/dist/'),
                'excludes' => [
                    base_path('app/Providers'),
                    base_path('app/Facades'),
                    base_path('app/Services'),
                    base_path('app/Console'),
                    base_path('app/Jobs'),
                    base_path('app/Mail'),
                    base_path('app/Notifications'),
                    base_path('app/Events'),
                    base_path('app/Listeners'),
                ],
            ],
            'scanOptions' => [
                'exclude' => [
                    'App\Facades\*',
                ],
                'pattern' => '*.php',
                // HAPUS bagian 'analyser' => [...] yang menyebabkan error
            ],
            'generate_always' => env('L5_SWAGGER_GENERATE_ALWAYS', false),
            'generate_yaml_copy' => env('L5_SWAGGER_GENERATE_YAML_COPY', false),
            'swagger_version' => env('L5_SWAGGER_VERSION', '3.0'),
            'proxy' => env('L5_SWAGGER_PROXY', false),
            'validator_url' => env('L5_SWAGGER_VALIDATOR_URL', null),
            'ui' => [
                'display' => [
                    'doc_expansion' => env('L5_SWAGGER_DOC_EXPANSION', 'none'),
                    'filter' => env('L5_SWAGGER_FILTER', true),
                ],
                'authorization' => [
                    'persist_authorization' => env('L5_SWAGGER_PERSIST_AUTHORIZATION', false),
                ],
            ],
            'constants' => [
                'L5_SWAGGER_CONST_HOST' => env('L5_SWAGGER_CONST_HOST', 'http://localhost'),
            ],
        ],
    ],
];