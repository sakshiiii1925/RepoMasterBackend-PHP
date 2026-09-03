<?php

require_once __DIR__ . '/../helpers/response.php';

class AdminPaymentController
{
    private PDO $pdo;
private AdminPaymentService $paymentService;

public function __construct(
    PDO $pdo,
    AdminPaymentService $paymentService
) {
    $this->pdo = $pdo;
    $this->paymentService = $paymentService;
}
public function createPayment(): void
{
    try {

        $body = requestBody();

        $payment = $this->paymentService->createPayment(
            $body
        );

        successResponse(
            $payment,
            201
        );

    } catch (InvalidArgumentException $e) {

        errorResponse(
            $e->getMessage(),
            400
        );

    } catch (RuntimeException $e) {

        errorResponse(
            $e->getMessage(),
            400
        );
    }
}

    /**
     * Get users for Admin Payment screen.
     *
     * GET:
     * /api/admin/payment/users
     */
    public function users(): void
    {
        $sql = "
            SELECT
                id,
                full_name,
                email,
                mobile,
                address,
                role,
                status
            FROM users
            WHERE LOWER(COALESCE(role, '')) <> 'admin'
            ORDER BY full_name ASC
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();

        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        successResponse($users, 200);
    }

    /**
     * Get vehicles completed by selected user.
     *
     * GET:
     * /api/admin/payment/user-vehicles?user_id=2
     */
   public function userVehicles(): void
{
    $userId = isset($_GET['user_id'])
        ? (int)$_GET['user_id']
        : 0;

    if ($userId <= 0) {
        errorResponse(
            'Valid user_id is required',
            400
        );
        return;
    }

    /*
     * Return ONE ROW PER COMPLETED WORK.
     *
     * If user completed both Repo Mark and Parked
     * for the same vehicle, two rows will be returned.
     */

    $sql = "
        SELECT
            v.repo_year,
            v.repo_month,
            v.loan_number,
            v.vehicle_number,
            v.vehicle_type,
            v.repo_status,
            v.agency_id,
            v.repo_marked_by AS completed_by,
            v.repo_marked_at AS completed_at,
            'Repo Mark' AS work_type
        FROM vehicle v
        WHERE v.repo_marked_by = :repo_user

        UNION ALL

        SELECT
            v.repo_year,
            v.repo_month,
            v.loan_number,
            v.vehicle_number,
            v.vehicle_type,
            v.repo_status,
            v.agency_id,
            v.parked_by AS completed_by,
            v.parked_at AS completed_at,
            'Parked' AS work_type
        FROM vehicle v
        WHERE v.parked_by = :parked_user

        ORDER BY vehicle_number ASC
    ";

    $stmt = $this->pdo->prepare($sql);

    $stmt->execute([
        ':repo_user' => $userId,
        ':parked_user' => $userId
    ]);

    $vehicles = $stmt->fetchAll(PDO::FETCH_ASSOC);

    successResponse($vehicles, 200);
}

   
 public function rates(): void
    {
        $agencyId = trim(
            (string)($_GET['agency_id'] ?? '')
        );

        if ($agencyId === '') {
            errorResponse(
                'agency_id is required',
                400
            );
            return;
        }

        $rates = $this->paymentService->getRates(
            $agencyId
        );

        successResponse($rates, 200);
    }


    /**
     * Create or update payment rate.
     *
     * POST:
     * /api/admin/payment/rates
     */
    public function saveRate(): void
    {
        $body = requestBody();

        $agencyId = trim(
            (string)($body['agency_id'] ?? '')
        );

        $vehicleType = trim(
            (string)($body['vehicle_type'] ?? '')
        );

        $repoMarkRate = (float)(
            $body['repo_mark_rate'] ?? 0
        );

        $parkedRate = (float)(
            $body['parked_rate'] ?? 0
        );

        if ($agencyId === '') {
            errorResponse(
                'agency_id is required',
                400
            );
            return;
        }

        if ($vehicleType === '') {
            errorResponse(
                'vehicle_type is required',
                400
            );
            return;
        }

        $result = $this->paymentService->saveRate(
            $agencyId,
            $vehicleType,
            $repoMarkRate,
            $parkedRate
        );

        successResponse($result, 200);
    }


