<?php
require_once __DIR__ . '/../helpers/response.php';
class InvoiceController {public function __construct(private InvoiceService $s){}
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
}
