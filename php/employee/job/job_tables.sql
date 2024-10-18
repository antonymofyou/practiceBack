# Таблица Рабочих дней сотрудников
CREATE TABLE `managers_job_days` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT, -- ИД
    `manager_id` INT UNSIGNED NOT NULL, -- ИД сотрудника, ключ к сотрудникам
    `date` DATE NOT NULL, -- дата, к которой привязан день
    `report` TEXT NULL, -- отчет за день (заполняется в рабочий день либо позже)
    `comment` TEXT NULL, -- комментарий для дня (заполняется в любое время)
    `spent_time` INT NOT NULL DEFAULT 0, -- минуты потраченного времени за день (пользователь сам устанавливает, может не соответствовать периодам работы)
    `is_weekend` BOOLEAN NOT NULL, -- является ли этот день выходным
    `updated_at` DATETIME NOT NULL DEFAULT NOW(),
    `created_at` DATETIME NOT NULL DEFAULT NOW(),
    PRIMARY KEY (`id`),
    UNIQUE (`manager_id`, `date`) -- сотрудник не может создать для себя два рабочих дня на одинаковую дату
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

# Таблица Периодов работы для конкретного рабочего дня
CREATE TABLE `managers_job_time_periods` (
    `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT, -- ИД
    `day_id` INT UNSIGNED NOT NULL, -- ИД дня работы, к которому привязан период
    `period_start` TIME NOT NULL, -- Время начала периода
    `period_end` TIME NOT NULL, -- Время конца периода
    `created_at` DATETIME DEFAULT NOW(),
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
