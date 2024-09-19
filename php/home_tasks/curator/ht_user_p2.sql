CREATE TABLE IF NOT EXISTS `ht_user` (
  `ht_user_id` varchar(30) DEFAULT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `ht_number` smallint(5) UNSIGNED NOT NULL,
  `ht_user_ballov_p1` int(10) UNSIGNED DEFAULT NULL,
  `ht_user_maxballov_p1` int(10) UNSIGNED DEFAULT NULL,
  `q_nums_p1` smallint(5) UNSIGNED DEFAULT NULL,
  `q_num_now_p1` int(10) UNSIGNED DEFAULT NULL,
  `q_num_last_p1` int(10) UNSIGNED DEFAULT NULL,
  `ht_user_date_p1` date DEFAULT NULL,
  `ht_user_time_p1` time DEFAULT NULL,
  `ht_user_result1` mediumtext DEFAULT NULL,
  `ht_user_status_p1` varchar(20) DEFAULT NULL,
  `ht_user_ballov_p2` int(10) UNSIGNED DEFAULT NULL,
  `ht_user_maxballov_p2` int(10) UNSIGNED DEFAULT NULL,
  `ht_user_date_p2` date DEFAULT NULL,
  `ht_user_time_p2` time DEFAULT NULL,
  `q_nums_p2` smallint(5) UNSIGNED DEFAULT NULL,
  `q_num_now_p2` int(10) UNSIGNED DEFAULT NULL,
  `ht_user_result2` longtext DEFAULT NULL,
  `ht_user_status_p2` varchar(20) DEFAULT NULL,
  `ht_user_checker` bigint(20) UNSIGNED DEFAULT NULL,
  `ht_user_check_date` date DEFAULT NULL,
  `ht_user_tarif_num` int(11) DEFAULT NULL,
  `ht_user_apell_stud` mediumtext DEFAULT NULL,
  `ht_user_apell_teacher` mediumtext DEFAULT NULL,
  `timer_updated_at_p1` datetime(4) DEFAULT NULL,
  `timer_seconds_p1` double NOT NULL DEFAULT 0,
  `timer_updated_at_p2` datetime(4) DEFAULT NULL,
  `timer_seconds_p2` double NOT NULL DEFAULT 0,
  PRIMARY KEY (`user_id`,`ht_number`) USING BTREE,
  KEY `ind_status_p1` (`ht_user_status_p1`),
  KEY `ind_status_p2` (`ht_user_status_p2`),
  KEY `ht_tar_num` (`ht_user_tarif_num`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `ht_user_p2` (
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `ht_number` smallint(5) UNSIGNED NOT NULL,
  `q_number` tinyint(3) UNSIGNED NOT NULL,
  `q_id` smallint(5) UNSIGNED DEFAULT NULL,
  `q_task_number` tinyint(3) UNSIGNED DEFAULT NULL,
  `real_ball` tinyint(3) UNSIGNED DEFAULT NULL,
  `user_answer` text DEFAULT NULL,
  `user_old_answer` text DEFAULT NULL,
  `user_ball` tinyint(3) UNSIGNED DEFAULT NULL,
  `teacher_comment` text DEFAULT NULL,
  `teacher_json` text DEFAULT NULL,
  `is_checked` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`user_id`,`ht_number`,`q_number`),
  KEY `ht_p2` (`user_id`,`ht_number`),
  KEY `ht_p2_user` (`user_id`),
  KEY `ht_p2_h_q` (`ht_number`,`q_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `questions2` (
  `q2_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `q2_question` mediumtext DEFAULT NULL,
  `q2_obyaz_dz` tinyint(1) NOT NULL DEFAULT 1,
  `q2_answer` mediumtext DEFAULT NULL,
  `q2_description` mediumtext DEFAULT NULL,
  `q2_task_number` int(10) UNSIGNED DEFAULT NULL,
  `q2_chapter` varchar(50) DEFAULT NULL,
  `q2_lesson_num` int(10) UNSIGNED DEFAULT NULL,
  `q2_public` tinyint(1) DEFAULT NULL,
  PRIMARY KEY (`q2_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

 /* Словарь со следующими полями:
        Поля вопроса:
        - q2Id                  - ИД вопроса
        - q2Question            - Текст вопроса
        - q2ObyazDz             - Обязательность задания(0/1)
        - isProbnik             - Является ли задание пробником(0/1)

        Поля ответа:
        - htUserId              - ИД задания ученику
        - htNumber              - Номер задания
        - qNumber               - Номер вопроса
        - qId                   - ИД вопроса
        - realBall              - Максимальное количество баллов за задание
        - userAnswer            - Ответ ученика
        - teacherJson           - JSON с информацией о тексте(??), содержит матрицу с полями: start_pos, finish_pos, color, zacherkn, cur_subcomment
        - isChecked             - Проверена ли работа(0/1)
        - htUserChecker         - ИД проверяющего 
        - checker               - Фамилия и имя проверяющего
        - htUserStatusP2        - Статус домашнего задания: Выполняется, Готово, Отклонен, Самопров, Проверено, Аппеляция
        - htUserTarifNum        - Тариф ученика(1: Демократичный, 2: Авторитарный, 3: Тоталитарный)
        - htUserApellStud       - Текст аппеляции ученика(Только если статус = Аппеляция)
        - htUserApellTeacher    - Комментарий учителя к аппеляции(Только если статус = Аппеляция)
    
        Поля пользователя:
        - userVkId              - ИД ВК отвечающего ученика
        - userName              - Имя ученика
        - userSurname           - Фамилия ученика
        - userTarifNum          - Тариф ученика(1: Демократичный, 2: Авторитарный, 3: Тоталитарный)
        - userGoalBall          - Желаемые баллы??
        - userCurator           - ИД куратора ученика
        - userCuratorDz         - ИД куратора ученика по ДЗ
    */