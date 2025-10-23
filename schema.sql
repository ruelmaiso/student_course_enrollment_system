-- Updated schema with new tables and procedures
CREATE TABLE students(
	student_id INT NOT NULL PRIMARY KEY AUTO_INCREMENT,
	name VARCHAR(50) NOT NULL,
	age INT NOT NULL,
	email VARCHAR(100) UNIQUE NOT NULL,
	address VARCHAR(100) NOT NULL,
	allowance DECIMAL(10,2) DEFAULT 0.00
);

CREATE TABLE courses(
	course_id INT AUTO_INCREMENT PRIMARY KEY,
	course_name VARCHAR(100) NOT NULL,
	course_fee DECIMAL(10,2) DEFAULT 0.00
);

CREATE TABLE enrollments(
	student_id INT,
	course_id INT,
	enrollment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY(student_id, course_id),
	FOREIGN KEY(student_id) REFERENCES students(student_id),
	FOREIGN KEY(course_id) REFERENCES courses(course_id)
);

CREATE TABLE allowance_logs(
	log_id INT AUTO_INCREMENT PRIMARY KEY,
	student_id INT,
	old_allowance DECIMAL(10,2),
	new_allowance DECIMAL(10,2),
	changed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
	FOREIGN KEY(student_id) REFERENCES students(student_id)
);

CREATE TABLE payments(
	payment_id INT AUTO_INCREMENT PRIMARY KEY,
	student_id INT,
	course_id INT,
	amount DECIMAL(10,2),
	payment_date DATETIME DEFAULT CURRENT_TIMESTAMP,
	FOREIGN KEY(student_id) REFERENCES students(student_id),
	FOREIGN KEY(course_id) REFERENCES courses(course_id)
);

-- Stored Procedure for enrolling students
DELIMITER $$

CREATE PROCEDURE enroll_student(
IN p_student_id INT,
IN p_course_id INT)
BEGIN
INSERT INTO enrollments(
student_id, course_id, 
enrollment_date)
VALUES (p_student_id,
p_course_id, CURDATE());
END $$

-- Trigger to prevent duplicate enrollments
CREATE TRIGGER trg_prevent_duplicate_enrollment
BEFORE INSERT ON enrollments
FOR EACH ROW 
BEGIN 
IF EXISTS (SELECT 1 FROM enrollments
WHERE student_id = NEW.student_id
AND course_id = NEW.course_id)
THEN SIGNAL SQLSTATE '45000'
SET MESSAGE_TEXT = 'Duplicate enrollment not allowed';
END IF;
END $$

-- Trigger to log allowance changes
CREATE TRIGGER trg_log_allowance_change
AFTER UPDATE ON students
FOR EACH ROW
BEGIN
IF OLD.allowance != NEW.allowance THEN
INSERT INTO allowance_logs (student_id, old_allowance, new_allowance, changed_at)
VALUES (NEW.student_id, OLD.allowance, NEW.allowance, NOW());
END IF;
END $$

DELIMITER ;