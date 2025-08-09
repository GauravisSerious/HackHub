USE hackathon_db;

CREATE TABLE IF NOT EXISTS hackathon_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    registration_deadline DATETIME NOT NULL,
    submission_deadline DATETIME NOT NULL,
    max_team_size INT NOT NULL DEFAULT 4,
    min_team_size INT NOT NULL DEFAULT 1,
    contact_email VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

INSERT INTO hackathon_settings (name, description, registration_deadline, submission_deadline)
VALUES ('Hackathon Hub', 'Join us for an amazing hackathon experience!', DATE_ADD(NOW(), INTERVAL 30 DAY), DATE_ADD(NOW(), INTERVAL 60 DAY)); 