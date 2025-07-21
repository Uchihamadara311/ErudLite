-- Add Academic Year to Clearance if not exists
ALTER TABLE Clearance ADD COLUMN IF NOT EXISTS Academic_Year VARCHAR(20);
ALTER TABLE Clearance ADD COLUMN IF NOT EXISTS Is_Current BOOLEAN DEFAULT FALSE;

-- Update Schedule_Details for better time management
ALTER TABLE Schedule_Details ADD COLUMN IF NOT EXISTS Start_Time TIME;
ALTER TABLE Schedule_Details ADD COLUMN IF NOT EXISTS End_Time TIME;

-- Add Grade Level to Subject if not exists
ALTER TABLE Subject ADD COLUMN IF NOT EXISTS Grade_Level INT;

-- Add Academic Year to Enrollment if not exists
ALTER TABLE Enrollment ADD COLUMN IF NOT EXISTS Academic_Year VARCHAR(20);

-- Add performance indexes
CREATE INDEX IF NOT EXISTS idx_enrollment_academic_year ON Enrollment(Academic_Year);
CREATE INDEX IF NOT EXISTS idx_schedule_details_time ON Schedule_Details(Start_Time, End_Time);

-- Update existing subjects with grade levels
UPDATE Subject SET Grade_Level = 
    CASE 
        WHEN Subject_Name LIKE '%1' THEN 1
        WHEN Subject_Name LIKE '%2' THEN 2
        WHEN Subject_Name LIKE '%3' THEN 3
        WHEN Subject_Name LIKE '%4' THEN 4
        WHEN Subject_Name LIKE '%5' THEN 5
        WHEN Subject_Name LIKE '%6' THEN 6
        ELSE 1
    END
WHERE Grade_Level IS NULL;
