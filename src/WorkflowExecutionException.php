<?php

namespace Blueprintau\Automaton;

use RuntimeException;
use Throwable;

class WorkflowExecutionException extends RuntimeException
{
    public function __construct(
        string $message,
        protected string $actionId,
        protected array $pipelinePath,
        protected mixed $stepInput = null,
        ?Throwable $previous = null
    ) {
        $pathString = $this->formatPath($pipelinePath);
        $fullMessage = "Workflow error at {$pathString} [action: '{$actionId}']: {$message}";

        parent::__construct($fullMessage, 0, $previous);
    }

    public function getActionId(): string
    {
        return $this->actionId;
    }

    /**
     * Returns the array breadcrumb path, e.g. [3, 'actions', 0, 'then', 0]
     */
    public function getPipelinePath(): array
    {
        return $this->pipelinePath;
    }

    public function getStepInput(): mixed
    {
        return $this->stepInput;
    }

    public function getPathString(): string
    {
        return $this->formatPath($this->pipelinePath);
    }

    protected function formatPath(array $path): string
    {
        return empty($path) ? 'root' : implode(' -> ', array_map(function ($segment) {
            return is_numeric($segment) ? "[{$segment}]" : $segment;
        }, $path));
    }
}