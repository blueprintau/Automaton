<?php

namespace Blueprintau\Automaton;

abstract class Action
{

    abstract public function getId(): string;

    abstract public function run(mixed $input, WorkflowContext $context,array $options = []): mixed;

}
