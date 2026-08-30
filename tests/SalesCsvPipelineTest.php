<?php

namespace Blueprintau\Automaton\Tests;


use Blueprintau\Automaton\ActionRunner;
use Blueprintau\Automaton\Actions\Csv\CsvParse;
use Blueprintau\Automaton\Actions\Logic\LogicForeach;
use Blueprintau\Automaton\Actions\Logic\LogicIf;
use Blueprintau\Automaton\Actions\Math\MathAdd;
use Blueprintau\Automaton\Actions\Variable\VariableInit;
use Blueprintau\Automaton\Actions\Variable\VariablePush;
use Blueprintau\Automaton\Actions\Variable\VariableReturn;
use Blueprintau\Automaton\Actions\Variable\VariableSet;
use PHPUnit\Framework\TestCase;

class SalesCsvPipelineTest extends TestCase
{
    private ActionRunner $runner;
    private string $csvContent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->runner = new ActionRunner();
        $this->runner->register(new CsvParse());
        $this->runner->register(new LogicForeach());
        $this->runner->register(new LogicIf());
        $this->runner->register(new MathAdd());
        $this->runner->register(new VariableInit());
        $this->runner->register(new VariableSet());
        $this->runner->register(new VariablePush());
        $this->runner->register(new VariableReturn());

        $this->csvContent = file_get_contents(__DIR__ . '/fixtures/demo1.csv');
    }

    /**
     * Test 1: Complex Nested Aggregation
     * Parses CSV -> Filters for Region "Australia and Oceania" -> Filters Sales Channel "Offline" -> Sums "Total Profit"
     */
    public function test_calculate_total_profit_for_australia_offline_orders(): void
    {
        $script = [
            [
                'action' => 'variable.init',
                'options' => ['name' => 'total_profit', 'value' => 0.0],
            ],
            [
                'action' => 'csv.parse',
                'options' => ['header' => true],
            ],
            [
                'action' => 'foreach',
                'options' => [
                    'actions' => [
                        [
                            'action' => 'if',
                            'options' => [
                                'target'     => 'Region',
                                'comparison' => 'equals',
                                'value'      => 'Australia and Oceania',
                                'then'       => [
                                    [
                                        'action' => 'if',
                                        'options' => [
                                            'target'     => 'Sales Channel',
                                            'comparison' => 'equals',
                                            'value'      => 'Offline',
                                            'then'       => [
                                                [
                                                    'action' => 'math.add',
                                                    'options' => [
                                                        'target'    => 'total_profit',
                                                        'key'       => 'Total Profit',
                                                        'precision' => 2,
                                                    ],
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'action' => 'variable.return',
                'options' => ['name' => 'total_profit'],
            ],
        ];

        $result = $this->runner->run($script, $this->csvContent);

        // Tuvalu (951410.50) + Fiji (727423.20) + Australia (60418.38) + Australia (147031.74) = 1886283.82
        $this->assertSame(1886283.82, (float)$result);
    }

    /**
     * Test 2: Extraction & Array Accumulation with Nested Conditions
     * Parses CSV -> Finds "Europe" orders where "Item Type" is "Cosmetics" -> Collects country names
     */
    public function test_extract_european_cosmetics_countries(): void
    {
        $script = [
            [
                'action' => 'variable.init',
                'options' => ['name' => 'target_countries', 'value' => []],
            ],
            [
                'action' => 'csv.parse',
                'options' => ['header' => true],
            ],
            [
                'action' => 'foreach',
                'options' => [
                    'actions' => [
                        [
                            'action' => 'if',
                            'options' => [
                                'target'     => 'Region',
                                'comparison' => 'equals',
                                'value'      => 'Europe',
                                'then'       => [
                                    [
                                        'action' => 'if',
                                        'options' => [
                                            'target'     => 'Item Type',
                                            'comparison' => 'equals',
                                            'value'      => 'Cosmetics',
                                            'then'       => [
                                                [
                                                    'action' => 'variable.push',
                                                    'options' => [
                                                        'target' => 'target_countries',
                                                        'key'    => 'Country',
                                                    ],
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'action' => 'variable.return',
                'options' => ['name' => 'target_countries'],
            ],
        ];

        $countries = $this->runner->run($script, $this->csvContent);

        $this->assertIsArray($countries);
        $this->assertEqualsCanonicalizing(
            ['Switzerland', 'France', 'Austria', 'Iceland', 'Romania'],
            $countries
        );    }

    /**
     * Test 3: Multi-Variable Accumulator in a Single Loop
     * Tracks both total units sold and total high-priority ("H") orders count simultaneously
     */
    public function test_multi_variable_tracking_in_single_pipeline(): void
    {
        $script = [
            [
                'action' => 'variable.init',
                'options' => ['name' => 'total_units', 'value' => 0],
            ],
            [
                'action' => 'variable.init',
                'options' => ['name' => 'high_priority_count', 'value' => 0],
            ],
            [
                'action' => 'csv.parse',
                'options' => ['header' => true],
            ],
            [
                'action' => 'foreach',
                'options' => [
                    'actions' => [
                        // Sum units for every row
                        [
                            'action' => 'math.add',
                            'options' => [
                                'target' => 'total_units',
                                'key'    => 'Units Sold',
                            ],
                        ],
                        // Count if order priority is 'H'
                        [
                            'action' => 'if',
                            'options' => [
                                'target'     => 'Order Priority',
                                'comparison' => 'equals',
                                'value'      => 'H',
                                'then'       => [
                                    [
                                        'action' => 'math.add',
                                        'options' => [
                                            'target' => 'high_priority_count',
                                            'value'  => 1,
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'action' => 'variable.return',
                'options' => ['name' => 'high_priority_count'],
            ],
        ];

        $highPriorityCount = $this->runner->run($script, $this->csvContent);

        $this->assertSame(30.0, (float)$highPriorityCount);
    }
}