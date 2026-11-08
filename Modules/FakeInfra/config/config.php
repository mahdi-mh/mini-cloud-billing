<?php

return [
    // the routes only exist in the process that plays the infrastructure
    'serve' => (bool) env('FAKE_INFRA_SERVE', false),

    // share of calls (0..1) that fail without doing anything
    'fail_rate' => (float) env('FAKE_INFRA_FAIL_RATE', 0.2),

    // share of calls (0..1) that do the work and then answer with an error
    'lie_rate' => (float) env('FAKE_INFRA_LIE_RATE', 0.2),

    // seconds before a new server is ready
    'create_delay' => (int) env('FAKE_INFRA_CREATE_DELAY', 4),
];
