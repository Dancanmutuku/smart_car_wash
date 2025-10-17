<?php
try {
    $db = new PDO('sqlite:' . __DIR__ . '/../database/car_wash.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Create tables
    $db->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        email TEXT UNIQUE NOT NULL,
        password TEXT NOT NULL,
        role TEXT DEFAULT 'customer',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS services (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        service_name TEXT,
        description TEXT,
        price REAL,
        duration INTEGER,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS bookings (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        car_model TEXT,
        license_plate TEXT,
        service_id INTEGER,
        booking_time DATETIME,
        status TEXT DEFAULT 'pending',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // Insert default admin if missing
    $exists = $db->query("SELECT COUNT(*) as c FROM users WHERE role='admin'")->fetch(PDO::FETCH_ASSOC)['c'];
    if ($exists == 0) {
        $password = password_hash('admin123', PASSWORD_DEFAULT);
        $db->exec("INSERT INTO users (name, email, password, role) VALUES ('Admin', 'admin@wash.com', '$password', 'admin')");
    }

    // Sample services
    $count = $db->query("SELECT COUNT(*) as c FROM services")->fetch(PDO::FETCH_ASSOC)['c'];
    if ($count == 0) {
        $db->exec("INSERT INTO services (service_name, description, price, duration) VALUES
            ('Basic Wash', 'Exterior wash and dry', 5.00, 15),
            ('Standard Wash', 'Exterior + interior vacuum', 10.00, 30),
            ('Deluxe Wash', 'Full clean + wax', 20.00, 45)");
    }
} catch (PDOException $e) {
    echo 'DB Error: ' . $e->getMessage();
}
?>
