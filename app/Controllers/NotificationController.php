<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\NotificationService;
use App\Helpers\ResponseHelper;
use Exception;

/**
 * Class NotificationController
 * 
 * Routes incoming HTTP requests to NotificationService actions.
 */
class NotificationController
{
    private NotificationService $notifService;

    public function __construct()
    {
        $this->notifService = new NotificationService();
    }

    private function getAuthenticatedUserId(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $userId = $_SESSION['user_id'] ?? null;
        if (!$userId) {
            ResponseHelper::error("Unauthenticated request.", 401);
            exit;
        }
        return $userId;
    }

    /**
     * GET /api/notifications
     */
    public function index(): void
    {
        $userId = $this->getAuthenticatedUserId();
        $isRead = isset($_GET['is_read']) ? ($_GET['is_read'] === 'true' || $_GET['is_read'] === '1') : null;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;

        try {
            $notifications = $this->notifService->getNotifications($userId, $isRead, $limit);
            ResponseHelper::success("Notifications list fetched successfully.", ['notifications' => $notifications]);
        } catch (Exception $e) {
            ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * GET /api/notifications/unread-count
     */
    public function unreadCount(): void
    {
        $userId = $this->getAuthenticatedUserId();

        try {
            $count = $this->notifService->getUnreadCount($userId);
            ResponseHelper::success("Unread count fetched successfully.", ['unread_count' => $count]);
        } catch (Exception $e) {
            ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * PUT /api/notifications/{id}/read
     */
    public function read(string $id): void
    {
        $userId = $this->getAuthenticatedUserId();

        try {
            $res = $this->notifService->markAsRead($id, $userId);
            if ($res) {
                ResponseHelper::success("Notification marked as read.");
            } else {
                ResponseHelper::error("Failed to update notification status.", 500);
            }
        } catch (Exception $e) {
            ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * PUT /api/notifications/read-all
     */
    public function readAll(): void
    {
        $userId = $this->getAuthenticatedUserId();

        try {
            $res = $this->notifService->markAllAsRead($userId);
            if ($res) {
                ResponseHelper::success("All notifications marked as read.");
            } else {
                ResponseHelper::error("Failed to update notifications status.", 500);
            }
        } catch (Exception $e) {
            ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * DELETE /api/notifications/{id}
     */
    public function destroy(string $id): void
    {
        $userId = $this->getAuthenticatedUserId();

        try {
            $res = $this->notifService->deleteNotification($id, $userId);
            if ($res) {
                ResponseHelper::success("Notification deleted successfully.");
            } else {
                ResponseHelper::error("Failed to delete notification.", 500);
            }
        } catch (Exception $e) {
            ResponseHelper::error($e->getMessage(), 500);
        }
    }
}
