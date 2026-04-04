-- This script runs once when the MySQL container is first created.
-- It ensures the jeem_portal database exists with proper character set.

CREATE DATABASE IF NOT EXISTS `jeem_portal`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

-- Grant all privileges to the application user
GRANT ALL PRIVILEGES ON `jeem_portal`.* TO 'jeem_user'@'%';
FLUSH PRIVILEGES;
