<?php

class RepoImageController
{
    private RepoImageService $service;
    private PDO $pdo;

    public function __construct(
        RepoImageService $service,
        PDO $pdo
    ) {
        $this->service = $service;
        $this->pdo = $pdo;
    }


    /*
    |--------------------------------------------------------------------------
    | Get user's agency from database
    |--------------------------------------------------------------------------
    */

    private function getUserAgencyId(
        string $userEmail
    ): string {

        $userEmail = trim($userEmail);

        if ($userEmail === '') {
            throw new Exception(
                'User email is required.'
            );
        }

        $stmt = $this->pdo->prepare("
            SELECT
                agency_id,
                status
            FROM users
            WHERE email = ?
            LIMIT 1
        ");

        $stmt->execute([
            $userEmail
        ]);

        $user =
            $stmt->fetch(
                PDO::FETCH_ASSOC
            );

        if (!$user) {
            throw new Exception(
                'User not found.'
            );
        }

        if (
            strcasecmp(
                (string)$user['status'],
                'ACTIVE'
            ) !== 0
        ) {
            throw new Exception(
                'User is not active.'
            );
        }

        $agencyId = trim(
            (string)(
                $user['agency_id']
                ?? ''
            )
        );

        if ($agencyId === '') {
            throw new Exception(
                'User is not assigned to an Agency ID.'
            );
        }

        return $agencyId;
    }


    /*
    |--------------------------------------------------------------------------
    | Upload images
    |--------------------------------------------------------------------------
    */

    public function upload(
        string $vehicleNumber
    ): void {

        try {

            $vehicleNumber =
                trim(
                    urldecode(
                        $vehicleNumber
                    )
                );

            if ($vehicleNumber === '') {

                errorResponse(
                    'Vehicle number is required',
                    400
                );

                return;
            }


            $status =
                isset($_POST['status'])
                    ? trim($_POST['status'])
                    : '';


            if ($status === '') {

                errorResponse(
                    'Status is required',
                    400
                );

                return;
            }


            $userName =
                isset($_POST['user_name'])
                    ? trim($_POST['user_name'])
                    : '';


            $userEmail =
                isset($_POST['user_email'])
                    ? trim($_POST['user_email'])
                    : '';


            if ($userEmail === '') {

                errorResponse(
                    'User email is required',
                    400
                );

                return;
            }


            /*
             * userName can be empty.
             *
             * RepoImageService will get the
             * correct name from the database.
             */

            $result =
                $this->service->uploadImages(
                    $vehicleNumber,
                    $status,
                    $userName,
                    $userEmail,
                    $_FILES
                );


            http_response_code(200);

            header(
                'Content-Type: application/json'
            );

            echo json_encode(
                $result
            );

        } catch (Throwable $e) {

            errorResponse(
                $e->getMessage(),
                400
            );
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Admin/User - List uploaded images
    |--------------------------------------------------------------------------
    */

    public function listUploadedImages(): void
    {
        try {

            /*
             * Get email from request.
             *
             * Use the same user_email that
             * your existing Android/web login
             * already has.
             */

            $userEmail =
                isset($_GET['user_email'])
                    ? trim($_GET['user_email'])
                    : '';

            if ($userEmail === '') {

                errorResponse(
                    'User email is required',
                    400
                );

                return;
            }


            /*
             * Get REAL agency from database.
             */

            $agencyId =
                $this->getUserAgencyId(
                    $userEmail
                );


            /*
             * Only same-agency records
             * will be returned.
             */

            $result =
                $this->service
                    ->getUploadedImageList(
                        $agencyId
                    );


            http_response_code(200);

            header(
                'Content-Type: application/json'
            );

            echo json_encode([
                'success' => true,
                'data' => $result
            ]);

        } catch (Throwable $e) {

            errorResponse(
                $e->getMessage(),
                500
            );
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Get uploaded image details
    |--------------------------------------------------------------------------
    */

    public function getUploadedImage(
        int $id
    ): void {

        try {

            if ($id <= 0) {

                errorResponse(
                    'Invalid uploaded image ID',
                    400
                );

                return;
            }


            $userEmail =
                isset($_GET['user_email'])
                    ? trim($_GET['user_email'])
                    : '';


            if ($userEmail === '') {

                errorResponse(
                    'User email is required',
                    400
                );

                return;
            }


            /*
             * Get real agency.
             */

            $agencyId =
                $this->getUserAgencyId(
                    $userEmail
                );


            /*
             * ID + agency together.
             *
             * Therefore Agency 2 cannot access
             * an Agency 1 image even if it knows
             * the image ID.
             */

            $result =
                $this->service
                    ->getUploadedImagesById(
                        $id,
                        $agencyId
                    );


            if (!$result) {

                errorResponse(
                    'Uploaded image record not found',
                    404
                );

                return;
            }


            http_response_code(200);

            header(
                'Content-Type: application/json'
            );

            echo json_encode([
                'success' => true,
                'data' => $result
            ]);

        } catch (Throwable $e) {

            errorResponse(
                $e->getMessage(),
                500
            );
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Delete uploaded images
    |--------------------------------------------------------------------------
    */

    public function deleteUploadedImages(
        int $id
    ): void {

        try {

            if ($id <= 0) {

                errorResponse(
                    'Invalid uploaded image ID',
                    400
                );

                return;
            }


            $userEmail =
                isset($_GET['user_email'])
                    ? trim($_GET['user_email'])
                    : '';


            if ($userEmail === '') {

                errorResponse(
                    'User email is required',
                    400
                );

                return;
            }


            /*
             * Get REAL agency from database.
             */

            $agencyId =
                $this->getUserAgencyId(
                    $userEmail
                );


            /*
             * Delete only if the image belongs
             * to this agency.
             */

            $deleted =
                $this->service
                    ->deleteUploadedImages(
                        $id,
                        $agencyId
                    );


            if (!$deleted) {

                errorResponse(
                    'Uploaded image record not found',
                    404
                );

                return;
            }


            http_response_code(200);

            header(
                'Content-Type: application/json'
            );

            echo json_encode([
                'success' => true,
                'message' =>
                    'Uploaded images deleted successfully'
            ]);

        } catch (Throwable $e) {

            errorResponse(
                $e->getMessage(),
                500
            );
        }
    }
}