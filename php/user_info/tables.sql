CREATE TABLE IF NOT EXISTS `user-question` (
  `uq_id` varchar(30) NOT NULL,
  `user_vk_id` int(10) UNSIGNED NOT NULL,
  `q_id` int(10) UNSIGNED NOT NULL,
  `uq_nabrano` int(3) UNSIGNED DEFAULT NULL,
  `uq_max_balls` int(3) UNSIGNED DEFAULT NULL,
  `uq_status` int(1) UNSIGNED DEFAULT NULL,
  `uq_isdifficult` tinyint(1) NOT NULL DEFAULT 0,
  `uq_asked` int(10) UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`uq_id`),
  UNIQUE KEY `uq_id` (`uq_id`),
  KEY `ind_u_q` (`user_vk_id`,`q_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `zachets_auto` (
  `za_id` int(10) UNSIGNED NOT NULL,
  `za_date_start` date DEFAULT NULL,
  `za_deadline` date DEFAULT NULL,
  `za_max_time` int(10) UNSIGNED NOT NULL DEFAULT 20,
  `za_max_popitok` int(10) UNSIGNED NOT NULL DEFAULT 3,
  `za_questions` int(10) UNSIGNED NOT NULL DEFAULT 10,
  `za_max_errors` int(10) UNSIGNED NOT NULL DEFAULT 2,
  `za_lesson_numbers` mediumtext DEFAULT NULL,
  `za_showed` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`za_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `zachet_user` (
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `zachet_id` int(10) UNSIGNED NOT NULL,
  `zu_status` varchar(15) DEFAULT NULL,
  `zu_popitka` int(10) UNSIGNED DEFAULT NULL,
  `zu_q_num_now` int(10) UNSIGNED DEFAULT NULL,
  `zu_result` mediumtext DEFAULT NULL,
  `zu_date` date DEFAULT NULL,
  `zu_time_start` time DEFAULT NULL,
  `zu_time_end` time DEFAULT NULL,
  `zu_errors` int(10) UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`user_id`,`zachet_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
