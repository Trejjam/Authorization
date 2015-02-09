-- phpMyAdmin SQL Dump
-- version 4.2.11
-- http://www.phpmyadmin.net
--
-- Počítač: localhost
-- Vytvořeno: Stř 31. pro 2014, 13:49
-- Verze serveru: 5.5.40-MariaDB-0ubuntu0.14.10.1
-- Verze PHP: 5.5.12-2ubuntu4.1

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;

--
-- Struktura tabulky `users__user_request`
--

CREATE TABLE IF NOT EXISTS `users__user_request` (
`id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `hash` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` enum('activate','lostPassword') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `used` enum('yes','no') NOT NULL DEFAULT 'no',
  `timeout` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Klíče pro tabulku `users__user_request`
--
ALTER TABLE `users__user_request`
 ADD PRIMARY KEY (`id`), ADD KEY `user_id` (`user_id`);

--
-- AUTO_INCREMENT pro tabulku `users__user_request`
--
ALTER TABLE `users__user_request`
MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Omezení pro tabulku `users__user_request`
--
ALTER TABLE `users__user_request`
ADD CONSTRAINT `users__user_request_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users__users` (`id`) ON UPDATE CASCADE;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
