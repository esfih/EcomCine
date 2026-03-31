<?php
/**
 * EcomCine Update Server — Server-side configuration.
 *
 * Keep real secrets only on the deployed host.
 */

return [
    'github_token'    => 'replace-with-github-token',
    'github_owner'    => 'esfih',
    'github_repo'     => 'EcomCine',
    'plugin_slug'     => 'ecomcine',
    'release_tag_prefix' => 'v',
    'download_secret' => 'replace-with-long-random-secret',
    'cache_ttl'       => 900,
    'name'            => 'EcomCine',
    'author'          => 'EcomCine',
    'homepage'        => 'https://ecomcine.com',
    'description'     => 'Unified EcomCine app plugin updates.',
    'requires'        => '6.5',
    'requires_php'    => '8.1',
    'tested'          => '6.7',
];
