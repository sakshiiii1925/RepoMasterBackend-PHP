<?php

use Dompdf\Dompdf;
use Dompdf\Options;

class InvoicePdfService
{
    public function download(
        array $invoice,
        string $filename
    ): never {

        // =========================================================
        // DOMPDF
        // =========================================================

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


        // =========================================================
        // LOGO
        // =========================================================

        $logoHtml = '';

        $logoPath = __DIR__ . '/../assets/launchlogo.png';

        if (file_exists($logoPath)) {

            $logoData = base64_encode(
                file_get_contents($logoPath)
            );

            $logoHtml = '
                <img
                    src="data:image/png;base64,' . $logoData . '"
                    class="logo"
                >
            ';
        }


        // =========================================================
        // DIGITAL SIGNATURE
        // =========================================================

        $signatureHtml = '';

        $signaturePath =
            __DIR__ . '/../assets/digital_sign.png';

        if (file_exists($signaturePath)) {

            $signatureData = base64_encode(
                file_get_contents($signaturePath)
            );

            $signatureHtml = '
                <img
                    src="data:image/png;base64,' .
                    $signatureData .
                    '"
                    class="signature-image"
                >
            ';
        }


        // =========================================================
        // VALUES
        // =========================================================

        $invoiceNumber = $this->e(
            $invoice['invoiceNumber'] ??
            $invoice['invoice_number'] ??
            'N/A'
        );

        $invoiceDate = $this->formatDate(
            $invoice['invoiceDate'] ??
            $invoice['invoice_date'] ??
            null
        );

        $invoiceBank = $this->e(
            $invoice['invoiceBank'] ??
            $invoice['invoice_bank'] ??
            'FINANCE COMPANY'
        );

        $branch = $this->e(
            $invoice['branch'] ?? 'N/A'
        );

        $customerName = $this->e(
            $invoice['customerName'] ??
            $invoice['customer_name'] ??
            'N/A'
        );

        $loanNumber = $this->e(
            $invoice['loanNumber'] ??
            $invoice['loan_number'] ??
            'N/A'
        );

        $vehicleNumber = $this->e(
            $invoice['vehicleNumber'] ??
            $invoice['vehicle_number'] ??
            'N/A'
        );

        $vehicleType = $this->e(
            $invoice['vehicleType'] ??
            $invoice['vehicle_type'] ??
            'N/A'
        );

        $vehicleMake = $this->e(
            $invoice['vehicleMake'] ??
            $invoice['vehicle_make'] ??
            'N/A'
        );

        $vehicleModel = $this->e(
            $invoice['vehicleModel'] ??
            $invoice['vehicle_model'] ??
            'N/A'
        );

        $engineNumber = $this->e(
            $invoice['engineNumber'] ??
            $invoice['engine_number'] ??
            'N/A'
        );

        $chassisNumber = $this->e(
            $invoice['chassisNumber'] ??
            $invoice['chassis_number'] ??
            'N/A'
        );

        $yardName = $this->e(
            $invoice['yardName'] ??
            $invoice['yard_name'] ??
            'N/A'
        );

        $yardAddress = $this->e(
            $invoice['yardAddress'] ??
            $invoice['yard_address'] ??
            'N/A'
        );

        $description1 = $this->e(
            $invoice['description1'] ??
            $invoice['description_1'] ??
            'Basic Charges'
        );

        $description2 = $this->e(
            $invoice['description2'] ??
            $invoice['description_2'] ??
            'Additional Charges'
        );


        // =========================================================
        // AMOUNTS
        // =========================================================

        $basic1 = $this->money(
            $invoice['basic1Amount'] ??
            $invoice['basic1_amount'] ??
            0
        );

        $basic2 = $this->money(
            $invoice['basic2Amount'] ??
            $invoice['basic2_amount'] ??
            0
        );

        $totalBasic = $this->money(
            $invoice['totalBasic'] ??
            $invoice['total_basic'] ??
            0
        );

        $cgst = $this->money(
            $invoice['cgst'] ?? 0
        );

        $sgst = $this->money(
            $invoice['sgst'] ?? 0
        );

        $igst = $this->money(
            $invoice['igst'] ?? 0
        );

        $gst = $this->money(
            $invoice['gst'] ?? 0
        );

        $invoiceTotal = $this->money(
            $invoice['invoiceTotal'] ??
            $invoice['invoice_total'] ??
            0
        );

        $dpd = (int)(
            $invoice['dpd'] ?? 0
        );

        $dpdChargePercent = number_format(
            (float)(
                $invoice['dpdChargePercent'] ??
                $invoice['dpd_charge_percent'] ??
                0
            ),
            2
        ) . '%';

        $dpdExtraCharge = $this->money(
            $invoice['dpdExtraCharge'] ??
            $invoice['dpd_extra_charge'] ??
            0
        );

        $dpdTotalAmount =
            $invoice['dpdTotalAmount'] ??
            $invoice['dpd_total_amount'] ??
            $invoice['invoiceTotal'] ??
            $invoice['invoice_total'] ??
            0;

        $dpdTotalAmount = $this->money(
            $dpdTotalAmount
        );


        // =========================================================
        // PAYMENT
        // =========================================================

        $paymentReceived = $this->money(
            $invoice['paymentReceived'] ??
            $invoice['payment_received'] ??
            0
        );

        $remainingAmount = $this->money(
            $invoice['remainingAmount'] ??
            $invoice['remaining_amount'] ??
            0
        );

        $paymentStatus = $this->e(
            $invoice['paymentStatus'] ??
            $invoice['payment_status'] ??
            'Pending'
        );

        $paymentDate = $this->e(
            $invoice['paymentDate'] ??
            $invoice['payment_date'] ??
            'N/A'
        );


        // =========================================================
        // CREATED INFORMATION
        // =========================================================

        $createdBy = $this->e(
            $invoice['createdBy'] ??
            $invoice['created_by'] ??
            'N/A'
        );

        $createdDate = $this->e(
            $invoice['createdDate'] ??
            $invoice['created_date'] ??
            'N/A'
        );


        // =========================================================
        // HTML
        // =========================================================

        $html = '
<!DOCTYPE html>

<html>

<head>

<meta charset="UTF-8">

<style>

@page {
    margin: 22px 25px 35px 25px;
}

body {
    font-family: DejaVu Sans, sans-serif;
    color: #172033;
    font-size: 9px;
    margin: 0;
}

.logo {
    width: 55px;
    height: 55px;
    margin-bottom: 4px;
}

.header {
    text-align: center;
    margin-bottom: 8px;
}

.company-name {
    font-size: 19px;
    font-weight: bold;
    color: #172033;
}

.company-subtitle {
    font-size: 9px;
    margin-top: 2px;
    color: #555f70;
}

.section-title {
    font-size: 11px;
    font-weight: bold;
    margin-top: 10px;
    margin-bottom: 4px;
}

table {
    width: 100%;
    border-collapse: collapse;
}

.info-table {
    margin-top: 4px;
}

.info-cell {
    border: 1px solid #172033;
    padding: 6px;
    vertical-align: top;
}

.no-border {
    border: none !important;
}

.small-label {
    font-weight: bold;
}

.billing-table {
    margin-top: 3px;
}

.billing-table th {
    background: #172033;
    color: white;
    border: 1px solid #172033;
    padding: 6px;
    font-size: 8px;
    text-align: center;
}

.billing-table td {
    border: 1px solid #bfc6d1;
    padding: 5px;
    font-size: 8px;
}

.billing-right {
    text-align: right;
}

.billing-center {
    text-align: center;
}

.summary-row td {
    font-weight: bold;
}

.grand-total td {
    font-size: 9px;
    font-weight: bold;
    background: #fff3d8;
    border: 1px solid #172033;
}

.payment-table {
    margin-top: 3px;
}

.payment-cell {
    border: 1px solid #bfc6d1;
    padding: 7px;
    vertical-align: top;
}

.payment-line {
    font-weight: bold;
    font-size: 8px;
    margin-bottom: 5px;
}

.signature-title {
    font-size: 11px;
    font-weight: bold;
    margin-top: 12px;
    margin-bottom: 5px;
}

.signature-cell {
    width: 50%;
    padding: 6px;
    vertical-align: top;
}

.signature-image {
    width: 110px;
    height: 45px;
    margin-top: 3px;
    margin-bottom: 2px;
}

.center {
    text-align: center;
}

.footer {
    position: fixed;
    bottom: -20px;
    left: 0;
    right: 0;
    text-align: center;
    font-size: 7px;
    color: #718096;
}

</style>

</head>

<body>


<!-- =========================================================
     LOGO + COMPANY HEADER
     ========================================================= -->

<div class="header">

    ' . $logoHtml . '

    <div class="company-name">
        REPO MASTER
    </div>

    <div class="company-subtitle">
        Vehicle Recovery Management
    </div>

</div>


<!-- =========================================================
     TO MANAGER + INVOICE
     ========================================================= -->

<table class="info-table">

<tr>

<td class="info-cell" width="65%">

    <strong>TO,</strong><br>
    THE MANAGER,<br>
    ' . $invoiceBank . '

</td>


<td class="info-cell" width="35%">

    <strong>INVOICE</strong><br>

    Invoice No : ' . $invoiceNumber . '<br>

    Date : ' . $invoiceDate . '

</td>

</tr>

</table>


<!-- =========================================================
     FINANCE + CUSTOMER
     ========================================================= -->

<table class="info-table">

<tr>

<td class="info-cell" width="50%">

    <strong>FINANCE DETAILS</strong><br><br>

    Finance Bank : ' . $invoiceBank . '<br>

    Branch : ' . $branch . '

</td>


<td class="info-cell" width="50%">

    <strong>CUSTOMER DETAILS</strong><br><br>

    Customer Name : ' . $customerName . '<br>

    Loan Number : ' . $loanNumber . '<br>

    Vehicle Number : ' . $vehicleNumber . '

</td>

</tr>

</table>


<!-- =========================================================
     VEHICLE DETAILS
     ========================================================= -->

<table class="info-table">

<tr>

<td class="info-cell" width="50%">

    <strong>VEHICLE DETAILS</strong><br><br>

    Vehicle Type : ' . $vehicleType . '<br>

    Vehicle Make : ' . $vehicleMake . '<br>

    Vehicle Model : ' . $vehicleModel . '

</td>


<td class="info-cell" width="50%">

    <strong>VEHICLE / YARD</strong><br><br>

    Engine No : ' . $engineNumber . '<br>

    Chassis No : ' . $chassisNumber . '<br>

    Yard Name : ' . $yardName . '<br>

    Yard Address : ' . $yardAddress . '

</td>

</tr>

</table>


<!-- =========================================================
     BILLING
     ========================================================= -->

<div class="section-title">
    DETAILS OF BILLING
</div>


<table class="billing-table">

<thead>

<tr>

<th width="8%">
    SR.NO
</th>

<th width="67%">
    DETAILS OF BILLING
</th>

<th width="25%">
    AMOUNT
</th>

</tr>

</thead>


<tbody>


<tr>

<td class="billing-center">
    1
</td>

<td>
    ' . $description1 . '
</td>

<td class="billing-right">
    ' . $basic1 . '
</td>

</tr>


<tr>

<td class="billing-center">
    2
</td>

<td>
    ' . $description2 . '
</td>

<td class="billing-right">
    ' . $basic2 . '
</td>

</tr>


<tr class="summary-row">

<td></td>

<td class="billing-right">
    TOTAL BASIC
</td>

<td class="billing-right">
    ' . $totalBasic . '
</td>

</tr>


<tr>

<td></td>

<td>
    CGST
</td>

<td class="billing-right">
    ' . $cgst . '
</td>

</tr>


<tr>

<td></td>

<td>
    SGST
</td>

<td class="billing-right">
    ' . $sgst . '
</td>

</tr>


<tr>

<td></td>

<td>
    IGST
</td>

<td class="billing-right">
    ' . $igst . '
</td>

</tr>


<tr>

<td></td>

<td>
    GST
</td>

<td class="billing-right">
    ' . $gst . '
</td>

</tr>


<tr class="summary-row">

<td></td>

<td class="billing-right">
    INVOICE TOTAL
</td>

<td class="billing-right">
    ' . $invoiceTotal . '
</td>

</tr>


<tr>

<td></td>

<td>
    DPD
</td>

<td class="billing-right">
    ' . $dpd . ' Days
</td>

</tr>


<tr>

<td></td>

<td>
    DPD CHARGE RATE
</td>

<td class="billing-right">
    ' . $dpdChargePercent . '
</td>

</tr>


<tr>

<td></td>

<td>
    DPD EXTRA CHARGE
</td>

<td class="billing-right">
    ' . $dpdExtraCharge . '
</td>

</tr>


<tr class="grand-total">

<td colspan="2" class="billing-right">
    TOTAL AMOUNT INCLUDING DPD
</td>

<td class="billing-right">
    ' . $dpdTotalAmount . '
</td>

</tr>


</tbody>

</table>


<!-- =========================================================
     PAYMENT SUMMARY
     ========================================================= -->

<div class="section-title">
    PAYMENT SUMMARY
</div>


<table class="payment-table">

<tr>

<td class="payment-cell" width="50%">

    <div class="payment-line">
        TOTAL BILL AMOUNT:
        ' . $dpdTotalAmount . '
    </div>

    <div class="payment-line">
        PAYMENT RECEIVED:
        ' . $paymentReceived . '
    </div>

</td>


<td class="payment-cell" width="50%">

    <div class="payment-line">
        REMAINING AMOUNT:
        ' . $remainingAmount . '
    </div>

    <div class="payment-line">
        PAYMENT STATUS:
        ' . $paymentStatus . '
    </div>

    <div class="payment-line">
        PAYMENT DATE:
        ' . $paymentDate . '
    </div>

</td>

</tr>

</table>


<!-- =========================================================
     CREATED INFORMATION
     ========================================================= -->

<table class="info-table">

<tr>

<td class="info-cell" width="50%">

    <strong>Created By</strong><br>
    ' . $createdBy . '

</td>


<td class="info-cell" width="50%">

    <strong>Created Date</strong><br>
    ' . $createdDate . '

</td>

</tr>

</table>


<!-- =========================================================
     DIGITAL SIGNATURE
     ========================================================= -->

<div class="signature-title">
    DIGITAL SIGNATURE
</div>


<table>

<tr>


<!-- LEFT -->

<td class="signature-cell">

    <strong>Authorized By</strong><br>

    ' . $createdBy . '

    <br><br>

    ____________________________<br>

    Authorized Signatory

</td>


<!-- RIGHT -->

<td class="signature-cell center">

    <strong>Digitally Signed By</strong><br>

    <strong>REPO MASTER</strong>

    <br>

    ' . $signatureHtml . '

    <br>

    ____________________________<br>

    <strong>Authorized Signature</strong>

    <br><br>

    <span style="font-size:7px;">
        Digitally signed on:<br>
         ' . date("d/m/Y h:i:s A") . '
    </span>

    <br>

    <span style="font-size:6.5px;">
        This document is digitally signed.
    </span>

</td>


</tr>

</table>


<div class="footer">
    RepoMaster Invoice
</div>


</body>

</html>
';


// =========================================================
// GENERATE PDF
// =========================================================

$dompdf->loadHtml($html);

$dompdf->setPaper(
    'A4',
    'portrait'
);

$dompdf->render();


        // =========================================================
        // CLEAR OUTPUT
        // =========================================================

        while (ob_get_level() > 0) {
            ob_end_clean();
        }


        // =========================================================
        // DOWNLOAD
        // =========================================================

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


    // =========================================================
    // ESCAPE HTML
    // =========================================================

    private function e($value): string
    {
        return htmlspecialchars(
            (string)($value ?? ''),
            ENT_QUOTES,
            'UTF-8'
        );
    }


    // =========================================================
    // MONEY
    // =========================================================

    private function money($amount): string
    {
        return '₹' . number_format(
            (float)($amount ?? 0),
            2
        );
    }


    // =========================================================
    // DATE
    // =========================================================

    private function formatDate($date): string
    {
        if (empty($date)) {
            return 'N/A';
        }

        try {

            $input = new DateTime(
                (string)$date
            );

            return $input->format(
                'd/m/Y'
            );

        } catch (Exception $e) {

            return (string)$date;
        }
    }
}