    /**
     * Delete payment rate.
     *
     * DELETE:
     * /api/admin/payment/rates/{id}?agency_id=1
     */
    public function deleteRate(int $id): void
    {
        $agencyId = trim(
            (string)($_GET['agency_id'] ?? '')
        );

        if ($id <= 0) {
            errorResponse(
                'Valid rate id is required',
                400
            );
            return;
        }

        if ($agencyId === '') {
            errorResponse(
                'agency_id is required',
                400
            );
            return;
        }

        $deleted = $this->paymentService->deleteRate(
            $id,
            $agencyId
        );

        if (!$deleted) {
            errorResponse(
                'Payment rate not found',
                404
            );
            return;
        }

        successResponse(
            'Payment rate deleted successfully',
            200
        );
    }
/**
 * Calculate payment for selected vehicle.
 *
 * GET:
 * /api/admin/payment/calculate
 */
public function calculate(): void
{
    $userId = isset($_GET['user_id'])
        ? (int)$_GET['user_id']
        : 0;

    $repoYear = trim(
        (string)($_GET['repo_year'] ?? '')
    );

    $repoMonth = trim(
        (string)($_GET['repo_month'] ?? '')
    );

    $loanNumber = trim(
        (string)($_GET['loan_number'] ?? '')
    );

    $workType = trim(
        (string)($_GET['work_type'] ?? '')
    );

    if ($userId <= 0) {
        errorResponse(
            'Valid user_id is required',
            400
        );
        return;
    }

    if ($repoYear === '') {
        errorResponse(
            'repo_year is required',
            400
        );
        return;
    }

    if ($repoMonth === '') {
        errorResponse(
            'repo_month is required',
            400
        );
        return;
    }

    if ($loanNumber === '') {
        errorResponse(
            'loan_number is required',
            400
        );
        return;
    }

    if ($workType === '') {
        errorResponse(
            'work_type is required',
            400
        );
        return;
    }

    try {

        $result =
            $this->paymentService
                ->calculateVehiclePayment(
                    $userId,
                    $repoYear,
                    $repoMonth,
                    $loanNumber,
                    $workType
                );

        successResponse(
            $result,
            200
        );

    } catch (InvalidArgumentException $e) {

        errorResponse(
            $e->getMessage(),
            400
        );

    } catch (RuntimeException $e) {

        errorResponse(
            $e->getMessage(),
            400
        );
    }
}


  
public function summary(): void
{
    $userId = isset($_GET['user_id'])
        ? (int)$_GET['user_id']
        : 0;

    if ($userId <= 0) {
        errorResponse(
            'Valid user_id is required',
            400
        );
        return;
    }

    $summary =
        $this->paymentService
            ->getUserPaymentSummary($userId);

    successResponse(
        $summary,
        200
    );
}  
public function history(): void
{
    try {

        $userId = isset($_GET['user_id'])
            ? (int) $_GET['user_id']
            : 0;

        if ($userId <= 0) {
            errorResponse(
                'Valid user_id is required',
                400
            );
            return;
        }

        $history =
            $this->paymentService
                ->getUserPaymentHistory($userId);

        successResponse(
            $history,
            200
        );

    } catch (InvalidArgumentException $e) {

        errorResponse(
            $e->getMessage(),
            400
        );

    } catch (RuntimeException $e) {

        errorResponse(
            $e->getMessage(),
            400
        );

    } catch (Throwable $e) {

        errorResponse(
            $e->getMessage(),
            500
        );
    }
}
public function delete(int $id): void
{
    try {

        if ($id <= 0) {
            errorResponse(
                'Valid payment id is required',
                400
            );
            return;
        }

        $result =
            $this->paymentService
                ->deletePayment($id);

        if (($result['success'] ?? false) === true) {

            jsonResponse($result, 200);

        } else {

            jsonResponse($result, 404);
        }

    } catch (Throwable $e) {

        errorResponse(
            $e->getMessage(),
            500
        );
    }
}
 
       
}