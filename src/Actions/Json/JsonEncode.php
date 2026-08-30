<?php

namespace Blueprintau\Automaton\Actions\Json;

use Blueprintau\Automaton\Action;
use Blueprintau\Automaton\WorkflowContext;

class JsonEncode extends Action
{

    public function getId(): string
    {
       return "json.encode";
    }

    public function run(mixed $input, WorkflowContext $context, array $options = []): mixed
    {
        return json_encode($input);
    }
}