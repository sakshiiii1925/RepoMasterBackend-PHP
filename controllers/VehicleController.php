<?php
require_once __DIR__ . '/../helpers/response.php';
class VehicleController {
 public function __construct(private VehicleService $s,private ExcelService $excel,private UserService $userService){}
public function list()
{
    $userId = (int)queryParam(
        'userId',
        0
    );

    if ($userId <= 0) {
        errorResponse(
            'userId is required',
            400
        );
        return;
    }

    $agencyId =
        $this->userService
            ->getUserAgencyId($userId);

    jsonResponse(
        $this->s->getAllVehicles(
            $agencyId
        )
    );
}
 public function add(){jsonResponse($this->s->addVehicle(requestBody()));}
 public function update($k){jsonResponse($this->s->updateVehicle($k,requestBody()));}
 public function status($k)
{
    $b = requestBody();

    // Status comes from JSON body
    $status = trim((string)($b['status'] ?? ''));

    // userId comes from URL query parameter:
    // ?userId=2
    $userId = (int)queryParam('userId', 0);

    // Optional user information
    $userName = trim((string)($b['userName'] ?? ''));
    $userEmail = trim((string)($b['userEmail'] ?? ''));

    if ($status === '') {
        errorResponse('Status is required', 400);
        return;
    }

    if ($userId <= 0) {
        errorResponse('userId is required', 400);
        return;
    }

    $r = $this->s->updateStatus(
        $k,
        $status,
        $userId,
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
public function search()
{
    $keyword = trim(
        (string)queryParam('keyword', '')
    );

    $userId = (int)queryParam('userId', 0);

    if ($userId <= 0) {
        errorResponse(
            'userId is required',
            400
        );
        return;
    }

    $agencyId = $this->userService
        ->getUserAgencyId($userId);

    jsonResponse(
        $this->s->searchVehicleNumbers(
            $keyword,
            $agencyId
        )
    );
}
 
public function get($k)
{
    $userId = (int)queryParam(
        'userId',
        0
    );

    if ($userId <= 0) {
        errorResponse(
            'userId is required',
            400
        );
        return;
    }

    $agencyId =
        $this->userService
            ->getUserAgencyId($userId);

    $r =
        $this->s->getVehicleForUser(
            $k,
            $agencyId
        );

    if (!$r) {
        errorResponse(
            'Vehicle Not Found',
            404
        );
        return;
    }

    jsonResponse($r);
}
 public function upload(){jsonResponse($this->excel->upload($_FILES['file']??[],(string)queryParam('agencyId','')));}
 public function assign($k){$ok=$this->s->assignVehicleToYard($k,(int)queryParam('yardId',0));if(!$ok)errorResponse('Vehicle not found',404);jsonResponse('Vehicle assigned to yard successfully');}
 public function yard($id){jsonResponse($this->s->getVehiclesByYard((int)$id,(string)queryParam('agencyId','')));}
 public function removeYard($k){$ok=$this->s->removeVehicleFromYard($k);if(!$ok)errorResponse('Vehicle not found',404);jsonResponse('Vehicle removed from yard successfully');}
}
