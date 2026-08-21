<?php
require_once __DIR__ . '/../helpers/mappers.php';
class InvoiceService {
    public function __construct(private PDO $pdo) {}
    public function create(array $i): array { $map=['invoiceNumber'=>'invoice_number','invoiceDate'=>'invoice_date','repoYear'=>'repo_year','repoMonth'=>'repo_month','invoiceBank'=>'invoice_bank','invoiceAddress'=>'invoice_address','loanNumber'=>'loan_number','customerName'=>'customer_name','vehicleNumber'=>'vehicle_number','vehicleType'=>'vehicle_type','vehicleMake'=>'vehicle_make','vehicleModel'=>'vehicle_model','engineNumber'=>'engine_number','chassisNumber'=>'chassis_number','description1'=>'description_1','basic1Amount'=>'basic1_amount','description2'=>'description_2','basic2Amount'=>'basic2_amount','cgst'=>'cgst','sgst'=>'sgst','igst'=>'igst','totalBasic'=>'total_basic','gst'=>'gst','invoiceTotal'=>'invoice_total','remarks'=>'remarks','createdBy'=>'created_by','createdDate'=>'created_date','gstPercent'=>'gst_percent','paymentDate'=>'payment_date','paymentReceived'=>'payment_received','paymentStatus'=>'payment_status','agencyId'=>'agency_id'];$cols=[];$vals=[];$params=[];foreach($map as $json=>$db){$cols[]=$db;$vals[]='?';$params[]=$i[$json]??null;}$s=$this->pdo->prepare('INSERT INTO invoice ('.implode(',',$cols).') VALUES ('.implode(',',$vals).')');$s->execute($params);return $this->get((int)$this->pdo->lastInsertId()); }
    public function get(int $id): array { $s=$this->pdo->prepare('SELECT * FROM invoice WHERE id=?');$s->execute([$id]);$r=$s->fetch();if(!$r)throw new RuntimeException('Invoice not found');return invoiceRow($r); }
    public function list(string $agencyId): array {$s=$this->pdo->prepare('SELECT * FROM invoice WHERE agency_id=? ORDER BY id DESC');$s->execute([$agencyId]);return array_map(fn($r)=>invoiceRow($r),$s->fetchAll());}
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
}
