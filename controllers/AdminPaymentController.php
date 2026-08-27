<?php

require_once __DIR__ . '/../helpers/response.php';

class AdminPaymentController
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
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
        ? (int) $_GET['user_id']
        : 0;

    if ($userId <= 0) {
        errorResponse('Valid user_id is required', 400);
        return;
    }

    $sql = "
        SELECT
            v.repo_year,
            v.repo_month,
            v.loan_number,
            v.vehicle_number,
            v.vehicle_type,
            v.repo_status,

            v.repo_marked_by,
            v.repo_marked_at,

            v.parked_by,
            v.parked_at,

            v.total_charges

        FROM vehicle v

        WHERE
            v.repo_marked_by = :user_id_repo
            OR
            v.parked_by = :user_id_parked

        ORDER BY v.vehicle_number ASC
    ";

    $stmt = $this->pdo->prepare($sql);

    $stmt->execute([
        ':user_id_repo' => $userId,
        ':user_id_parked' => $userId
    ]);

    $vehicles = $stmt->fetchAll(PDO::FETCH_ASSOC);

    successResponse($vehicles, 200);
}
         
       
}