<?php

class RepoImageService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /*
    |--------------------------------------------------------------------------
    | Upload Images
    |--------------------------------------------------------------------------
    */

    public function uploadImages(
        string $vehicleNumber,
        string $status,
        string $userName,
        string $userEmail,
        array $files
    ): array {

        $vehicleNumber = strtoupper(
            str_replace(
                ['-', '/', '.', ' '],
                '',
                trim($vehicleNumber)
            )
        );

        $status = trim($status);
        $userName = trim($userName);
        $userEmail = trim($userEmail);

        /*
        |--------------------------------------------------------------------------
        | 1. Validate status
        |--------------------------------------------------------------------------
        */

        if (
            strcasecmp($status, 'repo mark') !== 0 &&
            strcasecmp($status, 'Parked') !== 0
        ) {
            throw new Exception(
                'Images can only be uploaded for Repo Mark or Parked status.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | 2. Find user and get agency
        |--------------------------------------------------------------------------
        */

        $userStmt = $this->pdo->prepare("
            SELECT
                id,
                full_name,
                email,
                agency_id,
                status
            FROM users
            WHERE email = ?
            LIMIT 1
        ");

        $userStmt->execute([$userEmail]);

        $user = $userStmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            throw new Exception('User not found.');
        }

        if (
            strcasecmp(
                (string)$user['status'],
                'ACTIVE'
            ) !== 0
        ) {
            throw new Exception('User is not active.');
        }

        $userId = (int)$user['id'];

        if ($userName === '') {
            $userName = (string)$user['full_name'];
        }

        $userAgencyId = trim(
            (string)($user['agency_id'] ?? '')
        );

        if ($userAgencyId === '') {
            throw new Exception(
                'Your account is not assigned to an Agency ID.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | 3. Find vehicle
        |--------------------------------------------------------------------------
        */

        $vehicleStmt = $this->pdo->prepare("
            SELECT *
            FROM vehicle
            WHERE UPPER(
                REPLACE(
                    REPLACE(
                        REPLACE(
                            REPLACE(
                                vehicle_number,
                                '-',
                                ''
                            ),
                            '/',
                            ''
                        ),
                        '.',
                        ''
                    ),
                    ' ',
                    ''
                )
            ) = ?
            LIMIT 1
        ");

        $vehicleStmt->execute([
            $vehicleNumber
        ]);

        $vehicle = $vehicleStmt->fetch(
            PDO::FETCH_ASSOC
        );

        if (!$vehicle) {
            throw new Exception(
                'Vehicle not found.'
            );
        }

        $vehicleAgencyId = trim(
            (string)($vehicle['agency_id'] ?? '')
        );

        /*
        |--------------------------------------------------------------------------
        | 4. STRICT AGENCY VALIDATION
        |--------------------------------------------------------------------------
        |
        | User and vehicle MUST belong to the same agency.
        |
        */

        if (
            $vehicleAgencyId === '' ||
            $userAgencyId !== $vehicleAgencyId
        ) {
            throw new Exception(
                'You are not authorized to upload images for this vehicle.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | 5. Required images
        |--------------------------------------------------------------------------
        */

        $requiredImages = [
            'inventory_image_1',
            'inventory_image_2',
            'vehicle_image_1',
            'vehicle_image_2',
            'vehicle_image_3',
            'vehicle_image_4',
            'vehicle_image_5'
        ];

        foreach ($requiredImages as $imageName) {

            if (
                !isset($files[$imageName]) ||
                !is_array($files[$imageName]) ||
                (
                    $files[$imageName]['error']
                    ?? UPLOAD_ERR_NO_FILE
                ) !== UPLOAD_ERR_OK
            ) {
                throw new Exception(
                    "Missing image: {$imageName}"
                );
            }
        }

        /*
        |--------------------------------------------------------------------------
        | 6. Create upload directory
        |--------------------------------------------------------------------------
        */

        $safeVehicleNumber = preg_replace(
            '/[^A-Z0-9_-]/',
            '',
            $vehicleNumber
        );

        $uploadDirectory =
            __DIR__ .
            '/../uploads/vehicles/' .
            $safeVehicleNumber .
            '/';

        if (!is_dir($uploadDirectory)) {

            if (
                !mkdir(
                    $uploadDirectory,
                    0775,
                    true
                )
            ) {
                throw new Exception(
                    'Unable to create upload directory.'
                );
            }
        }

        /*
        |--------------------------------------------------------------------------
        | 7. Save images
        |--------------------------------------------------------------------------
        */

        $savedImages = [];

        foreach ($requiredImages as $imageName) {

            $file = $files[$imageName];

            /*
            | Maximum size = 10 MB
            */

            if (
                ($file['size'] ?? 0)
                > 10 * 1024 * 1024
            ) {
                throw new Exception(
                    "{$imageName} image exceeds 10 MB."
                );
            }

            /*
            | Check MIME type
            */

            $finfo = new finfo(
                FILEINFO_MIME_TYPE
            );

            $mimeType = $finfo->file(
                $file['tmp_name']
            );

            $allowedTypes = [
                'image/jpeg' => 'jpg',
                'image/png'  => 'png',
                'image/webp' => 'webp'
            ];

            if (
                !isset(
                    $allowedTypes[$mimeType]
                )
            ) {
                throw new Exception(
                    "Invalid image type for {$imageName}."
                );
            }

            $extension =
                $allowedTypes[$mimeType];

            /*
            | Generate unique filename
            */

            $filename =
                $imageName .
                '_' .
                date('Ymd_His') .
                '_' .
                bin2hex(random_bytes(5)) .
                '.' .
                $extension;

            $destination =
                $uploadDirectory .
                $filename;

            if (
                !move_uploaded_file(
                    $file['tmp_name'],
                    $destination
                )
            ) {
                throw new Exception(
                    "Failed to save {$imageName}."
                );
            }

            $savedImages[$imageName] =
                'uploads/vehicles/' .
                $safeVehicleNumber .
                '/' .
                $filename;
        }

        /*
        |--------------------------------------------------------------------------
        | 8. Insert image record
        |--------------------------------------------------------------------------
        */

        $imageStmt = $this->pdo->prepare("
            INSERT INTO vehicle_repo_images
            (
                vehicle_number,
                user_id,
                agency_id,
                status,
                user_name,
                user_email,

                inventory_image_1,
                inventory_image_2,

                vehicle_image_1,
                vehicle_image_2,
                vehicle_image_3,
                vehicle_image_4,
                vehicle_image_5
            )
            VALUES
            (
                :vehicle_number,
                :user_id,
                :agency_id,
                :status,
                :user_name,
                :user_email,

                :inventory_image_1,
                :inventory_image_2,

                :vehicle_image_1,
                :vehicle_image_2,
                :vehicle_image_3,
                :vehicle_image_4,
                :vehicle_image_5
            )
        ");

        $imageStmt->execute([
            ':vehicle_number' =>
                $vehicleNumber,

            ':user_id' =>
                $userId,

            ':agency_id' =>
                $userAgencyId,

            ':status' =>
                $status,

            ':user_name' =>
                $userName,

            ':user_email' =>
                $userEmail,

            ':inventory_image_1' =>
                $savedImages['inventory_image_1'],

            ':inventory_image_2' =>
                $savedImages['inventory_image_2'],

            ':vehicle_image_1' =>
                $savedImages['vehicle_image_1'],

            ':vehicle_image_2' =>
                $savedImages['vehicle_image_2'],

            ':vehicle_image_3' =>
                $savedImages['vehicle_image_3'],

            ':vehicle_image_4' =>
                $savedImages['vehicle_image_4'],

            ':vehicle_image_5' =>
                $savedImages['vehicle_image_5']
        ]);

        /*
        |--------------------------------------------------------------------------
        | 9. Update vehicle status
        |--------------------------------------------------------------------------
        */

        if (
            strcasecmp(
                $status,
                'repo mark'
            ) === 0
        ) {

            $stmt = $this->pdo->prepare("
                UPDATE vehicle
                SET
                    repo_status = ?,
                    repo_marked_by = ?,
                    repo_marked_at = NOW()
                WHERE repo_year = ?
                  AND repo_month = ?
                  AND loan_number = ?
            ");

            $stmt->execute([
                $status,
                $userId,
                $vehicle['repo_year'],
                $vehicle['repo_month'],
                $vehicle['loan_number']
            ]);
        }

        elseif (
            strcasecmp(
                $status,
                'Parked'
            ) === 0
        ) {

            $stmt = $this->pdo->prepare("
                UPDATE vehicle
                SET
                    repo_status = ?,
                    parked_by = ?,
                    parked_at = NOW()
                WHERE repo_year = ?
                  AND repo_month = ?
                  AND loan_number = ?
            ");

            $stmt->execute([
                $status,
                $userId,
                $vehicle['repo_year'],
                $vehicle['repo_month'],
                $vehicle['loan_number']
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | 10. Return result
        |--------------------------------------------------------------------------
        */

        return [
            'success' => true,
            'message' =>
                'Images uploaded successfully.',

            'vehicleNumber' =>
                $vehicleNumber,

            'status' =>
                $status,

            'userId' =>
                $userId,

            'userName' =>
                $userName,

            'userEmail' =>
                $userEmail,

            'agencyId' =>
                $userAgencyId,

            'images' =>
                $savedImages
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | Get uploaded image list by agency
    |--------------------------------------------------------------------------
    |
    | Admin and users of the SAME agency can see these.
    |
    */

    public function getUploadedImageList(
        string $agencyId
    ): array {

        $agencyId = trim($agencyId);

        if ($agencyId === '') {
            throw new Exception(
                'Agency ID is required.'
            );
        }

        $sql = "
            SELECT
                id,
                vehicle_number,
                user_id,
                agency_id,
                user_name,
                user_email,
                status,
                created_at,
                updated_at,
                uploaded_at

            FROM vehicle_repo_images

            WHERE agency_id = :agency_id

            ORDER BY created_at DESC
        ";

        $stmt = $this->pdo->prepare($sql);

        $stmt->execute([
            ':agency_id' => $agencyId
        ]);

        return $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Get uploaded image details by ID + agency
    |--------------------------------------------------------------------------
    */

    public function getUploadedImagesById(
        int $id,
        string $agencyId
    ): ?array {

        $agencyId = trim($agencyId);

        if ($agencyId === '') {
            throw new Exception(
                'Agency ID is required.'
            );
        }

        $sql = "
            SELECT
                id,
                vehicle_number,
                user_id,
                agency_id,
                user_name,
                user_email,
                status,

                inventory_image_1,
                inventory_image_2,

                vehicle_image_1,
                vehicle_image_2,
                vehicle_image_3,
                vehicle_image_4,
                vehicle_image_5,

                created_at,
                updated_at,
                uploaded_at

            FROM vehicle_repo_images

            WHERE id = :id
              AND agency_id = :agency_id

            LIMIT 1
        ";

        $stmt = $this->pdo->prepare($sql);

        $stmt->execute([
            ':id' =>
                $id,

            ':agency_id' =>
                $agencyId
        ]);

        $result =
            $stmt->fetch(
                PDO::FETCH_ASSOC
            );

        return $result ?: null;
    }


    /*
    |--------------------------------------------------------------------------
    | Delete uploaded images by ID + agency
    |--------------------------------------------------------------------------
    */

    public function deleteUploadedImages(
        int $id,
        string $agencyId
    ): bool {

        $agencyId = trim($agencyId);

        if ($agencyId === '') {
            throw new Exception(
                'Agency ID is required.'
            );
        }

        /*
        | Get image paths ONLY from same agency
        */

        $stmt = $this->pdo->prepare("
            SELECT
                inventory_image_1,
                inventory_image_2,
                vehicle_image_1,
                vehicle_image_2,
                vehicle_image_3,
                vehicle_image_4,
                vehicle_image_5

            FROM vehicle_repo_images

            WHERE id = :id
              AND agency_id = :agency_id

            LIMIT 1
        ");

        $stmt->execute([
            ':id' =>
                $id,

            ':agency_id' =>
                $agencyId
        ]);

        $record =
            $stmt->fetch(
                PDO::FETCH_ASSOC
            );

        if (!$record) {
            return false;
        }

        /*
        | Delete database record
        */

        $delete = $this->pdo->prepare("
            DELETE FROM vehicle_repo_images

            WHERE id = :id
              AND agency_id = :agency_id
        ");

        $delete->execute([
            ':id' =>
                $id,

            ':agency_id' =>
                $agencyId
        ]);

        /*
        | Delete physical files
        */

        $imageColumns = [
            'inventory_image_1',
            'inventory_image_2',
            'vehicle_image_1',
            'vehicle_image_2',
            'vehicle_image_3',
            'vehicle_image_4',
            'vehicle_image_5'
        ];

        foreach ($imageColumns as $column) {

            $relativePath =
                trim(
                    (string)(
                        $record[$column]
                        ?? ''
                    )
                );

            if ($relativePath === '') {
                continue;
            }

            $filePath =
                dirname(__DIR__) .
                '/' .
                $relativePath;

            if (
                is_file($filePath)
            ) {
                @unlink($filePath);
            }
        }

        return true;
    }
}