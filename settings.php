<?php
require_once 'db_config.php';
$message = ""; $msg_type = "";

// EDIT SETTINGS (POST) — runs BEFORE body tag, so updated color applies immediately
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'edit_settings') {
    $timezone   = $_POST['timezone'];
    $language   = $_POST['language'];
    $themeColor = $_POST['theme_color'];
    $remStatus  = isset($_POST['reminder_status']) ? 1 : 0;
    $settingName = "default_settings";

    $check = mysqli_query($conn, "SELECT * FROM AccountSetting WHERE UserID=$current_user_id");
    if (mysqli_num_rows($check) > 0) {
        $stmt = $conn->prepare("UPDATE AccountSetting SET Timezone=?, Language=?, ThemeColor=?, ReminderStatus=? WHERE UserID=?");
        $stmt->bind_param("sssii", $timezone, $language, $themeColor, $remStatus, $current_user_id);
    } else {
        $stmt = $conn->prepare("INSERT INTO AccountSetting (UserID, SettingName, Timezone, Language, ThemeColor, ReminderStatus) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("issssi", $current_user_id, $settingName, $timezone, $language, $themeColor, $remStatus);
    }
    if ($stmt->execute()) {
        $message = "Settings saved!"; $msg_type = "success";
        // Refresh the page-wide background color so it applies immediately
        $user_bg_color = (!empty($themeColor) && $themeColor != '#2C3E50' && $themeColor != '#FFFFFF')
                         ? $themeColor : '#f5f0eb';
    }
    else { $message = "Error: " . $conn->error; $msg_type = "danger"; }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>OurCal - Account Settings</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.1/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="css/style.css">
</head>
<body style="background-color: <?php echo $user_bg_color; ?>;">
<?php
include 'navbar.php';

// Fetch current settings
$settings = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT IFNULL(Timezone, 'UTC') AS Timezone,
            IFNULL(Language, 'en') AS Language,
            IFNULL(ThemeColor, '#f5f0eb') AS ThemeColor,
            IFNULL(ReminderStatus, 1) AS ReminderStatus
     FROM AccountSetting WHERE UserID=$current_user_id"));

if (!$settings) {
    $settings = ['Timezone' => 'UTC', 'Language' => 'en', 'ThemeColor' => '#f5f0eb', 'ReminderStatus' => 1];
}
?>

<div class="container-fluid" style="padding: 25px;">
    <div class="page-header">
        <h2>&#9881; Account Settings</h2>
        <p>Customize your OurCal experience</p>
    </div>

    <?php if ($message != "") { ?>
        <div class="alert alert-<?php echo $msg_type; ?> alert-dismissible fade show flash-msg">
            <?php echo $message; ?>
            <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
    <?php } ?>

    <div class="row">
        <!-- Current Settings Display -->
        <div class="col-md-4" style="margin-bottom:20px;">
            <div class="section-title">Current Settings</div>
            <div class="content-card" style="padding:0;">
                <div class="setting-item">
                    <span class="setting-label">&#127760; Timezone</span>
                    <span class="setting-value"><?php echo $settings['Timezone']; ?></span>
                </div>
                <div class="setting-item">
                    <span class="setting-label">&#127463; Language</span>
                    <span class="setting-value"><?php echo strtoupper($settings['Language']); ?></span>
                </div>
                <div class="setting-item">
                    <span class="setting-label">&#127912; Background Color</span>
                    <span class="setting-value">
                        <span style="display:inline-block; width:20px; height:20px; border-radius:50%;
                                     background-color:<?php echo $settings['ThemeColor']; ?>;
                                     vertical-align:middle; margin-right:5px; border:1px solid #ccc;"></span>
                        <?php echo $settings['ThemeColor']; ?>
                        <?php if (strtolower($settings['ThemeColor']) == '#f5f0eb') { ?>
                            <small style="color:#888;">(Default)</small>
                        <?php } ?>
                    </span>
                </div>
                <div class="setting-item">
                    <span class="setting-label">&#128276; Reminders</span>
                    <span class="setting-value">
                        <span class="badge badge-<?php echo $settings['ReminderStatus'] ? 'success' : 'secondary'; ?>">
                            <?php echo $settings['ReminderStatus'] ? 'ON' : 'OFF'; ?>
                        </span>
                    </span>
                </div>
            </div>
        </div>

        <!-- Edit Settings Form -->
        <div class="col-md-8">
            <div class="section-title">Edit Settings</div>
            <div class="content-card" style="padding:25px;">
                <form action="" method="POST">
                    <input type="hidden" name="action" value="edit_settings">
                    <input type="hidden" name="current_user_id" value="<?php echo $current_user_id; ?>">

                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label><b>Timezone</b></label>
                            <select name="timezone" class="form-control">
                                <?php
                                $timezones = ['UTC','America/New_York','America/Chicago','America/Denver','America/Los_Angeles','America/Phoenix','Europe/London','Europe/Berlin','Asia/Tokyo','Asia/Shanghai','Australia/Sydney'];
                                foreach ($timezones as $tz) {
                                    $sel = ($settings['Timezone'] == $tz) ? 'selected' : '';
                                    echo "<option value='$tz' $sel>$tz</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-md-6 form-group">
                            <label><b>Language</b></label>
                            <select name="language" class="form-control">
                                <option value="en" <?php if($settings['Language']=='en') echo 'selected'; ?>>English</option>
                                <option value="es" <?php if($settings['Language']=='es') echo 'selected'; ?>>Spanish</option>
                                <option value="fr" <?php if($settings['Language']=='fr') echo 'selected'; ?>>French</option>
                                <option value="de" <?php if($settings['Language']=='de') echo 'selected'; ?>>German</option>
                            </select>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label><b>Background Color</b></label>
                            <div style="display:flex; gap:8px; align-items:center;">
                                <input type="color" name="theme_color" id="bgColorInput" class="form-control"
                                       value="<?php echo $settings['ThemeColor']; ?>"
                                       style="height:45px; padding:3px; flex:1;">
                                <button type="button" class="btn btn-secondary"
                                        onclick="document.getElementById('bgColorInput').value='#f5f0eb';"
                                        style="white-space:nowrap;">Use Default</button>
                            </div>
                        </div>
                        <div class="col-md-6 form-group">
                            <label><b>Reminders</b></label><br>
                            <div style="padding-top:10px;">
                                <input type="checkbox" name="reminder_status" value="1"
                                       <?php if($settings['ReminderStatus']) echo 'checked'; ?>>
                                <label>Enable reminders for my events</label>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary">Save Settings</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
</body></html>
<?php $conn->close(); ?>
