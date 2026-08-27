<?php

namespace App\Application\Reports;

use Symfony\Component\HttpFoundation\StreamedResponse;

class CsvReportResponse
{
    /**
     * @param  callable(resource): void  $writer
     */
    public function stream(string $filename, callable $writer): StreamedResponse
    {
        return response()->stream(function () use ($writer): void {
            $handle = fopen('php://output', 'w');

            if ($handle === false) {
                throw new \RuntimeException(__('Unable to open CSV output stream.'));
            }

            try {
                $writer($handle);
            } finally {
                fclose($handle);
            }
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * @param  list<string>  $headers
     */
    public function fromRows(string $filename, array $headers, iterable $rows, callable $rowMapper): StreamedResponse
    {
        return $this->stream($filename, function ($handle) use ($headers, $rows, $rowMapper): void {
            fputcsv($handle, $headers);

            foreach ($rows as $row) {
                fputcsv($handle, $rowMapper($row));
            }
        });
    }
}
