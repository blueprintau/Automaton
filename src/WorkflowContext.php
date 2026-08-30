<?php

namespace Blueprintau\Automaton;

class WorkflowContext
{
    protected array $variables = [];
    protected ?ActionRunner $runner = null;

    public function set(string $key, mixed $value): void
    {
        $this->variables[$key] = $value;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->variables[$key] ?? $default;
    }

    public function remove(string $key): void
    {
        unset($this->variables[$key]);
    }

    public function setRunner(ActionRunner $runner): void
    {
        $this->runner = $runner;
    }

    public function getRunner(): ?ActionRunner
    {
        return $this->runner;
    }
}
