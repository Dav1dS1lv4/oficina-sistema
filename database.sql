-- ============================================
-- Sistema de Gestão de Oficina Automóvel
-- Schema da Base de Dados
-- ============================================

CREATE DATABASE IF NOT EXISTS oficina CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE oficina;

-- ============================================
-- TABELA: roles
-- Tipos de utilizador do sistema
-- ============================================
CREATE TABLE roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE
);

INSERT INTO roles (name) VALUES
('Administrador'),
('Funcionário'),
('Visitante');

-- ============================================
-- TABELA: users
-- Utilizadores que fazem login no sistema
-- ============================================
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role_id INT NOT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    reset_token VARCHAR(255) DEFAULT NULL,
    reset_token_expires DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(id)
);

-- ============================================
-- TABELA: clients
-- Donos de veículos (não fazem login)
-- ============================================
CREATE TABLE clients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) DEFAULT NULL,
    phone VARCHAR(20) DEFAULT NULL,
    address VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================
-- TABELA: vehicles
-- ============================================
CREATE TABLE vehicles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    license_plate VARCHAR(20) NOT NULL UNIQUE,
    brand VARCHAR(50) NOT NULL,
    model VARCHAR(50) NOT NULL,
    year INT NOT NULL,
    client_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id)
);

-- ============================================
-- TABELA: parts
-- Inventário de peças
-- ============================================
CREATE TABLE parts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    category VARCHAR(50) DEFAULT NULL,
    quantity INT NOT NULL DEFAULT 0,
    price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    supplier VARCHAR(100) DEFAULT NULL,
    low_stock_threshold INT NOT NULL DEFAULT 5,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ============================================
-- TABELA: maintenance
-- Histórico de serviços realizados
-- ============================================
CREATE TABLE maintenance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vehicle_id INT NOT NULL,
    user_id INT NOT NULL COMMENT 'Funcionário responsável',
    type VARCHAR(100) NOT NULL COMMENT 'Tipo de manutenção',
    service_date DATE NOT NULL,
    cost DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    notes TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- ============================================
-- TABELA: maintenance_parts
-- Ligação N:N entre manutenções e peças usadas
-- ============================================
CREATE TABLE maintenance_parts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    maintenance_id INT NOT NULL,
    part_id INT NOT NULL,
    quantity_used INT NOT NULL DEFAULT 1,
    FOREIGN KEY (maintenance_id) REFERENCES maintenance(id) ON DELETE CASCADE,
    FOREIGN KEY (part_id) REFERENCES parts(id)
);