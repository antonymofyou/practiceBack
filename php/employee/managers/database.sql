CREATE TABLE `managers` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_vk_id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `type` varchar(30) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  INDEX `user_vk_id` (`user_vk_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `manager_pers_data` (
    `manager_id` INT NOT NULL,
    `field`  VARCHAR(255) NOT NULL,
    `value` TEXT NULL,
    `comment` TEXT NULL,
    PRIMARY KEY (`manager_id`, `field`),
    INDEX (`manager_id`)
) ENGINE=innodb DEFAULT CHARSET=utf8mb4;