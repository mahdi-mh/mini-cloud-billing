<?php

return [
    'infra_url' => env('INFRA_URL', 'http://infra:8000/infra'),

    // how long we wait for an answer from the infrastructure
    'infra_timeout' => 5,

    // a sync pass that did not finish is repeated after this many seconds
    'sync_delay' => 3,

    // a server that is still not running after this many passes is given up on
    'sync_max_attempts' => 40,
];
