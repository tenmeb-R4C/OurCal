<?php
// ============================================================
// OurCal - Database Configuration (db_config.php)
// ============================================================
// Connection using mysqli_connect() (Module 8)
//
// For LOCAL XAMPP:
//   Host = "localhost", User = "root", Password = "", Database = "OurCal"
// For AWS RDS:
//  The commented part for hosting
// ============================================================

// --- XAMPP (Local) ---
$db_host     = "localhost";
$db_user     = "root";
$db_password = "";
$db_name     = "OurCal";

// --- AWS RDS (we have updated this section when hosting web online ---
// $db_host     = "our-rds-endpoint.us-east-1.rds.amazonaws.com";
// $db_user     = "admin";
// $db_password = "our_rds_password";
// $db_name     = "OurCal";

//http://3.208.9.107  (visit this public site to open web) 

$conn = mysqli_connect($db_host, $db_user, $db_password, $db_name);
if ($conn->connect_error) {
    die("Connection Failed: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

// ============================================================
// CURRENT USER — simulates a logged-in user
// The user switcher dropdown sends ?switch_user=ID via GET
// In a real app this would come from PHP sessions (and this is beyond our scope)
// ============================================================
$current_user_id = 1; // default user

// If user switcher was used, update current user
if (isset($_GET['switch_user'])) {
    $current_user_id = intval($_GET['switch_user']);
}
// Also check if it was passed via POST (for form submissions)
if (isset($_POST['current_user_id'])) {
    $current_user_id = intval($_POST['current_user_id']);
}

// Fetch current user info for use across all pages
$cu_result = mysqli_query($conn, "SELECT u.UserID, u.Username, u.UserType,
                                         IFNULL(p.DisplayName, u.Username) AS DisplayName,
                                         IFNULL(p.ProfilePictureURL, '') AS ProfilePic
                                  FROM `User` u
                                  LEFT JOIN Profile p ON u.UserID = p.UserID
                                  WHERE u.UserID = $current_user_id");
$current_user = mysqli_fetch_assoc($cu_result);

// Fetch user's saved background color (default to cream if not set or set to old default navy)
$bg_result = mysqli_query($conn, "SELECT ThemeColor FROM AccountSetting WHERE UserID=$current_user_id LIMIT 1");
$bg_row = mysqli_fetch_assoc($bg_result);
$user_bg_color = ($bg_row && !empty($bg_row['ThemeColor']) && $bg_row['ThemeColor'] != '#2C3E50' && $bg_row['ThemeColor'] != '#FFFFFF')
                 ? $bg_row['ThemeColor']
                 : '#f5f0eb';

// Helper: builds URL with switch_user parameter to maintain user context
function userUrl($page, $extra = "") {
    global $current_user_id;
    $sep = (strpos($page, '?') !== false) ? '&' : '?';
    $url = $page . $sep . "switch_user=" . $current_user_id;
    if ($extra != "") $url .= "&" . $extra;
    return $url;
}
?>
