<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\NotificationRepository;
use App\Services\BillReminderService;
use Exception;

/**
 * Class NotificationService
 * 
 * Manages user in-app alerts and trigger evaluations.
 */
class NotificationService
{
    private NotificationRepository $notificationRepo;
    private BillReminderService $billService;

    public function __construct()
    {
        $this->notificationRepo = new NotificationRepository();
        $this->billService = new BillReminderService();
    }

    /**
     * Get user notifications list, executing an on-demand evaluation of bill timelines first.
     */
    public function getNotifications(string $userId, ?bool $isRead = null, int $limit = 50): array
    {
        // On-demand evaluation for developer convenience
        $this->billService->evaluateBillReminders();

        $notifications = $this->notificationRepo->list($userId, $isRead, $limit);
        
        // Format time outputs dynamically
        foreach ($notifications as &$notif) {
            $notif['time_ago'] = $this->formatTimeAgo($notif['created_at']);
        }
        return $notifications;
    }

    /**
     * Get unread notifications count, executing an on-demand evaluation first.
     */
    public function getUnreadCount(string $userId): int
    {
        // On-demand evaluation
        $this->billService->evaluateBillReminders();

        return $this->notificationRepo->getUnreadCount($userId);
    }

    /**
     * Mark single notification as read.
     */
    public function markAsRead(string $id, string $userId): bool
    {
        return $this->notificationRepo->markAsRead($id, $userId);
    }

    /**
     * Mark all notifications for a user as read.
     */
    public function markAllAsRead(string $userId): bool
    {
        return $this->notificationRepo->markAllAsRead($userId);
    }

    /**
     * Delete a single notification.
     */
    public function deleteNotification(string $id, string $userId): bool
    {
        return $this->notificationRepo->delete($id, $userId);
    }

    /**
     * Helper to format timestamps to readable strings (e.g. '10m ago', '2h ago', '1d ago').
     */
    private function formatTimeAgo(string $timestampStr): string
    {
        $time = strtotime($timestampStr);
        if (!$time) {
            return 'just now';
        }
        
        $diff = time() - $time;
        if ($diff < 60) {
            return 'just now';
        }
        
        $min = (int)($diff / 60);
        if ($min < 60) {
            return "{$min}m ago";
        }
        
        $hours = (int)$min / 60;
        if ($hours < 24) {
            $hoursInt = (int)$hours;
            return "{$hoursInt}h ago";
        }
        
        $days = (int)($hours / 24);
        return "{$days}d ago";
    }
}
