<?php
namespace literal\DI;

/**
 * Create elements according to their definitions in the element map
 */
class Factory
{
    /** @var Container */
    private $container;

    /** @var ElementDefinition[] Creatable element definitions, indexed by element alias */
    private $elementMap = [];

    public function __construct(Container $container)
    {
        $this->container = $container;
        $container->setFactory($this);
    }

    /**
     * @param array $elementMapSource {<element alias>: <element definition>, ...}
     *
     *     An element definition is either a string representing a class name or
     *     a hash with the following elements (all of which are optional, except
     *     that either 'class' or 'creator' must be specified):
     *
     *     - class:   string, fully qualified class name of an object to be created.
     *     - creator: callable, function creating and returning the element.
     *     - file:    string, path to a PHP file to be loaded before creating the element.
     *     - args:    array|mixed, one or more expressions (see below) for arguments to be
     *                passed to the constructor or creator function.
     *     - submap:  array, another nested element map defining elements only accessible for the
     *                current element and the submap's elements themselves.
     *
     *     Argument expressions:
     *
     *     - "$":               The element qualifier the current element was requested with
     *     - "@ElementAlias":   Reference to a shared element "ElementAlias"
     *     - "@ElementAlias.$": Reference to a shared element "ElementAlias", appending the current qualifier
     *     - "#ElementAlias":   Reference to a private element "ElementAlias"
     *     - "#ElementAlias.$": Reference to a private element "ElementAlias", appending the current qualifier
     *
     *     All other argument expressions are passed to the constructor or creator function unmodified.
     */
    public function setElementMap(array $elementMapSource): void
    {
        $this->elementMap = [];
        foreach ($elementMapSource as $elementAlias => $elementDefinitionSource) {
            $this->elementMap[$elementAlias] = new ElementDefinition($elementDefinitionSource, $elementAlias);
        }
    }

    public function canCreateElement(string $elementKey): bool
    {
        return $this->findLongestMatchingElementAlias($elementKey) !== null;
    }

    public function createElement(string $elementKey)
    {
        $elementAlias = $this->findLongestMatchingElementAlias($elementKey);
        if ($elementAlias === null) {
            throw new UnknownElementException($elementKey);
        }
        $elementQualifier = $this->extractQualifierFromElementKey($elementKey, $elementAlias);
        $elementDefinition = $this->elementMap[$elementAlias];

        $argumentExpressionResolver = new ArgumentExpressionResolver(
            $elementDefinition->hasSubmap()
                ? $this->createChildContainer($elementDefinition->getSubmapSource())
                : $this->container,
            $elementQualifier
        );

        $elementCreator = new ElementCreator($elementDefinition, $argumentExpressionResolver);
        return $elementCreator->createElement();
    }

    private function findLongestMatchingElementAlias(string $elementKey): ?string
    {
        $parts = explode('.', $elementKey);
        while (count($parts) > 0) {
            $mapKeyCandidate = implode('.', $parts);
            if (isset($this->elementMap[$mapKeyCandidate])) {
                return $mapKeyCandidate;
            }
            array_pop($parts);
        }

        return null;
    }

    private function extractQualifierFromElementKey(string $elementKey, string $elementAlias): ?string
    {
        return strpos($elementKey, $elementAlias . '.') === 0
            ? substr($elementKey, strlen($elementAlias) + 1)
            : null;
    }

    private function createChildContainer(array $elementMap): Container
    {
        $childContainer = $this->container->createChildContainer();
        $childFactory = new self($childContainer);
        $childFactory->setElementMap($elementMap);
        return $childContainer;
    }
}
