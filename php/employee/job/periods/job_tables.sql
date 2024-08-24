CREATE TABLE `managers` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_vk_id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `type` varchar(30) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  INDEX (`user_vk_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `managers_job_periods` (
    `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT, -- ИД
    `manager_id` INT UNSIGNED NOT NULL, -- ИД сотрудника, ключ к сотрудникам
    `for_date` DATE NOT NULL, -- День периода, также ключ к отчёту
    `period_start` TIME NOT NULL, -- Время начала периода
    `period_end` TIME NOT NULL, -- Время конца периода
    `created_at` DATETIME DEFAULT now(),
    `updated_at` DATETIME DEFAULT now(),
    PRIMARY KEY (`id`) 
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `managers_job_reports` (
    `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT, -- ИД
    `manager_id` INT(10) UNSIGNED NOT NULL, -- ИД сотрудника, ключ к сотрудникам
    `for_date` DATE NOT NULL, -- День отчёта
    `work_time` TIME NOT NULL, -- Количество часов:минут работы в этот день
    `report` TEXT NULL, -- Сам отчёт
    `created_at` DATETIME DEFAULT now(),
    `updated_at` DATETIME DEFAULT now(),
    PRIMARY KEY (`id`),
    UNIQUE (`manager_id`, `for_date`) -- Нельзя составлять на один день несколько отчётов
) ENGINE=innodb DEFAULT CHARSET=utf8mb4;