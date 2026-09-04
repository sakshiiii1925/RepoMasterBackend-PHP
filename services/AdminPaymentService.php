
<?php

class AdminPaymentService
{
    public function __construct(
        private PDO $pdo
    ) {}

    /**
     * Create payment for a user's completed vehicle work.
     */
   public function createPayment(array $data): array
{
    $userId = (int)($data['user_id'] ?? 0);

    $repoYear = trim(
        (string)($data['repo_year'] ?? '')
    );

    $repoMonth = trim(
        (string)($data['repo_month'] ?? '')
    );

    $loanNumber = trim(
        (string)($data['loan_number'] ?? '')
    );

    /*
     * IMPORTANT:
     * Admin does NOT provide amount.
     * Backend calculates amount from payment_rates.
     */

    $paymentMethod = trim(
        (string)($data['payment_method'] ?? '')
    );

    $paymentDate = trim(
        (string)($data['payment_date'] ?? '')
    );

    $remarks = trim(
        (string)($data['remarks'] ?? '')
    );

    /*
     * work_type can be:
     *
     * Repo Mark
     * Parked
     *
     * We accept both work_type and repo_status
     * so the API remains flexible.
     */
    $workType = trim(
        (string)(
            $data['work_type']
            ?? $data['repo_status']
            ?? ''
        )
    );


    // ==========================================
    // VALIDATION
    // ==========================================

    if ($userId <= 0) {
        throw new InvalidArgumentException(
            'user_id is required'
        );
    }

    if (
        $repoYear === '' ||
        $repoMonth === '' ||
        $loanNumber === ''
    ) {
        throw new InvalidArgumentException(
            'Vehicle information is required'
        );
    }

    if ($paymentMethod === '') {
        throw new InvalidArgumentException(
            'payment_method is required'
        );
    }

    if ($workType === '') {
        throw new InvalidArgumentException(
            'work_type is required'
        );
    }


    // ==========================================
    // NORMALIZE WORK TYPE
    // ==========================================

    if (
        strcasecmp($workType, 'repo mark') === 0 ||
        strcasecmp($workType, 'repo_mark') === 0 ||
        strcasecmp($workType, 'repomark') === 0
    ) {

        $workType = 'Repo Mark';

    } elseif (
        strcasecmp($workType, 'parked') === 0 ||
        strcasecmp($workType, 'parked in godown') === 0 ||
        strcasecmp($workType, 'parked_in_godown') === 0
    ) {

        $workType = 'Parked';

    } else {

        throw new InvalidArgumentException(
            'Invalid work_type. Use Repo Mark or Parked'
        );
    }


    // ==========================================
    // FIND VEHICLE
    // ==========================================

    $sql = "
        SELECT
            repo_year,
            repo_month,
            loan_number,
            vehicle_number,
            vehicle_type,
            repo_status,
            agency_id,
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


    // ==========================================
    // VERIFY USER COMPLETED SELECTED WORK
    // ==========================================

    if ($workType === 'Repo Mark') {

        if (
            (int)($vehicle['repo_marked_by'] ?? 0)
            !== $userId
        ) {
            throw new RuntimeException(
                'Selected user did not complete Repo Mark for this vehicle'
            );
        }

    } elseif ($workType === 'Parked') {

        if (
            (int)($vehicle['parked_by'] ?? 0)
            !== $userId
        ) {
            throw new RuntimeException(
                'Selected user did not complete Parked work for this vehicle'
            );
        }
    }


    // ==========================================
    // GET AGENCY
    // ==========================================

    $agencyId = trim(
        (string)($vehicle['agency_id'] ?? '')
    );

    if ($agencyId === '') {
        throw new RuntimeException(
            'Vehicle agency is missing'
        );
    }


    // ==========================================
    // GET VEHICLE TYPE
    // ==========================================

    $vehicleType = trim(
        (string)($vehicle['vehicle_type'] ?? '')
    );

    if ($vehicleType === '') {
        throw new RuntimeException(
            'Vehicle type is missing'
        );
    }


    // ==========================================
    // GET RATE AUTOMATICALLY
    // ==========================================

    $rateStmt = $this->pdo->prepare("
        SELECT
            repo_mark_rate,
            parked_rate
        FROM payment_rates
        WHERE agency_id = ?
          AND LOWER(TRIM(vehicle_type)) =
              LOWER(TRIM(?))
        LIMIT 1
    ");

    $rateStmt->execute([
        $agencyId,
        $vehicleType
    ]);

    $rate = $rateStmt->fetch(PDO::FETCH_ASSOC);

    if (!$rate) {
        throw new RuntimeException(
            'Payment rate not configured for vehicle type: '
            . $vehicleType
            . ' for agency: '
            . $agencyId
        );
    }


    // ==========================================
    // SELECT RATE
    // ==========================================

    if ($workType === 'Repo Mark') {

        $amount = (float)$rate['repo_mark_rate'];

    } else {

        $amount = (float)$rate['parked_rate'];
    }


    if ($amount <= 0) {
        throw new RuntimeException(
            'Payment rate is zero or not configured for '
            . $vehicleType
            . ' - '
            . $workType
        );
    }

// ==========================================
// GET ALREADY PAID AMOUNT
// ==========================================

$paidStmt = $this->pdo->prepare("
    SELECT
        COALESCE(SUM(amount), 0)
    FROM admin_payment
    WHERE user_id = ?
      AND repo_year = ?
      AND repo_month = ?
      AND loan_number = ?
      AND LOWER(TRIM(repo_status)) =
          LOWER(TRIM(?))
");

$paidStmt->execute([
    $userId,
    $vehicle['repo_year'],
    $vehicle['repo_month'],
    $vehicle['loan_number'],
    $workType
]);

$paidAmount =
    (float)$paidStmt->fetchColumn();


// ==========================================
// CALCULATE REMAINING
// ==========================================

$remainingAmount =
    $amount - $paidAmount;

if ($remainingAmount < 0) {
    $remainingAmount = 0;
}


// ==========================================
// CHECK FULLY PAID
// ==========================================

if ($remainingAmount <= 0) {

    throw new RuntimeException(
        'This work has already been fully paid for vehicle '
        . $vehicle['vehicle_number']
    );
}
   

// ==========================================
// VALIDATE PAYMENT AMOUNT
// ==========================================

$paymentAmount = isset($data['payment_amount'])
    ? (float)$data['payment_amount']
    : $remainingAmount;

if ($paymentAmount <= 0) {
    throw new InvalidArgumentException(
        'payment_amount must be greater than 0'
    );
}

if ($paymentAmount > $remainingAmount) {
    throw new InvalidArgumentException(
        'Payment amount cannot be greater than remaining amount: '
        . number_format($remainingAmount, 2)
    );
}


// ==========================================
// INSERT PAYMENT
// ==========================================

if ($paymentDate !== '') {

    $sql = "
        INSERT INTO admin_payment (
            user_id,
            repo_year,
            repo_month,
            loan_number,
            vehicle_number,
            vehicle_type,
            repo_status,
            amount,
            payment_method,
            payment_date,
            remarks
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ";

    $stmt = $this->pdo->prepare($sql);

    $stmt->execute([
        $userId,
        $vehicle['repo_year'],
        $vehicle['repo_month'],
        $vehicle['loan_number'],
        $vehicle['vehicle_number'],
        $vehicleType,
        $workType,
        $paymentAmount,
        $paymentMethod,
        $paymentDate,
        $remarks !== '' ? $remarks : null
    ]);

} else {

    $sql = "
        INSERT INTO admin_payment (
            user_id,
            repo_year,
            repo_month,
            loan_number,
            vehicle_number,
            vehicle_type,
            repo_status,
            amount,
            payment_method,
            payment_date,
            remarks
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)
    ";

    $stmt = $this->pdo->prepare($sql);

    $stmt->execute([
        $userId,
        $vehicle['repo_year'],
        $vehicle['repo_month'],
        $vehicle['loan_number'],
        $vehicle['vehicle_number'],
        $vehicleType,
        $workType,
        $paymentAmount,
        $paymentMethod,
        $remarks !== '' ? $remarks : null
    ]);
}
    
 


    // ==========================================
    // GET INSERTED PAYMENT
    // ==========================================

    $paymentId = (int)$this->pdo->lastInsertId();

    $sql = "
        SELECT
            id,
            user_id,
            repo_year,
            repo_month,
            loan_number,
            vehicle_number,
            vehicle_type,
            repo_status,
            amount,
            payment_method,
            payment_date,
            remarks,
            created_at,
            updated_at
        FROM admin_payment
        WHERE id = ?
        LIMIT 1
    ";

    $stmt = $this->pdo->prepare($sql);

    $stmt->execute([
        $paymentId
    ]);

    $payment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$payment) {
        throw new RuntimeException(
            'Payment created but could not be retrieved'
        );
    }


    // ==========================================
    // GET USER PAYMENT SUMMARY
    // ==========================================

    $summary = $this->getUserPaymentSummary($userId);


    // ==========================================
    // RETURN PAYMENT + SUMMARY
    // ==========================================

    return [
        'payment' => $payment,

        'summary' => $summary
    ];
}
public function getUserPaymentSummary(int $userId): array
{
    if ($userId <= 0) {
        throw new InvalidArgumentException(
            'Valid user_id is required'
        );
    }


    // ==========================================
    // GET ALL COMPLETED VEHICLE WORK
    // ==========================================

    $sql = "
        SELECT
            v.repo_year,
            v.repo_month,
            v.loan_number,
            v.vehicle_number,
            v.vehicle_type,
            v.agency_id,
            v.repo_marked_by,
            v.parked_by
        FROM vehicle v
        WHERE
            v.repo_marked_by = ?
            OR
            v.parked_by = ?
        ORDER BY v.vehicle_number ASC
    ";

    $stmt = $this->pdo->prepare($sql);

    $stmt->execute([
        $userId,
        $userId
    ]);

    $vehicles = $stmt->fetchAll(PDO::FETCH_ASSOC);


    $totalDue = 0.0;
    $completedWork = 0;


    // ==========================================
    // CALCULATE TOTAL DUE
    // ==========================================

    foreach ($vehicles as $vehicle) {

        $agencyId = trim(
            (string)$vehicle['agency_id']
        );

        $vehicleType = trim(
            (string)$vehicle['vehicle_type']
        );

        if (
            $agencyId === '' ||
            $vehicleType === ''
        ) {
            continue;
        }


        // Get current agency rate
        $rateStmt = $this->pdo->prepare("
            SELECT
                repo_mark_rate,
                parked_rate
            FROM payment_rates
            WHERE agency_id = ?
              AND LOWER(TRIM(vehicle_type)) =
                  LOWER(TRIM(?))
            LIMIT 1
        ");

        $rateStmt->execute([
            $agencyId,
            $vehicleType
        ]);

        $rate = $rateStmt->fetch(PDO::FETCH_ASSOC);

        if (!$rate) {
            continue;
        }


        // Repo Mark completed by this user
        if (
            (int)$vehicle['repo_marked_by'] === $userId
        ) {

            $totalDue +=
                (float)$rate['repo_mark_rate'];

            $completedWork++;
        }


        // Parked completed by this user
        if (
            (int)$vehicle['parked_by'] === $userId
        ) {

            $totalDue +=
                (float)$rate['parked_rate'];

            $completedWork++;
        }
    }


    // ==========================================
    // GET TOTAL PAID
    // ==========================================

    $paidStmt = $this->pdo->prepare("
        SELECT
            COALESCE(SUM(amount), 0)
        FROM admin_payment
        WHERE user_id = ?
    ");

    $paidStmt->execute([
        $userId
    ]);

    $totalPaid = (float)$paidStmt->fetchColumn();


    // ==========================================
    // REMAINING
    // ==========================================

    $remaining = $totalDue - $totalPaid;

    /*
     * Never return negative remaining.
     */
    if ($remaining < 0) {
        $remaining = 0;
    }


    return [
        'user_id' =>
            $userId,

        'completed_work' =>
            $completedWork,

        'total_due' =>
            number_format(
                $totalDue,
                2,
                '.',
                ''
            ),

        'total_paid' =>
            number_format(
                $totalPaid,
                2,
                '.',
                ''
            ),

        'remaining' =>
            number_format(
                $remaining,
                2,
                '.',
                ''
            )
    ];
}
       
    /**
     * Get all payment rates for an agency.
     */
    public function getRates(string $agencyId): array
    {
        $sql = "
            SELECT
                id,
                agency_id,
                vehicle_type,
                repo_mark_rate,
                parked_rate,
                created_at,
                updated_at
            FROM payment_rates
            WHERE agency_id = ?
            ORDER BY vehicle_type ASC
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$agencyId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Create or update payment rate.
     */
    public function saveRate(
        string $agencyId,
        string $vehicleType,
        float $repoMarkRate,
        float $parkedRate
    ): array {

        $agencyId = trim($agencyId);
        $vehicleType = trim($vehicleType);

        if ($agencyId === '') {
            throw new InvalidArgumentException(
                'agency_id is required'
            );
        }

        if ($vehicleType === '') {
            throw new InvalidArgumentException(
                'vehicle_type is required'
            );
        }

        if ($repoMarkRate < 0) {
            throw new InvalidArgumentException(
                'repo_mark_rate cannot be negative'
            );
        }

        if ($parkedRate < 0) {
            throw new InvalidArgumentException(
                'parked_rate cannot be negative'
            );
        }

        /*
         * Check whether this agency already has
         * a rate for this vehicle type.
         */
        $check = $this->pdo->prepare("
            SELECT id
            FROM payment_rates
            WHERE agency_id = ?
              AND LOWER(TRIM(vehicle_type)) =
                  LOWER(TRIM(?))
            LIMIT 1
        ");

        $check->execute([
            $agencyId,
            $vehicleType
        ]);

        $existing = $check->fetch(PDO::FETCH_ASSOC);

        if ($existing) {

            // Update existing rate
            $stmt = $this->pdo->prepare("
                UPDATE payment_rates
                SET
                    repo_mark_rate = ?,
                    parked_rate = ?
                WHERE id = ?
            ");

            $stmt->execute([
                $repoMarkRate,
                $parkedRate,
                $existing['id']
            ]);

            $id = (int)$existing['id'];

        } else {

            // Create new rate
            $stmt = $this->pdo->prepare("
                INSERT INTO payment_rates
                (
                    agency_id,
                    vehicle_type,
                    repo_mark_rate,
                    parked_rate
                )
                VALUES (?, ?, ?, ?)
            ");

            $stmt->execute([
                $agencyId,
                $vehicleType,
                $repoMarkRate,
                $parkedRate
            ]);

            $id = (int)$this->pdo->lastInsertId();
        }

        /*
         * Return the saved record.
         */
        $stmt = $this->pdo->prepare("
            SELECT
                id,
                agency_id,
                vehicle_type,
                repo_mark_rate,
                parked_rate,
                created_at,
                updated_at
            FROM payment_rates
            WHERE id = ?
            LIMIT 1
        ");

        $stmt->execute([$id]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$result) {
            throw new RuntimeException(
                'Payment rate could not be loaded'
            );
        }

        return $result;
    }

    /**
     * Delete a payment rate.
     */
    public function deleteRate(int $id, string $agencyId): bool
    {
        $stmt = $this->pdo->prepare("
            DELETE FROM payment_rates
            WHERE id = ?
              AND agency_id = ?
        ");

        $stmt->execute([
            $id,
            $agencyId
        ]);

        return $stmt->rowCount() > 0;
    }
 /**
 * Calculate payment amount for selected vehicle/work.
 *
 * Admin selects:
 * - User
 * - Vehicle
 * - Work Type (Repo Mark / Parked)
 *
 * Backend:
 * - verifies user completed that exact work
 * - gets vehicle type
 * - gets agency
 * - gets configured rate
 * - returns calculated amount
 */
public function calculateVehiclePayment(
    int $userId,
    string $repoYear,
    string $repoMonth,
    string $loanNumber,
    string $workType
): array {

    if ($userId <= 0) {
        throw new InvalidArgumentException(
            'Valid user_id is required'
        );
    }

    $workType = trim($workType);

    // ==========================================
    // NORMALIZE WORK TYPE
    // ==========================================

    if (
        strcasecmp($workType, 'repo mark') === 0 ||
        strcasecmp($workType, 'repo_mark') === 0 ||
        strcasecmp($workType, 'repo marked') === 0 ||
        strcasecmp($workType, 'repo_marked') === 0
    ) {

        $workType = 'Repo Mark';

    } elseif (
        strcasecmp($workType, 'parked') === 0 ||
        strcasecmp($workType, 'parked in godown') === 0 ||
        strcasecmp($workType, 'parked_in_godown') === 0
    ) {

        $workType = 'Parked';

    } else {

        throw new InvalidArgumentException(
            'Invalid work_type. Use Repo Mark or Parked'
        );
    }

    // ==========================================
    // FIND VEHICLE
    // ==========================================

    $stmt = $this->pdo->prepare("
        SELECT
            repo_year,
            repo_month,
            loan_number,
            vehicle_number,
            vehicle_type,
            repo_status,
            agency_id,
            repo_marked_by,
            repo_marked_at,
            parked_by,
            parked_at
        FROM vehicle
        WHERE repo_year = ?
          AND repo_month = ?
          AND loan_number = ?
        LIMIT 1
    ");

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

    // ==========================================
    // VERIFY SELECTED USER COMPLETED WORK
    // ==========================================

    if ($workType === 'Repo Mark') {

        if (
            (int)($vehicle['repo_marked_by'] ?? 0)
            !== $userId
        ) {
            throw new RuntimeException(
                'Selected user did not complete Repo Mark for this vehicle'
            );
        }

        $completedAt =
            $vehicle['repo_marked_at'] ?? null;

    } else {

        if (
            (int)($vehicle['parked_by'] ?? 0)
            !== $userId
        ) {
            throw new RuntimeException(
                'Selected user did not complete Parked work for this vehicle'
            );
        }

        $completedAt =
            $vehicle['parked_at'] ?? null;
    }

    // ==========================================
    // VEHICLE TYPE
    // ==========================================

    $vehicleType = trim(
        (string)($vehicle['vehicle_type'] ?? '')
    );

    if ($vehicleType === '') {
        throw new RuntimeException(
            'Vehicle type is missing'
        );
    }

    // ==========================================
    // AGENCY
    // ==========================================

    $agencyId = trim(
        (string)($vehicle['agency_id'] ?? '')
    );

    if ($agencyId === '') {
        throw new RuntimeException(
            'Vehicle agency is missing'
        );
    }

    // ==========================================
    // GET RATE
    // ==========================================

    $rateStmt = $this->pdo->prepare("
        SELECT
            repo_mark_rate,
            parked_rate
        FROM payment_rates
        WHERE agency_id = ?
          AND LOWER(TRIM(vehicle_type)) =
              LOWER(TRIM(?))
        LIMIT 1
    ");

    $rateStmt->execute([
        $agencyId,
        $vehicleType
    ]);

    $rate = $rateStmt->fetch(PDO::FETCH_ASSOC);

    if (!$rate) {
        throw new RuntimeException(
            'Payment rate not configured for vehicle type: '
            . $vehicleType
            . ' for agency: '
            . $agencyId
        );
    }

    // ==========================================
    // TOTAL AMOUNT FOR THIS WORK
    // ==========================================

    if ($workType === 'Repo Mark') {

        $totalAmount =
            (float)$rate['repo_mark_rate'];

    } else {

        $totalAmount =
            (float)$rate['parked_rate'];
    }

    if ($totalAmount <= 0) {
        throw new RuntimeException(
            'Payment rate is zero or not configured for '
            . $vehicleType
            . ' - '
            . $workType
        );
    }

    // ==========================================
    // GET ALREADY PAID AMOUNT
    // ==========================================

    $paidStmt = $this->pdo->prepare("
        SELECT
            COALESCE(SUM(amount), 0)
        FROM admin_payment
        WHERE user_id = ?
          AND repo_year = ?
          AND repo_month = ?
          AND loan_number = ?
          AND LOWER(TRIM(repo_status)) =
              LOWER(TRIM(?))
    ");

    $paidStmt->execute([
        $userId,
        $repoYear,
        $repoMonth,
        $loanNumber,
        $workType
    ]);

    $paidAmount =
        (float)$paidStmt->fetchColumn();

    // ==========================================
    // REMAINING
    // ==========================================

    $remainingAmount =
        $totalAmount - $paidAmount;

    if ($remainingAmount < 0) {
        $remainingAmount = 0;
    }

    // ==========================================
    // FULLY PAID?
    // ==========================================

    $alreadyPaid =
        $remainingAmount <= 0;

    // ==========================================
    // GET PAYMENT HISTORY
    // ==========================================

    $historyStmt = $this->pdo->prepare("
        SELECT
            id,
            amount,
            payment_method,
            payment_date,
            remarks,
            created_at
        FROM admin_payment
        WHERE user_id = ?
          AND repo_year = ?
          AND repo_month = ?
          AND loan_number = ?
          AND LOWER(TRIM(repo_status)) =
              LOWER(TRIM(?))
        ORDER BY id ASC
    ");

    $historyStmt->execute([
        $userId,
        $repoYear,
        $repoMonth,
        $loanNumber,
        $workType
    ]);

    $paymentHistory =
        $historyStmt->fetchAll(PDO::FETCH_ASSOC);

    // ==========================================
    // RETURN
    // ==========================================

    return [

        'user_id' =>
            $userId,

        'repo_year' =>
            $vehicle['repo_year'],

        'repo_month' =>
            $vehicle['repo_month'],

        'loan_number' =>
            $vehicle['loan_number'],

        'vehicle_number' =>
            $vehicle['vehicle_number'],

        'vehicle_type' =>
            $vehicleType,

        'agency_id' =>
            $agencyId,

        'work_type' =>
            $workType,

        'total_amount' =>
            number_format(
                $totalAmount,
                2,
                '.',
                ''
            ),

        'paid_amount' =>
            number_format(
                $paidAmount,
                2,
                '.',
                ''
            ),

        'remaining_amount' =>
            number_format(
                $remainingAmount,
                2,
                '.',
                ''
            ),

        'already_paid' =>
            $alreadyPaid,

        'completed_at' =>
            $completedAt,

        'payment_history' =>
            $paymentHistory
    ];
}
    
public function getUserPaymentHistory(int $userId): array
{
    $sql = "
        SELECT
            id,
            user_id,
            repo_year,
            repo_month,
            loan_number,
            vehicle_number,
            vehicle_type,
            repo_status,
            amount,
            payment_method,
            payment_date,
            remarks,
            created_at,
            updated_at
        FROM admin_payment
        WHERE user_id = ?
          AND deleted_at IS NULL
        ORDER BY payment_date DESC, id DESC
    ";

    $stmt = $this->pdo->prepare($sql);

    $stmt->execute([
        $userId
    ]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
      
public function getMyPaymentHistory(int $userId): array
{
    $sql = "
        SELECT
            id,
            user_id,
            repo_year,
            repo_month,
            loan_number,
            vehicle_number,
            vehicle_type,
            repo_status,
            amount,
            payment_method,
            payment_date,
            remarks,
            created_at,
            updated_at
        FROM admin_payment
        WHERE user_id = ?
        AND deleted_at IS NULL
        ORDER BY payment_date DESC, id DESC
    ";

    $stmt = $this->pdo->prepare($sql);
    $stmt->execute([$userId]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
public function getMyPaymentSummary(int $userId): array
{
    /*
     * Calculate total amount that this user should receive
     * from completed Repo Mark and Parked work.
     */

    $workSql = "
        SELECT
            v.repo_year,
            v.repo_month,
            v.loan_number,
            v.vehicle_number,
            v.vehicle_type,
            v.agency_id,
            'Repo Mark' AS work_type
        FROM vehicle v
        WHERE v.repo_marked_by = ?

        UNION ALL

        SELECT
            v.repo_year,
            v.repo_month,
            v.loan_number,
            v.vehicle_number,
            v.vehicle_type,
            v.agency_id,
            'Parked' AS work_type
        FROM vehicle v
        WHERE v.parked_by = ?
    ";

    $stmt = $this->pdo->prepare($workSql);
    $stmt->execute([
        $userId,
        $userId
    ]);

    $works = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $totalDue = 0.0;

    foreach ($works as $work) {

        $rateColumn =
            $work['work_type'] === 'Repo Mark'
                ? 'repo_mark_rate'
                : 'parked_rate';

        $rateSql = "
            SELECT {$rateColumn}
            FROM payment_rates
            WHERE agency_id = ?
              AND UPPER(vehicle_type) = UPPER(?)
            LIMIT 1
        ";

        $rateStmt =
            $this->pdo->prepare($rateSql);

        $rateStmt->execute([
            $work['agency_id'],
            $work['vehicle_type']
        ]);

        $rate =
            $rateStmt->fetchColumn();

        if ($rate !== false) {
            $totalDue += (float)$rate;
        }
    }

    /*
     * Total payments already made.
     */

    $paidSql = "
        SELECT COALESCE(SUM(amount), 0)
        FROM admin_payment
        WHERE user_id = ?
    ";

    $paidStmt =
        $this->pdo->prepare($paidSql);

    $paidStmt->execute([
        $userId
    ]);

    $totalPaid =
        (float)$paidStmt->fetchColumn();

    $remaining =
        max(
            0,
            $totalDue - $totalPaid
        );

    return [
        'user_id' =>
            $userId,

        'completed_work' =>
            count($works),

        'total_due' =>
            number_format(
                $totalDue,
                2,
                '.',
                ''
            ),

        'total_paid' =>
            number_format(
                $totalPaid,
                2,
                '.',
                ''
            ),

        'remaining' =>
            number_format(
                $remaining,
                2,
                '.',
                ''
            )
    ];
}
public function deletePayment(int $paymentId): array
{
    if ($paymentId <= 0) {
        return [
            'success' => false,
            'message' => 'Invalid payment id'
        ];
    }

    $stmt = $this->pdo->prepare("
        UPDATE admin_payment
        SET deleted_at = NOW()
        WHERE id = ?
          AND deleted_at IS NULL
    ");

    $stmt->execute([
        $paymentId
    ]);

    if ($stmt->rowCount() === 0) {
        return [
            'success' => false,
            'message' => 'Payment not found'
        ];
    }

    return [
        'success' => true,
        'message' => 'Payment history deleted successfully'
    ];
}

  
public function getUsersByAgency(string $agencyId): array
{
    $agencyId = trim($agencyId);

    if ($agencyId === '') {
        throw new InvalidArgumentException(
            'agency_id is required'
        );
    }

    $stmt = $this->pdo->prepare("
        SELECT
            id,
            full_name,
            email,
            mobile,
            address,
            role,
            status,
            agency_id
        FROM users
        WHERE agency_id = ?
          AND status = 'ACTIVE'
        ORDER BY full_name ASC
    ");

    $stmt->execute([
        $agencyId
    ]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
public function getUserVehicles(
    int $userId,
    string $agencyId
): array {

    if ($userId <= 0) {
        throw new InvalidArgumentException(
            'Valid user_id is required'
        );
    }

    $agencyId = trim($agencyId);

    if ($agencyId === '') {
        throw new InvalidArgumentException(
            'agency_id is required'
        );
    }

    // =====================================================
    // GET VEHICLES WHERE THIS USER COMPLETED WORK
    // =====================================================

    $stmt = $this->pdo->prepare("
        SELECT
            v.repo_year,
            v.repo_month,
            v.loan_number,
            v.vehicle_number,
            v.vehicle_type,
            v.agency_id,
            v.repo_marked_by,
            v.repo_marked_at,
            v.parked_by,
            v.parked_at
        FROM vehicle v
        WHERE v.agency_id = ?
          AND (
                v.repo_marked_by = ?
                OR
                v.parked_by = ?
              )
        ORDER BY v.vehicle_number ASC
    ");

    $stmt->execute([
        $agencyId,
        $userId,
        $userId
    ]);

    $vehicles = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $result = [];

    foreach ($vehicles as $vehicle) {

        $vehicleType = trim(
            (string)($vehicle['vehicle_type'] ?? '')
        );

        if ($vehicleType === '') {
            continue;
        }

        $vehicleAgencyId = trim(
            (string)($vehicle['agency_id'] ?? '')
        );

        if ($vehicleAgencyId === '') {
            continue;
        }


        // =================================================
        // GET PAYMENT RATE
        // =================================================

        $rateStmt = $this->pdo->prepare("
            SELECT
                repo_mark_rate,
                parked_rate
            FROM payment_rates
            WHERE agency_id = ?
              AND LOWER(TRIM(vehicle_type)) =
                  LOWER(TRIM(?))
            LIMIT 1
        ");

        $rateStmt->execute([
            $vehicleAgencyId,
            $vehicleType
        ]);

        $rate = $rateStmt->fetch(PDO::FETCH_ASSOC);

        if (!$rate) {
            continue;
        }


        // =================================================
        // CHECK REPO MARK WORK
        // =================================================

        if (
            (int)($vehicle['repo_marked_by'] ?? 0)
            === $userId
        ) {

            $totalAmount =
                (float)$rate['repo_mark_rate'];

            if ($totalAmount > 0) {

                $paidStmt = $this->pdo->prepare("
                    SELECT
                        COALESCE(SUM(amount), 0)
                    FROM admin_payment
                    WHERE user_id = ?
                      AND repo_year = ?
                      AND repo_month = ?
                      AND loan_number = ?
                      AND LOWER(TRIM(repo_status)) =
                          'repo mark'
                ");

                $paidStmt->execute([
                    $userId,
                    $vehicle['repo_year'],
                    $vehicle['repo_month'],
                    $vehicle['loan_number']
                ]);

                $paidAmount =
                    (float)$paidStmt->fetchColumn();

                $remainingAmount =
                    $totalAmount - $paidAmount;

                // =========================================
                // IMPORTANT
                // Fully paid => DO NOT ADD TO RESULT
                // =========================================

                if ($remainingAmount > 0) {

                    $result[] = [
                        'repo_year' =>
                            $vehicle['repo_year'],

                        'repo_month' =>
                            $vehicle['repo_month'],

                        'loan_number' =>
                            $vehicle['loan_number'],

                        'vehicle_number' =>
                            $vehicle['vehicle_number'],

                        'vehicle_type' =>
                            $vehicleType,

                        'agency_id' =>
                            $vehicleAgencyId,

                        'repo_marked_by' =>
                            $vehicle['repo_marked_by'],

                        'repo_marked_at' =>
                            $vehicle['repo_marked_at'],

                        'parked_by' =>
                            $vehicle['parked_by'],

                        'parked_at' =>
                            $vehicle['parked_at'],

                        'work_type' =>
                            'Repo Mark',

                        'completed_at' =>
                            $vehicle['repo_marked_at'],

                        'total_amount' =>
                            number_format(
                                $totalAmount,
                                2,
                                '.',
                                ''
                            ),

                        'paid_amount' =>
                            number_format(
                                $paidAmount,
                                2,
                                '.',
                                ''
                            ),

                        'remaining_amount' =>
                            number_format(
                                $remainingAmount,
                                2,
                                '.',
                                ''
                            )
                    ];
                }
            }
        }


        // =================================================
        // CHECK PARKED WORK
        // =================================================

        if (
            (int)($vehicle['parked_by'] ?? 0)
            === $userId
        ) {

            $totalAmount =
                (float)$rate['parked_rate'];

            if ($totalAmount > 0) {

                $paidStmt = $this->pdo->prepare("
                    SELECT
                        COALESCE(SUM(amount), 0)
                    FROM admin_payment
                    WHERE user_id = ?
                      AND repo_year = ?
                      AND repo_month = ?
                      AND loan_number = ?
                      AND LOWER(TRIM(repo_status)) =
                          'parked'
                ");

                $paidStmt->execute([
                    $userId,
                    $vehicle['repo_year'],
                    $vehicle['repo_month'],
                    $vehicle['loan_number']
                ]);

                $paidAmount =
                    (float)$paidStmt->fetchColumn();

                $remainingAmount =
                    $totalAmount - $paidAmount;


                // =========================================
                // IMPORTANT
                // Fully paid => DO NOT ADD TO RESULT
                // =========================================

                if ($remainingAmount > 0) {

                    $result[] = [
                        'repo_year' =>
                            $vehicle['repo_year'],

                        'repo_month' =>
                            $vehicle['repo_month'],

                        'loan_number' =>
                            $vehicle['loan_number'],

                        'vehicle_number' =>
                            $vehicle['vehicle_number'],

                        'vehicle_type' =>
                            $vehicleType,

                        'agency_id' =>
                            $vehicleAgencyId,

                        'repo_marked_by' =>
                            $vehicle['repo_marked_by'],

                        'repo_marked_at' =>
                            $vehicle['repo_marked_at'],

                        'parked_by' =>
                            $vehicle['parked_by'],

                        'parked_at' =>
                            $vehicle['parked_at'],

                        'work_type' =>
                            'Parked',

                        'completed_at' =>
                            $vehicle['parked_at'],

                        'total_amount' =>
                            number_format(
                                $totalAmount,
                                2,
                                '.',
                                ''
                            ),

                        'paid_amount' =>
                            number_format(
                                $paidAmount,
                                2,
                                '.',
                                ''
                            ),

                        'remaining_amount' =>
                            number_format(
                                $remainingAmount,
                                2,
                                '.',
                                ''
                            )
                    ];
                }
            }
        }
    }

    return $result;
}
}


