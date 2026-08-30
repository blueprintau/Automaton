<?php

namespace Blueprintau\Automaton\Actions\Variable;

use Blueprintau\Automaton\Action;
use Blueprintau\Automaton\WorkflowContext;

class VariableInit extends Action
{

    public function getId(): string
    {
        return 'variable.init';
    }

    public function run(mixed $input,WorkflowContext $context,$options = []): mixed
    {
        $name = $options['name'] ?? 'output';
        $default = $options['value'] ?? [];

        $context->set($name, $default);

        return $input;
    }

}