-- Add submission_date column to projects table
ALTER TABLE projects
ADD COLUMN submission_date TIMESTAMP NULL DEFAULT NULL AFTER status;

-- Update existing submitted projects to have a submission date
UPDATE projects 
SET submission_date = created_at 
WHERE status = 'submitted' AND submission_date IS NULL; 