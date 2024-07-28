CREATE TABLE `staff` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `vk_id` INT NOT NULL,
    `type` VARCHAR(255) NOT NULL,
    `first_name` VARCHAR(255) NOT NULL,
    `last_name` VARCHAR(255) NOT NULL,
    `middle_name` VARCHAR(255) NOT NULL,
    `blocked`     BOOLEAN NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`)
) ENGINE=innodb DEFAULT CHARSET=utf8mb4;

CREATE TABLE `staff_pers_data` (
    `staff_id` INT NOT NULL,
    `field`  VARCHAR(255) NOT NULL,
    `value` TEXT NULL,
    `comment` TEXT NULL,
    PRIMARY KEY (`user_id`, `field`),
    INDEX (`user_id`)
) ENGINE=innodb DEFAULT CHARSET=utf8mb4;