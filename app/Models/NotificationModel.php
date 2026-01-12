<?php

namespace App\Models;

use CodeIgniter\Model;

class NotificationModel extends Model
{
    protected $table            = 'notifications';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'created_by',
        'message',
        'type',
        'link',
        'related_id',
    ];

    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;

    protected array $casts = [];
    protected array $castHandlers = [];

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = '';
    protected $deletedField  = '';

    // Validation
    protected $validationRules      = [];
    protected $validationMessages   = [];
    protected $skipValidation       = false;
    protected $cleanValidationRules = true;

    // Callbacks
    protected $allowCallbacks = true;
    protected $beforeInsert   = [];
    protected $afterInsert    = [];
    protected $beforeUpdate   = [];
    protected $afterUpdate    = [];
    protected $beforeFind     = [];
    protected $afterFind      = [];
    protected $beforeDelete   = [];
    protected $afterDelete    = [];

    /**
     * Get notifications for a specific user with pagination
     */
    public function getUserNotifications(int $userId, array $filters = []): array
    {
        $page = $filters['page'] ?? 1;
        $limit = $filters['limit'] ?? 50;
        $offset = ($page - 1) * $limit;

        $builder = $this->db->table('notification_users nu');
        $builder->select('
            n.*,
            u.first_name,
            u.last_name,
            u.email,
            u.profile_picture,
            nu.is_read,
            nu.read_at,
            nu.created_at as received_at
        ');
        $builder->join('notifications n', 'n.id = nu.notification_id');
        $builder->join('users u', 'u.id = n.created_by');
        $builder->where('nu.user_id', $userId);
        $builder->orderBy('nu.created_at', 'DESC');

        $total = $builder->countAllResults(false);
        $notifications = $builder->get($limit, $offset)->getResultArray();

        return [
            'data' => $notifications,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
            ]
        ];
    }

    /**
     * Get notification with creator details for a specific user
     */
    public function getNotificationWithCreator(int $notificationId): ?array
    {
        return $this->select('
            notifications.*,
            users.first_name,
            users.last_name,
            users.email,
            users.profile_picture
        ')
            ->join('users', 'users.id = notifications.created_by')
            ->where('notifications.id', $notificationId)
            ->first();
    }

    /**
     * Mark notification as read for a specific user
     */
    public function markAsRead(int $notificationId, int $userId): bool
    {
        return $this->db->table('notification_users')
            ->where('notification_id', $notificationId)
            ->where('user_id', $userId)
            ->update([
                'is_read' => 1,
                'read_at' => date('Y-m-d H:i:s')
            ]);
    }

    /**
     * Mark all notifications as read for a user
     */
    public function markAllAsRead(int $userId): bool
    {
        return $this->db->table('notification_users')
            ->where('user_id', $userId)
            ->where('is_read', 0)
            ->update([
                'is_read' => 1,
                'read_at' => date('Y-m-d H:i:s')
            ]);
    }

    /**
     * Get unread notification count for a user
     */
    public function getUnreadCount(int $userId): int
    {
        return $this->db->table('notification_users')
            ->where('user_id', $userId)
            ->where('is_read', 0)
            ->countAllResults();
    }

    /**
     * Delete notification for a specific user
     */
    public function deleteForUser(int $notificationId, int $userId): bool
    {
        return $this->db->table('notification_users')
            ->where('notification_id', $notificationId)
            ->where('user_id', $userId)
            ->delete();
    }

    /**
     * Delete old read notifications (older than specified days)
     */
    public function deleteOldNotifications(int $days = 30): int
    {
        $date = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        // Delete old notification_users records
        $this->db->table('notification_users')
            ->where('is_read', 1)
            ->where('created_at <', $date)
            ->delete();

        // Delete orphaned notifications
        $this->db->query("
            DELETE n FROM notifications n
            LEFT JOIN notification_users nu ON nu.notification_id = n.id
            WHERE nu.id IS NULL
        ");

        return $this->db->affectedRows();
    }
}
