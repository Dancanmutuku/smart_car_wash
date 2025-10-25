<?php
try {
    $db = new PDO('sqlite:' . __DIR__ . '/../database/car_wash.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // --- Users Table ---
    $db->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        email TEXT UNIQUE NOT NULL,
        password TEXT NOT NULL,
        role TEXT DEFAULT 'customer',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // --- Services Table ---
    $db->exec("CREATE TABLE IF NOT EXISTS services (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        service_name TEXT NOT NULL,
        description TEXT,
        price REAL,
        duration INTEGER,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // Ensure 'category' column exists in services
    $columns = $db->query("PRAGMA table_info(services)")->fetchAll(PDO::FETCH_ASSOC);
    $column_names = array_column($columns, 'name');
    if (!in_array('category', $column_names)) {
        $db->exec("ALTER TABLE services ADD COLUMN category TEXT DEFAULT 'General'");
    }

    // --- Bookings Table ---
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

    // Ensure 'staff_id' column exists in bookings
    $columns = $db->query("PRAGMA table_info(bookings)")->fetchAll(PDO::FETCH_ASSOC);
    $column_names = array_column($columns, 'name');
    if (!in_array('staff_id', $column_names)) {
        $db->exec("ALTER TABLE bookings ADD COLUMN staff_id INTEGER");
    }

    // --- Feedback Table ---
    $db->exec("CREATE TABLE IF NOT EXISTS feedback (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        booking_id INTEGER,
        user_id INTEGER,
        rating INTEGER,
        comment TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // --- Logs Table ---
    $db->exec("CREATE TABLE IF NOT EXISTS logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        action TEXT,
        details TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // --- Activity Table ---
    $db->exec("CREATE TABLE IF NOT EXISTS activity (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        activity_type TEXT,
        description TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // --- Insert default admin if missing ---
    $exists = $db->query("SELECT COUNT(*) as c FROM users WHERE role='admin'")->fetch(PDO::FETCH_ASSOC)['c'];
    if ($exists == 0) {
        $password = password_hash('admin123', PASSWORD_DEFAULT);
        $db->exec("INSERT INTO users (name, email, password, role) VALUES ('Admin', 'admin@wash.com', '$password', 'admin')");
    }

    // --- Sample Services if empty ---
    $count = $db->query("SELECT COUNT(*) as c FROM services")->fetch(PDO::FETCH_ASSOC)['c'];
    if ($count == 0) {
        $db->exec("INSERT INTO services (service_name, description, price, duration, category) VALUES
            ('Basic Wash', 'Exterior wash and dry', 5.00, 15, 'Exterior'),
            ('Standard Wash', 'Exterior + interior vacuum', 10.00, 30, 'Interior'),
            ('Deluxe Wash', 'Full clean + wax', 20.00, 45, 'Full')");
    }

} catch (PDOException $e) {
    echo "DB Error: " . $e->getMessage();
}
?>
