-- Privacy-minimal page analytics: route, coarse account role, timestamp only.
-- No IP address, session ID, full URL/query string, or user ID is stored.
CREATE TABLE IF NOT EXISTS page_views (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    viewer_role VARCHAR(20) NOT NULL DEFAULT 'guest',
    route VARCHAR(255) NOT NULL,
    viewed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_page_views_viewed_at (viewed_at),
    KEY idx_page_views_route_date (route, viewed_at),
    KEY idx_page_views_role_date (viewer_role, viewed_at)
) ENGINE=InnoDB;
