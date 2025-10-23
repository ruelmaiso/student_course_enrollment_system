CREATE TABLE students(
	student_id INT NOT NULL PRIMARY KEY AUTO_INCREMENT,
	name VARCHAR(50) NOT NULL,
	age INT NOT NULL,
	email VARCHAR(100) UNIQUE NOT NULL,
	address VARCHAR(100) NOT NULL,
	allowance INT
);

CREATE TABLE courses(
	course_id INT AUTO_INCREMENT PRIMARY KEY,
	course_name VARCHAR(100) NOT NULL
);

CREATE TABLE enrollments(
	student_id INT,
	course_id INT,
	enrollment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY(student_id, course_id),
	FOREIGN KEY(student_id) REFERENCES students(student_id),
	FOREIGN KEY(course_id) REFERENCES courses(course_id)
);
