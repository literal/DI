<?php
namespace literal\DI;

/**
 * Encapsulate interpretation and validation of element definition
 */
class ElementDefinition
{
    /** @var array */
    private $source = [];

    /** @var string */
    private $elementAlias;

    /**
     * @param array|string $definitionSource The element definition hash or string as received from the outside
     * @param string $elementAlias For identifying the offending element in error messages
     */
    public function __construct($definitionSource, string $elementAlias)
    {
        if (is_array($definitionSource)) {
            $this->source = $definitionSource;
        } elseif (is_string($definitionSource)) { // Shorthand syntax where definition is only a class name
            $this->source = ['class' => $definitionSource];
        }
        $this->elementAlias = $elementAlias;
    }

    public function getElementAlias(): string
    {
        return $this->elementAlias;
    }

    public function hasClassFile(): bool
    {
        return isset($this->source['file']);
    }

    public function getClassFile(): ?string
    {
        $classFile = $this->source['file'] ?? null;
        if ($classFile !== null && !is_string($classFile)) {
            throw new BadElementDefinitionException($this->elementAlias, 'Class file is not a string');
        }
        return $classFile;
    }

    public function getArgumentExpressions(): array
    {
        return isset($this->source['args'])
            ? (is_array($this->source['args']) ? $this->source['args'] : [$this->source['args']])
            : [];
    }

    public function hasClassName(): bool
    {
        return isset($this->source['class']);
    }

    public function getClassName(): ?string
    {
        $className = $this->source['class'] ?? null;
        if ($className !== null && !is_string($className)) {
            throw new BadElementDefinitionException($this->elementAlias, 'Class name is not a string');
        }
        return $className;
    }

    public function hasCreatorFunction(): bool
    {
        return isset($this->source['creator']);
    }

    public function getCreatorFunction(): callable
    {
        $creator = $this->source['creator'] ?? null;
        if ($creator !== null && !is_callable($creator)) {
            throw new BadElementDefinitionException($this->elementAlias, 'Creator is not a valid callable');
        }
        return $creator;
    }

    public function hasSubmap(): bool
    {
        return isset($this->source['submap']) && $this->source['submap'];
    }

    public function getSubmapSource(): array
    {
        $submap = $this->source['submap'] ?? [];
        if (!is_array($submap)) {
            throw new BadElementDefinitionException($this->elementAlias, 'Submap is not an array');
        }
        return $submap;
    }
}