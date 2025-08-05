-- Sample data for Student Attendance System
USE student_attendance;

-- Insert sample teachers
INSERT INTO teachers (name, email, password, role) VALUES
('Admin User', 'admin@school.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin'),
('John Teacher', 'john@school.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'teacher'),
('Jane Smith', 'jane@school.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'teacher');

-- Insert sample classes
INSERT INTO classes (class_name, teacher_id, description) VALUES
('10A - Mathematics', 2, 'Grade 10A Mathematics Class'),
('10B - Physics', 3, 'Grade 10B Physics Class'),
('11A - Chemistry', 2, 'Grade 11A Chemistry Class');

-- Insert sample students
INSERT INTO students (nis, name, email, class, password) VALUES
('2024001', 'Ahmad Rizki', 'ahmad@student.com', '10A', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
('2024002', 'Siti Nurhaliza', 'siti@student.com', '10A', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
('2024003', 'Budi Santoso', 'budi@student.com', '10B', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
('2024004', 'Dewi Lestari', 'dewi@student.com', '10B', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
('2024005', 'Eko Prasetyo', 'eko@student.com', '11A', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');

-- Insert sample attendance records
INSERT INTO attendance (student_id, class_id, date, time_in, status) VALUES
(1, 1, CURDATE(), '08:00:00', 'present'),
(2, 1, CURDATE(), '08:05:00', 'present'),
(3, 2, CURDATE(), '08:15:00', 'late'),
(4, 2, CURDATE(), '08:00:00', 'present');

-- Note: Default password for all users is 'password'
-- In production, users should change their passwords immediately
