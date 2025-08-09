-- Create hackathon_settings table
USE hackathon_db;

CREATE TABLE IF NOT EXISTS hackathon_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL DEFAULT 'Hackhub',
    description TEXT NOT NULL DEFAULT 'Join us for an amazing hackathon experience! Collaborate, code, and create innovative solutions.',
    registration_deadline DATETIME NOT NULL DEFAULT (NOW() + INTERVAL 30 DAY),
    submission_deadline DATETIME NOT NULL DEFAULT (NOW() + INTERVAL 60 DAY),
    max_team_size INT NOT NULL DEFAULT 4,
    min_team_size INT NOT NULL DEFAULT 1,
    contact_email VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert default settings
INSERT INTO hackathon_settings (name, description, registration_deadline, submission_deadline, max_team_size, min_team_size, contact_email)
VALUES (
    'Hackhub',
    'Join us for an amazing hackathon experience! Collaborate, code, and create innovative solutions.',
    DATE_ADD(NOW(), INTERVAL 30 DAY),
    DATE_ADD(NOW(), INTERVAL 60 DAY),
    4,
    1,
    'contact@hackathonhub.com'
); 