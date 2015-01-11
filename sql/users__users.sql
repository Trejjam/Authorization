-- phpMyAdmin SQL Dump
-- version 4.2.11
-- http://www.phpmyadmin.net
--
-- Počítač: localhost
-- Vytvořeno: Úte 16. pro 2014, 13:04
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
-- Struktura tabulky `users__users`
--

CREATE TABLE IF NOT EXISTS `users__users` (
`id` int(11) NOT NULL,
  `status` enum('enable','disable') COLLATE utf8_czech_ci NOT NULL DEFAULT 'enable',
  `activated` enum('no','yes') COLLATE utf8_czech_ci NOT NULL DEFAULT 'no',
  `username` varchar(60) COLLATE utf8_czech_ci NOT NULL,
  `password` text COLLATE utf8_czech_ci NOT NULL,
  `date_created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

--
-- Klíče pro tabulku `users__users`
--
ALTER TABLE `users__users`
 ADD PRIMARY KEY (`id`), ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT pro tabulku `users__users`
--
ALTER TABLE `users__users`
MODIFY `id` int(11) NOT NULL AUTO_INCREMENT,AUTO_INCREMENT=1;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
