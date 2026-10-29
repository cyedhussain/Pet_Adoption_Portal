-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 10, 2026 at 02:12 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `pet_adoption`
--

-- --------------------------------------------------------

--
-- Table structure for table `accepted`
--

CREATE TABLE `accepted` (
  `accept_id` int(4) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `contact` varchar(20) NOT NULL,
  `cnic` varchar(20) NOT NULL,
  `address` varchar(255) NOT NULL,
  `pet_id` int(4) NOT NULL,
  `location_id` int(4) NOT NULL,
  `adopter_id` int(4) DEFAULT NULL,
  `request_time` datetime NOT NULL,
  `accept_time` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `admin`
--

CREATE TABLE `admin` (
  `admin_id` int(4) NOT NULL,
  `name` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `contact` varchar(20) NOT NULL,
  `password` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin`
--

INSERT INTO `admin` (`admin_id`, `name`, `email`, `contact`, `password`) VALUES
(1, 'Manager', 'manager@gmail.com', '0520-9076986', 'manager');

-- --------------------------------------------------------

--
-- Table structure for table `adopted`
--

CREATE TABLE `adopted` (
  `adopt_id` int(4) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `contact` varchar(20) NOT NULL,
  `cnic` varchar(20) NOT NULL,
  `address` varchar(100) NOT NULL,
  `pet_category` varchar(100) NOT NULL,
  `pet_name` varchar(100) NOT NULL,
  `pet_intro1` text NOT NULL,
  `pet_intro2` text NOT NULL,
  `image` varchar(255) NOT NULL,
  `location_city` varchar(100) NOT NULL,
  `location_address` varchar(100) NOT NULL,
  `location_email` varchar(255) NOT NULL,
  `location_contact` varchar(20) NOT NULL,
  `adopter_id` int(4) DEFAULT NULL,
  `pet_id` int(4) DEFAULT NULL,
  `request_time` datetime NOT NULL,
  `accept_time` datetime NOT NULL,
  `adopt_time` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `adopted`
--

INSERT INTO `adopted` (`adopt_id`, `adopter_id`, `name`, `email`, `contact`, `cnic`, `address`, `pet_category`, `pet_name`, `pet_intro1`, `pet_intro2`, `image`, `location_city`, `location_address`, `location_email`, `location_contact`, `request_time`, `accept_time`, `adopt_time`) VALUES
(1, 2, 'ahtisham', 'ahtisham@gmail.com', '05648790564', '1234567890123', 'lefke, eul', 'Dogs', 'Angel', 'Angel, a 4-year-old female Cocker Spaniel, is soft, sensitive, and full of quiet warmth. With long ears, expressive eyes, and a heart that’s learning to trust again, she lives up to her name in every way.', 'Fully vaccinated and healthy, Angel is ready to begin again — in a home that feels safe, steady, and full of love.', '2.jpg', 'Lefkosa', 'Street 9, Lefkosa', 'lefkosa@gmail.com', '0543-8965230', '2026-03-14 22:40:52', '2026-03-18 02:01:13', '2026-03-18 13:35:03');

-- --------------------------------------------------------

--
-- Table structure for table `adopter`
--

CREATE TABLE `adopter` (
  `adopter_id` int(4) NOT NULL,
  `name` varchar(255) NOT NULL,
  `cnic` bigint(13) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `adopter`
--

INSERT INTO `adopter` (`adopter_id`, `name`, `cnic`, `email`, `password`) VALUES
(2, 'ahtisham', 1234567890123, 'ahtisham@gmail.com', 'ahtisham');

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `category_id` int(4) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` varchar(255) NOT NULL,
  `image` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`category_id`, `name`, `description`, `image`) VALUES
(1, 'Cats', 'Curious, cuddly and independent companions who fill a home with quiet purrs and playful mischief.', '21.jpg'),
(2, 'Dogs', 'Loyal friends of every size and energy level, ready for walks, games and a family to protect.', '22.jpg'),
(3, 'Rabbits', 'Gentle, social and surprisingly smart little hoppers who love space to explore and fresh greens.', '23.jpg');

-- --------------------------------------------------------

--
-- Table structure for table `contact`
--

CREATE TABLE `contact` (
  `contact_id` int(4) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `message` varchar(500) NOT NULL,
  `answer` varchar(500) NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `contact`
--

INSERT INTO `contact` (`contact_id`, `name`, `email`, `message`, `answer`, `timestamp`) VALUES
(1, 'ahtisham', 'ahtisham@gmail.com', 'a new message from ahtisham', 'this is the respose', '2026-02-23 08:21:26'),
(4, 'ahtisham', 'ahtisham@gmail.com', 'A quick brown fox jumps over the white lazy dog. ghjkj', 'aws', '2026-03-18 22:08:18'),
(5, 'ahtisham', 'ahtisham@gmail.com', 'a ghi klm', 'ghyjh', '2026-04-01 13:12:18'),
(6, 'ahtisham', 'ahtisham@gmail.com', 'thiss is ahtisham here', 'gh', '2026-04-10 05:40:08');

-- --------------------------------------------------------

--
-- Table structure for table `locations`
--

CREATE TABLE `locations` (
  `location_id` int(4) NOT NULL,
  `city` varchar(100) NOT NULL,
  `address` varchar(255) NOT NULL,
  `email` varchar(100) NOT NULL,
  `contact` varchar(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `locations`
--

INSERT INTO `locations` (`location_id`, `city`, `address`, `email`, `contact`) VALUES
(1, 'Lefke', 'Gemikonangi, Lefke', 'lefke@gmail.com', '0543-7898654'),
(2, 'Lefkosa', 'Street 9, Lefkosa', 'lefkosa@gmail.com', '0543-8965230'),
(6, 'posty', 'street 67, moh tai', 'posty@gmail.com', '0545-8976120');

-- --------------------------------------------------------

--
-- Table structure for table `pets`
--

CREATE TABLE `pets` (
  `pet_id` int(4) NOT NULL,
  `category_id` int(4) NOT NULL,
  `name` varchar(50) NOT NULL,
  `intro1` text NOT NULL,
  `intro2` text NOT NULL,
  `image` varchar(255) NOT NULL,
  `location_id` int(4) NOT NULL,
  `requested` int(1) NOT NULL DEFAULT 0,
  `breed` varchar(100) NOT NULL DEFAULT '',
  `age` int(3) DEFAULT NULL,
  `gender` varchar(10) NOT NULL DEFAULT '',
  `size` varchar(10) NOT NULL DEFAULT '',
  `vaccinated` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `pets`
--

INSERT INTO `pets` (`pet_id`, `category_id`, `name`, `intro1`, `intro2`, `image`, `location_id`, `requested`, `breed`, `age`, `gender`, `size`, `vaccinated`) VALUES
(1, 2, 'Sebestian', 'Sebestian , a 4-year-old male German Shepherd, is the very image of strength and sensitivity. With his sharp focus, expressive eyes, and calm presence, he’s not just a dog — he’s a silent guardian waiting for someone to call his own.', 'He’s healthy, fully vaccinated, and ready to start again — this time, with a family who sees him not just as a pet, but as a partner.', '1.jpg', 1, 0, 'German Shepherd', 4, 'Male', 'Large', 1),
(3, 1, 'Jerry', 'Jerry, a charming male cat, is full of curiosity and gentle mischief. With his alert eyes and soft paws, he brings energy and affection in equal measure — a perfect blend of cuddle and play.', 'Vaccinated and healthy, Jerry is all set to make your space brighter. If you’re looking for love with whiskers and a little fun — Jerry is ready.', '5.jpg', 1, 0, 'Domestic Shorthair', 2, 'Male', 'Small', 1),
(9, 3, 'Jack', 'Jack is a fluffy little rabbit with a big personality. He loves to binky around the room, nibble on fresh greens and flop down for a nap once the zoomies are over.', 'Litter trained and very gentle, Jack would suit a calm home with time for daily floor play and lots of gentle petting.', 'download (5).jpg', 2, 0, 'Holland Lop', 1, 'Male', 'Small', 0);

-- --------------------------------------------------------

--
-- Table structure for table `requests`
--

CREATE TABLE `requests` (
  `request_id` int(4) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `contact` varchar(20) NOT NULL,
  `cnic` varchar(20) NOT NULL,
  `address` varchar(200) NOT NULL,
  `pet_id` int(4) NOT NULL,
  `location_id` int(4) NOT NULL,
  `adopter_id` int(4) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `reason` varchar(500) NOT NULL DEFAULT '',
  `request_time` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `favourites`
--

CREATE TABLE `favourites` (
  `fav_id` int(4) NOT NULL,
  `adopter_id` int(4) NOT NULL,
  `pet_id` int(4) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `chat_messages`
-- (one conversation per adopter with the shelter; opens once an application is approved)
--

CREATE TABLE `chat_messages` (
  `chat_id` int(11) NOT NULL,
  `adopter_id` int(4) NOT NULL,
  `sender` varchar(10) NOT NULL,
  `admin_id` int(4) DEFAULT NULL,
  `body` text NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `read_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Community: group chats the shelter creates and adds adopters to
--

CREATE TABLE `community_groups` (
  `group_id` int(11) NOT NULL,
  `name` varchar(60) NOT NULL,
  `description` varchar(200) NOT NULL DEFAULT '',
  `created_by` int(4) DEFAULT NULL,
  `admin_last_read_id` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `community_members` (
  `member_id` int(11) NOT NULL,
  `group_id` int(11) NOT NULL,
  `adopter_id` int(4) NOT NULL,
  `last_read_id` int(11) NOT NULL DEFAULT 0,
  `added_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `community_messages` (
  `message_id` int(11) NOT NULL,
  `group_id` int(11) NOT NULL,
  `sender` varchar(10) NOT NULL,
  `adopter_id` int(4) DEFAULT NULL,
  `admin_id` int(4) DEFAULT NULL,
  `body` text NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `resetpassword`
--

CREATE TABLE `resetpassword` (
  `reset_id` int(4) NOT NULL,
  `randToken` varchar(255) NOT NULL,
  `user_id` int(4) NOT NULL,
  `user` varchar(50) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `accepted`
--
ALTER TABLE `accepted`
  ADD PRIMARY KEY (`accept_id`);

--
-- Indexes for table `admin`
--
ALTER TABLE `admin`
  ADD PRIMARY KEY (`admin_id`);

--
-- Indexes for table `adopted`
--
ALTER TABLE `adopted`
  ADD PRIMARY KEY (`adopt_id`);

--
-- Indexes for table `adopter`
--
ALTER TABLE `adopter`
  ADD PRIMARY KEY (`adopter_id`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`category_id`);

--
-- Indexes for table `contact`
--
ALTER TABLE `contact`
  ADD PRIMARY KEY (`contact_id`);

--
-- Indexes for table `locations`
--
ALTER TABLE `locations`
  ADD PRIMARY KEY (`location_id`);

--
-- Indexes for table `pets`
--
ALTER TABLE `pets`
  ADD PRIMARY KEY (`pet_id`);

--
-- Indexes for table `requests`
--
ALTER TABLE `requests`
  ADD PRIMARY KEY (`request_id`);

--
-- Indexes for table `resetpassword`
--
ALTER TABLE `resetpassword`
  ADD PRIMARY KEY (`reset_id`);

--
-- Indexes for table `favourites`
--
ALTER TABLE `favourites`
  ADD PRIMARY KEY (`fav_id`),
  ADD UNIQUE KEY `adopter_pet` (`adopter_id`,`pet_id`);

--
-- Indexes for table `chat_messages`
--
ALTER TABLE `chat_messages`
  ADD PRIMARY KEY (`chat_id`),
  ADD KEY `adopter_thread` (`adopter_id`,`chat_id`),
  ADD KEY `unread` (`sender`,`read_at`);

--
-- Indexes for the community tables
--
ALTER TABLE `community_groups`
  ADD PRIMARY KEY (`group_id`);

ALTER TABLE `community_members`
  ADD PRIMARY KEY (`member_id`),
  ADD UNIQUE KEY `group_adopter` (`group_id`,`adopter_id`),
  ADD KEY `adopter` (`adopter_id`);

ALTER TABLE `community_messages`
  ADD PRIMARY KEY (`message_id`),
  ADD KEY `group_thread` (`group_id`,`message_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `accepted`
--
ALTER TABLE `accepted`
  MODIFY `accept_id` int(4) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `admin`
--
ALTER TABLE `admin`
  MODIFY `admin_id` int(4) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `adopted`
--
ALTER TABLE `adopted`
  MODIFY `adopt_id` int(4) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `adopter`
--
ALTER TABLE `adopter`
  MODIFY `adopter_id` int(4) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `category_id` int(4) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `contact`
--
ALTER TABLE `contact`
  MODIFY `contact_id` int(4) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `locations`
--
ALTER TABLE `locations`
  MODIFY `location_id` int(4) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `pets`
--
ALTER TABLE `pets`
  MODIFY `pet_id` int(4) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `requests`
--
ALTER TABLE `requests`
  MODIFY `request_id` int(4) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `resetpassword`
--
ALTER TABLE `resetpassword`
  MODIFY `reset_id` int(4) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `favourites`
--
ALTER TABLE `favourites`
  MODIFY `fav_id` int(4) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `chat_messages`
--
ALTER TABLE `chat_messages`
  MODIFY `chat_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for the community tables
--
ALTER TABLE `community_groups`
  MODIFY `group_id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `community_members`
  MODIFY `member_id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `community_messages`
  MODIFY `message_id` int(11) NOT NULL AUTO_INCREMENT;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
