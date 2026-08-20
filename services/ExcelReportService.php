<?php

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class ExcelReportService
{
    public function download(
        string $filename,
        string $sheetName,
        array $headers,
        array $rows
    ): never {

        $book = new Spreadsheet();

        $sheet = $book->getActiveSheet();

        $sheet->setTitle($sheetName);

        // =========================
        // HEADERS
        // =========================
        foreach ($headers as $i => $header) {

            $column = Coordinate::stringFromColumnIndex($i + 1);

            $sheet->setCellValue(
                $column . '1',
                $header
            );
        }

        // =========================
        // DATA ROWS
        // =========================
        foreach ($rows as $ri => $row) {

            foreach ($row as $ci => $value) {

                $column = Coordinate::stringFromColumnIndex($ci + 1);

                $rowNumber = $ri + 2;

                $sheet->setCellValue(
                    $column . $rowNumber,
                    $value
                );
            }
        }

        // =========================
        // AUTO SIZE COLUMNS
        // =========================
        foreach (range(1, count($headers)) as $columnIndex) {

            $column = Coordinate::stringFromColumnIndex($columnIndex);

            $sheet
                ->getColumnDimension($column)
                ->setAutoSize(true);
        }

        // =========================
        // DOWNLOAD EXCEL
        // =========================
        header(
            'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        );

        header(
            'Content-Disposition: attachment; filename="' . $filename . '"'
        );

        header('Cache-Control: max-age=0');

        $writer = new Xlsx($book);

        $writer->save('php://output');

        exit;
    }
}