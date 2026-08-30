<?php

namespace Blueprintau\Automaton\Actions\Logic;


use Blueprintau\Automaton\Action;
use Blueprintau\Automaton\WorkflowContext;
use Blueprintau\Automaton\WorkflowExecutionException;

class LogicForeach extends Action
{
    public function getId(): string
    {
        return 'foreach';
    }

    public function run(mixed $input, WorkflowContext $context, array $options = []): mixed
    {
        if (!is_iterable($input)) {
            throw new WorkflowExecutionException(
                "Input for loop action must be an iterable list or array.",
                $this->getId(),
                $options['_path'] ?? [],
                $input
            );
        }

        $subActions = $options['actions'] ?? [];
        if (empty($subActions)) {
            return $input;
        }

        $runner = $options['runner'] ?? (method_exists($context, 'getRunner') ? $context->getRunner() : null);
        if ($runner === null) {
            throw new WorkflowExecutionException(
                "No ActionRunner instance available in WorkflowContext for loop execution.",
                $this->getId(),
                $options['_path'] ?? []
            );
        }

        $itemKey        = $options['item_key'] ?? '_item';
        $collectResults = (bool)($options['collect'] ?? false);
        $basePath       = $options['_path'] ?? [];
        $subActionPath  = array_merge($basePath, ['actions']);

        $results = [];

        try {
            foreach ($input as $index => $item) {
                // Set scoped loop variables for this iteration
                $context->set($itemKey, $item);
                $context->set('_index', $index);

                // Run sub-actions pipeline on the current item with the breadcrumb path passed down
                $iterationOutput = $runner->run($subActions, $item, $context, $subActionPath);

                if ($collectResults) {
                    $results[] = $iterationOutput;
                }
            }
        } finally {
            // Clean up loop context variables after completion or on failure
            if (method_exists($context, 'remove')) {
                $context->remove($itemKey);
                $context->remove('_index');
            }
        }

        return $collectResults ? $results : $input;
    }
}