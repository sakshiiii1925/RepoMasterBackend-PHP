<?php

class RepoImageService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }
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
     * Only these statuses require repo images.
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
     * ---------------------------------------------------------
     * 1. Find user
     * ---------------------------------------------------------
     *
     * We use the existing user_email sent by Android.
     * This avoids changing your current Retrofit API.
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

    if (strcasecmp((string)$user['status'], 'ACTIVE') !== 0) {
        throw new Exception('User is not active.');
    }

    $userId = (int)$user['id'];

    /*
     * Use database user name when available.
     */
    if ($userName === '') {
        $userName = (string)$user['full_name'];
    }

    $userAgencyId = (string)($user['agency_id'] ?? '');

    /*
     * ---------------------------------------------------------
     * 2. Find vehicle
     * ---------------------------------------------------------
     */

    $vehicleStmt = $this->pdo->prepare("
        SELECT *
        FROM vehicle
        WHERE UPPER(
            REPLACE(
                REPLACE(
                    REPLACE(
                        REPLACE(vehicle_number, '-', ''),
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

    $vehicleStmt->execute([$vehicleNumber]);

    $vehicle = $vehicleStmt->fetch(PDO::FETCH_ASSOC);

    if (!$vehicle) {
        throw new Exception('Vehicle not found.');
    }

    $vehicleAgencyId = (string)($vehicle['agency_id'] ?? '');

    /*
     * ---------------------------------------------------------
     * 3. Validate agency
     * ---------------------------------------------------------
     */

    if (
        $userAgencyId !== '' &&
        $vehicleAgencyId !== '' &&
        $userAgencyId !== $vehicleAgencyId
    ) {
        throw new Exception(
            'You are not authorized to upload images for this vehicle.'
        );
    }

    /*
     * ---------------------------------------------------------
     * 4. Validate all 7 images
     * ---------------------------------------------------------
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
            ($files[$imageName]['error'] ?? UPLOAD_ERR_NO_FILE)
                !== UPLOAD_ERR_OK
        ) {
            throw new Exception(
                "Missing image: {$imageName}"
            );
        }
    }

    /*
     * ---------------------------------------------------------
     * 5. Create vehicle upload directory
     * ---------------------------------------------------------
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
     * ---------------------------------------------------------
     * 6. Save images
     * ---------------------------------------------------------
     */

    $savedImages = [];

    foreach ($requiredImages as $imageName) {

        $file = $files[$imageName];

        $originalName =
            basename(
                (string)($file['name'] ?? '')
            );

        $extension =
            strtolower(
                pathinfo(
                    $originalName,
                    PATHINFO_EXTENSION
                )
            );

        /*
         * Allow normal image extensions only.
         */
        $allowedExtensions = [
            'jpg',
            'jpeg',
            'png',
            'webp'
        ];

        if (
            !in_array(
                $extension,
                $allowedExtensions,
                true
            )
        ) {
            throw new Exception(
                "Invalid image type for {$imageName}."
            );
        }

        /*
         * Generate a unique filename.
         */
        $filename =
            $imageName .
            '_' .
            date('Ymd_His') .
            '_' .
            bin2hex(random_bytes(4)) .
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

        /*
         * Relative path returned to application.
         */
        $relativePath =
            'uploads/vehicles/' .
            $safeVehicleNumber .
            '/' .
            $filename;

        $savedImages[$imageName] = $relativePath;
    }

    /*
     * ---------------------------------------------------------
     * 7. Save image information
     * ---------------------------------------------------------
     *
     * Keep your existing vehicle_repo_images structure.
     *
     * If your table uses different column names, keep those
     * column names from your current INSERT/UPDATE query.
     */

    $inventory1 =
        $savedImages['inventory_image_1'];

    $inventory2 =
        $savedImages['inventory_image_2'];

    $vehicle1 =
        $savedImages['vehicle_image_1'];

    $vehicle2 =
        $savedImages['vehicle_image_2'];

    $vehicle3 =
        $savedImages['vehicle_image_3'];

    $vehicle4 =
        $savedImages['vehicle_image_4'];

    $vehicle5 =
        $savedImages['vehicle_image_5'];

    /*
     * Use your existing INSERT/UPDATE query here.
     *
     * Example structure:
     */
    $imageStmt = $this->pdo->prepare("
    INSERT INTO vehicle_repo_images
    (
        vehicle_number,
        user_id,
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
    ':vehicle_number' => $vehicleNumber,
    ':user_id' => $userId,
    ':status' => $status,
    ':user_name' => $userName,
    ':user_email' => $userEmail,
    ':inventory_image_1' => $inventory1,
    ':inventory_image_2' => $inventory2,
    ':vehicle_image_1' => $vehicle1,
    ':vehicle_image_2' => $vehicle2,
    ':vehicle_image_3' => $vehicle3,
    ':vehicle_image_4' => $vehicle4,
    ':vehicle_image_5' => $vehicle5
]);

    /*
     * ---------------------------------------------------------
     * 8. IMPORTANT:
     * Update vehicle status AND action information
     * ---------------------------------------------------------
     */

    if (strcasecmp($status, 'repo mark') === 0) {

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

    elseif (strcasecmp($status, 'Parked') === 0) {

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
     * ---------------------------------------------------------
     * 9. Return result
     * ---------------------------------------------------------
     */

    return [
        'success' => true,
        'message' => 'Images uploaded successfully.',
        'vehicleNumber' => $vehicleNumber,
        'status' => $status,
        'userId' => $userId,
        'userName' => $userName,
        'userEmail' => $userEmail,
        'images' => $savedImages
    ];
}


    /*
|--------------------------------------------------------------------------
| Get all uploaded image records
|--------------------------------------------------------------------------
*/

public function getUploadedImages(): array
{
    $sql = "
        SELECT
            id,
            vehicle_number,
            user_id,
            user_name,
            user_email,
            status,
            created_at,
            updated_at,
            uploaded_at
        FROM vehicle_repo_images
        ORDER BY created_at DESC
    ";

    $stmt = $this->pdo->prepare($sql);

    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


/*
|--------------------------------------------------------------------------
| Get uploaded images by ID
|--------------------------------------------------------------------------
*/

public function getUploadedImageById(
    int $id
): ?array {

    $sql = "
        SELECT
            id,
            vehicle_number,
            user_id,
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

        LIMIT 1
    ";

    $stmt = $this->pdo->prepare($sql);

    $stmt->execute([
        ':id' => $id
    ]);

    $result =
        $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$result) {
        return null;
    }

    return $result;
}


    /*
    |--------------------------------------------------------------------------
    | Upload single image
    |--------------------------------------------------------------------------
    */

    private function uploadImage(
        array $file,
        string $directory,
        string $prefix
    ): string {

        /*
        |--------------------------------------------------------------------------
        | File error
        |--------------------------------------------------------------------------
        */

        if (
            $file['error']
            !== UPLOAD_ERR_OK
        ) {

            throw new Exception(
                'Image upload failed'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Maximum size = 10 MB
        |--------------------------------------------------------------------------
        */

        if (
            $file['size'] >
            10 * 1024 * 1024
        ) {

            throw new Exception(
                $prefix .
                ' image exceeds 10 MB'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | MIME type
        |--------------------------------------------------------------------------
        */

        $finfo =
            new finfo(
                FILEINFO_MIME_TYPE
            );


        $mimeType =
            $finfo->file(
                $file['tmp_name']
            );


        $allowedTypes = [

            'image/jpeg' =>
                'jpg',

            'image/png' =>
                'png',

            'image/webp' =>
                'webp'
        ];


        if (
            !isset(
                $allowedTypes[$mimeType]
            )
        ) {

            throw new Exception(
                'Invalid image type for ' .
                $prefix
            );
        }


        $extension =
            $allowedTypes[$mimeType];


        /*
        |--------------------------------------------------------------------------
        | Filename
        |--------------------------------------------------------------------------
        */

        $fileName =
            $prefix .
            '_' .
            date('Ymd_His') .
            '_' .
            bin2hex(
                random_bytes(5)
            ) .
            '.' .
            $extension;


        $destination =
            $directory .
            $fileName;


        /*
        |--------------------------------------------------------------------------
        | Move image
        |--------------------------------------------------------------------------
        */

        if (
            !move_uploaded_file(
                $file['tmp_name'],
                $destination
            )
        ) {

            throw new Exception(
                'Unable to save ' .
                $prefix .
                ' image'
            );
        }


        return $fileName;
    }
    /*
|--------------------------------------------------------------------------
| Admin: Get uploaded image list
|--------------------------------------------------------------------------
*/

public function getUploadedImageList(): array
{
    $sql = "
        SELECT
            id,
            vehicle_number,
            user_id,
            user_name,
            user_email,
            status,
            created_at,
            updated_at,
            uploaded_at
        FROM vehicle_repo_images
        ORDER BY created_at DESC
    ";

    $stmt = $this->pdo->prepare($sql);

    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


/*
|--------------------------------------------------------------------------
| Admin: Get uploaded images by ID
|--------------------------------------------------------------------------
*/

public function getUploadedImagesById(
    int $id
): ?array {

    $sql = "
        SELECT
            id,
            vehicle_number,
            user_id,
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

        LIMIT 1
    ";

    $stmt = $this->pdo->prepare($sql);

    $stmt->execute([
        ':id' => $id
    ]);

    $result =
        $stmt->fetch(PDO::FETCH_ASSOC);

    return $result ?: null;
}
/*
|--------------------------------------------------------------------------
| Admin: Delete uploaded image record
|--------------------------------------------------------------------------
*/

public function deleteUploadedImages(
    int $id
): bool {

    /*
    |--------------------------------------------------------------------------
    | Get image paths first
    |--------------------------------------------------------------------------
    */

    $sql = "
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
        LIMIT 1
    ";

    $stmt = $this->pdo->prepare($sql);

    $stmt->execute([
        ':id' => $id
    ]);

    $record = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$record) {
        return false;
    }


    /*
    |--------------------------------------------------------------------------
    | Delete database record
    |--------------------------------------------------------------------------
    */

    $delete = $this->pdo->prepare("
        DELETE FROM vehicle_repo_images
        WHERE id = :id
    ");

    $delete->execute([
        ':id' => $id
    ]);


    /*
    |--------------------------------------------------------------------------
    | Delete physical image files
    |--------------------------------------------------------------------------
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

        $relativePath = $record[$column] ?? '';

        if ($relativePath === '') {
            continue;
        }


        /*
        | Path stored in DB:
        |
        | uploads/vehicles/MH12CD6666/image.jpg
        |
        */

        $filePath =
            dirname(__DIR__) .
            '/' .
            $relativePath;


        if (file_exists($filePath)) {

            unlink($filePath);
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Remove empty vehicle upload directory
    |--------------------------------------------------------------------------
    */

    return true;
}
}