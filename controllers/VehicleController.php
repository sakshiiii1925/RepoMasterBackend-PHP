<?php
require_once __DIR__ . '/../helpers/response.php';
class VehicleController {
 public function __construct(private VehicleService $s,private ExcelService $excel){}
 public function list(){jsonResponse($this->s->getAllVehicles((string)queryParam('agencyId','')));}
 public function add(){jsonResponse($this->s->addVehicle(requestBody()));}
 public function update($k){jsonResponse($this->s->updateVehicle($k,requestBody()));}
 public function status($k)
{
    $b = requestBody();

    $status = (string)($b['status'] ?? '');
    $userName = (string)($b['userName'] ?? '');
    $userEmail = (string)($b['userEmail'] ?? '');

    if ($status === '') {
        errorResponse('Status is required', 400);
        return;
    }

    $r = $this->s->updateStatus(
        $k,
        $status,
        $userName,
        $userEmail
    );

    if (!$r) {
        errorResponse('Vehicle Not Found', 404);
        return;
    }

    jsonResponse($r);
}
 public function delete($k){$this->s->deleteVehicle($k);jsonResponse('Vehicle Deleted Successfully');}
 public function bulk(){jsonResponse($this->s->addAllVehicles(requestBody()));}
 public function search(){jsonResponse($this->s->searchVehicleNumbers((string)queryParam('keyword','')));}
 public function get($k){$r=$this->s->getVehicle($k);if(!$r)errorResponse('Vehicle Not Found',404);jsonResponse($r);}
 public function upload(){jsonResponse($this->excel->upload($_FILES['file']??[],(string)queryParam('agencyId','')));}
 public function assign($k){$ok=$this->s->assignVehicleToYard($k,(int)queryParam('yardId',0));if(!$ok)errorResponse('Vehicle not found',404);jsonResponse('Vehicle assigned to yard successfully');}
 public function yard($id){jsonResponse($this->s->getVehiclesByYard((int)$id,(string)queryParam('agencyId','')));}
 public function removeYard($k){$ok=$this->s->removeVehicleFromYard($k);if(!$ok)errorResponse('Vehicle not found',404);jsonResponse('Vehicle removed from yard successfully');}
}
