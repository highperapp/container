<?php

declare(strict_types=1);

namespace HighPerApp\HighPer\Container;

use Psr\Container\NotFoundExceptionInterface;

/**
 * Not Found Exception
 *
 * Thrown when a service is not found in the container
 */
class NotFoundException extends ContainerException implements NotFoundExceptionInterface
{
}
