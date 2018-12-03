<?php
namespace literal\DI;

use PHPUnit\Framework\TestCase;
use Phake;

use literal\DI\DummyElements\{Element, ConstructorArgRecorder};

/**
 * @covers literal\DI\Factory
 * @covers literal\DI\ElementDefinition
 * @covers literal\DI\ElementCreator
 * @covers literal\DI\ArgumentExpressionResolver
 */
class FactoryTest extends TestCase
{
    /** @var Factory */
    private $object;

    /** @var Container */
    private $containerMock;

    protected function setUp()
    {
        $this->containerMock = Phake::mock(Container::class);
        $this->object = new Factory($this->containerMock);
    }

    // Basic Element Creation
    // =========================================================================

    public function testElementMapMemberIsReportedCreatable()
    {
        $this->object->setElementMap([
            'ElementAlias' => ['class' => Element::class]
        ]);

        $this->assertTrue($this->object->canCreateElement('ElementAlias'));
    }

    public function testUnknownElementKeyIsNotReportedCreatable()
    {
        $this->assertFalse($this->object->canCreateElement('UnknownElementAlias'));
    }

    public function testCreateElementByClassName()
    {
        $this->object->setElementMap([
            'ElementAlias' => ['class' => Element::class]
        ]);

        $element = $this->object->createElement('ElementAlias');

        $this->assertInstanceOf(Element::class, $element);
    }

    public function testCreateElementByClassNameInShorthandSyntax()
    {
        $this->object->setElementMap([
            'ElementAlias' => Element::class
        ]);

        $element = $this->object->createElement('ElementAlias');

        $this->assertInstanceOf(Element::class, $element);
    }

    public function testCreateElementByNonExistentClassNameThrowsException()
    {
        $this->object->setElementMap([
            'ElementAlias' => ['class' => 'UnknownClass']
        ]);

        $this->expectException(ElementCreationFailureException::class);
        $this->object->createElement('ElementAlias');
    }

    public function testClassNameNotAStringThrowsException()
    {
        $this->object->setElementMap([
            'ElementAlias' => ['class' => 1234]
        ]);

        $this->expectException(BadElementDefinitionException::class);
        $this->object->createElement('ElementAlias');
    }

    public function testCreateElementByCallable()
    {
        $this->object->setElementMap([
            'ElementAlias' => [
                'creator' => function () {
                    return new Element();
                }
            ]
        ]);

        $element = $this->object->createElement('ElementAlias');

        $this->assertInstanceOf(Element::class, $element);
    }

    public function testCreateNonObjectElementByCallable()
    {
        $this->object->setElementMap([
            'ElementAlias' => [
                'creator' => function () {
                    return ['foo' => 'bar'];
                }
            ]
        ]);

        $element = $this->object->createElement('ElementAlias');

        $this->assertEquals(['foo' => 'bar'], $element);
    }

    public function testCreatorNotCallableThrowsException()
    {
        $this->object->setElementMap([
            'ElementAlias' => [
                'creator' => 'not a callable'
            ]
        ]);

        $this->expectException(BadElementDefinitionException::class);
        $this->object->createElement('ElementAlias');
    }

    public function testIncompleteElementDefinitionThrowsException()
    {
        $this->object->setElementMap([
            'ElementAlias' => [] // neither 'class' not 'creator' specified
        ]);

        $this->expectException(BadElementDefinitionException::class);
        $this->object->createElement('ElementAlias');
    }

    public function testCreateUnknownElementThrowsException()
    {
        $this->expectException(UnknownElementException::class);
        $this->object->createElement('UnknownElementAlias');
    }

    // Element Creation Arguments
    // =========================================================================

    public function testArgumentsArePassedToElementConstructor()
    {
        $this->object->setElementMap([
            'ElementAlias' => [
                'args' => ['foo', 123, ['bar' => 'quux']],
                'class' => ConstructorArgRecorder::class
            ]
        ]);

        /* @var $element ConstructorArgRecorder */
        $element = $this->object->createElement('ElementAlias');

        $this->assertEquals(['foo', 123, ['bar' => 'quux']], $element->constructorArgs);
    }

