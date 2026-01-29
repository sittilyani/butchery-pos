<?php
$host = 'localhost';
$db = 'butchery_db';
$user = 'root'; // Default in Laragon/XAMPP
$pass = ''; // Empty by default
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);
?>