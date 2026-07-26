<?php

namespace App\Services\PilgrimImport;

use PhpOffice\PhpSpreadsheet\IOFactory;
use Throwable;

/**
 * A parsed spreadsheet, reduced to a header row and the data rows beneath it
 * (each row keyed by its own header text). Throws IncompatibleExcelFileException
 * — with a message meant to be shown directly to the uploader — for anything
 * that isn't a usable pilgrim list: a corrupt/unsupported file, a blank sheet,
 * a header row with no columns, or headers with no data underneath them.
 */
class PilgrimExcelFile
{
    /**
     * @param  list<string>  $headers
     * @param  list<array<string, string>>  $rows
     */
    private function __construct(
        public readonly array $headers,
        public readonly array $rows,
    ) {}

    public static function fromPath(string $path): self
    {
        try {
            $spreadsheet = IOFactory::load($path);
            $data = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);
        } catch (Throwable $e) {
            throw new IncompatibleExcelFileException(
                __('This doesn\'t look like a valid Excel or CSV file. Please re-export it and try uploading it again.'),
                previous: $e,
            );
        }

        if (count($data) === 0) {
            throw new IncompatibleExcelFileException(__('The file appears to be empty.'));
        }

        $headerRow = array_shift($data);
        $headers = array_map(fn (mixed $header): string => trim((string) $header), $headerRow);

        while (count($headers) > 0 && end($headers) === '') {
            array_pop($headers);
        }

        if (count(array_filter($headers, fn (string $h): bool => $h !== '')) === 0) {
            throw new IncompatibleExcelFileException(__('The first row doesn\'t contain any column headers.'));
        }

        $rows = [];

        foreach ($data as $rawRow) {
            $isBlank = count(array_filter($rawRow, fn (mixed $v): bool => trim((string) $v) !== '')) === 0;

            if ($isBlank) {
                continue;
            }

            $row = [];

            foreach ($headers as $index => $header) {
                if ($header === '') {
                    continue;
                }

                $row[$header] = trim((string) ($rawRow[$index] ?? ''));
            }

            $rows[] = $row;
        }

        if (count($rows) === 0) {
            throw new IncompatibleExcelFileException(__('The file has column headers but no data rows underneath them.'));
        }

        return new self(array_values(array_filter($headers, fn (string $h): bool => $h !== '')), $rows);
    }
}
