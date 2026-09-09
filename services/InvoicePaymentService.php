<?php

class InvoicePaymentService
{
    public function __construct(
        private PDO $pdo
    ) {}

    // Add payment
   public function create(array $data): array
{
    $invoiceId =
        (int)($data['invoiceId'] ?? 0);

    $paymentAmount =
        round(
            (float)($data['paymentAmount'] ?? 0),
            2
        );

    if ($invoiceId <= 0) {
        throw new InvalidArgumentException(
            'Invalid invoice ID'
        );
    }

    if ($paymentAmount <= 0) {
        throw new InvalidArgumentException(
            'Payment amount must be greater than 0'
        );
    }

    try {

        $this->pdo->beginTransaction();

        /*
         * -----------------------------------------
         * 1. GET INVOICE
         * -----------------------------------------
         */
        $invoiceStmt = $this->pdo->prepare(
            'SELECT
                id,
                invoice_total,
                invoice_date,
                dpd_charge_percent
             FROM invoice
             WHERE id = ?
             FOR UPDATE'
        );

        $invoiceStmt->execute([
            $invoiceId
        ]);

        $invoice =
            $invoiceStmt->fetch(PDO::FETCH_ASSOC);

        if (!$invoice) {
            throw new RuntimeException(
                'Invoice not found'
            );
        }


        /*
         * -----------------------------------------
         * 2. INVOICE TOTAL
         * -----------------------------------------
         */
        $invoiceTotal =
            round(
                (float)($invoice['invoice_total'] ?? 0),
                2
            );


        /*
         * -----------------------------------------
         * 3. CALCULATE DPD
         * -----------------------------------------
         */
        $dpd = 0;

        if (!empty($invoice['invoice_date'])) {

            try {

                $invoiceDate =
                    new DateTime(
                        $invoice['invoice_date']
                    );

                $today =
                    new DateTime('today');

                if ($invoiceDate <= $today) {

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
         * 4. DPD PERCENT
         * -----------------------------------------
         */
        $dpdChargePercent =
            round(
                (float)(
                    $invoice['dpd_charge_percent']
                    ?? 0
                ),
                2
            );


        /*
         * -----------------------------------------
         * 5. DPD EXTRA CHARGE
         * -----------------------------------------
         */
        $dpdExtraCharge =
            round(
                $invoiceTotal
                * ($dpdChargePercent / 100)
                * $dpd,
                2
            );


        /*
         * -----------------------------------------
         * 6. TOTAL INCLUDING DPD
         * -----------------------------------------
         */
        $dpdTotalAmount =
            round(
                $invoiceTotal
                + $dpdExtraCharge,
                2
            );


        /*
         * -----------------------------------------
         * 7. GET ALREADY PAID
         * -----------------------------------------
         */
        $paidStmt = $this->pdo->prepare(
            'SELECT COALESCE(
                SUM(payment_amount),
                0
             )
             FROM invoice_payment
             WHERE invoice_id = ?'
        );

        $paidStmt->execute([
            $invoiceId
        ]);

        $alreadyPaid =
            round(
                (float)$paidStmt->fetchColumn(),
                2
            );


        /*
         * -----------------------------------------
         * 8. REMAINING BEFORE PAYMENT
         * -----------------------------------------
         */
        $remaining =
            round(
                max(
                    0,
                    $dpdTotalAmount - $alreadyPaid
                ),
                2
            );


        /*
         * -----------------------------------------
         * 9. PAYMENT VALIDATION
         * -----------------------------------------
         */
        if ($paymentAmount > $remaining) {

            throw new InvalidArgumentException(
                'Payment amount cannot exceed remaining amount. ' .
                'Remaining amount: ₹' .
                number_format(
                    $remaining,
                    2,
                    '.',
                    ''
                )
            );
        }


        /*
         * -----------------------------------------
         * 10. PAYMENT DATE
         * -----------------------------------------
         */
        $paymentDate =
            !empty($data['paymentDate'])
                ? $data['paymentDate']
                : date('Y-m-d');


        /*
         * -----------------------------------------
         * 11. INSERT PAYMENT HISTORY
         * -----------------------------------------
         */
        $stmt = $this->pdo->prepare(
            'INSERT INTO invoice_payment
            (
                invoice_id,
                payment_date,
                payment_amount,
                remarks,
                created_by,
                created_date
            )
            VALUES (?, ?, ?, ?, ?, ?)'
        );

        $stmt->execute([
            $invoiceId,
            $paymentDate,
            $paymentAmount,
            $data['remarks'] ?? null,
            $data['createdBy'] ?? null,
            date('Y-m-d')
        ]);


        /*
         * Save payment ID
         */
        $paymentId =
            (int)$this->pdo->lastInsertId();


        /*
         * -----------------------------------------
         * 12. NEW TOTAL PAID
         * -----------------------------------------
         */
        $newTotalPaid =
            round(
                $alreadyPaid + $paymentAmount,
                2
            );


        /*
         * -----------------------------------------
         * 13. NEW REMAINING
         * -----------------------------------------
         */
        $newRemaining =
            round(
                max(
                    0,
                    $dpdTotalAmount - $newTotalPaid
                ),
                2
            );


        /*
         * -----------------------------------------
         * 14. PAYMENT STATUS
         * -----------------------------------------
         */
        if ($newTotalPaid <= 0) {

            $paymentStatus = 'Pending';

        } elseif ($newRemaining <= 0) {

            $paymentStatus = 'Paid';

        } else {

            $paymentStatus = 'Partial';
        }


        /*
         * -----------------------------------------
         * 15. UPDATE MAIN INVOICE
         * -----------------------------------------
         */
        $updateInvoiceStmt =
            $this->pdo->prepare(
                'UPDATE invoice
                 SET
                    payment_date = ?,
                    payment_received = ?,
                    payment_status = ?
                 WHERE id = ?'
            );

        $updateInvoiceStmt->execute([
            $paymentDate,
            $newTotalPaid,
            $paymentStatus,
            $invoiceId
        ]);


        /*
         * IMPORTANT:
         *
         * Do NOT use rowCount() here.
         *
         * MySQL can return 0 even when UPDATE
         * executed successfully if values are unchanged.
         */


        /*
         * -----------------------------------------
         * 16. COMMIT
         * -----------------------------------------
         */
        $this->pdo->commit();


        /*
         * -----------------------------------------
         * 17. RETURN PAYMENT
         * -----------------------------------------
         */
        return $this->get($paymentId);

    } catch (Throwable $e) {

        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }

        throw $e;
    }
}

    // Get single payment
    public function get(int $id): array
    {
        $stmt =
            $this->pdo->prepare(
                'SELECT *
                 FROM invoice_payment
                 WHERE id = ?'
            );

        $stmt->execute([
            $id
        ]);

        $payment =
            $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$payment) {
            throw new RuntimeException(
                'Payment not found'
            );
        }

        return [
            'id' =>
                (int)$payment['id'],

            'invoiceId' =>
                (int)$payment['invoice_id'],

            'paymentDate' =>
                $payment['payment_date'],

            'paymentAmount' =>
                (float)$payment['payment_amount'],

            'remarks' =>
                $payment['remarks'],

            'createdBy' =>
                $payment['created_by'],

            'createdDate' =>
                $payment['created_date']
        ];
    }


    // Get all payments for invoice
    public function listByInvoice(
        int $invoiceId
    ): array {

        $stmt =
            $this->pdo->prepare(
                'SELECT *
                 FROM invoice_payment
                 WHERE invoice_id = ?
                 ORDER BY payment_date ASC, id ASC'
            );

        $stmt->execute([
            $invoiceId
        ]);

        $rows =
            $stmt->fetchAll(
                PDO::FETCH_ASSOC
            );

        return array_map(
            function ($payment) {

                return [
                    'id' =>
                        (int)$payment['id'],

                    'invoiceId' =>
                        (int)$payment['invoice_id'],

                    'paymentDate' =>
                        $payment['payment_date'],

                    'paymentAmount' =>
                        (float)$payment['payment_amount'],

                    'remarks' =>
                        $payment['remarks'],

                    'createdBy' =>
                        $payment['created_by'],

                    'createdDate' =>
                        $payment['created_date']
                ];

            },
            $rows
        );
    }


    // Delete payment
    public function delete(
        int $id
    ): void {

        $stmt =
            $this->pdo->prepare(
                'DELETE FROM invoice_payment
                 WHERE id = ?'
            );

        $stmt->execute([
            $id
        ]);
    }
}