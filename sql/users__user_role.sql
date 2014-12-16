-- phpMyAdmin SQL Dump
-- version 4.2.11
-- http://www.phpmyadmin.net
--
-- Počítač: localhost
-- Vytvořeno: Úte 16. pro 2014, 13:06
-- Verze serveru: 5.5.40-MariaDB-0ubuntu0.14.10.1
-- Verze PHP: 5.5.12-2ubuntu4.1

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;

-- --------------------------------------------------------

--
-- Struktura tabulky `users__user_role`
--

CREATE TABLE IF NOT EXISTS `users__user_role` (
`id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `role_id` int(11) NOT NULL
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

--
-- Klíče pro tabulku `users__user_role`
--
ALTER TABLE `users__user_role`
 ADD PRIMARY KEY (`id`), ADD KEY `user_id` (`user_id`), ADD KEY `role_id` (`role_id`);

--
-- AUTO_INCREMENT pro tabulku `users__user_role`
--
ALTER TABLE `users__user_role`
MODIFY `id` int(11) NOT NULL AUTO_INCREMENT,AUTO_INCREMENT=1;

--
-- Omezení pro tabulku `users__user_role`
--
ALTER TABLE `users__user_role`
ADD CONSTRAINT `users__user_role_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users__users` (`id`) ON UPDATE CASCADE,
ADD CONSTRAINT `users__user_role_ibfk_2` FOREIGN KEY (`role_id`) REFERENCES `users__roles` (`id`) ON UPDATE CASCADE;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
