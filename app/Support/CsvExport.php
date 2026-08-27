<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\StreamedResponse;

class CsvExport
{
    /**
     * Stream a CSV download with a UTF-8 BOM for Excel compatibility.
     *
     * @param  list<string>  $headers
     * @param  iterable<int, list<string|int|float|bool|null>>  $rows
     */
    public static function download(string $filename, array $headers, iterable $rows): StreamedResponse
    {
        $safeFilename = preg_replace('/[^A-Za-z0-9_\-\.]/', '_', $filename) ?: 'export.csv';

        return response()->streamDownload(function () use ($headers, $rows): void {
            $file = fopen('php://output', 'w');
            if ($file === false) {
                return;
            }

            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($file, $headers);

            foreach ($rows as $row) {
                fputcsv($file, array_map(static function ($value) {
                    if (is_bool($value)) {
                        return $value ? 'Yes' : 'No';
                    }

                    if ($value === null) {
                        return '';
                    }

                    return $value;
                }, $row));
            }

            fclose($file);
        }, $safeFilename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
