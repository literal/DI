<?php
namespace literal\DI;

use InvalidArgumentException;
use Psr\Container\NotFoundExceptionInterface;
use Throwable;

class UnknownElementException extends InvalidArgumentException implements NotFoundExceptionInterface
{
    public function __construct(string $elementKey, int $code = 0, Throwable $previous = null)
    {
        parent::__construct('Unknown element key "' . $elementKey . '"', $code, $previous);
    }
}
