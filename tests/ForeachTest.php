<?php

namespace Blueprintau\Automaton\Tests;

use Blueprintau\Automaton\ActionRunner;
use Blueprintau\Automaton\Actions\Logic\LogicForeach;
use Blueprintau\Automaton\Actions\Math\MathAdd;
use Blueprintau\Automaton\Actions\Variable\VariableInit;
use Blueprintau\Automaton\Actions\Variable\VariablePush;
use Blueprintau\Automaton\Actions\Variable\VariableReturn;
use Blueprintau\Automaton\WorkflowExecutionException;
use PHPUnit\Framework\TestCase;

class ForeachTest extends TestCase
{
    private ActionRunner $runner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->runner = new ActionRunner();
        $this->runner->register(new LogicForeach());
        $this->runner->register(new MathAdd());
        $this->runner->register(new VariableInit());
        $this->runner->register(new VariablePush());
        $this->runner->register(new VariableReturn());
    }

    public function test_foreach_iterates_and_accumulates_sum(): void
    {
        $payload = [
            ['amount' => 10.50],
            ['amount' => 25.25],
            ['amount' => 14.25],
        ];

        $script = [
            [
                'action' => 'variable.init',
                'options' => ['name' => 'total', 'value' => 0.0],
            ],
            [
                'action' => 'foreach',
                'options' => [
                    'actions' => [
                        [
                            'action' => 'math.add',
                            'options' => [
                                'target' => 'total',
                                'key'    => 'amount',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'action' => 'variable.return',
                'options' => ['name' => 'total'],
            ],
        ];

        $output = $this->runner->run($script, $payload);

        $this->assertSame(50.0, (float) $output);
    }

    public function test_foreach_extracts_and_pushes_to_array_variable(): void
    {
        $payload = [
            ['id' => 1, 'title' => 'First Item'],
            ['id' => 2, 'title' => 'Second Item'],
            ['id' => 3, 'title' => 'Third Item'],
        ];

        $script = [
            [
                'action' => 'variable.init',
                'options' => ['name' => 'titles', 'value' => []],
            ],
            [
                'action' => 'foreach',
                'options' => [
                    'actions' => [
                        [
                            'action' => 'variable.push',
                            'options' => [
                                'target' => 'titles',
                                'key'    => 'title',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'action' => 'variable.return',
                'options' => ['name' => 'titles'],
            ],
        ];

        $output = $this->runner->run($script, $payload);

        $this->assertSame(['First Item', 'Second Item', 'Third Item'], $output);
    }

    public function test_foreach_propagates_breadcrumb_path_on_subaction_error(): void
    {
        $payload = [['id' => 1]];

        $script = [
            [
                'action' => 'foreach',
                'options' => [
                    'actions' => [
                        [
                            'action' => 'unknown.action',
                            'options' => [],
                        ],
                    ],
                ],
            ],
        ];

        try {
            $this->runner->run($script, $payload);
            $this->fail('Expected WorkflowExecutionException was not thrown.');
        } catch (WorkflowExecutionException $e) {
            $this->assertSame('unknown.action', $e->getActionId());
            $this->assertSame([0, 'actions', 0], $e->getPipelinePath());
            $this->assertStringContainsString('not registered', $e->getMessage());
        }
    }

    public function test_foreach_throws_exception_when_input_is_not_iterable(): void
    {
        $script = [
            [
                'action' => 'foreach',
                'options' => [
                    'actions' => [
                        [
                            'action' => 'variable.push',
                            'options' => ['target' => 'items'],
                        ],
                    ],
                ],
            ],
        ];

        $this->expectException(WorkflowExecutionException::class);
        $this->expectExceptionMessage('Input for loop action must be an iterable list or array');

        $this->runner->run($script, 'invalid_string_payload');
    }
}