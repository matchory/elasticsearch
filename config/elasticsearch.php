<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Default Elasticsearch Connection Name
    |--------------------------------------------------------------------------
    |
    | Here you may specify which of the Elasticsearch connections below you wish
    | to use as your default connection for all work.
    |
    */
    'default' => env('ELASTIC_CONNECTION', 'default'),

    /*
    |--------------------------------------------------------------------------
    | Elasticsearch Connections
    |--------------------------------------------------------------------------
    |
    | Here are each of the Elasticsearch connections setup for your application.
    | Of course, examples of configuring each Elasticsearch platform.
    |
    */
    'connections' => [
        'default' => [
            'hosts' => env('ELASTICSEARCH_HOST', 'http://localhost:9200'),
            'index' => env('ELASTICSEARCH_INDEX', 'my_index'),
        ],

        //'authenticated' => [
        //    'hosts' => env('ELASTICSEARCH_HOST', 'https://localhost:9200'),
        //    'index' => env('ELASTICSEARCH_INDEX', 'my_index'),
        //    'sslVerification' => false,
        //    'basicAuthentication' => [
        //        'username' => env('ELASTICSEARCH_USERNAME', 'elastic'),
        //        'password' => env('ELASTICSEARCH_PASSWORD'),
        //    ],
        //],
        //
        //'authenticated_with_apikey' => [
        //    'hosts' => env('ELASTICSEARCH_HOST', 'https://localhost:9200'),
        //    'index' => env('ELASTICSEARCH_INDEX', 'my_index'),
        //    'apiKey' => [
        //        'id' => env('ELASTICSEARCH_API_KEY_ID'),
        //        'apiKey' => env('ELASTICSEARCH_API_KEY'),
        //    ],
        //],
        //
        //'multiple_hosts' => [
        //    'hosts' => env(
        //        'ELASTICSEARCH_HOST',
        //        'https://first.host.tld:9200,https://second.host.tld:9200,https://third.host.tld:9200'
        //    ),
        //],
        //
        //'various_settings' => [
        //
        //    // Set Elastic Cloud ID to connect to Elastic Cloud
        //    'elasticCloudId' => env('ELASTICSEARCH_CLOUD_ID'),
        //
        //    // Set number or retries (default is equal to number of nodes)
        //    'retries' => 3,
        //
        //    // Set the selector algorithm
        //    'selector' => true,
        //
        //    // Whether to sniff the connection on startup
        //    'sniffOnStart' => false,
        //
        //    'sslCert' => [
        //
        //        // The name of a file containing a PEM-formatted public TLS certificate
        //        'cert' => env('ELASTICSEARCH_TLS_CERT_PATH'),
        //
        //        // Optional passphrase for the certificate
        //        'password' => env('ELASTICSEARCH_TLS_CERT_PASSPHRASE'),
        //    ],
        //
        //    'sslKey' => [
        //
        //        // The name of a file containing a private TLS key
        //        'key' => env('ELASTICSEARCH_TLS_KEY_PATH'),
        //
        //        // Optional passphrase used to decrypt the private TLS key
        //        'password' => env('ELASTICSEARCH_TLS_KEY_PASSPHRASE'),
        //    ],
        //
        //    // Enable or disable verification of the SSL certificate
        //    'sslVerification' => false,
        //
        //    // Set or disable the x-elastic-client-meta header
        //    'elasticMetaHeader' => true,
        //
        //    // Include the port in Host header.
        //    // See: https://github.com/elastic/elasticsearch-php/issues/993
        //    'includePortInHostHeader' => true,
        //],
    ],

    /*
    |--------------------------------------------------------------------------
    | Elasticsearch Indices
    |--------------------------------------------------------------------------
    |
    | Here you can define your indices, with separate settings and mappings.
    | Edit settings and mappings and run 'php artisan es:index:update' to update
    | indices on elasticsearch server.
    |
    | 'my_index' is just for test. Replace it with a real index name.
    |
    */
    'indices' => [
        'my_index_1' => [
            'aliases' => [
                'my_index',
            ],

            'settings' => [
                'number_of_shards' => 1,
                'number_of_replicas' => 0,
                'index.mapping.ignore_malformed' => false,

                'analysis' => [
                    'filter' => [
                        'english_stop' => [
                            'type' => 'stop',
                            'stopwords' => '_english_',
                        ],
                        'english_keywords' => [
                            'type' => 'keyword_marker',
                            'keywords' => ['example'],
                        ],
                        'english_stemmer' => [
                            'type' => 'stemmer',
                            'language' => 'english',
                        ],
                        'english_possessive_stemmer' => [
                            'type' => 'stemmer',
                            'language' => 'possessive_english',
                        ],
                    ],
                    'analyzer' => [
                        'rebuilt_english' => [
                            'tokenizer' => 'standard',
                            'filter' => [
                                'english_possessive_stemmer',
                                'lowercase',
                                'english_stop',
                                'english_keywords',
                                'english_stemmer',
                            ],
                        ],
                    ],
                ],
            ],

            'mappings' => [
                'posts' => [
                    'properties' => [
                        'title' => [
                            'type' => 'text',
                            'analyzer' => 'english',
                        ],
                    ],
                ],
            ],
        ],
    ],
];
