<?php

class RepoImageController
{
    private RepoImageService $service;

    public function __construct(
        RepoImageService $service
    ) {
        $this->service = $service;
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

            $vehicleNumber = trim(
                urldecode($vehicleNumber)
            );

            if ($vehicleNumber === '') {

                errorResponse(
                    'Vehicle number is required',
                    400
                );

                return;
            }


            $status = isset($_POST['status'])
                ? trim($_POST['status'])
                : '';


            if ($status === '') {

                errorResponse(
                    'Status is required',
                    400
                );

                return;
            }


            $userName = isset($_POST['user_name'])
                ? trim($_POST['user_name'])
                : '';


            $userEmail = isset($_POST['user_email'])
                ? trim($_POST['user_email'])
                : '';


            if ($userName === '') {

                errorResponse(
                    'User name is required',
                    400
                );

                return;
            }


            if ($userEmail === '') {

                errorResponse(
                    'User email is required',
                    400
                );

                return;
            }


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
    | Admin - List uploaded images
    |--------------------------------------------------------------------------
    */

    public function listUploadedImages(): void
    {
        try {

            $result =
                $this->service->getUploadedImageList();


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
    | Admin - Get uploaded image details
    |--------------------------------------------------------------------------
    */

    public function getUploadedImage(
        int $id
    ): void {

        try {

            $result =
                $this->service->getUploadedImagesById(
                    $id
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
| Admin - Delete uploaded images
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


        $deleted =
            $this->service->deleteUploadedImages(
                $id
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