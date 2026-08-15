<?php

use Modules\Search\Services\QbankSearchService;

return [
    'name' => 'Search',

    /*
    |--------------------------------------------------------------------------
    | Context search providers
    |--------------------------------------------------------------------------
    |
    | Adding a new context only requires a provider implementing
    | ScopedSearchProvider and a new entry here. API routing and response
    | formatting remain shared.
    |
    */
    'scopes' => [
        'qbank' => QbankSearchService::class,
    ],
];
