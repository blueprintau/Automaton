<?php

namespace Blueprintau\Automaton\Actions\Math;

class MathMultiply extends BaseMathematicalAction
{
    public function getId(): string
    {
        return 'math.multiply';
    }

    protected function calculate(float $left, float $right, array $options = [], mixed $input = null): float
    {
        return $left * $right;
    }
}