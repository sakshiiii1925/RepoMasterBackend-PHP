<?php

class UserPaymentController
{
    private PDO $pdo;
    private AdminPaymentService $paymentService;

    public function __construct(
        PDO $pdo,
        AdminPaymentService $paymentService
    ) {
        $this->pdo = $pdo;
        $this->paymentService =
            $paymentService;
    }


    // =====================================================
    // PAYMENT HISTORY
    // =====================================================

    public function history(): void
    {
        try {

            /*
             * IMPORTANT:
             *
             * Replace this with the user ID obtained
             * from your existing authentication/session
             * mechanism.
             */
            $userId =
                isset($_GET['user_id'])
                    ? (int)$_GET['user_id']
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
                    ->getMyPaymentHistory(
                        $userId
                    );

            successResponse(
                $history,
                200
            );

        } catch (Throwable $e) {

            errorResponse(
                $e->getMessage(),
                500
            );
        }
    }


    // =====================================================
    // PAYMENT SUMMARY
    // =====================================================

    public function summary(): void
    {
        try {

            /*
             * Same authentication note as above.
             */
            $userId =
                isset($_GET['user_id'])
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
                    ->getMyPaymentSummary(
                        $userId
                    );

            successResponse(
                $summary,
                200
            );

        } catch (Throwable $e) {

            errorResponse(
                $e->getMessage(),
                500
            );
        }
    }
}