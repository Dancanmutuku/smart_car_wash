<?php
// Booking actions
if(isset($_POST['booking_action']) && isset($_POST['booking_id'])){
    include('bookings.php'); // handles approve/reject/assign
    exit;
}

// Services actions
if(isset($_POST['create_service']) || isset($_POST['edit_service']) || isset($_POST['delete_service'])){
    include('services.php'); // handles CRUD
    exit;
}

// Supplies actions
if(isset($_POST['save_supply']) || isset($_POST['delete_supply'])){
    include('supplies.php'); // handles CRUD
    exit;
}

// Users actions
if(isset($_POST['create_user']) || isset($_POST['delete_user'])){
    include('users.php'); // handles user management
    exit;
}

// Export bookings
if(isset($_POST['export_bookings'])){
    include('export_bookings.php');
    exit;
}
