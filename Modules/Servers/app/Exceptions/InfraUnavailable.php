<?php

namespace Modules\Servers\Exceptions;

use RuntimeException;

/** The infrastructure failed, timed out or answered with an error. We don't know if the action happened. */
class InfraUnavailable extends RuntimeException {}
