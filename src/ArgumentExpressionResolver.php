<?php
namespace literal\DI;

/**
 * Resolve argument expressions from element definitions
 */
class ArgumentExpressionResolver
{
    /** @var Container */
    private $container;

    /** @var string|null */
    private $elementQualifier;

    public function __construct(Container $container, ?string $elementQualifier)
    {
        $this->container = $container;
        $this->elementQualifier = $elementQualifier;
    }

    public function getArgumentValue($argumentExpression)
    {
        if ($argumentExpression === '$') {
            return $this->elementQualifier;
        }

        if ($this->isElementReferenceExpression($argumentExpression)) {
            return $this->resolveElementReferenceExpression($argumentExpression);
        }

        return $argumentExpression;
    }

    private function isElementReferenceExpression($argumentExpression): bool
    {
        return is_string($argumentExpression) && strlen($argumentExpression) > 1
            && ($argumentExpression[0] === '@' || $argumentExpression[0] === '#');
    }

    private function resolveElementReferenceExpression(string $argumentExpression)
    {
        $referencedElementKey = $this->resolveReferencedElementKey(substr($argumentExpression, 1));
        return $argumentExpression[0] === '@'
            ? $this->container->getSharedElement($referencedElementKey)
            : $this->container->createPrivateElement($referencedElementKey);
    }

    private function resolveReferencedElementKey(string $referencedElementExpression): string
    {
        return str_replace(
            '.$',
            isset($this->elementQualifier) ? '.' . $this->elementQualifier : '',
            $referencedElementExpression
        );
    }
}
