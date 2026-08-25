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

        /*
        |--------------------------------------------------------------------------
        | Validate status
        |--------------------------------------------------------------------------
        */

        $allowedStatuses = [
            'repo mark',
            'Parked'
        ];

        if (!in_array($status, $allowedStatuses, true)) {

            throw new Exception(
                'Invalid status. Only repo mark or Parked is allowed.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Check vehicle
        |--------------------------------------------------------------------------
        */

        $vehicleStmt = $this->pdo->prepare("
            SELECT vehicle_number
            FROM vehicle
            WHERE vehicle_number = :vehicle_number
            LIMIT 1
        ");

        $vehicleStmt->execute([
            ':vehicle_number' => $vehicleNumber
        ]);

        $vehicle = $vehicleStmt->fetch(PDO::FETCH_ASSOC);

        if (!$vehicle) {

            throw new Exception(
                'Vehicle not found'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Required images
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
                $files[$imageName]['error']
                    !== UPLOAD_ERR_OK
            ) {

                throw new Exception(
                    $imageName . ' is required'
                );
            }
        }


        /*
        |--------------------------------------------------------------------------
        | Upload directory
        |--------------------------------------------------------------------------
        */

        $safeVehicleNumber = preg_replace(
            '/[^a-zA-Z0-9_-]/',
            '_',
            $vehicleNumber
        );


        $uploadDirectory =
            dirname(__DIR__) .
            '/uploads/vehicles/' .
            $safeVehicleNumber .
            '/';


        if (!is_dir($uploadDirectory)) {

            if (!mkdir(
                $uploadDirectory,
                0777,
                true
            )) {

                throw new Exception(
                    'Unable to create upload directory'
                );
            }
        }


        /*
        |--------------------------------------------------------------------------
        | Upload images
        |--------------------------------------------------------------------------
        */

        $inventoryImage1 =
            $this->uploadImage(
                $files['inventory_image_1'],
                $uploadDirectory,
                'inventory_1'
            );


        $inventoryImage2 =
            $this->uploadImage(
                $files['inventory_image_2'],
                $uploadDirectory,
                'inventory_2'
            );


        $vehicleImage1 =
            $this->uploadImage(
                $files['vehicle_image_1'],
                $uploadDirectory,
                'vehicle_1'
            );


        $vehicleImage2 =
            $this->uploadImage(
                $files['vehicle_image_2'],
                $uploadDirectory,
                'vehicle_2'
            );


        $vehicleImage3 =
            $this->uploadImage(
                $files['vehicle_image_3'],
                $uploadDirectory,
                'vehicle_3'
            );


        $vehicleImage4 =
            $this->uploadImage(
                $files['vehicle_image_4'],
                $uploadDirectory,
                'vehicle_4'
            );


        $vehicleImage5 =
            $this->uploadImage(
                $files['vehicle_image_5'],
                $uploadDirectory,
                'vehicle_5'
            );


        /*
        |--------------------------------------------------------------------------
        | Relative paths
        |--------------------------------------------------------------------------
        */

        $basePath =
            'uploads/vehicles/' .
            $safeVehicleNumber .
            '/';


        $inventoryPath1 =
            $basePath . $inventoryImage1;

        $inventoryPath2 =
            $basePath . $inventoryImage2;

        $vehiclePath1 =
            $basePath . $vehicleImage1;

        $vehiclePath2 =
            $basePath . $vehicleImage2;

        $vehiclePath3 =
            $basePath . $vehicleImage3;

        $vehiclePath4 =
            $basePath . $vehicleImage4;

        $vehiclePath5 =
            $basePath . $vehicleImage5;


        /*
        |--------------------------------------------------------------------------
        | Transaction
        |--------------------------------------------------------------------------
        */

        $this->pdo->beginTransaction();

        try {

            /*
            |--------------------------------------------------------------------------
            | Save image information
            |--------------------------------------------------------------------------
            */

            $sql = "
                INSERT INTO vehicle_repo_images (

                    vehicle_number,
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

                VALUES (

                    :vehicle_number,
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

                ON DUPLICATE KEY UPDATE

                    status =
                        VALUES(status),

                    inventory_image_1 =
                        VALUES(inventory_image_1),

                    inventory_image_2 =
                        VALUES(inventory_image_2),

                    vehicle_image_1 =
                        VALUES(vehicle_image_1),

                    vehicle_image_2 =
                        VALUES(vehicle_image_2),

                    vehicle_image_3 =
                        VALUES(vehicle_image_3),

                    vehicle_image_4 =
                        VALUES(vehicle_image_4),

                    vehicle_image_5 =
                        VALUES(vehicle_image_5)
            ";


            $stmt =
                $this->pdo->prepare($sql);


            $stmt->execute([

                ':vehicle_number' =>
                    $vehicleNumber,

                ':status' =>
                    $status,
                    ':user_name' =>
    $userName,

':user_email' =>
    $userEmail,

                ':inventory_image_1' =>
                    $inventoryPath1,

                ':inventory_image_2' =>
                    $inventoryPath2,

                ':vehicle_image_1' =>
                    $vehiclePath1,

                ':vehicle_image_2' =>
                    $vehiclePath2,

                ':vehicle_image_3' =>
                    $vehiclePath3,

                ':vehicle_image_4' =>
                    $vehiclePath4,

                ':vehicle_image_5' =>
                    $vehiclePath5
            ]);


            /*
            |--------------------------------------------------------------------------
            | Update vehicle status
            |--------------------------------------------------------------------------
            */

            $update =
                $this->pdo->prepare("
                    UPDATE vehicle

                    SET repo_status =
                        :status

                    WHERE vehicle_number =
                        :vehicle_number
                ");


            $update->execute([

                ':status' =>
                    $status,

                ':vehicle_number' =>
                    $vehicleNumber
            ]);


            /*
            |--------------------------------------------------------------------------
            | Commit
            |--------------------------------------------------------------------------
            */

            $this->pdo->commit();


        } catch (Throwable $e) {

            if (
                $this->pdo->inTransaction()
            ) {

                $this->pdo->rollBack();
            }

            throw $e;
        }


        return [

            'success' => true,

            'message' =>
                'Images uploaded and vehicle status updated successfully',

            'vehicle_number' =>
                $vehicleNumber,

            'status' =>
                $status,

            'images' => [

                'inventory_image_1' =>
                    $inventoryPath1,

                'inventory_image_2' =>
                    $inventoryPath2,

                'vehicle_image_1' =>
                    $vehiclePath1,

                'vehicle_image_2' =>
                    $vehiclePath2,

                'vehicle_image_3' =>
                    $vehiclePath3,

                'vehicle_image_4' =>
                    $vehiclePath4,

                'vehicle_image_5' =>
                    $vehiclePath5
            ]
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