<?php
namespace literal\DI;

use Psr\Container\ContainerInterface;

/**
 * Wrap the Container providing read-only access to the Container's local objects
 */
class Locator implements ContainerInterface
{
    /** @var Container */
    protected $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * @param string $key
     * @return bool
     */
    public function has($key): bool
    {
        return $this->container->isElementKnownLocally($key);
    }

    /**
     * @param string $key
     * @return mixed
     */
    public function get($key)
    {
        return $this->container->getSharedLocalElement($key);
    }
}