    public function testShorthandSyntaxForSingleArgument()
    {
        $this->object->setElementMap([
            'ElementAlias' => [
                'args' => 'sole arg', // Note this is not an array
                'class' => ConstructorArgRecorder::class
            ]
        ]);

        /* @var $element ConstructorArgRecorder */
        $element = $this->object->createElement('ElementAlias');

        $this->assertEquals(['sole arg'], $element->constructorArgs);
    }

    public function testArgumentsArePassedToElementCreatorFunction()
    {
        $capturedArguments = [];
        $this->object->setElementMap([
            'ElementAlias' => [
                'args' => ['foo', 123, ['bar' => 'quux']],
                'creator' => function () use (&$capturedArguments) {
                    $capturedArguments = func_get_args();
                    return new Element;
                }
            ]
        ]);

        $this->object->createElement('ElementAlias');

        $this->assertEquals(['foo', 123, ['bar' => 'quux']], $capturedArguments);
    }

    public function testSharedElementReferenceIsFetchedFromContainer()
    {
        $sharedDependency = new Element;
        Phake::when($this->containerMock)->getSharedElement('SharedDependencyAlias')->thenReturn($sharedDependency);
        $this->object->setElementMap([
            'ElementAlias' => [
                // '@' marks the rest of the string as a shared element's key
                'args' => ['@SharedDependencyAlias'],
                'class' => ConstructorArgRecorder::class
            ]
        ]);

        /* @var $element ConstructorArgRecorder */
        $element = $this->object->createElement('ElementAlias');

        $this->assertSame($sharedDependency, $element->constructorArgs[0]);
    }

    public function testPrivateElementReferenceIsFetchedFromContainer()
    {
        $privateDependency = new Element;
        Phake::when($this->containerMock)->createPrivateElement('PrivateDependencyAlias')->thenReturn($privateDependency);
        $this->object->setElementMap([
            'ElementAlias' => [
                // '#' marks the rest of the string as the key of an element to be created as private instance
                'args' => ['#PrivateDependencyAlias'],
                'class' => ConstructorArgRecorder::class
            ]
        ]);

        /* @var $element ConstructorArgRecorder */
        $element = $this->object->createElement('ElementAlias');

        $this->assertSame($privateDependency, $element->constructorArgs[0]);
    }

    // Element Qualifiers
    // =========================================================================

    public function testElementMapMemberIsReportedCreatableWhenQualifierIsAppended()
    {
        $this->object->setElementMap([
            'ElementAlias' => ['class' => Element::class]
        ]);

        $this->assertTrue($this->object->canCreateElement('ElementAlias.qualifier'));
    }

    /**
     * The qualifier is not actually used here. We only verify that
     * the factory does not regard the qualifier as part of the object alias.
     */
    public function testCreateObjectWithQualifier()
    {
        $this->object->setElementMap([
            'ElementAlias' => ['class' => Element::class]
        ]);

        $element = $this->object->createElement('ElementAlias.qualifier');

        $this->assertInstanceOf(Element::class, $element);
    }

    public function testDollarSignArgumentYieldsElementQualifier()
    {
        $this->object->setElementMap([
            'ElementAlias' => [
                // Special argument '$' represents the requested element qualifier
                'args' => ['$'],
                'class' => ConstructorArgRecorder::class
            ]
        ]);

        /* @var $element ConstructorArgRecorder */
        $element = $this->object->createElement('ElementAlias.qualifier');

        $this->assertEquals(['qualifier'], $element->constructorArgs);
    }

    public function testOnlyTheUnmatchedTrailingPartOfTheElementKeyIsConsideredAQualifier()
    {
        $this->object->setElementMap([
            // Note the dot in the element alias
            'ElementAli.as' => [
                'args' => ['$'],
                'class' => ConstructorArgRecorder::class
            ]
        ]);

        /* @var $element ConstructorArgRecorder */
        $element = $this->object->createElement('ElementAli.as.qualifier');

        $this->assertEquals(['qualifier'], $element->constructorArgs);
    }

