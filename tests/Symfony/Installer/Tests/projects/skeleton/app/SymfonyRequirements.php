<?php

class Requirement
{
    public function isFulfilled()
    {
        return true;
    }
}

class RequirementCollection implements IteratorAggregate
{
    public function getIterator()
    {
        return new ArrayIterator(array());
    }

    public function getRequirements()
    {
        return array();
    }
}

class SymfonyRequirements extends RequirementCollection
{
}
