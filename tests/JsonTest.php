<?php

namespace Blueprintau\Automaton\Tests;

use Blueprintau\Automaton\ActionRunner;
use Blueprintau\Automaton\Actions\Json\JsonEncode;
use Blueprintau\Automaton\Actions\Json\JsonParse;
use Blueprintau\Automaton\Actions\Json\JsonValidate;
use Blueprintau\Automaton\WorkflowExecutionException;
use PHPUnit\Framework\TestCase;
use PHPUnit\Util\Json;

class JsonTest extends TestCase
{
    private ActionRunner $runner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->runner = new ActionRunner();
        $this->runner->register(new JsonParse());
        $this->runner->register(new JsonEncode());
        $this->runner->register(new JsonValidate());
    }

    public function test_json_decode_associative(): void
    {
        $json = <<<'JSON'
[
    {"username": "jack.harris", "password": 123},
    {"username": "john.smith", "password": "abc", "roles": ["admin", "user"]}
]
JSON;

        $script = [
            ['action' => 'json.parse', 'options' => ['associative' => true]],
        ];

        $output = $this->runner->run($script, $json);

        $this->assertIsArray($output);
        $this->assertCount(2, $output);
        $this->assertSame('jack.harris', $output[0]['username']);
        $this->assertSame(123, $output[0]['password']);
        $this->assertSame('john.smith', $output[1]['username']);
        $this->assertSame('abc', $output[1]['password']);
        $this->assertContains('admin', $output[1]['roles']);
    }

    public function test_json_decode_as_objects(): void
    {
        $json = '{"username": "jack.harris"}';

        $script = [
            ['action' => 'json.parse', 'options' => ['associative' => false]],
        ];

        $output = $this->runner->run($script, $json);

        $this->assertIsObject($output);
        $this->assertSame('jack.harris', $output->username);
    }

    public function test_json_encode_passing(): void
    {
        $data = ['name' => 'Automaton', 'active' => true];

        $script = [
            ['action' => 'json.encode'],
        ];

        $output = $this->runner->run($script, $data);

        $this->assertIsString($output);
        $this->assertJsonStringEqualsJsonString(json_encode($data), $output);
    }

    public function test_json_decode_fails_with_breadcrumb_details(): void
    {
        $malformedJson = '{ "name": "Broken", }';

        $script = [
            ['action' => 'json.parse', 'options' => ['associative' => true]],
        ];

        try {
            $this->runner->run($script, $malformedJson);
            $this->fail('Expected WorkflowExecutionException was not thrown.');
        } catch (WorkflowExecutionException $e) {
            $this->assertSame('json.parse', $e->getActionId());
            $this->assertSame([0], $e->getPipelinePath());
            $this->assertStringContainsString('JSON parse error', $e->getMessage());
        }
    }

    public function test_json_decode_fails_when_input_is_not_a_string(): void
    {
        $script = [
            ['action' => 'json.parse'],
        ];

        $this->expectException(WorkflowExecutionException::class);
        $this->expectExceptionMessage('Input must be a string');

        $this->runner->run($script, ['already', 'an', 'array']);
    }

    public function test_json_validate_passing() : void
    {
        $script = [
            ['action' => 'json.validate'],
        ];

        $json = <<<'JSON'
[
    {"username": "jack.harris", "password": 123},
    {"username": "john.smith", "password": "abc", "roles": ["admin", "user"]}
]
JSON;

        $output = $this->runner->run($script, $json);

        $this->assertTrue($output);
    }

    public function test_json_validate_failing() : void
    {
        $script = [
            ['action' => 'json.validate'],
        ];

        $json = <<<'TEXT'
[not valid json
    {"username": "jack.harris", "password": 123},
    {"username": "john.smith", "password": "abc", "roles": ["admin", "user"]}
]
TEXT;

        $output = $this->runner->run($script, $json);

        $this->assertFalse($output);
    }



}