<?php

namespace Blueprintau\Automaton\Actions\Math;

class MathAdd extends BaseMathematicalAction
{

    public function getId(): string
    {
        return 'math.add';
    }

    protected function calculate(float $left, float $right, array $options = [], mixed $input = null): float
    {
        return $left + $right;
    }

}