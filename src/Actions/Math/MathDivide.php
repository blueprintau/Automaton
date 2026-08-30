<?php

namespace Blueprintau\Automaton\Actions\Math;

use Blueprintau\Automaton\WorkflowExecutionException;

class MathDivide extends BaseMathematicalAction
{
    public function getId(): string
    {
        return 'math.divide';
    }

    protected function calculate(
        float $left,
        float $right,
        array $options = [],
        mixed $input = null
    ): float {
        if ($right == 0.0) {
            throw new WorkflowExecutionException(
                "Division by zero encountered in 'math.divide'.",
                $this->getId(),
                $options['_path'] ?? [],
                $input
            );
        }

        return $left / $right;
    }
}