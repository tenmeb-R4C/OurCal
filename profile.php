<?php require_once 'db_config.php'; ?>
<!DOCTYPE html>
<html>
<head>
    <title>OurCal - My Profile</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.1/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="css/style.css">
</head>
<body style="background-color: <?php echo $user_bg_color; ?>;">
<?php
require_once 'db_config.php';
include 'navbar.php';
$message = ""; $msg_type = "";

// EDIT PROFILE (POST)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'edit_profile') {
    $display  = $_POST['display_name'];
    $bio      = $_POST['bio'];
    $status   = $_POST['status_message'];
    $pic_url  = $_POST['profile_pic_url'];

    // Check if profile exists
    $check = mysqli_query($conn, "SELECT * FROM Profile WHERE UserID=$current_user_id");
    if (mysqli_num_rows($check) > 0) {
        $stmt = $conn->prepare("UPDATE Profile SET DisplayName=?, Bio=?, StatusMessage=?, ProfilePictureURL=? WHERE UserID=?");
        $stmt->bind_param("ssssi", $display, $bio, $status, $pic_url, $current_user_id);
    } else {
        $stmt = $conn->prepare("INSERT INTO Profile (UserID, DisplayName, Bio, StatusMessage, ProfilePictureURL) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("issss", $current_user_id, $display, $bio, $status, $pic_url);
    }
    if ($stmt->execute()) { $message = "Profile updated!"; $msg_type = "success"; }
    else { $message = "Error: " . $conn->error; $msg_type = "danger"; }
    $stmt->close();

    // Refresh current user data
    $cu_result = mysqli_query($conn, "SELECT u.UserID, u.Username, u.UserType,
                                             IFNULL(p.DisplayName, u.Username) AS DisplayName,
                                             IFNULL(p.ProfilePictureURL, '') AS ProfilePic
                                      FROM `User` u LEFT JOIN Profile p ON u.UserID=p.UserID
                                      WHERE u.UserID=$current_user_id");
    $current_user = mysqli_fetch_assoc($cu_result);
}

// Fetch full profile data
$profile = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT u.Username, u.Email, u.DateJoined, u.UserType,
            IFNULL(p.DisplayName, u.Username) AS DisplayName,
            IFNULL(p.Bio, '') AS Bio,
            IFNULL(p.StatusMessage, '') AS StatusMessage,
            IFNULL(p.ProfilePictureURL, '') AS ProfilePictureURL,
            IFNULL(ph.PhoneNo, '') AS PhoneNo,
            IFNULL(a.Address, '') AS Address
     FROM `User` u
     LEFT JOIN Profile p ON u.UserID=p.UserID
     LEFT JOIN UserPhone ph ON u.UserID=ph.UserID
     LEFT JOIN UserAddress a ON u.UserID=a.UserID
     WHERE u.UserID=$current_user_id"));

// Get initials for avatar
$initials = strtoupper(substr($profile['DisplayName'], 0, 2));
?>

<div class="container-fluid" style="padding: 25px;">
    <div class="page-header">
        <h2>&#128100; My Profile</h2>
        <p>View and edit your personal information</p>
    </div>

    <?php if ($message != "") { ?>
        <div class="alert alert-<?php echo $msg_type; ?> alert-dismissible fade show flash-msg">
            <?php echo $message; ?>
            <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
    <?php } ?>

    <div class="row">
        <!-- Profile Card -->
        <div class="col-md-4" style="margin-bottom:20px;">
            <div class="profile-card">
                <?php if (!empty($profile['ProfilePictureURL'])) { ?>
                    <div class="profile-avatar" style="padding:0; overflow:hidden;">
                        <img src="<?php echo htmlspecialchars($profile['ProfilePictureURL']); ?>"
                             alt="<?php echo htmlspecialchars($profile['DisplayName']); ?>"
                             style="width:100%; height:100%; object-fit:cover; border-radius:50%;"
                             onerror="this.parentNode.innerHTML='<?php echo addslashes($initials); ?>'; this.parentNode.removeAttribute('style');">
                    </div>
                <?php } else { ?>
                    <div class="profile-avatar"><?php echo $initials; ?></div>
                <?php } ?>
                <div class="profile-name"><?php echo $profile['DisplayName']; ?></div>
                <div class="profile-role">
                    <span class="badge badge-<?php echo ($profile['UserType']=='Admin') ? 'danger' : 'success'; ?>">
                        <?php echo $profile['UserType']; ?>
                    </span>
                </div>
                <?php if ($profile['StatusMessage'] != '') { ?>
                    <p style="color:#718096; font-style:italic;">"<?php echo $profile['StatusMessage']; ?>"</p>
                <?php } ?>
                <hr>
                <p style="font-size:0.85rem; color:#718096;">
                    <b>Username:</b> <?php echo $profile['Username']; ?><br>
                    <b>Email:</b> <?php echo $profile['Email']; ?><br>
                    <b>Phone:</b> <?php echo ($profile['PhoneNo'] != '') ? $profile['PhoneNo'] : '-'; ?><br>
                    <b>Address:</b> <?php echo ($profile['Address'] != '') ? $profile['Address'] : '-'; ?><br>
                    <b>Joined:</b> <?php echo $profile['DateJoined']; ?>
                </p>
            </div>
        </div>

        <!-- Edit Profile Form -->
        <div class="col-md-8">
            <div class="section-title">Edit Profile</div>
            <div class="content-card" style="padding:25px;">
                <form action="" method="POST">
                    <input type="hidden" name="action" value="edit_profile">
                    <input type="hidden" name="current_user_id" value="<?php echo $current_user_id; ?>">
                    <div class="form-group">
                        <label><b>Display Name *</b></label>
                        <input type="text" name="display_name" class="form-control"
                               value="<?php echo htmlspecialchars($profile['DisplayName']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label><b>Bio</b></label>
                        <textarea name="bio" class="form-control" rows="3"><?php echo htmlspecialchars($profile['Bio']); ?></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label><b>Status Message</b></label>
                            <input type="text" name="status_message" class="form-control"
                                   value="<?php echo htmlspecialchars($profile['StatusMessage']); ?>"
                                   placeholder="e.g. Available for collaboration">
                        </div>
                        <div class="col-md-6 form-group">
                            <label><b>Profile Picture URL</b></label>
                            <input type="text" name="profile_pic_url" class="form-control"
                                   value="<?php echo htmlspecialchars($profile['ProfilePictureURL']); ?>"
                                   placeholder="/profiles/your_photo.jpg">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">Save Profile</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
</body></html>
<?php $conn->close(); ?>
