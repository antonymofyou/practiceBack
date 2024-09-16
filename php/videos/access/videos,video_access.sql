CREATE TABLE IF NOT EXISTS `videos` (
  `video_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `video_filename` varchar(50) DEFAULT NULL,
  `video_shown_name` varchar(100) DEFAULT NULL,
  `video_description` mediumtext DEFAULT NULL,
  `video_chapter` varchar(50) DEFAULT NULL,
  `video_public` tinyint(1) DEFAULT NULL,
  `video_lesson_num` int(11) DEFAULT NULL,
  `video_obyaz` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`video_id`),
  UNIQUE KEY `video_id` (`video_id`,`video_filename`,`video_shown_name`),
  KEY `v_les_num` (`video_lesson_num`),
  KEY `v_chapter` (`video_chapter`),
  KEY `v_obyaz` (`video_obyaz`),
  KEY `v_public` (`video_public`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `video_access` (
  `video_id` int(10) UNSIGNED NOT NULL,
  `user_vk_id` bigint(20) UNSIGNED NOT NULL,
  `access` tinyint(1) DEFAULT 0,
  `views` int(10) UNSIGNED DEFAULT 0,
  `watched` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`video_id`,`user_vk_id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
