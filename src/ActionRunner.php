<?php

namespace Blueprintau\Automaton;

use Closure;
use Throwable;

class ActionRunner
{
    /** @var array<string, Action> */
    protected array $actions = [];

    /** @var array<string, array<Closure>> */
    protected array $listeners = [];

    public function register(Action $action): self
    {
        if (method_exists($action, 'setRunner')) {
            $action->setRunner($this);
        }

        $this->actions[$action->getId()] = $action;
        return $this;
    }

    /**
     * Register a callback listener for lifecycle events:
     * 'step.start', 'step.finish', 'step.error', 'stream.chunk'
     */
    public function on(string $event, Closure $callback): self
    {
        $this->listeners[$event][] = $callback;
        return $this;
    }

    /**
     * Dispatch an event to specific listeners and wildcard ('*') listeners.
     */
    public function emit(string $event, array $payload): void
    {
        // If no listeners exist at all for this event or wildcard, exit early
        if (empty($this->listeners[$event]) && empty($this->listeners['*'])) {
            return;
        }

        // 1. Specific event listeners
        foreach ($this->listeners[$event] ?? [] as $listener) {
            $listener($payload, $event);
        }

        // 2. Wildcard listeners
        foreach ($this->listeners['*'] ?? [] as $listener) {
            $listener($payload, $event);
        }
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
                $exception = new WorkflowExecutionException(
                    "Automation Action '{$actionId}' is not registered in ActionRunner.",
                    (string) $actionId,
                    $currentPath
                );

                $this->emit('step.error', [
                    'path'   => $currentPath,
                    'action' => $actionId,
                    'error'  => $exception->getMessage(),
                ]);

                throw $exception;
            }

            // Emit Step Start
            $this->emit('step.start', [
                'path'      => $currentPath,
                'action'    => $actionId,
                'options'   => $options,
                'input'     => $payload,
                'timestamp' => microtime(true),
            ]);

            $startTime = microtime(true);

            try {
                $payload = $this->actions[$actionId]->run($payload, $context, $options);

                // Emit Step Finish
                $this->emit('step.finish', [
                    'path'          => $currentPath,
                    'action'        => $actionId,
                    'output'        => $payload,
                    'duration_ms'   => round((microtime(true) - $startTime) * 1000, 2),
                    'context_state' => method_exists($context, 'all') ? $context->all() : [],
                ]);
            } catch (WorkflowExecutionException $e) {
                $enrichedException = empty($e->getPipelinePath())
                    ? new WorkflowExecutionException(
                        $e->getRawMessage(),
                        $actionId,
                        $currentPath,
                        $payload,
                        $e
                    )
                    : $e;

                $this->emit('step.error', [
                    'path'   => $currentPath,
                    'action' => $actionId,
                    'error'  => $enrichedException->getMessage(),
                ]);

                throw $enrichedException;
            } catch (Throwable $e) {
                $wrappedException = new WorkflowExecutionException(
                    $e->getMessage(),
                    $actionId,
                    $currentPath,
                    $payload,
                    $e
                );

                $this->emit('step.error', [
                    'path'   => $currentPath,
                    'action' => $actionId,
                    'error'  => $wrappedException->getMessage(),
                ]);

                throw $wrappedException;
            }
        }

        return $payload;
    }
}