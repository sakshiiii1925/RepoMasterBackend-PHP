<?php

class AdminNotificationController
{
    private AdminNotificationService $service;

    public function __construct(
        AdminNotificationService $service
    ) {
        $this->service = $service;
    }

    public function list(): void
    {
        $agencyId = $_GET['agencyId'] ?? '';

        if ($agencyId === '') {
            errorResponse(
                'agencyId is required',
                400
            );
            return;
        }

        successResponse(
            $this->service->getNotifications($agencyId)
        );
    }

    public function unreadCount(): void
    {
        $agencyId = $_GET['agencyId'] ?? '';

        if ($agencyId === '') {
            errorResponse(
                'agencyId is required',
                400
            );
            return;
        }

        successResponse([
            'count' =>
                $this->service->getUnreadCount($agencyId)
        ]);
    }

    public function markRead(int $id): void
    {
        $this->service->markRead($id);

        successResponse([
            'message' => 'Notification marked as read'
        ]);
    }
}