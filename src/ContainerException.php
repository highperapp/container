<?php

declare(strict_types=1);

namespace HighPerApp\HighPer\Container;

use Psr\Container\ContainerExceptionInterface;

/**
 * Container Exception
 *
 * General container exception for configuration and resolution errors
 */
class ContainerException extends \Exception implements ContainerExceptionInterface
{
}
