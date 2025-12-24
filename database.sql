DROP DATABASE IF EXISTS wsudorm;
CREATE DATABASE wsudorm;
USE wsudorm;

-- Users table
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    password VARCHAR(100) NOT NULL,
    role VARCHAR(50) NOT NULL
);

-- Dorm table
CREATE TABLE dorm (
    id INT PRIMARY KEY AUTO_INCREMENT,
    total INT NOT NULL,
    block_id INT NOT NULL,
    FOREIGN KEY(block_id) REFERENCES block(id)
);


-- Block table (belongs to a dorm)
CREATE TABLE block (
    id INT PRIMARY KEY AUTO_INCREMENT,
    total INT,
);

-- Student table (links user to dorm/block)
CREATE TABLE student (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    block_id INT NOT NULL,
    dorm_id INT NOT NULL,
    FOREIGN KEY(user_id) REFERENCES users(id),
    FOREIGN KEY(block_id) REFERENCES block(id),
    FOREIGN KEY(dorm_id) REFERENCES dorm(id)
);

-- Proctor table (links user to block)
CREATE TABLE proctor (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    block_id INT NOT NULL,
    FOREIGN KEY(user_id) REFERENCES users(id),
    FOREIGN KEY(block_id) REFERENCES block(id)
);
