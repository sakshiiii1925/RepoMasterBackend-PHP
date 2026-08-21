<?php

require_once __DIR__ .
    '/../helpers/response.php';

class InvoicePaymentController
{
    public function __construct(
        private InvoicePaymentService $service
    ) {}


    // POST /api/invoices/{id}/payments
    public function add(
        int $invoiceId
    ) {

        $data = requestBody();

        $data['invoiceId'] =
            $invoiceId;

        jsonResponse(
            $this->service->create($data)
        );
    }


    // GET /api/invoices/{id}/payments
    public function list(
        int $invoiceId
    ) {

        jsonResponse(
            $this->service->listByInvoice(
                $invoiceId
            )
        );
    }


    // GET /api/invoice-payments/{id}
    public function get(
        int $id
    ) {

        jsonResponse(
            $this->service->get($id)
        );
    }


    // DELETE /api/invoice-payments/{id}
    public function delete(
        int $id
    ) {

        $this->service->delete($id);

        jsonResponse(
            'Payment deleted successfully'
        );
    }
}