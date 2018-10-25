<?php
namespace dummyelements;

class ConstructorArgRecorder
{
    public $constructorArgs = [];

    public function __construct()
    {
        $this->constructorArgs = func_get_args();
    }
}
