<?php
namespace literal\DI;

use DomainException;
use Psr\Container\ContainerExceptionInterface;
use Throwable;

class BadElementDefinitionException extends DomainException implements ContainerExceptionInterface
{
    public function __construct(string $elementAlias, string $message, int $code = 0, Throwable $previous = null)
    {
        parent::__construct('Bad element definition "' . $elementAlias . '":' . $message, $code, $previous);
    }
}
