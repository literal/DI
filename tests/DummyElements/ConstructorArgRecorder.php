<?php
namespace literal\DI\DummyElements;

class ConstructorArgRecorder
{
    public $constructorArgs = [];

    public function __construct()
    {
        $this->constructorArgs = func_get_args();
    }
}
