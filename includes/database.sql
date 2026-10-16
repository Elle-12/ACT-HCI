CREATE DATABASE IF NOT EXISTS resto_bistro
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE resto_bistro;

CREATE TABLE IF NOT EXISTS users (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    fullname VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    phone VARCHAR(20) NULL,
    address TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS menu_items (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT NULL,
    price DECIMAL(10, 2) NOT NULL,
    image_url VARCHAR(255) NULL,
    is_available TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS reservations (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    reservation_date DATE NOT NULL,
    reservation_time TIME NOT NULL,
    party_size INT NOT NULL,
    special_requests TEXT NULL,
    status ENUM('pending', 'confirmed', 'checked_in', 'completed', 'cancelled', 'no_show') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_reservations_user_date (user_id, reservation_date, reservation_time),
    CONSTRAINT fk_reservations_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS transactions (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    reservation_id INT NULL,
    user_id INT NULL,
    transaction_number VARCHAR(50) NULL UNIQUE,
    total_amount DECIMAL(10, 2) NOT NULL,
    payment_status ENUM('pending', 'paid', 'failed', 'refunded', 'cancelled') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_transactions_reservation (reservation_id),
    INDEX idx_transactions_user (user_id),
    CONSTRAINT fk_transactions_reservation
        FOREIGN KEY (reservation_id) REFERENCES reservations(id) ON DELETE SET NULL,
    CONSTRAINT fk_transactions_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS reservation_items (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    reservation_id INT NOT NULL,
    menu_item_id INT NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    price DECIMAL(10, 2) NOT NULL,
    special_instructions TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_reservation_items_reservation
        FOREIGN KEY (reservation_id) REFERENCES reservations(id) ON DELETE CASCADE,
    CONSTRAINT fk_reservation_items_menu_item
        FOREIGN KEY (menu_item_id) REFERENCES menu_items(id)
) ENGINE=InnoDB;

INSERT INTO menu_items (name, description, price, image_url)
SELECT 'Bistro Ribeye', 'Grilled ribeye with herb butter and seasonal vegetables.', 895.00, 'img/2.png'
WHERE NOT EXISTS (SELECT 1 FROM menu_items WHERE name = 'Bistro Ribeye');

INSERT INTO menu_items (name, description, price, image_url)
SELECT 'Beef Barolo', 'Slow-braised beef in a rich red wine sauce.', 645.00, 'img/1.png'
WHERE NOT EXISTS (SELECT 1 FROM menu_items WHERE name = 'Beef Barolo');

INSERT INTO menu_items (name, description, price, image_url)
SELECT 'Pan-Seared Duck', 'Crisp-skinned duck breast with a citrus glaze.', 725.00, 'img/2.png'
WHERE NOT EXISTS (SELECT 1 FROM menu_items WHERE name = 'Pan-Seared Duck');

INSERT INTO menu_items (name, description, price, image_url)
SELECT 'Truffle Pesto Pasta', 'Fresh pasta tossed with basil pesto and truffle.', 425.00, 'img/1.png'
WHERE NOT EXISTS (SELECT 1 FROM menu_items WHERE name = 'Truffle Pesto Pasta');

INSERT INTO menu_items (name, description, price, image_url)
SELECT 'Herb-Crusted Salmon', 'Oven-roasted salmon with lemon and garden herbs.', 575.00, 'img/2.png'
WHERE NOT EXISTS (SELECT 1 FROM menu_items WHERE name = 'Herb-Crusted Salmon');

INSERT INTO menu_items (name, description, price, image_url)
SELECT 'Mushroom Risotto', 'Creamy arborio rice with mushrooms and parmesan.', 395.00, 'img/1.png'
WHERE NOT EXISTS (SELECT 1 FROM menu_items WHERE name = 'Mushroom Risotto');
