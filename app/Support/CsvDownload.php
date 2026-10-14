<?php
namespace App\Support;

use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\CSV\Writer;

/** A CSV the browser downloads, streamed row by row so a long list never has to fit in memory. Opens cleanly in Excel (UTF-8 with a byte order mark). */
class CsvDownload
{
    public static function stream(string $filename, array $header, iterable $rows)
    {
        return response()->streamDownload(function () use ($header, $rows) {
            $writer = new Writer();
            $writer->openToFile('php://output');
            $writer->addRow(Row::fromValues($header));
            foreach ($rows as $row) {
                $writer->addRow(Row::fromValues(array_map(fn ($v) => $v === null ? '' : $v, array_values($row))));
            }
            $writer->close();
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
