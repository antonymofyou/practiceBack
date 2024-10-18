CREATE TABLE `tickets_dz` (
  `ticket_id` int(10) UNSIGNED NOT NULL,
  `lesson_number` int(10) UNSIGNED DEFAULT NULL,
  `task_number` int(10) UNSIGNED DEFAULT NULL,
  `type` varchar(15) DEFAULT NULL,
  `status` varchar(10) DEFAULT NULL,
  `quest_name` varchar(200) DEFAULT NULL,
  `user_vk_id` bigint(20) UNSIGNED NOT NULL,
  `importance` tinyint(3) UNSIGNED NOT NULL DEFAULT 5,
  `when_made` datetime DEFAULT NULL,
  `when_changed` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `tickets_mess_dz` (
  `mess_id` bigint(20) UNSIGNED NOT NULL,
  `ticket_id` int(10) UNSIGNED NOT NULL,
  `user_vk_id` bigint(20) UNSIGNED NOT NULL,
  `comment` text DEFAULT NULL,
  `comment_dtime` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `ticket_dz_user` (
  `ticket_id` int(10) UNSIGNED NOT NULL,
  `user_vk_id` bigint(20) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


ALTER TABLE `tickets_dz`
  ADD PRIMARY KEY (`ticket_id`),
  ADD KEY `tdz_u_id` (`user_vk_id`),
  ADD KEY `tdz_status` (`status`);

ALTER TABLE `tickets_mess_dz`
  ADD PRIMARY KEY (`mess_id`),
  ADD KEY `tmdz_id` (`ticket_id`),
  ADD KEY `tmdz_u_id` (`user_vk_id`),
  ADD KEY `c_dtimedz` (`comment_dtime`);

ALTER TABLE `ticket_dz_user`
  ADD PRIMARY KEY (`ticket_id`,`user_vk_id`);

ALTER TABLE `tickets_dz`
  MODIFY `ticket_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `tickets_mess_dz`
  MODIFY `mess_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;
