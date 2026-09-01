<?php

namespace App\Application\Reports;

use Symfony\Component\HttpFoundation\StreamedResponse;

class CsvReportResponse
{
    private const TEMP_MEMORY_LIMIT_BYTES = 5 * 1024 * 1024;

    /**
     * @param  callable(resource): void  $writer
     */
    public function stream(string $filename, callable $writer): StreamedResponse
    {
        return response()->stream(function () use ($writer): void {
            $temporary = fopen('php://temp/maxmemory:'.self::TEMP_MEMORY_LIMIT_BYTES, 'w+');
            $output = fopen('php://output', 'w');

            if ($temporary === false || $output === false) {
                throw new \RuntimeException(__('Unable to open CSV output stream.'));
            }

            try {
                $writer($temporary);
                rewind($temporary);

                while (($row = fgetcsv($temporary, null, ',', '"', '\\')) !== false) {
                    if ($row === [null]) {
                        fwrite($output, PHP_EOL);

                        continue;
                    }

                    $this->writeRow($output, $row);
                }
            } finally {
                fclose($temporary);
                fclose($output);
            }
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$this->safeFilename($filename).'"',
        ]);
    }

    public function safeFilename(string $filename): string
    {
        $safe = str_replace(["\0", "\r", "\n", '/', '\\'], '_', trim($filename));
        $safe = preg_replace('/[^A-Za-z0-9._-]+/', '_', $safe) ?? '';
        $safe = preg_replace('/\.{2,}/', '.', $safe) ?? '';
        $safe = trim($safe, '._-');

        if ($safe === '') {
            $safe = 'report.csv';
        } elseif (! str_ends_with(strtolower($safe), '.csv')) {
            $safe .= '.csv';
        }

        if (strlen($safe) > 180) {
            $safe = rtrim(substr($safe, 0, 176), '._-').'.csv';
        }

        return $safe;
    }

    /**
     * @param  resource  $handle
     * @param  array<int, mixed>  $row
     */
    public function writeRow($handle, array $row): void
    {
        $written = fputcsv(
            $handle,
            array_map(fn (mixed $cell): mixed => $this->sanitizeCell($cell), $row),
            ',',
            '"',
            '\\'
        );

        if ($written === false) {
            throw new \RuntimeException(__('Unable to write CSV output row.'));
        }
    }

    /**
     * @param  list<string>  $headers
     */
    public function fromRows(string $filename, array $headers, iterable $rows, callable $rowMapper): StreamedResponse
    {
        return $this->stream($filename, function ($handle) use ($headers, $rows, $rowMapper): void {
            $this->writeRow($handle, $headers);

            foreach ($rows as $row) {
                $this->writeRow($handle, $rowMapper($row));
            }
        });
    }

    private function sanitizeCell(mixed $cell): mixed
    {
        if (! is_string($cell)) {
            return $cell;
        }

        $candidate = ltrim($cell);
        if ($candidate === '' || is_numeric($candidate)) {
            return $cell;
        }

        if (in_array($candidate[0], ['=', '+', '-', '@'], true)) {
            return "'{$cell}";
        }

        return $cell;
    }
}
