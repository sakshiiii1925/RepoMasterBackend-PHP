<?php

class ExcelController
{
    public function template(): never
    {
        $file = __DIR__ . '/../templates/Vehicle_Template.xlsx';

        /*
         * Check whether template exists
         */
        if (!is_file($file)) {
            http_response_code(404);

            header('Content-Type: application/json; charset=utf-8');

            echo json_encode([
                'success' => false,
                'message' => 'Excel template file not found.',
                'file' => $file
            ]);

            exit;
        }

        /*
         * Clear any previous output
         */
        if (ob_get_length()) {
            ob_end_clean();
        }

        /*
         * Excel MIME type
         */
        header(
            'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        );

        /*
         * Force download
         */
        header(
            'Content-Disposition: attachment; filename="Vehicle_Template.xlsx"'
        );

        /*
         * File size
         */
        header(
            'Content-Length: ' . filesize($file)
        );

        /*
         * Prevent caching
         */
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');

        /*
         * Send file
         */
        readfile($file);

        exit;
    }
}