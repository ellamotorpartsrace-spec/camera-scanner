-- Ella Scanner Database Structure
-- Reconstructed: 2026-04-02
-- This file contains the complete database structure without ALTER TABLE statements.

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `ella_scanner`
--

-- --------------------------------------------------------

--
-- Table structure for table `scans`
--

DROP TABLE IF EXISTS `scan_history`;
DROP TABLE IF EXISTS `scans`;

CREATE TABLE `scans` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code_value` varchar(255) NOT NULL,
  `code_type` enum('QR','BARCODE','MANUAL','UNKNOWN') NOT NULL DEFAULT 'UNKNOWN',
  `courier` varchar(50) DEFAULT NULL,
  `parcel_size` enum('POUCH','BULKY') DEFAULT 'POUCH',
  `platform` enum('Lazada','TikTok','Shopee','') NOT NULL DEFAULT '',
  `gs1_gtin` varchar(20) DEFAULT NULL,
  `gs1_batch` varchar(50) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `scanned_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `returned_at` timestamp NULL DEFAULT NULL,
  `update_count` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_code_value` (`code_value`),
  KEY `idx_scanned_at` (`scanned_at`),
  KEY `idx_code_type` (`code_type`),
  KEY `idx_gs1_gtin` (`gs1_gtin`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_scans_courier` (`courier`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `scan_history`
--

CREATE TABLE `scan_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `scan_id` int(11) NOT NULL COMMENT 'References scans.id',
  `code_value` varchar(255) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `scanned_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_scan_id` (`scan_id`),
  KEY `idx_code_value` (`code_value`),
  KEY `idx_scanned_at` (`scanned_at`),
  CONSTRAINT `fk_scan_history_scans` FOREIGN KEY (`scan_id`) REFERENCES `scans` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
