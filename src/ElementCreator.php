<?php
namespace literal\DI;

/**
 * Create element from element definition
 */
class ElementCreator
{
    /** @var ArgumentExpressionResolver */
    private $argumentExpressionResolver;

    /** @var ElementDefinition */
    private $elementDefinition;

    /**
     * @param ElementDefinition $elementDefinition
     * @param ArgumentExpressionResolver $argumentExpressionResolver Used for
     *     fetching the created element's dependencies
     */
    public function __construct(
        ElementDefinition $elementDefinition,
        ArgumentExpressionResolver $argumentExpressionResolver
    )
    {
        $this->elementDefinition = $elementDefinition;
        $this->argumentExpressionResolver = $argumentExpressionResolver;
    }

    public function createElement()
    {
        if ($this->elementDefinition->hasClassFile()) {
            $this->loadClassFile($this->elementDefinition->getClassFile());
        }

        $constructionArguments =
            $this->resolveConstructionArguments($this->elementDefinition->getArgumentExpressions());

        if ($this->elementDefinition->hasClassName()) {
            return $this->createElementByClassName(
                $this->elementDefinition->getClassName(),
                $constructionArguments
            );
        }

        if ($this->elementDefinition->hasCreatorFunction()) {
            return $this->createElementByCallable(
                $this->elementDefinition->getCreatorFunction(),
                $constructionArguments
            );
        }

        throw new BadElementDefinitionException(
            $this->elementDefinition->getElementAlias(),
            'Neither "class" nor "creator" specified'
        );
    }

    private function loadClassFile(string $classFile): void
    {
        if (false === include_once $classFile) {
            throw new ElementCreationFailureException(
                $this->elementDefinition->getElementAlias(),
                'Class file ' . $classFile . ' cannot be included'
            );
        }
    }

    private function resolveConstructionArguments(array $argumentExpressions): array
    {
        $argumentValues = [];
        foreach ($argumentExpressions as $argumentExpression) {
            $argumentValues[] = $this->argumentExpressionResolver->getArgumentValue($argumentExpression);
        }
        return $argumentValues;
    }

    private function createElementByClassName(string $className, array $arguments)
    {
        if (!class_exists($className)) {
            throw new ElementCreationFailureException(
                $this->elementDefinition->getElementAlias(),
                'Undefined class ' . $className
            );
        }

        return new $className(...$arguments);
    }

    private function createElementByCallable(callable $creator, array $arguments)
    {
        return \call_user_func_array($creator, $arguments);
    }
}