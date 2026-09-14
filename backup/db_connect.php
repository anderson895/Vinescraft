<?php
$servername = "localhost";
$username = "root"; 
$password = ""; 
$dbname = "gazette_db"; // Pinalitan na natin ito ng gazette_db

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}

// Pwede mo itong i-comment out (lagyan ng // sa unahan) kapag sure ka nang gumagana
//echo "Connected successfully"; 
?>