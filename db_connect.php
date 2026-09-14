<?php
$servername = "localhost";
$username = "root"; 
$password = ""; 
$dbname = "chubs_db"; // Pinalitan na natin ito ng gazette_db

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}

// Pwede mo itong i-comment out (lagyan ng // sa unahan) kapag sure ka nang gumagana
//echo "Connected successfully";

// Shared helpers + auto-migration. Ang chubs_migrate() ay isang query lang kapag
// updated na ang schema. Naka-try/catch para kahit pumalya, tuloy pa rin ang site.
require_once __DIR__ . '/settings.php';
require_once __DIR__ . '/inventory_helpers.php';
require_once __DIR__ . '/db_migrate.php';
try {
  chubs_migrate($conn);
} catch (Throwable $e) {
  // Huwag pigilan ang page kahit may problema sa migration.
}
?>