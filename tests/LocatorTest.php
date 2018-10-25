<?php
namespace literal\DI;

use PHPUnit\Framework\TestCase;
use Phake;

/**
 * @covers literal\DI\Locator
 */
class LocatorTest extends TestCase
{
    /** @var Locator */
    private $object;

    /** @var Container */
    private $containerMock;

    protected function setUp()
    {
        $this->containerMock = Phake::mock(Container::class);
        $this->object = new Locator($this->containerMock);
    }

    public function testExistsReturnsWhetherTheElementKeyIsKnownLocallyToTheContainer()
    {
        Phake::when($this->containerMock)->isElementKnownLocally('Foo')->thenReturn(true);

        $result = $this->object->has('Foo');

        $this->assertTrue($result);
    }

    public function testGetGetsLocalElementFromContainer()
    {
        Phake::when($this->containerMock)->getSharedLocalElement('Foo')->thenReturn('The Foo Element');

        $result = $this->object->get('Foo');

        $this->assertEquals('The Foo Element', $result);
    }
}
