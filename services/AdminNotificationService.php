<?php

class AdminNotificationService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function createNotification(
        string $agencyId,
        string $vehicleNumber,
        string $status,
        string $userName,
        string $userEmail
    ): bool {

        $message =
            "Vehicle {$vehicleNumber} changed to {$status}";

        $stmt = $this->pdo->prepare("
            INSERT INTO admin_notifications
            (
                agency_id,
                vehicle_number,
                status,
                user_name,
                user_email,
                message
            )
            VALUES
            (
                :agency_id,
                :vehicle_number,
                :status,
                :user_name,
                :user_email,
                :message
            )
        ");

        return $stmt->execute([
            ':agency_id' => $agencyId,
            ':vehicle_number' => $vehicleNumber,
            ':status' => $status,
            ':user_name' => $userName,
            ':user_email' => $userEmail,
            ':message' => $message
        ]);
    }

    public function getNotifications(
        string $agencyId
    ): array {

        $stmt = $this->pdo->prepare("
            SELECT
                id,
                vehicle_number,
                status,
                user_name,
                user_email,
                message,
                is_read,
                created_at
            FROM admin_notifications
            WHERE agency_id = :agency_id
            AND is_read = 0
            ORDER BY created_at DESC
        ");

        $stmt->execute([
            ':agency_id' => $agencyId
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getUnreadCount(
        string $agencyId
    ): int {

        $stmt = $this->pdo->prepare("
            SELECT COUNT(*)
            FROM admin_notifications
            WHERE agency_id = :agency_id
            AND is_read = 0
        ");

        $stmt->execute([
            ':agency_id' => $agencyId
        ]);

        return (int)$stmt->fetchColumn();
    }

    public function markRead(
        int $id
    ): bool {

        $stmt = $this->pdo->prepare("
            UPDATE admin_notifications
            SET is_read = 1
            WHERE id = :id
        ");

        return $stmt->execute([
            ':id' => $id
        ]);
    }
}