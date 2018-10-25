<?php
namespace literal\DI;

/**
 * Manage shared elements and delegate construction of new elements to the Factory.
 */
class Container
{
    /** @var array {<element key>: <element>, ...} */
    private $sharedElements = [];

    /**
     * @var Factory|null Technically, the factory is optional, even though the
     *     Container is rather useless without it.
     */
    private $factory;

    /** @var Container|null Optional parent providing elements not known to this container */
    private $parentContainer;

    public function __construct()
    {
        // Predefined key for this container itself
        $this->setSharedElement('Container', $this);
    }

    public function setFactory(Factory $factory): void
    {
        $this->factory = $factory;
    }

    public function setParentContainer(Container $parentContainer): void
    {
        $this->parentContainer = $parentContainer;
    }

    /**
     * Create container instance for a new local context
     */
    public function createChildContainer(): Container
    {
        $childContainer = new self();
        $childContainer->setParentContainer($this);
        return $childContainer;
    }

    /**
     * Register existing element
     */
    public function setSharedElement(string $elementKey, $element): void
    {
        $this->sharedElements[$elementKey] = $element;
    }

    /**
     * Retrieve or create element by its key
     *
     * When the element is created, every subsequent call to getSharedElement() will
     * return the same instance for the given key.
     *
     * If a parent container exists and the element neither exists nor can be
     * created locally, the parent container is asked for the element.
     */
    public function getSharedElement(string $elementKey)
    {
        $element = $this->tryGettingSharedLocalElement($elementKey)
            ?? $this->tryGettingSharedParentElement($elementKey);
        if ($element === null) {
            throw new UnknownElementException($elementKey);
        }
        return $element;
    }

    /**
     * Retrieve or create an element by its key from this container's scope only.
     *
     * Created elements are shared: every subsequent call to getSharedElement() or
     * getSharedLocalElement() will return the same instance for the given key.
     *
     * Parent containers - if any - are not being accessed.
     */
    public function getSharedLocalElement(string $elementKey)
    {
        $element = $this->tryGettingSharedLocalElement($elementKey);
        if ($element === null) {
            throw new UnknownElementException($elementKey);
        }
        return $element;
    }

    private function tryGettingSharedLocalElement(string $elementKey)
    {
        if (!isset($this->sharedElements[$elementKey]) && $this->canFactoryCreateElement($elementKey)) {
            $this->sharedElements[$elementKey] = $this->factory->createElement($elementKey);
        }
        return $this->sharedElements[$elementKey] ?? null;
    }

    private function tryGettingSharedParentElement(string $elementKey)
    {
        return $this->parentContainer
            ? $this->parentContainer->getSharedElement($elementKey)
            : null;
    }

    /**
     * Create an exclusive private element not stored in and shared through the container.
     *
     * If a parent container exists and the object is not creatable locally, the parent container
     * is asked to create the object.
     */
    public function createPrivateElement(string $elementKey)
    {
        $element = $this->canFactoryCreateElement($elementKey)
            ? $this->factory->createElement($elementKey)
            : ($this->parentContainer ? $this->parentContainer->createPrivateElement($elementKey) : null);
        if ($element === null) {
            throw new UnknownElementException($elementKey);
        }
        return $element;
    }

    /**
     * Check whether the specified object exists or can be created by this container or
     * any of it's parents (if any). In other words: Check if $elementKey is valid.
     */
    public function isElementKnown(string $elementKey): bool
    {
        return $this->isElementKnownLocally($elementKey) || $this->isElementKnownToParent($elementKey);
    }

    /**
     * Check whether the specified object exists or can be created by this container only
     * (ignoring the parent container).
     */
    public function isElementKnownLocally(string $elementKey): bool
    {
        return isset($this->sharedElements[$elementKey]) || $this->canFactoryCreateElement($elementKey);
    }

    private function canFactoryCreateElement(string $elementKey): bool
    {
        return $this->factory && $this->factory->canCreateElement($elementKey);
    }

    private function isElementKnownToParent(string $elementKey): bool
    {
        return $this->parentContainer && $this->parentContainer->isElementKnown($elementKey);
    }
}
