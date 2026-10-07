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
public function count()
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

    if (!$agencyId) {
        errorResponse(
            'Agency not found for user',
            404
        );
        return;
    }

    $count =
        $this->s->getVehicleCount(
            $agencyId
        );

    jsonResponse([
        'success' => true,
        'count' => $count
    ]);
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
 
// =========================================================
// DELETE MULTIPLE VEHICLES
// =========================================================

public function bulkDelete()
{
    $userId =
        (int)queryParam(
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

    if (!$agencyId) {

        errorResponse(
            'Agency not found for user',
            404
        );

        return;
    }

    $body =
        requestBody();

    $vehicleNumbers =
        $body['vehicleNumbers'] ?? [];

    if (
        !is_array($vehicleNumbers) ||
        empty($vehicleNumbers)
    ) {

        errorResponse(
            'vehicleNumbers are required',
            400
        );

        return;
    }

    $deletedCount =
        $this->s->deleteMultipleVehicles(
            $vehicleNumbers,
            $agencyId
        );

    jsonResponse([
        'success' => true,
        'deletedCount' => $deletedCount,
        'message' =>
            $deletedCount .
            ' vehicle(s) deleted successfully'
    ]);
}


// =========================================================
// DELETE ALL VEHICLES BY UPLOAD DATE
// =========================================================

public function deleteByDate(
    $date
) {

    $userId =
        (int)queryParam(
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

    if (!$agencyId) {

        errorResponse(
            'Agency not found for user',
            404
        );

        return;
    }

    $date =
        trim((string)$date);

    if ($date === '') {

        errorResponse(
            'Upload date is required',
            400
        );

        return;
    }

    $deletedCount =
        $this->s->deleteVehiclesByUploadDate(
            $date,
            $agencyId
        );

    jsonResponse([
        'success' => true,
        'deletedCount' => $deletedCount,
        'date' => $date,
        'message' =>
            $deletedCount .
            ' vehicle(s) deleted successfully'
    ]);
}


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

public function upload()
{
    $file = $_FILES['file'] ?? [];

    $agencyId = trim(
        (string) queryParam('agencyId', '')
    );

    if ($agencyId === '') {
        errorResponse('Agency ID is required.', 400);
    }

    if (
        empty($file['tmp_name']) ||
        ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK
    ) {
        errorResponse('No Excel file was uploaded.', 400);
    }

    try {
        $result = $this->excel->upload(
            $file,
            $agencyId
        );

        // Return all Excel validation errors to the frontend.
        if (($result['success'] ?? true) === false) {
            jsonResponse([
                'success' => false,
                'message' => $result['message']
                    ?? 'Excel upload failed.',
                'totalRows' => $result['totalRows'] ?? 0,
                'inserted' => $result['inserted'] ?? 0,
                'updated' => $result['updated'] ?? 0,
                'failed' => $result['failed'] ?? 0,
                'errors' => $result['errors'] ?? []
            ], 400);
        }

        jsonResponse([
            'success' => true,
            'message' => 'Excel uploaded successfully.',
            'totalRows' => $result['totalRows'] ?? 0,
            'inserted' => $result['inserted'] ?? 0,
            'updated' => $result['updated'] ?? 0,
            'failed' => $result['failed'] ?? 0,
            'errors' => $result['errors'] ?? []
        ]);

    } catch (Throwable $e) {
        error_log('Vehicle Excel upload error: ' . $e->getMessage());

        errorResponse(
            'Excel upload failed: ' . $e->getMessage(),
            400
        );
    }
}


 public function assign($k){$ok=$this->s->assignVehicleToYard($k,(int)queryParam('yardId',0));if(!$ok)errorResponse('Vehicle not found',404);jsonResponse('Vehicle assigned to yard successfully');}
 public function yard($id){jsonResponse($this->s->getVehiclesByYard((int)$id,(string)queryParam('agencyId','')));}
 public function removeYard($k){$ok=$this->s->removeVehicleFromYard($k);if(!$ok)errorResponse('Vehicle not found',404);jsonResponse('Vehicle removed from yard successfully');}
}
