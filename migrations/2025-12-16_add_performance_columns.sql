-- Aligns performance_scores table with the fields used by score_employee.php
-- Run this on the target database (user_auth). Safe to re-run; uses IF NOT EXISTS.

ALTER TABLE `performance_scores`
  ADD COLUMN IF NOT EXISTS `Work Performance_q` INT DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `Work Performance_e` INT DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `Work Performance_t` INT DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `Work Performance_a` DECIMAL(5,2) DEFAULT NULL,

  ADD COLUMN IF NOT EXISTS `Cooperation & Teamwork_q` INT DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `Cooperation & Teamwork_e` INT DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `Cooperation & Teamwork_t` INT DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `Cooperation & Teamwork_a` DECIMAL(5,2) DEFAULT NULL,

  ADD COLUMN IF NOT EXISTS `Communication_q` INT DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `Communication_e` INT DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `Communication_t` INT DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `Communication_a` DECIMAL(5,2) DEFAULT NULL,

  ADD COLUMN IF NOT EXISTS `Dependability (Attendance & Commitment)_q` INT DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `Dependability (Attendance & Commitment)_e` INT DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `Dependability (Attendance & Commitment)_t` INT DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `Dependability (Attendance & Commitment)_a` DECIMAL(5,2) DEFAULT NULL,

  ADD COLUMN IF NOT EXISTS `Initiative_q` INT DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `Initiative_e` INT DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `Initiative_t` INT DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `Initiative_a` DECIMAL(5,2) DEFAULT NULL,

  ADD COLUMN IF NOT EXISTS `Professional Presentation_q` INT DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `Professional Presentation_e` INT DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `Professional Presentation_t` INT DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `Professional Presentation_a` DECIMAL(5,2) DEFAULT NULL,

  ADD COLUMN IF NOT EXISTS `total` DECIMAL(10,2) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `average` DECIMAL(5,2) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `score` INT DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `evaluation_date` DATETIME DEFAULT CURRENT_TIMESTAMP,
  ADD COLUMN IF NOT EXISTS `comments` TEXT,
  ADD COLUMN IF NOT EXISTS `recommendation` VARCHAR(20) DEFAULT NULL;
