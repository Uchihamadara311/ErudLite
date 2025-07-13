<?php
class Database {
    private $host = 'localhost';
    private $dbname = 'erudlite_pms';
    private $username = 'root';
    private $password = '';
    private $pdo;
    
    public function connect() {
        if ($this->pdo === null) {
            try {
                $this->pdo = new PDO(
                    "mysql:host={$this->host};dbname={$this->dbname};charset=utf8",
                    $this->username,
                    $this->password,
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false
                    ]
                );
            } catch (PDOException $e) {
                die("Database connection failed: " . $e->getMessage());
            }
        }
        return $this->pdo;
    }
    
    public function createTables() {
        $sql = "
            CREATE TABLE IF NOT EXISTS students (
                id INT AUTO_INCREMENT PRIMARY KEY,
                student_id VARCHAR(50) UNIQUE NOT NULL,
                student_name VARCHAR(100) NOT NULL,
                grade_level VARCHAR(20) NOT NULL,
                section VARCHAR(50) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS teachers (
                id INT AUTO_INCREMENT PRIMARY KEY,
                teacher_name VARCHAR(100) NOT NULL,
                subject VARCHAR(100),
                email VARCHAR(100),
                phone VARCHAR(20),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS report_cards (
                id INT AUTO_INCREMENT PRIMARY KEY,
                student_id VARCHAR(50) NOT NULL,
                school_year VARCHAR(20) NOT NULL,
                teacher_name VARCHAR(100) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE
            );

            CREATE TABLE IF NOT EXISTS grades (
                id INT AUTO_INCREMENT PRIMARY KEY,
                report_card_id INT NOT NULL,
                subject VARCHAR(100) NOT NULL,
                quarter_1 DECIMAL(5,2) DEFAULT NULL,
                quarter_2 DECIMAL(5,2) DEFAULT NULL,
                quarter_3 DECIMAL(5,2) DEFAULT NULL,
                quarter_4 DECIMAL(5,2) DEFAULT NULL,
                final_rating DECIMAL(5,2) DEFAULT NULL,
                remarks VARCHAR(50) DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (report_card_id) REFERENCES report_cards(id) ON DELETE CASCADE
            );

            CREATE TABLE IF NOT EXISTS attendance (
                id INT AUTO_INCREMENT PRIMARY KEY,
                student_id VARCHAR(50) NOT NULL,
                date DATE NOT NULL,
                status ENUM('Present', 'Absent', 'Late', 'Excused') DEFAULT 'Present',
                remarks TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE
            );

            CREATE TABLE IF NOT EXISTS subjects (
                id INT AUTO_INCREMENT PRIMARY KEY,
                subject_name VARCHAR(100) NOT NULL,
                grade_level VARCHAR(20) NOT NULL,
                description TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );
        ";
        
        try {
            $this->pdo->exec($sql);
            return true;
        } catch (PDOException $e) {
            error_log("Table creation failed: " . $e->getMessage());
            return false;
        }
    }
}
?>