<?php
require_once __DIR__ . '/../config/Database.php';

class PageView
{
    public static function record(string $route, string $role): void
    {
        $route = '/' . ltrim($route, '/');
        $route = substr($route, 0, 255);
        $allowedRoles = ['guest', 'student', 'lecturer', 'tutor', 'admin'];
        if (!in_array($role, $allowedRoles, true)) $role = 'guest';
        try {
            Database::query(
                'INSERT INTO page_views (viewer_role, route) VALUES (:role, :route)',
                [':role' => $role, ':route' => $route]
            );
            if (random_int(1, 250) === 1) {
                Database::query('DELETE FROM page_views WHERE viewed_at < DATE_SUB(NOW(), INTERVAL 90 DAY)');
            }
        } catch (Throwable $e) {
            // Analytics must never interrupt a user request if the optional
            // analytics migration has not reached a deployment yet.
            error_log('Page-view tracking unavailable: ' . $e->getMessage());
        }
    }

    public static function adminSummary(): array
    {
        $today = (int)Database::query('SELECT COUNT(*) FROM page_views WHERE viewed_at >= CURDATE()')->fetchColumn();
        $last7 = (int)Database::query('SELECT COUNT(*) FROM page_views WHERE viewed_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)')->fetchColumn();
        $last30 = (int)Database::query('SELECT COUNT(*) FROM page_views WHERE viewed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)')->fetchColumn();
        $byRole = Database::query(
            'SELECT viewer_role, COUNT(*) AS views FROM page_views
             WHERE viewed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
             GROUP BY viewer_role ORDER BY views DESC'
        )->fetchAll();
        $topRoutes = Database::query(
            'SELECT route, COUNT(*) AS views FROM page_views
             WHERE viewed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
             GROUP BY route ORDER BY views DESC, route ASC LIMIT 10'
        )->fetchAll();
        $daily = Database::query(
            'SELECT DATE(viewed_at) AS day, COUNT(*) AS views FROM page_views
             WHERE viewed_at >= DATE_SUB(CURDATE(), INTERVAL 13 DAY)
             GROUP BY DATE(viewed_at) ORDER BY day ASC'
        )->fetchAll();
        return ['today' => $today, 'last7' => $last7, 'last30' => $last30, 'by_role' => $byRole, 'top_routes' => $topRoutes, 'daily' => $daily];
    }
}
