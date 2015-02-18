-- phpMyAdmin SQL Dump
-- version 4.2.11
-- http://www.phpmyadmin.net
--
-- Počítač: localhost
-- Vytvořeno: Ned 11. led 2015, 21:23
-- Verze serveru: 5.5.40-MariaDB-0ubuntu0.14.10.1
-- Verze PHP: 5.5.12-2ubuntu4.1

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;

--
-- Struktura tabulky `users__identity_hash`
--

CREATE TABLE IF NOT EXISTS `users__identity_hash` (
`id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `hash` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
	`ip` VARCHAR(50)
		 COLLATE utf8mb4_unicode_ci NOT NULL,
  `action` enum('none','reload','logout','destroyed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'none'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Klíče pro tabulku `users__identity_hash`
--
ALTER TABLE `users__identity_hash`
 ADD PRIMARY KEY (`id`), ADD UNIQUE KEY `hash` (`hash`), ADD KEY `user_id` (`user_id`);

--
-- AUTO_INCREMENT pro tabulku `users__identity_hash`
--
ALTER TABLE `users__identity_hash`
MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Omezení pro tabulku `users__identity_hash`
--
ALTER TABLE `users__identity_hash`
ADD CONSTRAINT `users__identity_hash_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users__users` (`id`) ON UPDATE CASCADE;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
