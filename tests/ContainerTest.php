<?php
namespace literal\DI;

use PHPUnit\Framework\TestCase;
use Phake;

/**
 * @covers literal\DI\Container
 */
class ContainerTest extends TestCase
{
    /** @var Container */
    private $object;

    protected function setUp()
    {
        $this->object = new Container();
    }

    // Shared Element Basics
    // =========================================================================

    public function testSharedElementIsKnown()
    {
        $this->object->setSharedElement('ElementKey', new \stdClass);

        $this->assertTrue($this->object->isElementKnownLocally('ElementKey'));
        $this->assertTrue($this->object->isElementKnown('ElementKey'));
    }

    public function testCreatableElementIsKnown()
    {
        $this->object->setFactory($this->createFactoryMock(['ElementKey' => new \stdClass]));

        $this->assertTrue($this->object->isElementKnownLocally('ElementKey'));
        $this->assertTrue($this->object->isElementKnown('ElementKey'));
    }

    public function testUnknownElementIsNotKnown()
    {
        $this->assertFalse($this->object->isElementKnownLocally('UnknownElementKey'));
        $this->assertFalse($this->object->isElementKnown('UnknownElementKey'));
    }

    public function testSharedElementCanBeRetrieved()
    {
        $dummyElement = new \stdClass;

        $this->object->setSharedElement('ElementKey', $dummyElement);

        $this->assertSame($dummyElement, $this->object->getSharedLocalElement('ElementKey'));
        $this->assertSame($dummyElement, $this->object->getSharedElement('ElementKey'));
    }

    public function testNonExistentSharedElementIsCreated()
    {
        $dummyElement = new \stdClass;
        $this->object->setFactory($this->createFactoryMock(['ElementKey' => $dummyElement]));

        $this->assertSame($dummyElement, $this->object->getSharedLocalElement('ElementKey'));
        $this->assertSame($dummyElement, $this->object->getSharedElement('ElementKey'));
    }

    public function testPredefinedSharedElementKeyContainerYieldsContainerObjectItself()
    {
        $this->assertSame($this->object, $this->object->getSharedElement('Container'));
    }

    public function testFactoryIsNotCalledWhenSharedElementAlreadyExists()
    {
        $existingElement = new \stdClass;
        $this->object->setSharedElement('ElementKey', $existingElement);

        $creatableElement = new \stdClass;
        $factoryMock = $this->createFactoryMock(['ElementKey' => $creatableElement]);
        $this->object->setFactory($factoryMock);

        $this->assertSame($existingElement, $this->object->getSharedElement('ElementKey'));
        $this->assertNotSame($creatableElement, $this->object->getSharedElement('ElementKey'));
        Phake::verify($factoryMock, Phake::never())->createElement('ElementKey');
    }

    public function testGettingUnknownSharedElementThrowsException()
    {
        $this->expectException(UnknownElementException::class);

        $this->object->getSharedElement('UnknownElementKey');
    }

    // Private Element Creation
    // =========================================================================

    public function testFactoryIsCalledForCreatingPrivateElementEvenIfSharedElementExists()
    {
        $existingElement = new \stdClass;
        $this->object->setSharedElement('ElementKey', new $existingElement);

        $creatableElement = new \stdClass;
        $this->object->setFactory($this->createFactoryMock(['ElementKey' => $creatableElement]));

        $this->assertNotSame($existingElement, $this->object->createPrivateElement('ElementKey'));
        $this->assertSame($creatableElement, $this->object->createPrivateElement('ElementKey'));
    }

    public function testPrivateElementIsNewlyCreatedEachTime()
    {
        $factoryMock = $this->createFactoryMock(['ElementKey' => new \stdClass]);
        $this->object->setFactory($factoryMock);

        $this->object->createPrivateElement('ElementKey');
        $this->object->createPrivateElement('ElementKey');

        Phake::verify($factoryMock, Phake::times(2))->createElement('ElementKey');
    }

    public function testCreatingUnknownPrivateElementThrowsException()
    {
        $this->expectException(UnknownElementException::class);

        $this->object->createPrivateElement('UnknownElementKey');
    }

    // Container Nesting
    // =========================================================================

    public function testParentContainersSharedElementIsKnownButNotLocally()
    {
        $this->object->setSharedElement('ElementKey', new \stdClass);
        $childContainer = $this->object->createChildContainer();

        $this->assertTrue($childContainer->isElementKnown('ElementKey'));
        $this->assertFalse($childContainer->isElementKnownLocally('ElementKey'));
    }

    public function testParentContainersCreatableElementIsKnownButNotLocally()
    {
        $this->object->setFactory($this->createFactoryMock(['ElementKey' => new \stdClass]));

        $childContainer = $this->object->createChildContainer();

        $this->assertTrue($childContainer->isElementKnown('ElementKey'));
        $this->assertFalse($childContainer->isElementKnownLocally('ElementKey'));
    }

    public function testParentContainersSharedElementCanBeRetrievedThroughChildContainer()
    {
        $dummyElement = new \stdClass;
        $this->object->setSharedElement('ElementKey', $dummyElement);

        $childContainer = $this->object->createChildContainer();

        $this->assertSame($dummyElement, $childContainer->getSharedElement('ElementKey'));
    }

    public function testGetSharedLocalElementDoesNotAccessParentContainer()
    {
        $subject = new \stdClass;
        $this->object->setSharedElement('ElementKey', $subject);

        $childContainer = $this->object->createChildContainer();

        $this->expectException(UnknownElementException::class);
        $childContainer->getSharedLocalElement('ElementKey');
    }

    public function testPrivateElementCanBeCreatedByParentContainer()
    {
        $dummyElement = new \stdClass;
        $this->object->setFactory($this->createFactoryMock(['ElementKey' => $dummyElement]));

        $childContainer = $this->object->createChildContainer();

        $this->assertSame($dummyElement, $childContainer->createPrivateElement('ElementKey'));
    }

    public function testCreatableElementHidesParentContainersExistingElement()
    {
        $existingParentElement = new \stdClass;
        $this->object->setSharedElement('ElementKey', $existingParentElement);

        $childContainer = $this->object->createChildContainer();
        $creatableElement = new \stdClass;
        $childContainer->setFactory($this->createFactoryMock(['ElementKey' => $creatableElement]));

        $this->assertSame($creatableElement, $childContainer->getSharedElement('ElementKey'));
    }

    // Test Helpers
    // =========================================================================

    private function createFactoryMock(array $dummyElementMap = []): Factory
    {
        $factoryMock = Phake::mock(Factory::class);
        foreach ($dummyElementMap as $elementKey => $dummyElement) {
            Phake::when($factoryMock)->canCreateElement($elementKey)->thenReturn(true);
            Phake::when($factoryMock)->createElement($elementKey)->thenReturn($dummyElement);
        }
        return $factoryMock;
    }
}