    public function testElementWithMoreSpecificAliasHasPrecedence()
    {
        $this->object->setElementMap([
            'ElementAlias' => ['class' => \stdClass::class],
            'ElementAlias.foo' => ['class' => Element::class]
        ]);

        // The key 'ElementAlias.foo' matches both element map entries:
        // - Alias 'ElementAlias' + qualifier 'foo'
        // - Alias 'ElementAlias.foo' + no qualifier
        // In this case the longer (more specific) element alias shall have precedence.
        $element = $this->object->createElement('ElementAlias.foo');

        $this->assertInstanceOf(Element::class, $element);
    }

    public function testAppendedDollarSignForwardsQualifierToSharedDependency()
    {
        $sharedDependency = new Element;
        Phake::when($this->containerMock)
            // Note that we expect the qualifier to be appended to the alias here
            ->getSharedElement('SharedDependencyAlias.qualifier')
            ->thenReturn($sharedDependency);
        $this->object->setElementMap([
            'ElementAlias' => [
                // The trailing '.$' causes the qualifier that 'ElementAlias'
                // is requested with to be appended to 'SharedDependencyAlias', too.
                'args' => ['@SharedDependencyAlias.$'],
                'class' => ConstructorArgRecorder::class
            ]
        ]);

        /* @var $element ConstructorArgRecorder */
        $element = $this->object->createElement('ElementAlias.qualifier');

        $this->assertSame($sharedDependency, $element->constructorArgs[0]);
    }

    // Container Nesting
    // =========================================================================

    public function testPresenceOfSubmapCausesCreationOfChildContainer()
    {
        $childContainerMock = Phake::mock(Container::class);
        Phake::when($this->containerMock)->createChildContainer()->thenReturn($childContainerMock);
        Phake::when($childContainerMock)->getSharedElement('Container')->thenReturn($childContainerMock);
        $this->object->setElementMap([
            'ElementAlias' => [
                'args' => ['@Container'],
                'class' => ConstructorArgRecorder::class,
                'submap' => [
                    'PrivateElementAlias' => [
                        'class' => Element::class
                    ]
                ]
            ]
        ]);

        /* @var $element ConstructorArgRecorder */
        $element = $this->object->createElement('ElementAlias.qualifier');

        $this->assertSame($childContainerMock, $element->constructorArgs[0]);
    }

    public function testSubmapNotAnArrayThrowsException()
    {
        $this->object->setElementMap([
            'ElementAlias' => [
                'class' => Element::class,
                'submap' => 'foo'
            ]
        ]);

        $this->expectException(BadElementDefinitionException::class);
        $this->object->createElement('ElementAlias');
    }

    // Loading of class files
    // =========================================================================

    public function testLoadingClassFile()
    {
        $this->object->setElementMap([
            'ElementAlias' => [
                'file' => __DIR__ . '/DummyElements/LegacyElement.php',
                'class' => 'LegacyElement'
            ]
        ]);

        $element = $this->object->createElement('ElementAlias');

        $this->assertInstanceOf('LegacyElement', $element);
    }

    public function testClassFileNotAStringThrowsException()
    {
        $this->object->setElementMap([
            'ElementAlias' => [
                'file' => ['foo'],
                'class' => 'LegacyElement'
            ]
        ]);

        $this->expectException(BadElementDefinitionException::class);
        $this->object->createElement('ElementAlias');
    }

    public function testLoadNonExistentClassFileThrowsException()
    {
        $this->object->setElementMap([
            'ElementAlias' => [
                'file' => __DIR__ . '/non-existent-file',
                'class' => 'LegacyElement'
            ]
        ]);

        $this->expectException(ElementCreationFailureException::class);
        $this->runWithWarningsDisabled(function () {
            $this->object->createElement('ElementAlias');
        });
    }

    /**
     * Temporarily disable PHPUnit interception of PHP warnings and suppress output of warnings
     * notices to stderr (which unfortunately can't be intercepted with an output buffer).
     */
    private function runWithWarningsDisabled(callable $function)
    {
        $oldPhpUnitWarningInterceptState = \PHPUnit\Framework\Error\Warning::$enabled;
        $oldErrorReportingLevel = error_reporting(0);

        \PHPUnit\Framework\Error\Warning::$enabled = false;

        try {
            return $function();
        } finally {
            \PHPUnit\Framework\Error\Warning::$enabled = $oldPhpUnitWarningInterceptState;
            error_reporting($oldErrorReportingLevel);
        }
    }
}
