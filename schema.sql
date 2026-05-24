-- ============================================
-- LebanonCinema.com Database Schema
-- ============================================

CREATE DATABASE IF NOT EXISTS lebanon_cinema CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE lebanon_cinema;

CREATE TABLE chains (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100) NOT NULL,
    slug        VARCHAR(100) NOT NULL UNIQUE,
    logo_url    VARCHAR(255),
    website_url VARCHAR(255),
    color_hex   VARCHAR(7) DEFAULT '#e63946',
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE cinemas (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    chain_id    INT NOT NULL,
    name        VARCHAR(150) NOT NULL,
    slug        VARCHAR(150) NOT NULL UNIQUE,
    city        VARCHAR(100) NOT NULL,
    area        VARCHAR(100),
    address     TEXT,
    lat         DECIMAL(10,7),
    lng         DECIMAL(10,7),
    has_imax    BOOLEAN DEFAULT FALSE,
    has_vip     BOOLEAN DEFAULT FALSE,
    has_4dx     BOOLEAN DEFAULT FALSE,
    is_active   BOOLEAN DEFAULT TRUE,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (chain_id) REFERENCES chains(id)
);

CREATE TABLE movies (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    title        VARCHAR(255) NOT NULL,
    slug         VARCHAR(255) NOT NULL UNIQUE,
    synopsis     TEXT,
    poster_url   VARCHAR(500),
    trailer_url  VARCHAR(500),
    duration_min SMALLINT,
    rating       VARCHAR(20),
    language     VARCHAR(50),
    tmdb_id      INT,
    imdb_id      VARCHAR(20),
    release_date DATE,
    status       ENUM('now_showing','coming_soon','ended') DEFAULT 'now_showing',
    genres       VARCHAR(255),
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE showtimes (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    movie_id    INT NOT NULL,
    cinema_id   INT NOT NULL,
    show_date   DATE NOT NULL,
    show_time   TIME NOT NULL,
    format      VARCHAR(30) DEFAULT 'Standard',
    language    VARCHAR(30) DEFAULT 'English',
    subtitles   VARCHAR(30),
    booking_url VARCHAR(500),
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_showtime (movie_id, cinema_id, show_date, show_time),
    FOREIGN KEY (movie_id)  REFERENCES movies(id),
    FOREIGN KEY (cinema_id) REFERENCES cinemas(id),
    INDEX idx_date          (show_date),
    INDEX idx_cinema_date   (cinema_id, show_date),
    INDEX idx_movie_date    (movie_id,  show_date)
);

CREATE TABLE scraper_log (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    chain_slug   VARCHAR(50),
    scrape_date  DATE,
    status       ENUM('success','partial','failed'),
    movies_found SMALLINT DEFAULT 0,
    times_found  SMALLINT DEFAULT 0,
    error_msg    TEXT,
    ran_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Seed: chains
INSERT INTO chains (name, slug, website_url, color_hex) VALUES
('VOX Cinemas',    'vox',        'https://lbn.voxcinemas.com',       '#e63946'),
('Grand Cinemas',  'grand',      'https://www.grandcinemas.com',     '#1d3557'),
('Empire Cinemas', 'empire',     'https://www.empirecinemas.com.lb', '#2d6a4f'),
('CinemaCity',     'cinemacity', 'https://www.cinemacity.com.lb',    '#f4a261'),
('Cinemall',       'cinemall',   'https://cinemall.com.lb',          '#9b2226'),
('Stargate',       'stargate',   NULL,                               '#6a4c93');

-- Seed: cinemas
INSERT INTO cinemas (chain_id, name, slug, city, area, has_imax) VALUES
(1, 'VOX City Centre Beirut',      'vox-city-centre-beirut', 'Beirut',  'Hazmieh',   TRUE);

INSERT INTO cinemas (chain_id, name, slug, city, area) VALUES
(2, 'Grand Cinemas ABC Achrafieh', 'grand-abc-achrafieh',    'Beirut',  'Achrafieh'),
(2, 'Grand Cinemas ABC Dbayeh',    'grand-abc-dbayeh',       'Dbayeh',  'Dbayeh'),
(2, 'Grand Cinemas ABC Verdun',    'grand-abc-verdun',       'Beirut',  'Verdun'),
(2, 'Grand Cinemas Las Salinas',   'grand-las-salinas',      'Anfeh',   'Anfeh'),
(2, 'Grand Cinemas The Spot Saida','grand-the-spot-saida',   'Saida',   'Saida'),
(3, 'Empire Choueifat',            'empire-choueifat',       'Choueifat','Choueifat'),
(3, 'Empire Premier',              'empire-premier',         'Beirut',  'Dora'),
(4, 'CinemaCity Souks',            'cinemacity-souks',       'Beirut',  'Downtown'),
(5, 'Cinemall',                    'cinemall',               'Beirut',  'Dora'),
(6, 'Stargate Zahle',              'stargate-zahle',         'Zahle',   'Zahle'),
(1, 'MEGA Cinemas',                'mega-cinemas',           'Beirut',  NULL);
