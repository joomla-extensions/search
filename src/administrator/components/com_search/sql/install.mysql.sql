CREATE TABLE IF NOT EXISTS `#__core_log_searches` (
  `search_term` varchar(128) NOT NULL DEFAULT '',
  `hits` int unsigned NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;
