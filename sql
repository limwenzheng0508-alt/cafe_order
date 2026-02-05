-- 1. Customer Table
CREATE TABLE Customer (
    customer_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100),
    phone VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 2. MenuItem Table
CREATE TABLE MenuItem (
    menuitem_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL
);

-- 3. Reservation Table
CREATE TABLE Reservation (
    reservation_id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT,
    reservation_date DATE NOT NULL,
    reservation_time TIME NOT NULL,
    num_people INT NOT NULL,
    tables_needed INT NOT NULL,
    table_numbers VARCHAR(255),
    status ENUM('pending','confirmed','cancelled') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES Customer(customer_id)
);

-- 4. ReservationMenuItem Table
CREATE TABLE ReservationMenuItem (
    reservation_menuitem_id INT AUTO_INCREMENT PRIMARY KEY,
    reservation_id INT,
    menuitem_id INT,
    quantity INT DEFAULT 1,
    FOREIGN KEY (reservation_id) REFERENCES Reservation(reservation_id),
    FOREIGN KEY (menuitem_id) REFERENCES MenuItem(menuitem_id)
);

-- 5. Staff Table
CREATE TABLE Staff (
    staff_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100),
    role VARCHAR(50),
    email VARCHAR(100),
    password VARCHAR(255)
);

-- 6. Payment Table
CREATE TABLE Payment (
    payment_id INT AUTO_INCREMENT PRIMARY KEY,
    reservation_id INT,
    amount DECIMAL(10,2),
    payment_method ENUM('cash','card','online'),
    status ENUM('paid','unpaid') DEFAULT 'unpaid',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (reservation_id) REFERENCES Reservation(reservation_id)
);
