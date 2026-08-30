<?php

namespace Blueprintau\Automaton\Actions\Math;

class MathSubtract extends BaseMathematicalAction
{
    public function getId(): string
    {
        return 'math.subtract';
    }

    protected function calculate(float $left, float $right, array $options = [], mixed $input = null): float
    {
        return $left - $right;
    }

}