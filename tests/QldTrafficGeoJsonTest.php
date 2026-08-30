<?php

namespace Blueprintau\Automaton\Tests;

use Blueprintau\Automaton\ActionRunner;
use Blueprintau\Automaton\Actions\Json\JsonParse;
use Blueprintau\Automaton\Actions\Logic\LogicForeach;
use Blueprintau\Automaton\Actions\Logic\LogicIf;
use Blueprintau\Automaton\Actions\Math\MathAdd;
use Blueprintau\Automaton\Actions\Variable\VariableInit;
use Blueprintau\Automaton\Actions\Variable\VariablePush;
use Blueprintau\Automaton\Actions\Variable\VariableReturn;
use Blueprintau\Automaton\Actions\Variable\VariableSet;
use PHPUnit\Framework\TestCase;

class QldTrafficGeoJsonTest extends TestCase
{
    private ActionRunner $runner;
    private string $geoJson;

    protected function setUp(): void
    {
        parent::setUp();

        $this->runner = new ActionRunner();
        $this->runner->register(new JsonParse());
        $this->runner->register(new LogicForeach());
        $this->runner->register(new LogicIf());
        $this->runner->register(new MathAdd());
        $this->runner->register(new VariableInit());
        $this->runner->register(new VariableSet());
        $this->runner->register(new VariablePush());
        $this->runner->register(new VariableReturn());

        $this->geoJson = file_get_contents(__DIR__ . '/fixtures/demo2.json');
    }

    private function logBanner(string $title, mixed $data): void
    {
        fwrite(STDOUT, "\n\033[1;36m====================================================================\033[0m\n");
        fwrite(STDOUT, "\033[1;32m [AUTOMATON PIPELINE RESULT] {$title}\033[0m\n");
        fwrite(STDOUT, "\033[1;36m====================================================================\033[0m\n");
        if (is_array($data) || is_object($data)) {
            fwrite(STDOUT, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
        } else {
            fwrite(STDOUT, "  → Value: \033[1;33m" . var_export($data, true) . "\033[0m\n");
        }
    }

    /**
     * Test 1: Extract all closed roads in Bulloo Shire
     */
    public function test_extract_closed_roads_in_bulloo_shire(): void
    {
        $script = [
            [
                'action' => 'variable.init',
                'options' => ['name' => 'closed_roads', 'value' => []],
            ],
            [
                'action' => 'json.parse',
                'options' => ['associative' => true],
            ],
            [
                'action' => 'json.parse',
                'options' => ['associative' => true],
            ],
            [
                'action' => 'foreach',
                'options' => [
                    'actions' => [
                        [
                            'action' => 'if',
                            'options' => [
                                'target'     => 'properties.road_summary.local_government_area',
                                'comparison' => 'contains',
                                'value'      => 'Bulloo Shire',
                                'then'       => [
                                    [
                                        'action' => 'if',
                                        'options' => [
                                            'target'     => 'properties.impact.impact_subtype',
                                            'comparison' => 'equals',
                                            'value'      => 'Road closed to all traffic',
                                            'then'       => [
                                                [
                                                    'action' => 'variable.push',
                                                    'options' => [
                                                        'target' => 'closed_roads',
                                                        'key'    => 'properties.road_summary.road_name',
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
                'options' => ['name' => 'closed_roads'],
            ],
        ];

        $closedRoads = $this->runner->run($script, $this->geoJson);

        $this->logBanner("Closed Roads in Bulloo Shire", [
            'total_closed' => count($closedRoads),
            'road_names'   => $closedRoads,
        ]);

        $this->assertIsArray($closedRoads);
        $this->assertCount(17, $closedRoads);
        $this->assertContains('Hungerford Road / Hungerford Road (Hungerford Eulo Road)', $closedRoads);
        $this->assertContains('Orientos Road', $closedRoads);
        $this->assertContains('Camerons Corner Road', $closedRoads);
    }

    /**
     * Test 2: Filter and accumulate High Priority hazards/roadworks across all Shires
     */
    public function test_extract_high_priority_traffic_events(): void
    {
        $script = [
            [
                'action' => 'variable.init',
                'options' => ['name' => 'high_priority_events', 'value' => []],
            ],
            [
                'action' => 'variable.init',
                'options' => ['name' => 'high_priority_count', 'value' => 0],
            ],
            [
                'action' => 'json.parse',
                'options' => ['associative' => true],
            ],
            [
                'action' => 'json.parse',
                'options' => ['associative' => true],
            ],
            [
                'action' => 'foreach',
                'options' => [
                    'actions' => [
                        [
                            'action' => 'if',
                            'options' => [
                                'target'     => 'properties.event_priority',
                                'comparison' => 'equals',
                                'value'      => 'High',
                                'then'       => [
                                    [
                                        'action' => 'math.add',
                                        'options' => [
                                            'target' => 'high_priority_count',
                                            'value'  => 1,
                                        ],
                                    ],
                                    [
                                        'action' => 'variable.push',
                                        'options' => [
                                            'target' => 'high_priority_events',
                                            'key'    => 'properties.road_summary.road_name',
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
                'options' => ['name' => 'high_priority_events'],
            ],
        ];

        $events = $this->runner->run($script, $this->geoJson);

        $this->logBanner("High Priority Event Roads", $events);

        $this->assertIsArray($events);
        $this->assertCount(5, $events);
        $this->assertContains('Mitchell Highway', $events);
        $this->assertContains('Diamantina Developmental Road (Diamantina Dev Road)', $events);
    }

    /**
     * Test 3: Locate Paroo Shire Incidents
     */
    public function test_find_paroo_shire_incidents(): void
    {
        $script = [
            [
                'action' => 'variable.init',
                'options' => ['name' => 'paroo_hazards', 'value' => []],
            ],
            [
                'action' => 'json.parse',
                'options' => ['associative' => true],
            ],
            [
                'action' => 'json.parse',
                'options' => ['associative' => true],
            ],
            [
                'action' => 'foreach',
                'options' => [
                    'actions' => [
                        [
                            'action' => 'if',
                            'options' => [
                                'target'     => 'properties.road_summary.local_government_area',
                                'comparison' => 'contains',
                                'value'      => 'Paroo Shire',
                                'then'       => [
                                    [
                                        'action' => 'variable.push',
                                        'options' => [
                                            'target' => 'paroo_hazards',
                                            'key'    => 'properties.road_summary',
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
                'options' => ['name' => 'paroo_hazards'],
            ],
        ];

        $incidents = $this->runner->run($script, $this->geoJson);

        $this->logBanner("Paroo Shire Traffic Summaries", $incidents);

        $this->assertCount(2, $incidents);
        $this->assertSame('Balonne Highway', $incidents[1]['road_name']);
        $this->assertSame('Cunnamulla', $incidents[1]['locality']);
    }
}