<?php

class AdminPaymentService
{
    public function __construct(
        private PDO $pdo
    ) {}

    /**
     * Save payment made by admin to a user
     */
    public function createPayment(array $data): array
    {
        $userId = (int)($data['user_id'] ?? 0);
        $repoYear = (string)($data['vehicle_repo_year'] ?? '');
        $repoMonth = (string)($data['vehicle_repo_month'] ?? '');
        $loanNumber = (string)($data['vehicle_loan_number'] ?? '');
        $workType = trim((string)($data['work_type'] ?? ''));
        $amount = (float)($data['amount'] ?? 0);
        $paymentMethod = trim((string)($data['payment_method'] ?? ''));
        $paymentNote = trim((string)($data['payment_note'] ?? ''));
        $createdBy = isset($data['created_by'])
            ? (int)$data['created_by']
            : null;

        if ($userId <= 0) {
            throw new InvalidArgumentException('user_id is required');
        }

        if ($repoYear === '' || $repoMonth === '' || $loanNumber === '') {
            throw new InvalidArgumentException(
                'Vehicle information is required'
            );
        }

        if ($workType === '') {
            throw new InvalidArgumentException('work_type is required');
        }

        if ($amount <= 0) {
            throw new InvalidArgumentException(
                'Payment amount must be greater than 0'
            );
        }

        if ($paymentMethod === '') {
            throw new InvalidArgumentException(
                'payment_method is required'
            );
        }

        /*
         * Verify that the vehicle actually belongs
         * to the selected user.
         */
        $sql = "
            SELECT
                vehicle_number,
                vehicle_type,
                repo_status,
                repo_marked_by,
                parked_by
            FROM vehicle
            WHERE repo_year = ?
              AND repo_month = ?
              AND loan_number = ?
            LIMIT 1
        ";

        $stmt = $this->pdo->prepare($sql);

        $stmt->execute([
            $repoYear,
            $repoMonth,
            $loanNumber
        ]);

        $vehicle = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$vehicle) {
            throw new RuntimeException(
                'Vehicle not found'
            );
        }

        /*
         * Make sure selected user actually completed
         * the selected work.
         */
        $workTypeLower = strtolower($workType);

        if ($workTypeLower === 'repo mark') {

            if ((int)$vehicle['repo_marked_by'] !== $userId) {
                throw new RuntimeException(
                    'This user did not complete Repo Mark for this vehicle'
                );
            }

        } elseif ($workTypeLower === 'parked') {

            if ((int)$vehicle['parked_by'] !== $userId) {
                throw new RuntimeException(
                    'This user did not complete Parking for this vehicle'
                );
            }

        } else {

            throw new InvalidArgumentException(
                'Invalid work_type'
            );
        }

        /*
         * Insert payment
         */
        $sql = "
            INSERT INTO admin_payment (
                user_id,
                vehicle_repo_year,
                vehicle_repo_month,
                vehicle_loan_number,
                vehicle_number,
                vehicle_type,
                work_type,
                amount,
                payment_method,
                payment_note,
                created_by
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ";

        $stmt = $this->pdo->prepare($sql);

        $stmt->execute([
            $userId,
            $repoYear,
            $repoMonth,
            $loanNumber,
            $vehicle['vehicle_number'],
            $vehicle['vehicle_type'],
            $workType,
            $amount,
            $paymentMethod,
            $paymentNote !== '' ? $paymentNote : null,
            $createdBy
        ]);

        $paymentId = (int)$this->pdo->lastInsertId();

        /*
         * Return inserted payment
         */
        $sql = "
            SELECT
                id,
                user_id,
                vehicle_repo_year,
                vehicle_repo_month,
                vehicle_loan_number,
                vehicle_number,
                vehicle_type,
                work_type,
                amount,
                payment_method,
                payment_note,
                paid_at,
                created_by
            FROM admin_payment
            WHERE id = ?
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$paymentId]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}