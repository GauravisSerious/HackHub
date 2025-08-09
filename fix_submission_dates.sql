-- Fix any NULL or incorrect submission dates for submitted projects
UPDATE projects 
SET submission_date = CASE 
    WHEN submission_date IS NULL OR submission_date = '1970-01-01 00:00:00' THEN created_at
    ELSE submission_date
END
WHERE status = 'submitted'; 