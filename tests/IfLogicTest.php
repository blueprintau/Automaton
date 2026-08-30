<?php

namespace Blueprintau\Automaton\Tests;

use Blueprintau\Automaton\ActionRunner;
use Blueprintau\Automaton\Actions\Json\JsonParse;
use Blueprintau\Automaton\Actions\Logic\LogicIf;
use Blueprintau\Automaton\Actions\Variable\VariableInit;
use Blueprintau\Automaton\Actions\Variable\VariablePush;
use Blueprintau\Automaton\Actions\Variable\VariableReturn;
use Blueprintau\Automaton\Actions\Variable\VariableSet;
use Blueprintau\Automaton\WorkflowExecutionException;
use PHPUnit\Framework\TestCase;

class IfLogicTest extends TestCase
{
    private ActionRunner $runner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->runner = new ActionRunner();
        $this->runner->register(new JsonParse());
        $this->runner->register(new LogicIf());
        $this->runner->register(new VariableInit());
        $this->runner->register(new VariablePush());
        $this->runner->register(new VariableSet());
        $this->runner->register(new VariableReturn());
    }

    /**
     * Test 1: Executes 'then' branch when condition evaluates to true
     */
    public function test_if_executes_then_branch_when_condition_matches(): void
    {
        $payload = [
            'username' => 'jack.harris',
            'role'     => 'admin',
        ];

        $script = [
            [
                'action' => 'variable.init',
                'options' => ['name' => 'status', 'value' => 'pending'],
            ],
            [
                'action' => 'if',
                'options' => [
                    'target'     => 'role',
                    'comparison' => 'equals',
                    'value'      => 'admin',
                    'then'       => [
                        [
                            'action' => 'variable.set',
                            'options' => ['name' => 'status', 'value' => 'authorized'],
                        ],
                    ],
                    'else'       => [
                        [
                            'action' => 'variable.set',
                            'options' => ['name' => 'status', 'value' => 'unauthorized'],
                        ],
                    ],
                ],
            ],
            [
                'action' => 'variable.return',
                'options' => ['name' => 'status'],
            ],
        ];

        $output = $this->runner->run($script, $payload);

        $this->assertSame('authorized', $output);
    }

    /**
     * Test 2: Executes 'else' branch when condition evaluates to false
     */
    public function test_if_executes_else_branch_when_condition_fails(): void
    {
        $payload = [
            'username' => 'john.smith',
            'role'     => 'guest',
        ];

        $script = [
            [
                'action' => 'variable.init',
                'options' => ['name' => 'status', 'value' => 'pending'],
            ],
            [
                'action' => 'if',
                'options' => [
                    'target'     => 'role',
                    'comparison' => 'equals',
                    'value'      => 'admin',
                    'then'       => [
                        [
                            'action' => 'variable.set',
                            'options' => ['name' => 'status', 'value' => 'authorized'],
                        ],
                    ],
                    'else'       => [
                        [
                            'action' => 'variable.set',
                            'options' => ['name' => 'status', 'value' => 'unauthorized'],
                        ],
                    ],
                ],
            ],
            [
                'action' => 'variable.return',
                'options' => ['name' => 'status'],
            ],
        ];

        $output = $this->runner->run($script, $payload);

        $this->assertSame('unauthorized', $output);
    }

    /**
     * Test 3: Evaluates nested dot-notation paths
     */
    public function test_if_evaluates_nested_dot_notation_path(): void
    {
        $payload = [
            'event' => [
                'road_summary' => [
                    'local_government_area' => 'Paroo Shire',
                ],
            ],
        ];

        $script = [
            [
                'action' => 'variable.init',
                'options' => ['name' => 'is_paroo', 'value' => false],
            ],
            [
                'action' => 'if',
                'options' => [
                    'target'     => 'event.road_summary.local_government_area',
                    'comparison' => 'contains',
                    'value'      => 'Paroo',
                    'then'       => [
                        [
                            'action' => 'variable.set',
                            'options' => ['name' => 'is_paroo', 'value' => true],
                        ],
                    ],
                ],
            ],
            [
                'action' => 'variable.return',
                'options' => ['name' => 'is_paroo'],
            ],
        ];

        $output = $this->runner->run($script, $payload);

        $this->assertTrue($output);
    }

    /**
     * Test 4: Verifies breadcrumb error path when a sub-action in 'then' throws an error
     */
    public function test_if_propagates_breadcrumb_path_on_subaction_failure(): void
    {
        $payload = ['status' => 'active'];

        $script = [
            [
                'action' => 'if',
                'options' => [
                    'target'     => 'status',
                    'comparison' => 'equals',
                    'value'      => 'active',
                    'then'       => [
                        [
                            'action' => 'non_existent_action',
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
            $this->assertSame('non_existent_action', $e->getActionId());
            $this->assertSame([0, 'then', 0], $e->getPipelinePath());
            $this->assertStringContainsString('not registered', $e->getMessage());
        }
    }
}