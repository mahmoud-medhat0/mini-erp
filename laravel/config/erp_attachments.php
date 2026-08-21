<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Attachment entity registry
    |--------------------------------------------------------------------------
    |
    | Browser-provided entity_type values are matched only against this allowlist.
    | Future ERP modules can register their real business entities here when their
    | policies exist. Unknown entity types are denied by default.
    |
    */
    'entities' => [
        'company' => [
            'table' => 'company',
            'key' => 'id',
            'permissions' => [
                'view' => 'settings.configure',
                'attach' => 'settings.configure',
            ],
        ],
        'branch' => [
            'table' => 'branch',
            'key' => 'id',
            'permissions' => [
                'view' => 'settings.configure',
                'attach' => 'settings.configure',
            ],
        ],
        'number_sequence' => [
            'table' => 'number_sequence',
            'key' => 'id',
            'permissions' => [
                'view' => 'settings.configure',
                'attach' => 'settings.configure',
            ],
        ],
    ],
];
