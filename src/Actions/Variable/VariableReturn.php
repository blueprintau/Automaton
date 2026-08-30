<?php

namespace Blueprintau\Automaton\Actions\Variable;

use Blueprintau\Automaton\Action;
use Blueprintau\Automaton\WorkflowContext;

class VariableReturn extends Action
{
    public function getId(): string
    {
        return 'variable.return';
    }

    public function run(mixed $input, WorkflowContext $context,array $options = []): mixed
    {
        $name = $options['name'] ?? null;
        $default = $options['default'] ?? null;

        if ($name === null) {
            return $input;
        }

        return $context->get($name, $default);
    }
}