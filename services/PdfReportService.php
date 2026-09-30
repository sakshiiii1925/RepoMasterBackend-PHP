<?php

use Dompdf\Dompdf;
use Dompdf\Options;

class PdfReportService
{
    public function download(
        string $filename,
        string $title,
        array $headers,
        array $rows
    ): never {

        $options = new Options();

        $options->set(
            'isRemoteEnabled',
            false
        );

        $options->set(
            'defaultFont',
            'DejaVu Sans'
        );

        $dompdf = new Dompdf($options);


        // ==========================================
        // LOAD REPOMASTER LOGO
        // ==========================================

        $logoPath = __DIR__ . '/../assets/launchlogo.png';

        $logoHtml = '';

        if (file_exists($logoPath)) {

            $logoData = base64_encode(
                file_get_contents($logoPath)
            );

            $logoHtml =
                '<img
                    src="data:image/png;base64,' .
                    $logoData .
                    '"
                    class="logo"
                >';
        }


        // ==========================================
        // BUILD TABLE ROWS
        // ==========================================

        $tableRows = '';

        foreach ($rows as $row) {

            $tableRows .= '<tr>';

            foreach ($row as $value) {

                $value = htmlspecialchars(
                    (string)($value ?? ''),
                    ENT_QUOTES,
                    'UTF-8'
                );

                $tableRows .= '<td>' . $value . '</td>';
            }

            $tableRows .= '</tr>';
        }


        // ==========================================
        // BUILD HEADERS
        // ==========================================

        $tableHeaders = '';

        foreach ($headers as $header) {

            $header = htmlspecialchars(
                (string)$header,
                ENT_QUOTES,
                'UTF-8'
            );

            $tableHeaders .= '<th>' . $header . '</th>';
        }


        // ==========================================
        // HTML
        // ==========================================

        $html = '
        <!DOCTYPE html>

        <html>

        <head>

            <meta charset="UTF-8">

            <style>

                @page {
                    margin: 35px 30px;
                }

                body {
                    font-family: DejaVu Sans, sans-serif;
                    color: #172033;
                    font-size: 11px;
                    margin: 0;
                    padding: 0;
                }


                /* =================================
                   PDF HEADER
                   ================================= */

                .header {
                    text-align: center;
                    margin-bottom: 22px;
                }


                .logo {
                    width: 70px;
                    height: 70px;
                    object-fit: contain;
                    margin-bottom: 7px;
                }


                .brand {
                    font-size: 22px;
                    font-weight: bold;
                    color: #172033;
                    margin-bottom: 5px;
                }


                .title {
                    font-size: 17px;
                    font-weight: bold;
                    color: #a45d00;
                }


                .date {
                    font-size: 9px;
                    color: #718096;
                    margin-top: 5px;
                }


                /* =================================
                   TABLE
                   ================================= */

                table {
                    width: 100%;
                    border-collapse: collapse;
                    margin-top: 15px;
                }


                th {
                    background: #172033;
                    color: #ffffff;
                    padding: 8px 6px;
                    border: 1px solid #172033;
                    font-size: 10px;
                    text-align: left;
                }


                td {
                    padding: 7px 6px;
                    border: 1px solid #dfe4eb;
                    font-size: 9px;
                }


                tr:nth-child(even) td {
                    background: #f7f9fc;
                }


                /* =================================
                   EMPTY REPORT
                   ================================= */

                .empty {
                    text-align: center;
                    padding: 30px;
                    color: #718096;
                }


                /* =================================
                   FOOTER
                   ================================= */

                .footer {
                    position: fixed;
                    bottom: -20px;
                    left: 0;
                    right: 0;
                    text-align: center;
                    font-size: 8px;
                    color: #718096;
                }

            </style>

        </head>


        <body>


            <!-- =================================
                 HEADER
                 ================================= -->

            <div class="header">

                ' . $logoHtml . '

                <div class="brand">
                    RepoMaster
                </div>

                <div class="title">
                    ' .
                    htmlspecialchars(
                        $title,
                        ENT_QUOTES,
                        'UTF-8'
                    ) .
                '
                </div>

                <div class="date">
                    Generated on ' .
                    date('d-m-Y h:i A') .
                '
                </div>

            </div>';


        // ==========================================
        // REPORT CONTENT
        // ==========================================

        if (empty($rows)) {

            $html .= '
                <div class="empty">
                    No report data available.
                </div>
            ';

        } else {

            $html .= '

                <table>

                    <thead>

                        <tr>
                            ' . $tableHeaders . '
                        </tr>

                    </thead>


                    <tbody>

                        ' . $tableRows . '

                    </tbody>

                </table>

            ';
        }


        // ==========================================
        // FOOTER
        // ==========================================

        $html .= '

            <div class="footer">
                RepoMaster Report
            </div>

        </body>

        </html>
        ';


        // ==========================================
        // GENERATE PDF
        // ==========================================

        $dompdf->loadHtml($html);

        $dompdf->setPaper(
            'A4',
            'landscape'
        );

        $dompdf->render();


        // ==========================================
        // CLEAR PREVIOUS OUTPUT
        // ==========================================

        while (ob_get_level() > 0) {
            ob_end_clean();
        }


        // ==========================================
        // PDF DOWNLOAD
        // ==========================================

        header(
            'Content-Type: application/pdf'
        );

        header(
            'Content-Disposition: attachment; filename="' .
            $filename .
            '"'
        );

        header(
            'Cache-Control: private, max-age=0, must-revalidate'
        );

        header(
            'Pragma: public'
        );


        echo $dompdf->output();

        exit;
    }
}