<?php

namespace Blueprintau\Automaton\Actions\Json;

use Blueprintau\Automaton\Action;
use Blueprintau\Automaton\WorkflowContext;
use Blueprintau\Automaton\WorkflowExecutionException;

class JsonParse extends Action
{
    public function getId(): string
    {
        return "json.parse";
    }

    public function run(mixed $input, WorkflowContext $context, array $options = []): mixed
    {
        if (!is_string($input)) {
            $type = get_debug_type($input);
            throw new WorkflowExecutionException(
                "Input must be a string, received {$type}.",
                $this->getId(),
                $options['_path'] ?? []
            );
        }

        $associative = (bool)($options['associative'] ?? false);
        $depth       = (int)($options['depth'] ?? 512);
        $flags       = (int)($options['flags'] ?? 0);

        $output = json_decode($input, $associative, $depth, $flags);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $errorMsg = json_last_error_msg();
            $snippet  = substr(trim($input), 0, 80);
            if (strlen($input) > 80) {
                $snippet .= '...';
            }

            throw new WorkflowExecutionException(
                "JSON parse error ({$errorMsg}) near: '{$snippet}'",
                $this->getId(),
                $options['_path'] ?? []
            );
        }

        return $output;
    }
}