<?php

class InvoicePaymentService
{
    public function __construct(
        private PDO $pdo
    ) {}

    // Add payment
    public function create(array $data): array
    {
        $invoiceId = (int)($data['invoiceId'] ?? 0);

        $paymentAmount =
            (float)($data['paymentAmount'] ?? 0);

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

        // Check invoice
        $invoiceStmt =
            $this->pdo->prepare(
                'SELECT *
                 FROM invoice
                 WHERE id = ?'
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

        // Calculate already paid
        $paidStmt =
            $this->pdo->prepare(
                'SELECT COALESCE(
                    SUM(payment_amount), 0
                )
                FROM invoice_payment
                WHERE invoice_id = ?'
            );

        $paidStmt->execute([
            $invoiceId
        ]);

        $alreadyPaid =
            (float)$paidStmt->fetchColumn();

        $invoiceTotal =
            (float)($invoice['invoice_total'] ?? 0);

        $remaining =
            $invoiceTotal - $alreadyPaid;

        if ($paymentAmount > $remaining) {
            throw new InvalidArgumentException(
                'Payment amount cannot exceed remaining amount'
            );
        }

        // Insert payment
        $stmt =
            $this->pdo->prepare(
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

            $data['paymentDate'] ?? null,

            $paymentAmount,

            $data['remarks'] ?? null,

            $data['createdBy'] ?? null,

            date('Y-m-d')
        ]);

        return $this->get(
            (int)$this->pdo->lastInsertId()
        );
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