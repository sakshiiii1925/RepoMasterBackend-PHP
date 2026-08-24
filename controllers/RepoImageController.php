<?php

class RepoImageController
{
    private RepoImageService $service;

    public function __construct(
        RepoImageService $service
    ) {
        $this->service = $service;
    }

    public function upload(
        string $vehicleNumber
    ): void {

        try {

            /*
            |--------------------------------------------------------------------------
            | Vehicle number
            |--------------------------------------------------------------------------
            */

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


            /*
            |--------------------------------------------------------------------------
            | Status
            |--------------------------------------------------------------------------
            */

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
    //validate
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


            /*
            |--------------------------------------------------------------------------
            | Upload images
            |--------------------------------------------------------------------------
            */

            $result =
                $this->service->uploadImages(
                    $vehicleNumber,
                    $status,
                     $userName,
        $userEmail,
                    $_FILES
                );


            /*
            |--------------------------------------------------------------------------
            | Success response
            |--------------------------------------------------------------------------
            */

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
}