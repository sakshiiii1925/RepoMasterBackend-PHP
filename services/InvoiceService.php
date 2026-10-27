<?php
require_once __DIR__ . '/../helpers/mappers.php';
class InvoiceService {
    public function __construct(private PDO $pdo) {}
    
public function create(array $i): array
{
    /*
     * -----------------------------------------
     * GENERATE INVOICE NUMBER
     * -----------------------------------------
     *
     * Format:
     *
     * INV-2026-000001
     * INV-2026-000002
     * INV-2026-000003
     *
     * The number is generated on the server,
     * so Android does not need to enter it.
     */

    $year = date('Y');

    $this->pdo->beginTransaction();

    try {

        /*
         * Get the latest invoice number for
         * the current year.
         */
        $stmt = $this->pdo->prepare(
            "SELECT invoice_number
             FROM invoice
             WHERE invoice_number LIKE ?
             ORDER BY id DESC
             LIMIT 1
             FOR UPDATE"
        );

        $stmt->execute([
            "INV-{$year}-%"
        ]);

        $lastInvoiceNumber =
            $stmt->fetchColumn();


        /*
         * Calculate next sequence.
         */
        $nextNumber = 1;

        if ($lastInvoiceNumber) {

            /*
             * Example:
             *
             * INV-2026-000025
             *
             * Extract:
             *
             * 000025
             */

            $parts =
                explode(
                    '-',
                    $lastInvoiceNumber
                );

            if (count($parts) >= 3) {

                $lastSequence =
                    (int)$parts[2];

                $nextNumber =
                    $lastSequence + 1;
            }
        }


        /*
         * Create invoice number.
         *
         * Example:
         *
         * INV-2026-000026
         */
        $invoiceNumber =
            sprintf(
                'INV-%s-%06d',
                $year,
                $nextNumber
            );


        /*
         * -----------------------------------------
         * FIELD MAPPING
         * -----------------------------------------
         */

        $map = [

            'invoiceNumber' =>
                'invoice_number',

            'invoiceDate' =>
                'invoice_date',

            'repoYear' =>
                'repo_year',

            'repoMonth' =>
                'repo_month',

            'invoiceBank' =>
                'invoice_bank',

            'invoiceAddress' =>
                'invoice_address',

            'loanNumber' =>
                'loan_number',

            'customerName' =>
                'customer_name',

            'vehicleNumber' =>
                'vehicle_number',

            'vehicleType' =>
                'vehicle_type',

            'vehicleMake' =>
                'vehicle_make',

            'vehicleModel' =>
                'vehicle_model',

            'engineNumber' =>
                'engine_number',

            'chassisNumber' =>
                'chassis_number',

            'description1' =>
                'description_1',

            'basic1Amount' =>
                'basic1_amount',

            'description2' =>
                'description_2',

            'basic2Amount' =>
                'basic2_amount',

            'cgst' =>
                'cgst',

            'sgst' =>
                'sgst',

            'igst' =>
                'igst',

            'totalBasic' =>
                'total_basic',

            'gst' =>
                'gst',

            'invoiceTotal' =>
                'invoice_total',

            'remarks' =>
                'remarks',

            'createdBy' =>
                'created_by',

            'createdDate' =>
                'created_date',

            'gstPercent' =>
                'gst_percent',

            'dpdChargePercent' =>
                'dpd_charge_percent',

            'paymentDate' =>
                'payment_date',

            'paymentReceived' =>
                'payment_received',

            'paymentStatus' =>
                'payment_status',

            'agencyId' =>
                'agency_id'
        ];


        /*
         * -----------------------------------------
         * PREPARE INSERT
         * -----------------------------------------
         */

        $cols = [];
        $vals = [];
        $params = [];


        foreach ($map as $json => $db) {

            $cols[] = $db;
            $vals[] = '?';

            /*
             * IMPORTANT:
             *
             * Always use our generated
             * invoice number.
             */
            if ($json === 'invoiceNumber') {

                $params[] =
                    $invoiceNumber;

            } else {

                $params[] =
                    $i[$json] ?? null;
            }
        }


        /*
         * -----------------------------------------
         * INSERT
         * -----------------------------------------
         */

        $sql =
            'INSERT INTO invoice (' .
            implode(',', $cols) .
            ') VALUES (' .
            implode(',', $vals) .
            ')';

        $insert =
            $this->pdo->prepare($sql);

        $insert->execute($params);


        /*
         * Get newly created invoice ID.
         */
        $invoiceId =
            (int)$this->pdo->lastInsertId();


        /*
         * Commit transaction.
         */
        $this->pdo->commit();


        /*
         * Return complete invoice.
         */
        return $this->get(
            $invoiceId
        );

    } catch (Throwable $e) {

        if ($this->pdo->inTransaction()) {

            $this->pdo->rollBack();
        }

        throw $e;
    }
}


         
   public function get(int $id): array
{
    $sql = "
        SELECT
            i.*,

            /* Vehicle / finance information */
            v.finance AS vehicle_finance,
            v.agency_name AS vehicle_agency_name,
            v.branch AS vehicle_branch,

            /* Yard information */
            y.yard_name AS yard_name,
            y.yard_address AS yard_address,

            /* Total of all payments */
            COALESCE(
                (
                    SELECT SUM(ip.payment_amount)
                    FROM invoice_payment ip
                    WHERE ip.invoice_id = i.id
                ),
                0
            ) AS total_paid,
(
    SELECT ip.payment_date
    FROM invoice_payment ip
    WHERE ip.invoice_id = i.id
    ORDER BY ip.id DESC
    LIMIT 1
) AS latest_payment_date
        FROM invoice i

        LEFT JOIN vehicle v
            ON v.vehicle_number = i.vehicle_number
            AND v.loan_number = i.loan_number

        LEFT JOIN yard y
            ON y.id = v.yard_id

        WHERE i.id = ?

        LIMIT 1
    ";

    $s = $this->pdo->prepare($sql);
    $s->execute([$id]);

    $r = $s->fetch(PDO::FETCH_ASSOC);

    if (!$r) {
        throw new RuntimeException('Invoice not found');
    }

    /*
     * Convert invoice database fields
     * to Android JSON format.
     */
    $result = invoiceRow($r);


    /*
     * -----------------------------------------
     * FINANCE / BANK
     * -----------------------------------------
     *
     * Priority:
     * 1. Invoice bank
     * 2. Vehicle finance
     * 3. Vehicle agency name
     */
    $invoiceBank = trim(
        (string)($r['invoice_bank'] ?? '')
    );

    if ($invoiceBank === '') {
        $invoiceBank = trim(
            (string)($r['vehicle_finance'] ?? '')
        );
    }

    if ($invoiceBank === '') {
        $invoiceBank = trim(
            (string)($r['vehicle_agency_name'] ?? '')
        );
    }

    $result['invoiceBank'] =
        $invoiceBank !== ''
            ? $invoiceBank
            : null;


    /*
     * -----------------------------------------
     * BRANCH
     * -----------------------------------------
     *
     * Branch is taken from vehicle table.
     */
    $result['branch'] =
        $r['vehicle_branch'] ?? null;


    /*
     * -----------------------------------------
     * YARD DETAILS
     * -----------------------------------------
     */
    $result['yardName'] =
        $r['yard_name'] ?? null;

    $result['yardAddress'] =
        $r['yard_address'] ?? null;


   /*
 * -----------------------------------------
 * PAYMENT DETAILS
 * -----------------------------------------
 */

$invoiceTotal =
    (float)($r['invoice_total'] ?? 0);

$totalPaid =
    (float)($r['total_paid'] ?? 0);

$result['paymentReceived'] =
    $totalPaid;


/*
 * -----------------------------------------
 * DPD
 * -----------------------------------------
 */

$dpd = 0;

if (!empty($r['invoice_date'])) {

    try {

        $invoiceDate = new DateTime(
            $r['invoice_date']
        );

        $today = new DateTime('today');

        if ($invoiceDate > $today) {

            $dpd = 0;

        } else {

            $difference =
                $invoiceDate->diff($today);

            $dpd =
                (int)$difference->days;
        }

    } catch (Exception $e) {

        $dpd = 0;
    }
}


/*
 * -----------------------------------------
 * DPD CHARGE
 * -----------------------------------------
 */

$dpdChargePercent =
    (float)($r['dpd_charge_percent'] ?? 0);

$dpdExtraCharge =
    $invoiceTotal
    * ($dpdChargePercent / 100.0)
    * $dpd;

$dpdTotalAmount =
    $invoiceTotal + $dpdExtraCharge;


/*
 * -----------------------------------------
 * REMAINING AMOUNT
 * -----------------------------------------
 */

$dpdRemainingAmount =
    max(
        0,
        $dpdTotalAmount - $totalPaid
    );


/*
 * -----------------------------------------
 * PAYMENT STATUS
 * -----------------------------------------
 */

$paymentStatus = 'Pending';

if ($totalPaid <= 0) {

    $paymentStatus = 'Pending';

} elseif ($totalPaid < $dpdTotalAmount) {

    $paymentStatus = 'Partial';

} else {

    $paymentStatus = 'Paid';
}


/*
 * -----------------------------------------
 * RESPONSE
 * -----------------------------------------
 */

$result['dpd'] =
    $dpd;

$result['dpdChargePercent'] =
    $dpdChargePercent;

$result['dpdExtraCharge'] =
    $dpdExtraCharge;

$result['dpdTotalAmount'] =
    $dpdTotalAmount;

$result['paymentReceived'] =
    $totalPaid;

$result['remainingAmount'] =
    $dpdRemainingAmount;

$result['paymentDate'] =
    $r['latest_payment_date'] ?? null;

$result['paymentStatus'] =
    $paymentStatus;

    return $result;
}
public function list(string $agencyId): array
{
    $sql = "
        SELECT
            i.*,

            v.finance AS vehicle_finance,
            v.agency_name AS vehicle_agency_name,
            v.branch AS vehicle_branch,

            y.yard_name AS yard_name,
            y.yard_address AS yard_address,

            COALESCE(
                (
                    SELECT SUM(ip.payment_amount)
                    FROM invoice_payment ip
                    WHERE ip.invoice_id = i.id
                ),
                0
            ) AS total_paid,

            (
                SELECT ip.payment_date
                FROM invoice_payment ip
                WHERE ip.invoice_id = i.id
                ORDER BY ip.id DESC
                LIMIT 1
            ) AS latest_payment_date

        FROM invoice i

        LEFT JOIN vehicle v
            ON v.vehicle_number = i.vehicle_number
            AND v.loan_number = i.loan_number

        LEFT JOIN yard y
            ON y.id = v.yard_id

        WHERE i.agency_id = ?

        ORDER BY i.id DESC
    ";

    $s = $this->pdo->prepare($sql);
    $s->execute([$agencyId]);

    $rows = $s->fetchAll(PDO::FETCH_ASSOC);

    return array_map(
        fn($row) => invoiceRow($row),
        $rows
    );
}

      
    public function delete(int $id): void {$s=$this->pdo->prepare('DELETE FROM invoice WHERE id=?');$s->execute([$id]);}
    //updatePayment
    public function updatePayment(
    int $id,
    array $data
): array {

    // Check invoice exists
    $invoice = $this->get($id);

    $paymentReceived =
        isset($data['paymentReceived'])
            ? (float)$data['paymentReceived']
            : 0.0;

    $paymentDate =
        $data['paymentDate'] ?? null;

    $paymentStatus =
        $data['paymentStatus'] ?? 'Pending';


    // Validate payment amount
    if ($paymentReceived < 0) {

        throw new InvalidArgumentException(
            'Payment received cannot be negative'
        );
    }


    // Prevent overpayment
    $invoiceTotal =
        (float)($invoice['invoiceTotal'] ?? 0);

    if ($paymentReceived > $invoiceTotal) {

        throw new InvalidArgumentException(
            'Payment received cannot be greater than invoice total'
        );
    }


    // Update payment
    $stmt = $this->pdo->prepare(
        'UPDATE invoice
         SET payment_date = ?,
             payment_received = ?,
             payment_status = ?
         WHERE id = ?'
    );

    $stmt->execute([
        $paymentDate,
        $paymentReceived,
        $paymentStatus,
        $id
    ]);


    // Return updated invoice
    return $this->get($id);
}

public function searchVehiclesForInvoice(string $keyword): array
{
    $keyword = trim($keyword);

    if ($keyword === '') {
        return [];
    }

    // Remove spaces, hyphens, slashes and dots for vehicle number matching.
    $normalized = preg_replace('/[-\/.\s]/', '', $keyword);

    $sql = "
        SELECT
            v.*,
            y.yard_name,
            y.yard_address
        FROM vehicle v
        LEFT JOIN yard y
            ON y.id = v.yard_id
        WHERE
            (
                REPLACE(REPLACE(REPLACE(REPLACE(
                    UPPER(TRIM(v.vehicle_number)),
                    '-', ''), '/', ''), '.', ''), ' ', '')
                LIKE UPPER(?)
                OR TRIM(v.loan_number) LIKE ?
            )
            AND LOWER(TRIM(v.repo_status)) IN (
                'repo init and parked',
                'parked',
                'parked in godown'
            )
        ORDER BY v.vehicle_number ASC
        LIMIT 20
    ";

    $stmt = $this->pdo->prepare($sql);

    $stmt->execute([
        '%' . $normalized . '%',
        '%' . $keyword . '%'
    ]);

    $vehicles = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return array_map(function ($v) {
        return [
            'id' => [
                'repoYear' => $v['repo_year'] ?? null,
                'repoMonth' => $v['repo_month'] ?? null,
                'loanNumber' => $v['loan_number'] ?? null
            ],

            'vehicleNumber' => $v['vehicle_number'] ?? null,
            'loanNumber' => $v['loan_number'] ?? null,

            'ownerName' =>
                $v['owner_name'] ?? $v['customer_name'] ?? null,

            'customerAddress' =>
                $v['customer_address'] ?? $v['address'] ?? null,

            'vehicleType' => $v['vehicle_type'] ?? null,
            'vehicleMake' => $v['vehicle_make'] ?? null,
            'model' => $v['model'] ?? null,
            'engineNumber' => $v['engine_number'] ?? null,
            'chassisNumber' => $v['chassis_number'] ?? null,

            'finance' => $v['finance'] ?? null,
            'branch' => $v['branch'] ?? null,
            'area' => $v['area'] ?? null,

            'agencyId' => $v['agency_id'] ?? null,
            'repoStatus' => $v['repo_status'] ?? null,

            'yardId' => isset($v['yard_id'])
                ? (int) $v['yard_id']
                : null,

            'yardName' => $v['yard_name'] ?? null,
            'yardAddress' => $v['yard_address'] ?? null
        ];
    }, $vehicles);
}

       
public function updateDpdCharge(
    int $id,
    array $data
): array {

    // Check invoice exists
    $invoice = $this->get($id);

    $dpdChargePercent = isset($data['dpdChargePercent'])
        ? (float)$data['dpdChargePercent']
        : 0.0;

    // Validate percentage
    if ($dpdChargePercent < 0) {
        throw new InvalidArgumentException(
            'DPD charge percentage cannot be negative'
        );
    }

    // Optional safety limit
    if ($dpdChargePercent > 100) {
        throw new InvalidArgumentException(
            'DPD charge percentage cannot be greater than 100'
        );
    }

    // Update database
    $stmt = $this->pdo->prepare(
        'UPDATE invoice
         SET dpd_charge_percent = ?
         WHERE id = ?'
    );

    $stmt->execute([
        $dpdChargePercent,
        $id
    ]);

    // Return updated invoice
    return $this->get($id);
}    
        
}
