-- School MIS database for the PHP application in this project.
-- Import this file with phpMyAdmin or the MySQL/MariaDB command line.
-- Starter login: username admin, password School@123. Change it after login.

CREATE DATABASE IF NOT EXISTS `school_mis`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `school_mis`;

CREATE TABLE IF NOT EXISTS `users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(100) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('admin', 'teacher', 'parent') NOT NULL DEFAULT 'teacher',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `students` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(191) NOT NULL,
  `class` VARCHAR(100) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_students_name` (`name`),
  KEY `idx_students_class` (`class`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `teachers` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(191) NOT NULL,
  `subject` VARCHAR(191) DEFAULT NULL,
  `present` TINYINT(1) NOT NULL DEFAULT 0,
  `last_updated` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_teachers_present` (`present`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `results` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_id` INT UNSIGNED NOT NULL,
  `subject` VARCHAR(191) NOT NULL,
  `score` INT NOT NULL,
  `created_by` INT UNSIGNED DEFAULT NULL,
  `term` ENUM('1st', '2nd', '3rd') NOT NULL DEFAULT '1st',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_results_student_id` (`student_id`),
  KEY `idx_results_created_by` (`created_by`),
  KEY `idx_results_created_at` (`created_at`),
  CONSTRAINT `fk_results_student`
    FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_results_created_by`
    FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `absences` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_id` INT UNSIGNED NOT NULL,
  `date` DATE NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_absences_student_date` (`student_id`, `date`),
  CONSTRAINT `fk_absences_student`
    FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `teacher_attendance` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `teacher_id` INT UNSIGNED NOT NULL,
  `attendance_date` DATE NOT NULL,
  `present` TINYINT(1) NOT NULL DEFAULT 0,
  `marked_by` INT UNSIGNED DEFAULT NULL,
  `remarks` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_teacher_attendance_date` (`teacher_id`, `attendance_date`),
  KEY `idx_teacher_attendance_date` (`attendance_date`),
  CONSTRAINT `fk_teacher_attendance_teacher`
    FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_teacher_attendance_user`
    FOREIGN KEY (`marked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `users` (`username`, `password`, `role`)
VALUES (
  'admin',
  '$2y$10$OEKpgOkUHKJ4nUVisDr3DuIEO1pVfyTt3iT3uy2UDhrGL2QgQw8Le',
  'admin'
);

CREATE OR REPLACE VIEW `teacher_performance_summary` AS
SELECT
  t.`id`,
  t.`name`,
  t.`subject`,
  COUNT(DISTINCT r.`id`) AS `total_results_added`,
  AVG(r.`score`) AS `average_student_score`,
  MAX(r.`score`) AS `highest_score`,
  MIN(r.`score`) AS `lowest_score`,
  COUNT(CASE WHEN r.`score` >= 80 THEN 1 END) AS `excellent_count`,
  COUNT(CASE WHEN r.`score` >= 60 AND r.`score` < 80 THEN 1 END) AS `good_count`,
  COUNT(CASE WHEN r.`score` >= 40 AND r.`score` < 60 THEN 1 END) AS `average_count`,
  COUNT(CASE WHEN r.`score` < 40 THEN 1 END) AS `needs_improvement_count`,
  MAX(r.`created_at`) AS `last_result_added`,
  (
    SELECT COUNT(*)
    FROM `teacher_attendance` ta
    WHERE ta.`teacher_id` = t.`id` AND ta.`present` = 1
  ) AS `days_present`,
  (
    SELECT COUNT(*)
    FROM `teacher_attendance` ta
    WHERE ta.`teacher_id` = t.`id` AND ta.`present` = 0
  ) AS `days_absent`
FROM `teachers` t
LEFT JOIN `results` r ON r.`created_by` = t.`id`
GROUP BY t.`id`, t.`name`, t.`subject`;
