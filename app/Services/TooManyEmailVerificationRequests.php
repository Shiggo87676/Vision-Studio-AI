<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

final class TooManyEmailVerificationRequests extends RuntimeException
{
}
