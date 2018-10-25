<?php
namespace literal\DI;

use RuntimeException;
use Psr\Container\ContainerExceptionInterface;
use Throwable;

class ElementCreationFailureException extends RuntimeException implements ContainerExceptionInterface
{
    public function __construct(string $elementAlias, string $message, int $code = 0, Throwable $previous = null)
    {
        parent::__construct('Failed to create element "' . $elementAlias . '":' . $message, $code, $previous);
    }
}
