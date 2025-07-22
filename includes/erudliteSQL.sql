-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Jul 22, 2025 at 12:55 AM
-- Server version: 10.4.28-MariaDB
-- PHP Version: 8.2.4

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `erudlite`
--

-- --------------------------------------------------------

--
-- Table structure for table `Account`
--

CREATE TABLE `Account` (
  `Account_ID` int(11) NOT NULL,
  `Profile_ID` int(11) DEFAULT NULL,
  `Role_ID` int(11) DEFAULT NULL,
  `Login_ID` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `Account`
--

INSERT INTO `Account` (`Account_ID`, `Profile_ID`, `Role_ID`, `Login_ID`) VALUES
(20, 42, 22, NULL),
(21, 43, 23, NULL),
(22, 44, 24, NULL),
(23, 45, 25, NULL),
(24, 46, 26, NULL),
(25, 47, 27, NULL),
(26, 48, 28, NULL),
(27, 49, 29, NULL),
(28, 50, 30, NULL),
(29, 51, 31, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `Account_Details`
--

CREATE TABLE `Account_Details` (
  `Account_ID` int(11) NOT NULL,
  `Account_Name` varchar(100) DEFAULT NULL,
  `Description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `Assigned_Subject`
--

CREATE TABLE `Assigned_Subject` (
  `Instructor_ID` int(11) NOT NULL,
  `Subject_ID` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `Assigned_Subject`
--

INSERT INTO `Assigned_Subject` (`Instructor_ID`, `Subject_ID`) VALUES
(6, 72),
(6, 81),
(7, 2),
(7, 69),
(7, 78);

-- --------------------------------------------------------

--
-- Table structure for table `Class`
--

CREATE TABLE `Class` (
  `Class_ID` int(11) NOT NULL,
  `Clearance_ID` int(11) DEFAULT NULL,
  `Room_ID` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `Class`
--

INSERT INTO `Class` (`Class_ID`, `Clearance_ID`, `Room_ID`) VALUES
(28, 18, 10),
(30, 20, 10),
(31, 17, 10),
(32, 19, 10),
(33, 21, 12),
(34, 22, 12),
(35, 23, 12),
(36, 24, 12),
(37, 25, 14),
(38, 26, 14),
(39, 27, 14),
(40, 28, 14);

-- --------------------------------------------------------

--
-- Table structure for table `Classroom`
--

CREATE TABLE `Classroom` (
  `Room_ID` int(11) NOT NULL,
  `Room` varchar(50) DEFAULT NULL,
  `Section` varchar(50) DEFAULT NULL,
  `Floor_No` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `Classroom`
--

INSERT INTO `Classroom` (`Room_ID`, `Room`, `Section`, `Floor_No`) VALUES
(10, '101', 'Section A', 1),
(11, '102', 'Section B', 1),
(12, '201', 'Section A', 2),
(13, '202', 'Section B', 2),
(14, '301', 'Section A', 3),
(15, '302', 'Section B', 3),
(16, '401', 'Section A', 4),
(17, '402', 'Section B', 4),
(18, '501', 'Section A', 5),
(19, '502', 'Section B', 5),
(20, '601', 'Section A', 6),
(21, '602', 'Section B', 6);

-- --------------------------------------------------------

--
-- Table structure for table `Clearance`
--

CREATE TABLE `Clearance` (
  `Clearance_ID` int(11) NOT NULL,
  `School_Year` varchar(50) DEFAULT NULL,
  `Term` varchar(50) DEFAULT NULL,
  `Grade_Level` varchar(50) DEFAULT NULL,
  `Requirements` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `Clearance`
--

INSERT INTO `Clearance` (`Clearance_ID`, `School_Year`, `Term`, `Grade_Level`, `Requirements`) VALUES
(7, '2025-2026', 'First Semester', '1', NULL),
(8, '2025-2026', 'First Semester', '2', NULL),
(9, '2025-2026', 'First Semester', '3', NULL),
(10, '2025-2026', 'First Semester', '4', NULL),
(14, '2025-2026', 'First Semester', '5', NULL),
(15, '2025-2026', 'First Semester', '6', NULL),
(17, '2025-2026', '1st Semester', '1', NULL),
(18, '2025-2026', '2nd Semester', '1', NULL),
(19, '2025-2026', '3rd Semester', '1', NULL),
(20, '2025-2026', '4th Semester', '1', NULL),
(21, '2025-2026', '1st Semester', '2', NULL),
(22, '2025-2026', '2nd Semester', '2', NULL),
(23, '2025-2026', '3rd Semester', '2', NULL),
(24, '2025-2026', '4th Semester', '2', NULL),
(25, '2025-2026', '1st Semester', '3', NULL),
(26, '2025-2026', '2nd Semester', '3', NULL),
(27, '2025-2026', '3rd Semester', '3', NULL),
(28, '2025-2026', '4th Semester', '3', NULL),
(29, '2025-2026', '1st Semester', '4', NULL),
(30, '2025-2026', '2nd Semester', '4', NULL),
(31, '2025-2026', '3rd Semester', '4', NULL),
(32, '2025-2026', '4th Semester', '4', NULL),
(33, '2025-2026', '1st Semester', '5', NULL),
(34, '2025-2026', '2nd Semester', '5', NULL),
(35, '2025-2026', '3rd Semester', '5', NULL),
(36, '2025-2026', '4th Semester', '5', NULL),
(37, '2025-2026', '1st Semester', '6', NULL),
(38, '2025-2026', '2nd Semester', '6', NULL),
(39, '2025-2026', '3rd Semester', '6', NULL),
(40, '2025-2026', '4th Semester', '6', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `Contacts`
--

CREATE TABLE `Contacts` (
  `Contacts_ID` int(11) NOT NULL,
  `Contact_Number` varchar(15) DEFAULT NULL,
  `Emergency_Contact` varchar(15) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `Contacts`
--

INSERT INTO `Contacts` (`Contacts_ID`, `Contact_Number`, `Emergency_Contact`) VALUES
(12, '09123456789', '09123456789'),
(13, '09322931925', '09123456789'),
(14, '09123456789', '09123456789'),
(15, '09123456789', '09123456789'),
(16, '09123456789', '09123456789'),
(17, '09171234567', '09281234567'),
(18, '09181234568', '09391234568'),
(19, '09191234569', '09491234569'),
(20, '09201234570', '09591234570'),
(21, '09211234571', '09101234571');

-- --------------------------------------------------------

--
-- Table structure for table `Enrollment`
--

CREATE TABLE `Enrollment` (
  `Class_ID` int(11) NOT NULL,
  `Student_ID` int(11) NOT NULL,
  `Enrollment_Date` date DEFAULT NULL,
  `Status` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `Enrollment`
--

INSERT INTO `Enrollment` (`Class_ID`, `Student_ID`, `Enrollment_Date`, `Status`) VALUES
(31, 15, '2025-07-22', 'Inactive'),
(33, 12, '2025-07-22', 'Active'),
(33, 13, '2025-07-22', 'Active'),
(33, 15, '2025-07-22', 'Active');

-- --------------------------------------------------------

--
-- Table structure for table `Guardian`
--

CREATE TABLE `Guardian` (
  `Guardian_ID` int(11) NOT NULL,
  `Guardian_LastName` varchar(100) DEFAULT NULL,
  `Guardian_GivenName` varchar(100) DEFAULT NULL,
  `Guardian_Contact` varchar(15) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `Guardian_Relations`
--

CREATE TABLE `Guardian_Relations` (
  `Guardian_ID` int(11) NOT NULL,
  `Student_ID` int(11) DEFAULT NULL,
  `Relationship` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `Instructor`
--

CREATE TABLE `Instructor` (
  `Instructor_ID` int(11) NOT NULL,
  `Profile_ID` int(11) DEFAULT NULL,
  `Hire_Date` date DEFAULT NULL,
  `Employ_Status` varchar(50) DEFAULT NULL,
  `Specialization` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `Instructor`
--

INSERT INTO `Instructor` (`Instructor_ID`, `Profile_ID`, `Hire_Date`, `Employ_Status`, `Specialization`) VALUES
(6, 46, NULL, NULL, 'Music and Computer Science'),
(7, 51, NULL, NULL, 'Filipino');

-- --------------------------------------------------------

--
-- Table structure for table `Location`
--

CREATE TABLE `Location` (
  `Location_ID` int(11) NOT NULL,
  `Nationality` varchar(50) DEFAULT NULL,
  `Country_Code` varchar(10) DEFAULT NULL,
  `Address` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `Location`
--

INSERT INTO `Location` (`Location_ID`, `Nationality`, `Country_Code`, `Address`) VALUES
(12, 'Filipino', NULL, 'Erudlite School'),
(13, 'Filipino', NULL, 'Lapu-Lapu'),
(14, 'Filipino', NULL, 'home'),
(15, 'Filipino', NULL, 'Erudlite School'),
(16, 'Filipino', NULL, 'Talamban USC'),
(17, 'Filipino', NULL, '123 Mabini St, Manila'),
(18, 'Filipino', NULL, '45 Rizal Ave, Quezon City'),
(19, 'Filipino', NULL, '67 Bonifacio St, Makati'),
(20, 'Filipino', NULL, '89 Katipunan, Pasig'),
(21, 'Filipino', NULL, '12 Aurora Blvd, San Juan');

-- --------------------------------------------------------

--
-- Table structure for table `Login_Info`
--

CREATE TABLE `Login_Info` (
  `Login_ID` int(11) NOT NULL,
  `Status` varchar(50) DEFAULT NULL,
  `Last_Login` datetime DEFAULT NULL,
  `Updated_At` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `Profile`
--

CREATE TABLE `Profile` (
  `Profile_ID` int(11) NOT NULL,
  `Location_ID` int(11) DEFAULT NULL,
  `Contacts_ID` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `Profile`
--

INSERT INTO `Profile` (`Profile_ID`, `Location_ID`, `Contacts_ID`) VALUES
(42, 12, 12),
(43, 13, 13),
(44, 14, 14),
(45, 15, 15),
(46, 16, 16),
(47, 17, 17),
(48, 18, 18),
(49, 19, 19),
(50, 20, 20),
(51, 21, 21);

-- --------------------------------------------------------

--
-- Table structure for table `Profile_Bio`
--

CREATE TABLE `Profile_Bio` (
  `Profile_ID` int(11) NOT NULL,
  `Given_Name` varchar(100) DEFAULT NULL,
  `Last_Name` varchar(100) DEFAULT NULL,
  `Gender` varchar(50) DEFAULT NULL,
  `Date_of_Birth` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `Profile_Bio`
--

INSERT INTO `Profile_Bio` (`Profile_ID`, `Given_Name`, `Last_Name`, `Gender`, `Date_of_Birth`) VALUES
(42, 'Admin', '1.1', 'Male', NULL),
(43, 'Martin Nicholas', 'Del Rio', 'Male', NULL),
(44, 'Sarah', 'Taylor', 'Female', NULL),
(45, 'Admin', '1.2', 'Female', NULL),
(46, 'Miguel Tristan', 'Ebao', 'Male', NULL),
(47, 'Maria', 'Santos', 'Female', NULL),
(48, 'Juan', 'Dela Cruz', 'Male', NULL),
(49, 'Ana', 'Reyes', 'Female', NULL),
(50, 'Carlo', 'Mendoza', 'Male', NULL),
(51, 'Lea', 'Cruz', 'Male', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `Record`
--

CREATE TABLE `Record` (
  `Record_ID` int(11) NOT NULL,
  `Student_ID` int(11) DEFAULT NULL,
  `Instructor_ID` int(11) DEFAULT NULL,
  `Subject_ID` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `Record_Details`
--

CREATE TABLE `Record_Details` (
  `Record_ID` int(11) NOT NULL,
  `Clearance_ID` int(11) DEFAULT NULL,
  `Grade` int(11) DEFAULT NULL,
  `Record_Date` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `Role`
--

CREATE TABLE `Role` (
  `Role_ID` int(11) NOT NULL,
  `Role_Name` varchar(100) DEFAULT NULL,
  `Email` varchar(100) DEFAULT NULL,
  `Password_Hash` varchar(255) DEFAULT NULL,
  `Permissions` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `Role`
--

INSERT INTO `Role` (`Role_ID`, `Role_Name`, `Email`, `Password_Hash`, `Permissions`) VALUES
(22, NULL, 'admin@erudlite.com', '$2y$10$u6EAdWW28NzJWIKP8AWPTueI6AsY3oyR/XNp3uWTPQUCcyM73s9ma', 'Admin'),
(23, NULL, '24800203@erudlite.com', '$2y$10$wHye.h2l6C0FQIFg9smP/.T0MqxtKWzn1sLEkTAlRk96wFGY9Fx4u', 'Student'),
(24, NULL, 'sarahTaylor@erudlite.com', '$2y$10$3sViKcM.gYPbLG85lmITBO76DdHxNwUIZHMg5w6tu0Bop2XVUM3qi', 'Student'),
(25, NULL, 'admin2@erudlite.com', '$2y$10$UIt.bTcvfYh9w07QuiC/iuvp49xsujDMWumDEKjkUiObtCkZDbIhC', 'Admin'),
(26, NULL, '24101450@usc.edu.ph', '$2y$10$9GBH1Na8nmNj440dwaIdMOQhvTd.1s7CnEZjseOMcx/OjCJvLyJr2', 'Instructor'),
(27, NULL, 'maria.santos@erudlite.com', '$2y$10$Y..arLb7kAXDQhnkyyA80uyay0H3byxsItKMRyuIMgm2Px4yZ3wIO', 'Student'),
(28, NULL, 'juan.delacruz@erudlite.com', '$2y$10$pTARnkr1reJEfObTsUlljuy8FEA.23q/J7NgjAvo7I//AngdxCyJ6', 'Student'),
(29, NULL, 'ana.reyes@erudlite.com', '$2y$10$NIx5tZDU0wRTBuD1NFiFNuGCuxrl1ityKzM5DcQcORIDUJAUtk1HO', 'Student'),
(30, NULL, 'carlo.mendoza@erudlite.com', '$2y$10$vw7t9DCrPsE4N0rTB6KhYu0Dsraf5dCMfYHE0srdwJxKfWhTr2cv6', 'Student'),
(31, NULL, 'lea.cruz@erudlite.com', '$2y$10$wqpoZlOMdNlMq.omI7N14euRiC1.xmDJ/bFKxBTb5RvnyjvuMpyuG', 'Instructor');

-- --------------------------------------------------------

--
-- Table structure for table `Schedule`
--

CREATE TABLE `Schedule` (
  `Schedule_ID` int(11) NOT NULL,
  `Instructor_ID` int(11) DEFAULT NULL,
  `Class_ID` int(11) DEFAULT NULL,
  `Subject_ID` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `Schedule`
--

INSERT INTO `Schedule` (`Schedule_ID`, `Instructor_ID`, `Class_ID`, `Subject_ID`) VALUES
(6, 6, 33, 72);

-- --------------------------------------------------------

--
-- Table structure for table `schedule_details`
--

CREATE TABLE `schedule_details` (
  `Schedule_ID` int(11) NOT NULL,
  `Start_Time` time NOT NULL,
  `End_Time` time NOT NULL,
  `Day` varchar(50) NOT NULL,
  `Status` varchar(50) DEFAULT NULL,
  `Notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `schedule_details`
--

INSERT INTO `schedule_details` (`Schedule_ID`, `Start_Time`, `End_Time`, `Day`, `Status`, `Notes`) VALUES
(6, '07:30:00', '10:30:00', 'Monday', NULL, NULL),
(6, '07:30:00', '10:30:00', 'Tuesday', NULL, NULL),
(6, '07:30:00', '10:30:00', 'Wednesday', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `Student`
--

CREATE TABLE `Student` (
  `Student_ID` int(11) NOT NULL,
  `Profile_ID` int(11) DEFAULT NULL,
  `Health_Info` text DEFAULT NULL,
  `Behavior` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `Student`
--

INSERT INTO `Student` (`Student_ID`, `Profile_ID`, `Health_Info`, `Behavior`) VALUES
(11, 49, NULL, NULL),
(12, 50, NULL, NULL),
(13, 48, NULL, NULL),
(14, 47, NULL, NULL),
(15, 43, NULL, NULL),
(16, 44, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `Subject`
--

CREATE TABLE `Subject` (
  `Subject_ID` int(11) NOT NULL,
  `Subject_Name` varchar(100) DEFAULT NULL,
  `Description` text DEFAULT NULL,
  `Clearance_ID` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `Subject`
--

INSERT INTO `Subject` (`Subject_ID`, `Subject_Name`, `Description`, `Clearance_ID`) VALUES
(1, 'Music', 'Grade 1 Music curriculum', 7),
(2, 'Filipino', 'Grade 1 Filipino curriculum', 7),
(3, 'Mathematics', 'Grade 1 Mathematics curriculum', 7),
(61, 'English', 'Grade 1 English curriculum', 7),
(62, 'Science', 'Grade 1 Science curriculum', 7),
(63, 'Araling Panlipunan', 'Grade 1 Araling Panlipunan curriculum', 7),
(64, 'Arts', 'Grade 1 Arts curriculum', 7),
(65, 'Physical Education', 'Grade 1 Physical Education curriculum', 7),
(66, 'Health', 'Grade 1 Health curriculum', 7),
(67, 'Mathematics', 'Grade 2 Mathematics curriculum', 7),
(68, 'English', 'Grade 2 English curriculum', 8),
(69, 'Filipino', 'Grade 2 Filipino curriculum', 8),
(70, 'Science', 'Grade 2 Science curriculum', 8),
(71, 'Araling Panlipunan', 'Grade 2 Araling Panlipunan curriculum', 8),
(72, 'Music', 'Grade 2 Music curriculum', 8),
(73, 'Arts', 'Grade 2 Arts curriculum', 8),
(74, 'Physical Education', 'Grade 2 Physical Education curriculum', 8),
(75, 'Health', 'Grade 2 Health curriculum', 8),
(76, 'Mathematics', 'Grade 3 Mathematics curriculum', 8),
(77, 'English', 'Grade 3 English curriculum', 9),
(78, 'Filipino', 'Grade 3 Filipino curriculum', 9),
(79, 'Science', 'Grade 3 Science curriculum', 9),
(80, 'Araling Panlipunan', 'Grade 3 Araling Panlipunan curriculum', 9),
(81, 'Music', 'Grade 3 Music curriculum', 9),
(82, 'Arts', 'Grade 3 Arts curriculum', 9),
(83, 'Physical Education', 'Grade 3 Physical Education curriculum', 9),
(84, 'Health', 'Grade 3 Health curriculum', 9),
(85, 'Mathematics', 'Grade 4 Mathematics curriculum', 10),
(86, 'English', 'Grade 4 English curriculum', 10),
(87, 'Filipino', 'Grade 4 Filipino curriculum', 10),
(88, 'Science', 'Grade 4 Science curriculum', 10),
(89, 'Araling Panlipunan', 'Grade 4 Araling Panlipunan curriculum', 10),
(90, 'Music', 'Grade 4 Music curriculum', 10),
(91, 'Arts', 'Grade 4 Arts curriculum', 10),
(92, 'Physical Education', 'Grade 4 Physical Education curriculum', 10),
(93, 'Health', 'Grade 4 Health curriculum', 10),
(94, 'Technology and Livelihood Education', 'Grade 4 Technology and Livelihood Education curriculum', 10),
(95, 'Mathematics', 'Grade 5 Mathematics curriculum', 14),
(96, 'English', 'Grade 5 English curriculum', 14),
(97, 'Filipino', 'Grade 5 Filipino curriculum', 14),
(98, 'Science', 'Grade 5 Science curriculum', 14),
(99, 'Araling Panlipunan', 'Grade 5 Araling Panlipunan curriculum', 14),
(100, 'Music', 'Grade 5 Music curriculum', 14),
(101, 'Arts', 'Grade 5 Arts curriculum', 14),
(102, 'Physical Education', 'Grade 5 Physical Education curriculum', 14),
(103, 'Health', 'Grade 5 Health curriculum', 14),
(104, 'Technology and Livelihood Education', 'Grade 5 Technology and Livelihood Education curriculum', 14),
(105, 'Mathematics', 'Grade 6 Mathematics curriculum', 15),
(106, 'English', 'Grade 6 English curriculum', 15),
(107, 'Filipino', 'Grade 6 Filipino curriculum', 15),
(108, 'Science', 'Grade 6 Science curriculum', 15),
(109, 'Araling Panlipunan', 'Grade 6 Araling Panlipunan curriculum', 15),
(110, 'Music', 'Grade 6 Music curriculum', 15),
(111, 'Arts', 'Grade 6 Arts curriculum', 15),
(112, 'Physical Education', 'Grade 6 Physical Education curriculum', 15),
(113, 'Health', 'Grade 6 Health curriculum', 15),
(114, 'Technology and Livelihood Education', 'Grade 6 Technology and Livelihood Education curriculum', 15);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `Account`
--
ALTER TABLE `Account`
  ADD PRIMARY KEY (`Account_ID`),
  ADD KEY `Profile_ID` (`Profile_ID`),
  ADD KEY `Role_ID` (`Role_ID`),
  ADD KEY `Login_ID` (`Login_ID`);

--
-- Indexes for table `Account_Details`
--
ALTER TABLE `Account_Details`
  ADD PRIMARY KEY (`Account_ID`);

--
-- Indexes for table `Assigned_Subject`
--
ALTER TABLE `Assigned_Subject`
  ADD PRIMARY KEY (`Instructor_ID`,`Subject_ID`),
  ADD KEY `Subject_ID` (`Subject_ID`);

--
-- Indexes for table `Class`
--
ALTER TABLE `Class`
  ADD PRIMARY KEY (`Class_ID`),
  ADD KEY `Clearance_ID` (`Clearance_ID`),
  ADD KEY `Room_ID` (`Room_ID`);

--
-- Indexes for table `Classroom`
--
ALTER TABLE `Classroom`
  ADD PRIMARY KEY (`Room_ID`);

--
-- Indexes for table `Clearance`
--
ALTER TABLE `Clearance`
  ADD PRIMARY KEY (`Clearance_ID`);

--
-- Indexes for table `Contacts`
--
ALTER TABLE `Contacts`
  ADD PRIMARY KEY (`Contacts_ID`);

--
-- Indexes for table `Enrollment`
--
ALTER TABLE `Enrollment`
  ADD PRIMARY KEY (`Class_ID`,`Student_ID`),
  ADD KEY `Student_ID` (`Student_ID`);

--
-- Indexes for table `Guardian`
--
ALTER TABLE `Guardian`
  ADD PRIMARY KEY (`Guardian_ID`);

--
-- Indexes for table `Guardian_Relations`
--
ALTER TABLE `Guardian_Relations`
  ADD PRIMARY KEY (`Guardian_ID`),
  ADD KEY `Student_ID` (`Student_ID`);

--
-- Indexes for table `Instructor`
--
ALTER TABLE `Instructor`
  ADD PRIMARY KEY (`Instructor_ID`),
  ADD KEY `Profile_ID` (`Profile_ID`);

--
-- Indexes for table `Location`
--
ALTER TABLE `Location`
  ADD PRIMARY KEY (`Location_ID`);

--
-- Indexes for table `Login_Info`
--
ALTER TABLE `Login_Info`
  ADD PRIMARY KEY (`Login_ID`);

--
-- Indexes for table `Profile`
--
ALTER TABLE `Profile`
  ADD PRIMARY KEY (`Profile_ID`),
  ADD KEY `Location_ID` (`Location_ID`),
  ADD KEY `Contacts_ID` (`Contacts_ID`);

--
-- Indexes for table `Profile_Bio`
--
ALTER TABLE `Profile_Bio`
  ADD PRIMARY KEY (`Profile_ID`);

--
-- Indexes for table `Record`
--
ALTER TABLE `Record`
  ADD PRIMARY KEY (`Record_ID`),
  ADD KEY `Student_ID` (`Student_ID`),
  ADD KEY `Instructor_ID` (`Instructor_ID`),
  ADD KEY `Subject_ID` (`Subject_ID`);

--
-- Indexes for table `Record_Details`
--
ALTER TABLE `Record_Details`
  ADD PRIMARY KEY (`Record_ID`),
  ADD KEY `Clearance_ID` (`Clearance_ID`);

--
-- Indexes for table `Role`
--
ALTER TABLE `Role`
  ADD PRIMARY KEY (`Role_ID`),
  ADD UNIQUE KEY `UQ_EMAIL` (`Email`);

--
-- Indexes for table `Schedule`
--
ALTER TABLE `Schedule`
  ADD PRIMARY KEY (`Schedule_ID`),
  ADD KEY `Instructor_ID` (`Instructor_ID`),
  ADD KEY `Class_ID` (`Class_ID`),
  ADD KEY `Subject_ID` (`Subject_ID`);

--
-- Indexes for table `schedule_details`
--
ALTER TABLE `schedule_details`
  ADD PRIMARY KEY (`Schedule_ID`,`Day`,`Start_Time`,`End_Time`);

--
-- Indexes for table `Student`
--
ALTER TABLE `Student`
  ADD PRIMARY KEY (`Student_ID`),
  ADD KEY `Profile_ID` (`Profile_ID`);

--
-- Indexes for table `Subject`
--
ALTER TABLE `Subject`
  ADD PRIMARY KEY (`Subject_ID`),
  ADD KEY `Clearance_ID` (`Clearance_ID`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `Account`
--
ALTER TABLE `Account`
  MODIFY `Account_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT for table `Class`
--
ALTER TABLE `Class`
  MODIFY `Class_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;

--
-- AUTO_INCREMENT for table `Classroom`
--
ALTER TABLE `Classroom`
  MODIFY `Room_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `Clearance`
--
ALTER TABLE `Clearance`
  MODIFY `Clearance_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;

--
-- AUTO_INCREMENT for table `Contacts`
--
ALTER TABLE `Contacts`
  MODIFY `Contacts_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `Enrollment`
--
ALTER TABLE `Enrollment`
  MODIFY `Class_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT for table `Guardian`
--
ALTER TABLE `Guardian`
  MODIFY `Guardian_ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `Instructor`
--
ALTER TABLE `Instructor`
  MODIFY `Instructor_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `Location`
--
ALTER TABLE `Location`
  MODIFY `Location_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `Login_Info`
--
ALTER TABLE `Login_Info`
  MODIFY `Login_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `Profile`
--
ALTER TABLE `Profile`
  MODIFY `Profile_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=52;

--
-- AUTO_INCREMENT for table `Record`
--
ALTER TABLE `Record`
  MODIFY `Record_ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `Role`
--
ALTER TABLE `Role`
  MODIFY `Role_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT for table `Schedule`
--
ALTER TABLE `Schedule`
  MODIFY `Schedule_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `Student`
--
ALTER TABLE `Student`
  MODIFY `Student_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `Subject`
--
ALTER TABLE `Subject`
  MODIFY `Subject_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=115;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `Account`
--
ALTER TABLE `Account`
  ADD CONSTRAINT `account_ibfk_1` FOREIGN KEY (`Profile_ID`) REFERENCES `Profile` (`Profile_ID`),
  ADD CONSTRAINT `account_ibfk_2` FOREIGN KEY (`Role_ID`) REFERENCES `Role` (`Role_ID`),
  ADD CONSTRAINT `account_ibfk_3` FOREIGN KEY (`Login_ID`) REFERENCES `Login_Info` (`Login_ID`);

--
-- Constraints for table `Account_Details`
--
ALTER TABLE `Account_Details`
  ADD CONSTRAINT `account_details_ibfk_1` FOREIGN KEY (`Account_ID`) REFERENCES `Account` (`Account_ID`);

--
-- Constraints for table `Assigned_Subject`
--
ALTER TABLE `Assigned_Subject`
  ADD CONSTRAINT `assigned_subject_ibfk_1` FOREIGN KEY (`Instructor_ID`) REFERENCES `Instructor` (`Instructor_ID`),
  ADD CONSTRAINT `assigned_subject_ibfk_2` FOREIGN KEY (`Subject_ID`) REFERENCES `Subject` (`Subject_ID`);

--
-- Constraints for table `Class`
--
ALTER TABLE `Class`
  ADD CONSTRAINT `class_ibfk_1` FOREIGN KEY (`Clearance_ID`) REFERENCES `Clearance` (`Clearance_ID`),
  ADD CONSTRAINT `class_ibfk_2` FOREIGN KEY (`Room_ID`) REFERENCES `Classroom` (`Room_ID`);

--
-- Constraints for table `Enrollment`
--
ALTER TABLE `Enrollment`
  ADD CONSTRAINT `enrollment_ibfk_1` FOREIGN KEY (`Class_ID`) REFERENCES `Class` (`Class_ID`),
  ADD CONSTRAINT `enrollment_ibfk_2` FOREIGN KEY (`Student_ID`) REFERENCES `Student` (`Student_ID`);

--
-- Constraints for table `Guardian_Relations`
--
ALTER TABLE `Guardian_Relations`
  ADD CONSTRAINT `guardian_relations_ibfk_1` FOREIGN KEY (`Guardian_ID`) REFERENCES `Guardian` (`Guardian_ID`),
  ADD CONSTRAINT `guardian_relations_ibfk_2` FOREIGN KEY (`Student_ID`) REFERENCES `Student` (`Student_ID`);

--
-- Constraints for table `Instructor`
--
ALTER TABLE `Instructor`
  ADD CONSTRAINT `instructor_ibfk_1` FOREIGN KEY (`Profile_ID`) REFERENCES `Profile` (`Profile_ID`);

--
-- Constraints for table `Profile`
--
ALTER TABLE `Profile`
  ADD CONSTRAINT `profile_ibfk_1` FOREIGN KEY (`Location_ID`) REFERENCES `Location` (`Location_ID`),
  ADD CONSTRAINT `profile_ibfk_2` FOREIGN KEY (`Contacts_ID`) REFERENCES `Contacts` (`Contacts_ID`);

--
-- Constraints for table `Profile_Bio`
--
ALTER TABLE `Profile_Bio`
  ADD CONSTRAINT `profile_bio_ibfk_1` FOREIGN KEY (`Profile_ID`) REFERENCES `Profile` (`Profile_ID`);

--
-- Constraints for table `Record`
--
ALTER TABLE `Record`
  ADD CONSTRAINT `record_ibfk_1` FOREIGN KEY (`Student_ID`) REFERENCES `Student` (`Student_ID`),
  ADD CONSTRAINT `record_ibfk_2` FOREIGN KEY (`Instructor_ID`) REFERENCES `Instructor` (`Instructor_ID`),
  ADD CONSTRAINT `record_ibfk_3` FOREIGN KEY (`Subject_ID`) REFERENCES `Subject` (`Subject_ID`);

--
-- Constraints for table `Record_Details`
--
ALTER TABLE `Record_Details`
  ADD CONSTRAINT `record_details_ibfk_1` FOREIGN KEY (`Record_ID`) REFERENCES `Record` (`Record_ID`),
  ADD CONSTRAINT `record_details_ibfk_2` FOREIGN KEY (`Clearance_ID`) REFERENCES `Clearance` (`Clearance_ID`);

--
-- Constraints for table `Schedule`
--
ALTER TABLE `Schedule`
  ADD CONSTRAINT `schedule_ibfk_1` FOREIGN KEY (`Instructor_ID`) REFERENCES `Instructor` (`Instructor_ID`),
  ADD CONSTRAINT `schedule_ibfk_2` FOREIGN KEY (`Class_ID`) REFERENCES `Class` (`Class_ID`),
  ADD CONSTRAINT `schedule_ibfk_3` FOREIGN KEY (`Subject_ID`) REFERENCES `Subject` (`Subject_ID`);

--
-- Constraints for table `schedule_details`
--
ALTER TABLE `schedule_details`
  ADD CONSTRAINT `schedule_details_ibfk_1` FOREIGN KEY (`Schedule_ID`) REFERENCES `schedule` (`Schedule_ID`);

--
-- Constraints for table `Student`
--
ALTER TABLE `Student`
  ADD CONSTRAINT `student_ibfk_1` FOREIGN KEY (`Profile_ID`) REFERENCES `Profile` (`Profile_ID`);

--
-- Constraints for table `Subject`
--
ALTER TABLE `Subject`
  ADD CONSTRAINT `subject_ibfk_1` FOREIGN KEY (`Clearance_ID`) REFERENCES `Clearance` (`Clearance_ID`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
