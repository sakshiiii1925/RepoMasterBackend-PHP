<?php
require_once __DIR__ . '/../helpers/response.php';
class InvoiceController {public function __construct(private InvoiceService $s){}public function add(){jsonResponse($this->s->create(requestBody()));}public function get($id){jsonResponse($this->s->get((int)$id));}public function list(){jsonResponse($this->s->list((string)queryParam('agencyId','')));}public function delete($id){$this->s->delete((int)$id);jsonResponse('Invoice deleted successfully');}}
