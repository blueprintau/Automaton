<?php

namespace Blueprintau\Automaton\Tests;


use Blueprintau\Automaton\Action;
use Blueprintau\Automaton\ActionRunner;
use Blueprintau\Automaton\WorkflowContext;
use Blueprintau\Automaton\WorkflowExecutionException;
use PHPUnit\Framework\TestCase;

// Dummy action for testing standard execution
class UppercaseAction extends Action
{
    public function getId(): string
    {
        return 'string.uppercase';
    }

    public function run(mixed $input, WorkflowContext $context, array $options = []): mixed
    {
        return strtoupper((string) $input);
    }
}

// Dummy action for testing error emissions
class FailingAction extends Action
{
    public function getId(): string
    {
        return 'test.fail';
    }

    public function run(mixed $input, WorkflowContext $context, array $options = []): mixed
    {
        throw new \RuntimeException("Deliberate failure");
    }
}

class ActionRunnerEventsTest extends TestCase
{
    private ActionRunner $runner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->runner = new ActionRunner();
        $this->runner->register(new UppercaseAction());
        $this->runner->register(new FailingAction());
    }

    public function test_step_start_and_finish_events_are_emitted(): void
    {
        $startEvents = [];
        $finishEvents = [];

        $this->runner->on('step.start', function (array $event) use (&$startEvents) {
            $startEvents[] = $event;
        });

        $this->runner->on('step.finish', function (array $event) use (&$finishEvents) {
            $finishEvents[] = $event;
        });

        $pipeline = [
            [
                'action' => 'string.uppercase',
                'options' => ['foo' => 'bar'],
            ],
        ];

        $output = $this->runner->run($pipeline, 'hello world');

        $this->assertSame('HELLO WORLD', $output);

        // 1. Verify step.start payload
        $this->assertCount(1, $startEvents);
        $this->assertSame([0], $startEvents[0]['path']);
        $this->assertSame('string.uppercase', $startEvents[0]['action']);
        $this->assertSame('hello world', $startEvents[0]['input']);
        $this->assertSame('bar', $startEvents[0]['options']['foo']);
        $this->assertArrayHasKey('timestamp', $startEvents[0]);

        // 2. Verify step.finish payload
        $this->assertCount(1, $finishEvents);
        $this->assertSame([0], $finishEvents[0]['path']);
        $this->assertSame('string.uppercase', $finishEvents[0]['action']);
        $this->assertSame('HELLO WORLD', $finishEvents[0]['output']);
        $this->assertArrayHasKey('duration_ms', $finishEvents[0]);
        $this->assertIsFloat($finishEvents[0]['duration_ms']);
    }

    public function test_step_error_event_is_emitted_on_unregistered_action(): void
    {
        $errorEvents = [];

        $this->runner->on('step.error', function (array $event) use (&$errorEvents) {
            $errorEvents[] = $event;
        });

        $pipeline = [
            [
                'action' => 'nonexistent.action',
                'options' => [],
            ],
        ];

        try {
            $this->runner->run($pipeline, 'input data');
            $this->fail('Expected WorkflowExecutionException was not thrown.');
        } catch (WorkflowExecutionException $e) {
            $this->assertCount(1, $errorEvents);
            $this->assertSame([0], $errorEvents[0]['path']);
            $this->assertSame('nonexistent.action', $errorEvents[0]['action']);
            $this->assertStringContainsString('not registered', $errorEvents[0]['error']);
        }
    }

    public function test_step_error_event_is_emitted_on_action_failure(): void
    {
        $errorEvents = [];

        $this->runner->on('step.error', function (array $event) use (&$errorEvents) {
            $errorEvents[] = $event;
        });

        $pipeline = [
            [
                'action' => 'test.fail',
                'options' => [],
            ],
        ];

        try {
            $this->runner->run($pipeline, 'input data');
            $this->fail('Expected WorkflowExecutionException was not thrown.');
        } catch (WorkflowExecutionException $e) {
            $this->assertCount(1, $errorEvents);
            $this->assertSame([0], $errorEvents[0]['path']);
            $this->assertSame('test.fail', $errorEvents[0]['action']);
            $this->assertStringContainsString('Deliberate failure', $errorEvents[0]['error']);
        }
    }

    public function test_runner_works_without_listeners(): void
    {
        $pipeline = [
            [
                'action' => 'string.uppercase',
                'options' => [],
            ],
        ];

        // Should execute seamlessly with zero registered listeners
        $output = $this->runner->run($pipeline, 'silent test');
        $this->assertSame('SILENT TEST', $output);
    }
}