CREATE TABLE `balance_now` (
  `bn_user_id` bigint(20) UNSIGNED NOT NULL,
  `bn_balance` int(11) DEFAULT 0,
  `bn_last_date` date DEFAULT NULL,
  `bn_last_time` time DEFAULT NULL,
  PRIMARY KEY (`bn_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `balance_story` (
  `bs_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `bs_user_id` bigint(20) UNSIGNED NOT NULL,
  `bs_date` date DEFAULT NULL,
  `bs_time` time DEFAULT NULL,
  `bs_type` varchar(10) DEFAULT NULL,
  `bs_value` int(11) DEFAULT NULL,
  `bs_order_id` int(10) UNSIGNED DEFAULT NULL,
  `bs_comment` mediumtext DEFAULT NULL,
  `bs_changer` bigint(20) UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`bs_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `crm_comments` (
  `crm_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_vk_id` bigint(20) UNSIGNED NOT NULL,
  `crm_comment` text DEFAULT NULL,
  `crm_date` date DEFAULT NULL,
  `crm_time` time DEFAULT NULL,
  `crm_editor` bigint(20) UNSIGNED NOT NULL,
  PRIMARY KEY (`crm_id`),
  KEY `crm_u_id` (`user_vk_id`),
  KEY `crm_date_ind` (`crm_date`),
  KEY `crm_u_editor` (`crm_editor`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `users` (
  `user_vk_id` bigint(20) UNSIGNED NOT NULL,
  `apple_id` varchar(64) DEFAULT NULL,
  `user_name` varchar(30) DEFAULT NULL,
  `user_surname` varchar(30) DEFAULT NULL,
  `user_otch` varchar(30) DEFAULT NULL,
  `user_bdate` date DEFAULT NULL,
  `user_type` varchar(30) DEFAULT NULL,
  `user_tarif` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `user_tarif_num` int(11) DEFAULT NULL,
  `with_mistake_work` int(11) NOT NULL DEFAULT 0,
  `user_zachet` tinyint(1) DEFAULT NULL,
  `user_payday` tinyint(4) NOT NULL DEFAULT 0,
  `user_link` varchar(40) DEFAULT NULL,
  `user_ava_link` text DEFAULT NULL,
  `user_class_number` int(10) UNSIGNED DEFAULT NULL,
  `user_start_course_date` date DEFAULT NULL,
  `user_blocked` tinyint(1) DEFAULT 0,
  `user_allow_bot` tinyint(1) DEFAULT NULL,
  `user_curator` bigint(20) UNSIGNED DEFAULT 0,
  `user_curator_dz` bigint(20) UNSIGNED DEFAULT 0,
  `user_curator_zach` bigint(20) UNSIGNED DEFAULT 0,
  `user_tel` varchar(20) DEFAULT NULL,
  `user_email` varchar(70) DEFAULT NULL,
  `user_referer` bigint(20) UNSIGNED DEFAULT NULL,
  `ballov` int(11) DEFAULT NULL,
  `user_region` smallint(6) NOT NULL DEFAULT 0,
  `black_design` tinyint(1) NOT NULL DEFAULT 0,
  `goal_ball` smallint(6) DEFAULT NULL,
  PRIMARY KEY (`user_vk_id`),
  UNIQUE KEY `user_vk_id` (`user_vk_id`),
  UNIQUE KEY `apple_id` (`apple_id`),
  KEY `ind_u_sur` (`user_surname`),
  KEY `ind_u_cur` (`user_curator`),
  KEY `apple_id_ind` (`apple_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `users_add` (
  `user_vk_id` bigint(20) UNSIGNED NOT NULL,
  `user_goal_ball` smallint(6) DEFAULT NULL,
  `user_goal_vuz` varchar(255) DEFAULT NULL,
  `goal_budzhet` smallint(6) DEFAULT NULL,
  `user_osobennosti` text DEFAULT NULL,
  PRIMARY KEY (`user_vk_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
