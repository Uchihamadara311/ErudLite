-- PROFILE ---------------------
-- Table: Contacts
CREATE TABLE Contacts (
    Contacts_ID INT PRIMARY KEY NOT NULL AUTO_INCREMENT,
    Contact_Number VARCHAR(15),
    Emergency_Contact VARCHAR(15)
);

-- Table: Location
CREATE TABLE Location (
    Location_ID INT PRIMARY KEY NOT NULL AUTO_INCREMENT,
    Nationality VARCHAR(50),
    Country_Code VARCHAR(10),
    Address TEXT
);

-- Table: Profile
CREATE TABLE Profile (
    Profile_ID INT PRIMARY KEY NOT NULL AUTO_INCREMENT,
    Location_ID INT,
    Contacts_ID INT,
    FOREIGN KEY (Location_ID) REFERENCES Location(Location_ID),
    FOREIGN KEY (Contacts_ID) REFERENCES Contacts(Contacts_ID)
);

-- Table: Profile_Bio
CREATE TABLE Profile_Bio (
    Profile_ID INT PRIMARY KEY,
    Given_Name VARCHAR(100),
    Last_Name VARCHAR(100),
    Gender VARCHAR(50),
    Date_of_Birth DATE,
    FOREIGN KEY (Profile_ID) REFERENCES Profile(Profile_ID)
);


-- ACCOUNT ---------------------
-- Table: Role
CREATE TABLE Role (
    Role_ID INT PRIMARY KEY NOT NULL AUTO_INCREMENT,
    Role_Name VARCHAR(100),
    Email VARCHAR(100) UNIQUE,
    Password_Hash VARCHAR(255),
    Permissions VARCHAR(50)
);

-- Table: Login_Info
CREATE TABLE Login_Info (
    Login_ID INT PRIMARY KEY NOT NULL AUTO_INCREMENT,
    Status VARCHAR(50),
    Last_Login DATETIME,
    Updated_At DATETIME
);

-- Table: Account
CREATE TABLE Account (
    Account_ID INT PRIMARY KEY NOT NULL AUTO_INCREMENT,
    Profile_ID INT,
    Role_ID INT,
    Login_ID INT,
    FOREIGN KEY (Profile_ID) REFERENCES Profile(Profile_ID),
    FOREIGN KEY (Role_ID) REFERENCES Role(Role_ID),
    FOREIGN KEY (Login_ID) REFERENCES Login_Info(Login_ID)
);

-- Table: Account_Details
CREATE TABLE Account_Details (
    Account_ID INT PRIMARY KEY,
    Account_Name VARCHAR(100),
    Description TEXT,
    FOREIGN KEY (Account_ID) REFERENCES Account(Account_ID)
);


-- |.|1/4|.| ---------------------
-- Table: Student
CREATE TABLE Student (
    Student_ID INT PRIMARY KEY NOT NULL AUTO_INCREMENT,
    Profile_ID INT,
    Health_Info TEXT,
    Behavior TEXT,
    FOREIGN KEY (Profile_ID) REFERENCES Profile(Profile_ID)
);

-- GUARDIAN ---------------------
-- Table: Guardian
CREATE TABLE Guardian (
    Guardian_ID INT PRIMARY KEY NOT NULL AUTO_INCREMENT,
    Guardian_LastName VARCHAR(100),
    Guardian_GivenName VARCHAR(100),
    Guardian_Contact VARCHAR(15)
);

-- Table: Guardian_Relations
CREATE TABLE Guardian_Relations (
    Guardian_ID INT PRIMARY KEY,
    Student_ID INT,
    Relationship VARCHAR(50),
    FOREIGN KEY (Guardian_ID) REFERENCES Guardian(Guardian_ID),
    FOREIGN KEY (Student_ID) REFERENCES Student(Student_ID)
);


-- |.|2/4|.| ---------------------
-- Table: Instructor
CREATE TABLE Instructor (
    Instructor_ID INT PRIMARY KEY NOT NULL AUTO_INCREMENT,
    Profile_ID INT,
    Hire_Date DATE,
    Employ_Status VARCHAR(50),
    Specialization TEXT,
    FOREIGN KEY (Profile_ID) REFERENCES Profile(Profile_ID)
);


-- SUBJECT ---------------------
-- Table: Clearance
CREATE TABLE Clearance (
    Clearance_ID INT PRIMARY KEY NOT NULL AUTO_INCREMENT,
    School_Year VARCHAR(50),
    Term VARCHAR(50),
    Grade_Level VARCHAR(50),
    Requirements TEXT
);

-- Table: Subject
CREATE TABLE Subject (
    Subject_ID INT PRIMARY KEY NOT NULL AUTO_INCREMENT,
    Subject_Name VARCHAR(100),
    Description TEXT,
    Clearance_ID INT,
    FOREIGN KEY (Clearance_ID) REFERENCES Clearance(Clearance_ID)
);


-- |.|3/4|.| ---------------------
-- Table: Assigned_Subject
CREATE TABLE Assigned_Subject (
    Instructor_ID INT,
    Subject_ID INT,
    PRIMARY KEY (Instructor_ID, Subject_ID),
    FOREIGN KEY (Instructor_ID) REFERENCES Instructor(Instructor_ID),
    FOREIGN KEY (Subject_ID) REFERENCES Subject(Subject_ID)
);

-- CLASS ---------------------
-- Table: Classroom
CREATE TABLE Classroom (
    Room_ID INT PRIMARY KEY NOT NULL AUTO_INCREMENT,
    Room VARCHAR(50),
    Section VARCHAR(50),
    Floor_No INT
);

-- Table: Class
CREATE TABLE Class (
    Class_ID INT PRIMARY KEY NOT NULL AUTO_INCREMENT,
    Clearance_ID INT,
    Room_ID INT,
    FOREIGN KEY (Clearance_ID) REFERENCES Clearance(Clearance_ID),
    FOREIGN KEY (Room_ID) REFERENCES Classroom(Room_ID)
);


-- |.|4/4|.| ---------------------
-- Table: Enrollment
CREATE TABLE Enrollment (
    Class_ID INT NOT NULL AUTO_INCREMENT,
    Student_ID INT,
    Enrollment_Date DATE,
    Status VARCHAR(50),
    PRIMARY KEY (Class_ID, Student_ID),
    FOREIGN KEY (Class_ID) REFERENCES Class(Class_ID),
    FOREIGN KEY (Student_ID) REFERENCES Student(Student_ID)
);


-- SCHEDULE ---------------------
-- Table: Schedule
CREATE TABLE Schedule (
    Schedule_ID INT PRIMARY KEY NOT NULL AUTO_INCREMENT,
    Instructor_ID INT,
    Class_ID INT,
    Subject_ID INT,
    FOREIGN KEY (Instructor_ID) REFERENCES Instructor(Instructor_ID),
    FOREIGN KEY (Class_ID) REFERENCES Class(Class_ID),
    FOREIGN KEY (Subject_ID) REFERENCES Subject(Subject_ID)
);

-- Table: Schedule_Details
CREATE TABLE Schedule_Details (
    Schedule_ID INT PRIMARY KEY,
    Time TIME,
    Day VARCHAR(50),
    Status VARCHAR(50),
    Notes TEXT,
    FOREIGN KEY (Schedule_ID) REFERENCES Schedule(Schedule_ID)
);

-- END --