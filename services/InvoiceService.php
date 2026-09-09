<?php
require_once __DIR__ . '/../helpers/mappers.php';
class InvoiceService {
    public function __construct(private PDO $pdo) {}
    public function create(
        array $i): array {
         $map=['invoiceNumber'=>'invoice_number',
         'invoiceDate'=>'invoice_date',
         'repoYear'=>'repo_year',
         'repoMonth'=>'repo_month',
         'invoiceBank'=>'invoice_bank',
         'invoiceAddress'=>'invoice_address',
         'loanNumber'=>'loan_number',
         'customerName'=>'customer_name',
         'vehicleNumber'=>'vehicle_number',
         'vehicleType'=>'vehicle_type',
         'vehicleMake'=>'vehicle_make',
         'vehicleModel'=>'vehicle_model',
         'engineNumber'=>'engine_number',
         'chassisNumber'=>'chassis_number',
         'description1'=>'description_1',
         'basic1Amount'=>'basic1_amount',
         'description2'=>'description_2',
         'basic2Amount'=>'basic2_amount',
         'cgst'=>'cgst',
         'sgst'=>'sgst',
         'igst'=>'igst',
         'totalBasic'=>'total_basic',
         'gst'=>'gst',
         'invoiceTotal'=>'invoice_total',
         'remarks'=>'remarks',
         'createdBy'=>'created_by',
         'createdDate'=>'created_date',
         'gstPercent'=>'gst_percent',
         'dpdChargePercent' => 'dpd_charge_percent',
         'paymentDate'=>'payment_date',
         'paymentReceived'=>'payment_received',
         'paymentStatus'=>'payment_status',
         'agencyId'=>'agency_id'];
         $cols=[];
         $vals=[];
         $params=[];
         foreach($map as $json=>$db){$cols[]=$db;
         $vals[]='?';
         $params[]=$i[$json]??null;
         }$s=$this->pdo->prepare('INSERT INTO invoice (
         '.implode(',',$cols).') VALUES ('.implode(',',$vals).')');$s->execute(
            $params);return $this->get(
                (int)$this->pdo->lastInsertId());
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

    $sql = "
        SELECT
            v.*,
            y.yard_name,
            y.yard_address
        FROM vehicle v

        LEFT JOIN yard y
            ON y.id = v.yard_id

        WHERE
            LOWER(TRIM(v.vehicle_number)) LIKE LOWER(?)

            AND LOWER(TRIM(v.repo_status)) IN (
                'repo mark',
                'parked',
                'parked in godown'
            )

        ORDER BY v.vehicle_number ASC

        LIMIT 20
    ";

    $stmt = $this->pdo->prepare($sql);

    $stmt->execute([
        '%' . $keyword . '%'
    ]);

    $vehicles = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return array_map(
        function ($v) {

            return [

                /*
                 * IMPORTANT:
                 * Vehicle.kt expects VehicleId object
                 */
                'id' => [
                    'repoYear' =>
                        $v['repo_year'] ?? null,

                    'repoMonth' =>
                        $v['repo_month'] ?? null,

                    'loanNumber' =>
                        $v['loan_number'] ?? null
                ],

                /*
                 * Vehicle number
                 */
                'vehicleNumber' =>
                    $v['vehicle_number'] ?? null,


                /*
                 * Agency information
                 */
                'agencyMobile' =>
                    $v['agency_mobile'] ?? null,

                'agencyIdGiveByFinance' =>
                    $v['agency_id_give_by_finance'] ?? null,

                'agencyMobile2' =>
                    $v['agency_mobile2'] ?? null,

                'agencyName' =>
                    $v['agency_name'] ?? null,

                'agencyManager' =>
                    $v['agency_manager'] ?? null,


                /*
                 * Vehicle information
                 */
                'chassisNumber' =>
                    $v['chassis_number'] ?? null,

                'color' =>
                    $v['color'] ?? null,

                'manufactureName' =>
                    $v['manufacture_name'] ?? null,

                'vehicleMake' =>
                    $v['vehicle_make'] ?? null,

                'engineNumber' =>
                    $v['engine_number'] ?? null,

                /*
                 * IMPORTANT:
                 * Kotlin expects "model", NOT "vehicleModel"
                 */
                'model' =>
                    $v['model'] ?? null,


                /*
                 * Customer information
                 */
                'ownerMobile' =>
                    $v['owner_mobile'] ?? null,

                /*
                 * IMPORTANT:
                 * Kotlin expects ownerName
                 */
                'ownerName' =>
                    $v['owner_name']
                    ?? $v['customer_name']
                    ?? null,

                /*
                 * IMPORTANT:
                 * Kotlin expects customerAddress
                 */
                'customerAddress' =>
                    $v['customer_address']
                    ?? $v['address']
                    ?? null,

                'customerArea' =>
                    $v['customer_area'] ?? null,


                /*
                 * Location
                 */
                'branch' =>
                    $v['branch'] ?? null,

                'area' =>
                    $v['area'] ?? null,


                /*
                 * Vehicle type
                 */
                'vehicleType' =>
                    $v['vehicle_type'] ?? null,


                /*
                 * Repo status
                 */
                'repoStatus' =>
                    $v['repo_status'] ?? null,


                /*
                 * DPD / allocation
                 */
                'allocationDpd' =>
                    $v['allocation_dpd'] ?? null,


                /*
                 * Executive
                 */
                'executiveName' =>
                    $v['executive_name'] ?? null,


                /*
                 * Area manager
                 */
                'areaManagerName' =>
                    $v['area_manager_name'] ?? null,

                'areaManagerMobileNo' =>
                    $v['area_manager_mobile_no'] ?? null,

                'areaManagerEmailId' =>
                    $v['area_manager_email_id'] ?? null,


                /*
                 * Contact 2
                 */
                'contactName2' =>
                    $v['contact_name2'] ?? null,

                'contactName2Designation' =>
                    $v['contact_name2_designation'] ?? null,

                'contactName2MobileNo' =>
                    $v['contact_name2_mobile_no'] ?? null,


                /*
                 * Region manager
                 */
                'regionManagerName' =>
                    $v['region_manager_name'] ?? null,

                'regionManagerMobileNo' =>
                    $v['region_manager_mobile_no'] ?? null,

                'regionManagerEmailId' =>
                    $v['region_manager_email_id'] ?? null,


                /*
                 * Finance
                 */
                'finance' =>
                    $v['finance'] ?? null,


                /*
                 * Agency ID
                 */
                'agencyId' =>
                    $v['agency_id'] ?? null,


                /*
                 * Reference letter
                 */
                'refLetter' =>
                    $v['ref_letter'] ?? null,


                /*
                 * Charges
                 */
                'totalCharges' =>
                    isset($v['total_charges'])
                        ? (float)$v['total_charges']
                        : null,


                /*
                 * Upload information
                 */
                'uploadBy' =>
                    $v['upload_by'] ?? null,

                'uploadDate' =>
                    $v['upload_date'] ?? null,


                /*
                 * Yard
                 */
                'yardId' =>
                    isset($v['yard_id'])
                        ? (int)$v['yard_id']
                        : null,

                'yardName' =>
                    $v['yard_name'] ?? null,

                'yardAddress' =>
                    $v['yard_address'] ?? null
            ];
        },
        $vehicles
    );
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
