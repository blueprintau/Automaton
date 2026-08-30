<?php

namespace Blueprintau\Automaton\Actions\Math;

use Blueprintau\Automaton\Action;
use Blueprintau\Automaton\WorkflowContext;

abstract class BaseMathematicalAction extends Action
{
    abstract protected function calculate(
        float $left,
        float $right,
        array $options = [],
        mixed $input = null
    ): float;

    public function run(mixed $input, WorkflowContext $context, array $options = []): mixed
    {
        $targetVar = $options['target'] ?? null;
        $key       = $options['key'] ?? null;
        $byValue   = $options['value'] ?? $options['by'] ?? null;

        // 1. Resolve operand (the modifier)
        if ($byValue !== null) {
            $operand = (float) $byValue;
        } elseif ($key !== null && (is_array($input) || is_object($input))) {
            $operand = (float) $this->resolvePath($input, $key);
        } else {
            $operand = (float) $input;
        }

        // 2. Accumulator Mode
        if ($targetVar !== null) {
            $current = (float) $context->get($targetVar, 0.0);
            $result  = $this->calculate($current, $operand, $options, $input);

            if (isset($options['precision'])) {
                $result = round($result, (int) $options['precision']);
            }

            $context->set($targetVar, $result);
            return $input;
        }

        // 3. Pipeline Transformer Mode
        $baseValue = (float) ($key !== null ? $this->resolvePath($input, $key) : $input);
        $result    = $this->calculate($baseValue, $operand, $options, $input);

        if (isset($options['precision'])) {
            $result = round($result, (int) $options['precision']);
        }

        return $result;
    }

    protected function resolvePath(mixed $data, string $path): mixed
    {
        $segments = explode('.', $path);
        $current = $data;

        foreach ($segments as $segment) {
            if (is_array($current) && array_key_exists($segment, $current)) {
                $current = $current[$segment];
            } elseif (is_object($current) && isset($current->{$segment})) {
                $current = $current->{$segment};
            } else {
                return 0.0;
            }
        }

        return $current;
    }
}