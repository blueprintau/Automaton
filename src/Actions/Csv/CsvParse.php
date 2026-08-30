<?php

namespace Blueprintau\Automaton\Actions\Csv;

use Blueprintau\Automaton\Action;
use Blueprintau\Automaton\WorkflowContext;
use Blueprintau\Automaton\WorkflowExecutionException;

class CsvParse extends Action
{
    public function getId(): string
    {
        return 'csv.parse';
    }

    public function run(mixed $input, WorkflowContext $context, array $options = []): mixed
    {
        if (!is_string($input) || trim($input) === '') {
            throw new WorkflowExecutionException(
                "Input for 'csv.parse' must be a valid CSV string.",
                $this->getId(),
                $options['_path'] ?? [],
                $input
            );
        }

        $delimiter = $options['delimiter'] ?? ',';
        $hasHeader = (bool)($options['header'] ?? true);

        $lines = preg_split('/\r\n|\r|\n/', trim($input));
        $rows  = [];

        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }
            $rows[] = str_getcsv($line, $delimiter);
        }

        if (empty($rows)) {
            return [];
        }

        if ($hasHeader) {
            $headers = array_map('trim', array_shift($rows));
            $records = [];

            foreach ($rows as $row) {
                if (count($row) === count($headers)) {
                    $records[] = array_combine($headers, array_map('trim', $row));
                }
            }

            return $records;
        }

        return $rows;
    }
}