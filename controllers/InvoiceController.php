<?php
require_once __DIR__ . '/../helpers/response.php';
class InvoiceController {public function __construct(private InvoiceService $s,private InvoicePdfService $pdf){}
//add
public function add()
{
    jsonResponse(
        $this->s->create(requestBody()));}
//get
public function get($id)
{
    jsonResponse(
        $this->s->get((int)$id));}
//ListOfInvoice
public function list()
{
    jsonResponse(
        $this->s->list((string)queryParam('agencyId','')));}
//DeleteInvoice
public function delete($id)
{$this->s->delete((int)$id);
jsonResponse('Invoice deleted successfully');}
//updatePayment
public function updatePayment($id)
{
    jsonResponse(
        $this->s->updatePayment(
            (int)$id,
            requestBody()
        )
    );
}
public function searchVehicles()
{
    $keyword = trim((string)queryParam('keyword', ''));

    jsonResponse(
        $this->s->searchVehiclesForInvoice($keyword)
    );
}
public function updateDpdCharge(int $id)
{
    jsonResponse(
        $this->s->updateDpdCharge(
            $id,
            requestBody()
        )
    );
}
public function pdf($id)
{
    $invoice = $this->s->get((int)$id);

    if (!$invoice) {
        throw new InvalidArgumentException(
            'Invoice not found'
        );
    }

    $invoiceNumber =
        $invoice['invoiceNumber']
        ?? $invoice['invoice_number']
        ?? $id;

    $safeInvoiceNumber = preg_replace(
        '/[^A-Za-z0-9_-]/',
        '_',
        (string)$invoiceNumber
    );

    $filename =
        'Invoice_' .
        $safeInvoiceNumber .
        '.pdf';

    $this->pdf->download(
        $invoice,
        $filename
    );
}
}
