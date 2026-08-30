<?php

namespace Blueprintau\Automaton\Tests;

use Blueprintau\Automaton\ActionRunner;
use Blueprintau\Automaton\Actions\Variable\VariableInit;
use Blueprintau\Automaton\Actions\Variable\VariablePush;
use Blueprintau\Automaton\Actions\Variable\VariableReturn;
use Blueprintau\Automaton\Actions\Variable\VariableSet;
use Blueprintau\Automaton\WorkflowExecutionException;
use PHPUnit\Framework\TestCase;

class VariablesTest extends TestCase
{
    private ActionRunner $runner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->runner = new ActionRunner();
        $this->runner->register(new VariableInit());
        $this->runner->register(new VariableSet());
        $this->runner->register(new VariablePush());
        $this->runner->register(new VariableReturn());
    }

    public function test_variable_init_and_return(): void
    {
        $script = [
            [
                'action' => 'variable.init',
                'options' => [
                    'name'  => 'counter',
                    'value' => 10,
                ],
            ],
            [
                'action' => 'variable.return',
                'options' => [
                    'name' => 'counter',
                ],
            ],
        ];

        $output = $this->runner->run($script, null);

        $this->assertSame(10, $output);
    }

    public function test_variable_set_with_literal_value(): void
    {
        $script = [
            [
                'action' => 'variable.set',
                'options' => [
                    'name'  => 'status',
                    'value' => 'active',
                ],
            ],
            [
                'action' => 'variable.return',
                'options' => [
                    'name' => 'status',
                ],
            ],
        ];

        $output = $this->runner->run($script, ['ignored' => 'payload']);

        $this->assertSame('active', $output);
    }

    public function test_variable_set_extracts_nested_dot_notation_property(): void
    {
        $payload = [
            'user' => [
                'profile' => [
                    'email' => 'jack@example.com',
                ],
            ],
        ];

        $script = [
            [
                'action' => 'variable.set',
                'options' => [
                    'name' => 'extracted_email',
                    'key'  => 'user.profile.email',
                ],
            ],
            [
                'action' => 'variable.return',
                'options' => [
                    'name' => 'extracted_email',
                ],
            ],
        ];

        $output = $this->runner->run($script, $payload);

        $this->assertSame('jack@example.com', $output);
    }

    public function test_variable_push_accumulates_items(): void
    {
        $script = [
            [
                'action' => 'variable.init',
                'options' => [
                    'name'  => 'tags',
                    'value' => ['first'],
                ],
            ],
            [
                'action' => 'variable.push',
                'options' => [
                    'target' => 'tags',
                    'value'  => 'second',
                ],
            ],
            [
                'action' => 'variable.return',
                'options' => [
                    'name' => 'tags',
                ],
            ],
        ];

        $output = $this->runner->run($script, null);

        $this->assertSame(['first', 'second'], $output);
    }

    public function test_variable_push_extracts_nested_key_from_input(): void
    {
        $payload = [
            'id'         => 101,
            'properties' => [
                'title' => 'Paroo Road Update',
            ],
        ];

        $script = [
            [
                'action' => 'variable.init',
                'options' => [
                    'name'  => 'titles',
                    'value' => [],
                ],
            ],
            [
                'action' => 'variable.push',
                'options' => [
                    'target' => 'titles',
                    'key'    => 'properties.title',
                ],
            ],
            [
                'action' => 'variable.return',
                'options' => [
                    'name' => 'titles',
                ],
            ],
        ];

        $output = $this->runner->run($script, $payload);

        $this->assertSame(['Paroo Road Update'], $output);
    }

    public function test_variable_set_throws_exception_when_name_is_missing(): void
    {
        $script = [
            [
                'action' => 'variable.set',
                'options' => [
                    'value' => 123,
                ],
            ],
        ];

        try {
            $this->runner->run($script, null);
            $this->fail('Expected WorkflowExecutionException was not thrown.');
        } catch (WorkflowExecutionException $e) {
            $this->assertSame('variable.set', $e->getActionId());
            $this->assertSame([0], $e->getPipelinePath());
            $this->assertStringContainsString("Option 'name' is required", $e->getMessage());
        }
    }

    public function test_variable_return_falls_back_to_default_when_variable_undefined(): void
    {
        $script = [
            [
                'action' => 'variable.return',
                'options' => [
                    'name'    => 'undefined_variable',
                    'default' => 'fallback_value',
                ],
            ],
        ];

        $output = $this->runner->run($script, null);

        $this->assertSame('fallback_value', $output);
    }
}