<?php

namespace Blueprintau\Automaton\Actions\Variable;

use Blueprintau\Automaton\Action;
use Blueprintau\Automaton\WorkflowContext;
use Blueprintau\Automaton\WorkflowExecutionException;

class VariablePush extends Action
{
    public function getId(): string
    {
        return 'variable.push';
    }

    public function run(mixed $input, WorkflowContext $context, array $options = []): mixed
    {
        $targetVar = $options['target'] ?? null;

        if (!$targetVar || !is_string($targetVar)) {
            throw new WorkflowExecutionException(
                "Option 'target' is required for variable.push.",
                $this->getId(),
                $options['_path'] ?? []
            );
        }

        // 1. If explicit static 'value' option is provided, use it
        if (array_key_exists('value', $options)) {
            $payloadToPush = $options['value'];
        }
        // 2. If a specific 'key' path is requested, resolve dot-notation path from $input
        elseif (!empty($options['key'])) {
            $payloadToPush = $this->resolvePath($input, $options['key']);
        }
        // 3. Otherwise push the current $input payload directly
        else {
            $payloadToPush = $input;
        }

        // Retrieve existing array or initialize a new array in context
        $list = $context->get($targetVar, []);
        if (!is_array($list)) {
            $list = [$list];
        }

        $list[] = $payloadToPush;
        $context->set($targetVar, $list);

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