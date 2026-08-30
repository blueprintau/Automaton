<?php

namespace Blueprintau\Automaton\Actions\Logic;

use Blueprintau\Automaton\Action;
use Blueprintau\Automaton\WorkflowContext;

class LogicIf extends Action
{
    public function getId(): string
    {
        return "if";
    }

    public function run(mixed $input, WorkflowContext $context, array $options = []): mixed
    {
        // 1. Extract condition settings from options (or fallback to input)
        $targetPath = $options['target'] ?? null;
        $operator   = strtolower($options['operator'] ?? $options['comparison'] ?? 'equals');
        $expected   = $options['value'] ?? null;

        // 2. Resolve actual value to test (from input path, context, or raw input)
        $actual = $this->resolveValue($input, $context, $targetPath);

        // 3. Evaluate condition
        $isMatch = $this->evaluate($operator, $actual, $expected);

        // 4. Select the active branch and determine branch label for breadcrumb
        $branchLabel = $isMatch ? 'then' : 'else';
        $actions = $isMatch
            ? ($options['then'] ?? $options['actions'] ?? [])
            : ($options['else'] ?? []);

        // 5. Execute branch sub-actions with forwarded breadcrumb path
        $runner = $options['runner'] ?? (method_exists($context, 'getRunner') ? $context->getRunner() : null);

        if (!empty($actions) && $runner !== null) {
            $basePath = $options['_path'] ?? [];
            $branchPath = array_merge($basePath, [$branchLabel]);

            return $runner->run($actions, $input, $context, $branchPath);
        }

        // Return untouched input if no branch executed or runner not provided
        return $input;
    }

    /**
     * Resolves dot-notation paths on associative arrays, objects, or WorkflowContext.
     */
    private function resolveValue(mixed $input, WorkflowContext $context, ?string $path): mixed
    {
        if ($path === null || $path === '') {
            return $input;
        }

        // Support contextual lookup prefixes (e.g., "context.my_var" or "vars.my_var")
        if (str_starts_with($path, 'context.') || str_starts_with($path, 'vars.')) {
            $key = preg_replace('/^(context|vars)\./', '', $path);
            return $context->get($key);
        }

        $segments = explode('.', $path);
        $current = $input;

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

    /**
     * Evaluates comparison operations safely.
     */
    private function evaluate(string $operator, mixed $actual, mixed $expected): bool
    {
        $actualStr   = is_scalar($actual) ? (string)$actual : json_encode($actual);
        $expectedStr = is_scalar($expected) ? (string)$expected : json_encode($expected);

        return match ($operator) {
            'equals', 'eq', '=='           => strcasecmp($actualStr, $expectedStr) === 0,
            'not_equals', 'neq', '!='       => strcasecmp($actualStr, $expectedStr) !== 0,
            'contains', 'like'              => stripos($actualStr, $expectedStr) !== false,
            'not_contains', 'not_like'      => stripos($actualStr, $expectedStr) === false,
            'starts_with'                   => stripos($actualStr, $expectedStr) === 0,
            'ends_with'                     => str_ends_with(strtolower($actualStr), strtolower($expectedStr)),
            'greater_than', 'gt', '>'       => (float)$actual > (float)$expected,
            'less_than', 'lt', '<'          => (float)$actual < (float)$expected,
            'greater_than_or_equal', 'gte'  => (float)$actual >= (float)$expected,
            'less_than_or_equal', 'lte'     => (float)$actual <= (float)$expected,
            'is_empty', 'empty'             => empty($actual),
            'is_not_empty', 'not_empty'     => !empty($actual),
            'is_null', 'null'               => $actual === null,
            'is_not_null', 'not_null'       => $actual !== null,
            default                         => false,
        };
    }
}