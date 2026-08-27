<?php

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../helpers/response.php';

header('Content-Type: application/json');

try {

    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {

        response(false, "Only GET method is allowed");
        exit;
    }

    $userId = isset($_GET['user_id'])
        ? (int) $_GET['user_id']
        : 0;

    if ($userId <= 0) {

        response(false, "user_id is required");
        exit;
    }

    /*
     * Get vehicles completed by this user.
     *
     * Repo Marked:
     * repo_status = Repo Mark
     *
     * Parked:
     * repo_status = Parked in Godown
     */

    $sql = "
        SELECT
            vehicle_number,
            vehicle_type,
            repo_status,
            repo_year,
            repo_month
        FROM vehicle
        WHERE
            (
                repo_status = 'repo mark'
                OR
                repo_status = 'Parked'
            )
        AND agency_id = ?
        ORDER BY vehicle_number ASC
    ";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        throw new Exception(
            "SQL prepare failed: " . $conn->error
        );
    }

    $stmt->bind_param(
        "i",
        $userId
    );

    $stmt->execute();

    $result = $stmt->get_result();

    $vehicles = [];

    while ($row = $result->fetch_assoc()) {

        $vehicles[] = [
            "vehicle_number" =>
                $row["vehicle_number"],

            "vehicle_type" =>
                $row["vehicle_type"],

            "repo_status" =>
                $row["repo_status"],

            "repo_year" =>
                $row["repo_year"],

            "repo_month" =>
                $row["repo_month"]
        ];
    }

    response(
        true,
        "User vehicles fetched successfully",
        $vehicles
    );

} catch (Exception $e) {

    response(
        false,
        $e->getMessage()
    );
}