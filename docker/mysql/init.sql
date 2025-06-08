-- MySQL initialization script for Laravel testing

-- Create testing database
CREATE DATABASE IF NOT EXISTS testing;

-- Grant full privileges to laravel user on both databases
GRANT ALL PRIVILEGES ON flash_card_practice.* TO 'laravel'@'%';
GRANT ALL PRIVILEGES ON testing.* TO 'laravel'@'%';

-- Allow laravel user to create/drop databases (needed for tests)
GRANT CREATE, DROP ON *.* TO 'laravel'@'%';

-- Refresh privileges
FLUSH PRIVILEGES; 