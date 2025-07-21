-- Add Academic Year management
ALTER TABLE Clearance ADD COLUMN Academic_Year VARCHAR(20) NOT NULL;
ALTER TABLE Clearance ADD COLUMN Is_Current BOOLEAN DEFAULT FALSE;

-- Add Schedule Time constraints
ALTER TABLE Schedule_Details ADD COLUMN Start_Time TIME NOT NULL;
ALTER TABLE Schedule_Details ADD COLUMN End_Time TIME NOT NULL;
ALTER TABLE Schedule_Details DROP COLUMN Time;

-- Add Grades table
CREATE TABLE Student_Grades (
    Grade_ID INT PRIMARY KEY NOT NULL AUTO_INCREMENT,
    Student_ID INT,
    Subject_ID INT,
    Class_ID INT,
    Academic_Year VARCHAR(20),
    Term VARCHAR(20),
    Grade DECIMAL(5,2),
    Remarks TEXT,
    Created_At DATETIME DEFAULT CURRENT_TIMESTAMP,
    Updated_At DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (Student_ID) REFERENCES Student(Student_ID),
    FOREIGN KEY (Subject_ID) REFERENCES Subject(Subject_ID),
    FOREIGN KEY (Class_ID) REFERENCES Class(Class_ID)
);

-- Add indexes for performance
CREATE INDEX idx_enrollment_academic_year ON Enrollment(Academic_Year);
CREATE INDEX idx_schedule_details_time ON Schedule_Details(Start_Time, End_Time);
CREATE INDEX idx_student_grades_lookup ON Student_Grades(Student_ID, Academic_Year, Term);

-- Sample data for Subjects (Grades 1-6)
INSERT INTO Subject (Subject_Name, Description) VALUES
-- Grade 1
('Mathematics 1', 'Basic arithmetic and number sense'),
('English 1', 'Basic reading and writing skills'),
('Science 1', 'Introduction to basic science concepts'),
('Filipino 1', 'Basic Filipino language skills'),
('Social Studies 1', 'Introduction to community and society'),

-- Grade 2
('Mathematics 2', 'Advanced arithmetic and basic geometry'),
('English 2', 'Reading comprehension and writing'),
('Science 2', 'Basic life and physical science'),
('Filipino 2', 'Filipino reading and writing'),
('Social Studies 2', 'Philippine history and culture'),

-- Grade 3
('Mathematics 3', 'Multiplication, division, and fractions'),
('English 3', 'Grammar and composition'),
('Science 3', 'Earth science and biology basics'),
('Filipino 3', 'Filipino literature and composition'),
('Social Studies 3', 'Asian history and geography'),

-- Grade 4
('Mathematics 4', 'Decimals and basic algebra'),
('English 4', 'Literature and creative writing'),
('Science 4', 'Physics and chemistry basics'),
('Filipino 4', 'Advanced Filipino literature'),
('Social Studies 4', 'World history and geography'),

-- Grade 5
('Mathematics 5', 'Pre-algebra and statistics'),
('English 5', 'Advanced grammar and literature'),
('Science 5', 'Life science and ecology'),
('Filipino 5', 'Filipino poetry and prose'),
('Social Studies 5', 'Economics and civics'),

-- Grade 6
('Mathematics 6', 'Introduction to algebra and geometry'),
('English 6', 'Research and academic writing'),
('Science 6', 'Integrated science'),
('Filipino 6', 'Advanced Filipino composition'),
('Social Studies 6', 'Government and citizenship');

-- Add grade levels to subjects
ALTER TABLE Subject ADD COLUMN Grade_Level INT NOT NULL DEFAULT 1;
UPDATE Subject SET Grade_Level = 1 WHERE Subject_Name LIKE '%1';
UPDATE Subject SET Grade_Level = 2 WHERE Subject_Name LIKE '%2';
UPDATE Subject SET Grade_Level = 3 WHERE Subject_Name LIKE '%3';
UPDATE Subject SET Grade_Level = 4 WHERE Subject_Name LIKE '%4';
UPDATE Subject SET Grade_Level = 5 WHERE Subject_Name LIKE '%5';
UPDATE Subject SET Grade_Level = 6 WHERE Subject_Name LIKE '%6';
