-- Online Green Office Energy Monitoring System
-- Import this whole file once. It creates the database, the table and some sample rows.
-- phpMyAdmin: Import tab -> choose this file -> Import
-- Terminal:   mysql -u root < green_office_db.sql

CREATE DATABASE IF NOT EXISTS `green_office_db`;
USE `green_office_db`;

DROP TABLE IF EXISTS `tbl_reading`;

-- One row = one visit to the electricity meter.
-- meter_reading is the TOTAL number showing on the meter that day (in kWh),
-- not the amount used that month. The app works out the monthly usage by
-- subtracting the previous reading.
CREATE TABLE `tbl_reading` (
  `reading_id`    int(11) NOT NULL AUTO_INCREMENT,
  `reading_date`  date    NOT NULL,
  `meter_reading` int(11) NOT NULL,
  PRIMARY KEY (`reading_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `tbl_reading` (`reading_date`, `meter_reading`) VALUES
('2026-04-01', 12000),
('2026-05-01', 12850),
('2026-06-01', 13790),
('2026-07-01', 14760),
('2026-08-01', 15600),
('2026-09-01', 16380);
