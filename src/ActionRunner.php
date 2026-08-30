<?php

namespace Blueprintau\Automaton;

use Blueprintau\Automaton\WorkflowExecutionException;
use Throwable;

class ActionRunner
{
    /** @var array<string, Action> */
    protected array $actions = [];

    public function register(Action $action): self
    {
        if (method_exists($action, 'setRunner')) {
            $action->setRunner($this);
        }

        $this->actions[$action->getId()] = $action;
        return $this;
    }

    /**
     * Runs a pipeline of actions against $input.
     *
     * @param array $pipeline The array of action definitions
     * @param mixed $input Initial input payload
     * @param WorkflowContext|null $context Context instance
     * @param array $path Breadcrumb trail for tracking nested locations
     */
    public function run(
        array $pipeline,
        mixed $input = null,
        ?WorkflowContext $context = null,
        array $path = []
    ): mixed {
        $context = $context ?? new WorkflowContext();

        if (method_exists($context, 'setRunner')) {
            $context->setRunner($this);
        }

        $payload = $input;

        foreach ($pipeline as $index => $step) {
            $currentPath = array_merge($path, [$index]);
            $actionId = $step['action'] ?? null;
            $options = $step['options'] ?? [];

            // Supply runner instance and breadcrumb path to options
            $options['runner'] = $this;
            $options['_path']  = $currentPath;

            if (!$actionId || !isset($this->actions[$actionId])) {
                throw new WorkflowExecutionException(
                    "Automation Action '{$actionId}' is not registered in ActionRunner.",
                    (string) $actionId,
                    $currentPath
                );
            }

            try {
                $payload = $this->actions[$actionId]->run($payload, $context, $options);
            } catch (WorkflowExecutionException $e) {
                // If the action threw a WorkflowExecutionException without path details, enrich it
                if (empty($e->getPipelinePath())) {
                    throw new WorkflowExecutionException(
                        $e->getRawMessage(),
                        $actionId,
                        $currentPath,
                        $payload,
                        $e
                    );
                }
                throw $e;
            } catch (Throwable $e) {
                // Catch standard PHP warnings, errors, and exceptions
                throw new WorkflowExecutionException(
                    $e->getMessage(),
                    $actionId,
                    $currentPath,
                    $payload,
                    $e
                );
            }
        }

        return $payload;
    }
}