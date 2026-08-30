<?php

namespace Blueprintau\Automaton\Actions\Variable;

use Blueprintau\Automaton\Action;
use Blueprintau\Automaton\WorkflowContext;
use Blueprintau\Automaton\WorkflowExecutionException;

class VariableSet extends Action
{
    public function getId(): string
    {
        return "variable.set";
    }

    public function run(mixed $input, WorkflowContext $context, array $options = []): mixed
    {
        $varName = $options['name'] ?? null;

        if (!$varName || !is_string($varName)) {
            throw new WorkflowExecutionException(
                "Option 'name' is required for variable.set.",
                $this->getId(),
                $options['_path'] ?? []
            );
        }

        // 1. If explicit static 'value' option is supplied, use it
        if (array_key_exists('value', $options)) {
            $valueToSet = $options['value'];
        }
        // 2. If a specific 'key' / 'target' path is requested, resolve from $input
        elseif (!empty($options['key']) || !empty($options['target'])) {
            $path = $options['key'] ?? $options['target'];
            $valueToSet = $this->resolvePath($input, $path);
        }
        // 3. Otherwise, set the current $input directly
        else {
            $valueToSet = $input;
        }

        $context->set($varName, $valueToSet);

        // Return untouched input so downstream pipelining remains intact
        return $input;
    }

    /**
     * Resolves dot-notation paths on associative arrays and objects.
     */
    private function resolvePath(mixed $data, string $path): mixed
    {
        $segments = explode('.', $path);
        $current = $data;

        foreach ($segments as $segment) {
            if (is_array($current) && array_key_exists($segment, $current)) {
                $current = $current[$segment];
            } elseif (is_object($current) && isset($current->{$segment})) {
                $current = $current->{$segment};
            } else {
                return null;
            }
        }

        return $current;
    }
}