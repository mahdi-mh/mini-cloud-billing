<?php

namespace Modules\Wallet\Exceptions;

use RuntimeException;

/** The same idempotency key was reused for a different movement. */
class IdempotencyConflict extends RuntimeException {}
