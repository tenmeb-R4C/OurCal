<?php require_once 'db_config.php'; ?>
<!DOCTYPE html>
<html>
<head>
    <title>OurCal - My Events</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.1/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="css/style.css">
</head>
<body style="background-color: <?php echo $user_bg_color; ?>;">
<?php
require_once 'db_config.php';
include 'navbar.php';
$message = ""; $msg_type = "";
$is_admin = ($current_user['UserType'] == 'Admin');

// --- Helpers ---
function resolveCategory($conn, $input) {
    $input = trim($input); if (empty($input)) return NULL;
    $esc = mysqli_real_escape_string($conn, $input);
    $r = mysqli_query($conn, "SELECT CateogryID FROM Category WHERE CateogryName='$esc' LIMIT 1");
    if (mysqli_num_rows($r) > 0) return mysqli_fetch_assoc($r)['CateogryID'];
    $admin = mysqli_fetch_assoc(mysqli_query($conn, "SELECT UserID FROM Admin LIMIT 1"));
    $aid = $admin['UserID'];
    $stmt = $conn->prepare("INSERT INTO Category (CateogryName, AdminID) VALUES (?, ?)");
    $stmt->bind_param("si", $input, $aid); $stmt->execute();
    $id = $conn->insert_id; $stmt->close(); return $id;
}
function resolveLocation($conn, $input) {
    $input = trim($input); if (empty($input)) return NULL;
    $esc = mysqli_real_escape_string($conn, $input);
    $r = mysqli_query($conn, "SELECT LocationID FROM Location WHERE StreetAddress='$esc' OR CONCAT(StreetAddress,', ',IFNULL(City,''))='$esc' LIMIT 1");
    if (mysqli_num_rows($r) > 0) return mysqli_fetch_assoc($r)['LocationID'];
    if (strpos($input, ',') !== false) {
        $p = array_map('trim', explode(',', $input, 2));
        $stmt = $conn->prepare("INSERT INTO Location (StreetAddress, City) VALUES (?, ?)");
        $stmt->bind_param("ss", $p[0], $p[1]);
    } else {
        $stmt = $conn->prepare("INSERT INTO Location (StreetAddress) VALUES (?)");
        $stmt->bind_param("s", $input);
    }
    $stmt->execute(); $id = $conn->insert_id; $stmt->close(); return $id;
}

// ==================== ADD EVENT ====================
if (!$is_admin && $_SERVER['REQUEST_METHOD'] == 'POST' && ($_POST['action'] ?? '') == 'add') {
    $title = $_POST['title']; $desc = $_POST['description'];
    $start = $_POST['start_time']; $end = $_POST['end_time'];
    $calType = $_POST['calendar_type'];
    $catID = resolveCategory($conn, $_POST['category_input']);
    $locID = resolveLocation($conn, $_POST['location_input']);
    $eventType = $_POST['event_type']; // 'private' or 'shared'
    $audit = '{"created": "' . date('Y-m-d H:i:s') . '"}';

    $stmt = $conn->prepare("INSERT INTO Event (Title,Description,StartTime,EndTime,CalendarType,AuditLogs,CreatorID,CateogryID,LocationID) VALUES (?,?,?,?,?,?,?,?,?)");
    $stmt->bind_param("ssssssiii", $title, $desc, $start, $end, $calType, $audit, $current_user_id, $catID, $locID);
    if ($stmt->execute()) {
        $newEventID = $conn->insert_id;
        $stmt->close();

        // Tags
        $tags = array_filter(array_map('trim', explode(',', $_POST['tags'])));
        foreach ($tags as $tag) {
            $stmt = $conn->prepare("INSERT INTO EventTags (EventID, Tags) VALUES (?, ?)");
            $stmt->bind_param("is", $newEventID, $tag); $stmt->execute(); $stmt->close();
        }

        // Private or Shared
        if ($eventType == 'private') {
            $pnotes = $_POST['privacy_notes'];
            $stmt = $conn->prepare("INSERT INTO PrivateEvent (EventID, PrivacyNotes, CategoryFilter) VALUES (?, ?, ?)");
            $stmt->bind_param("iss", $newEventID, $pnotes, $calType); $stmt->execute(); $stmt->close();
        } else {
            $groupID = intval($_POST['share_group_id']);
            $accessLvl = $_POST['access_level'];
            $stmt = $conn->prepare("INSERT INTO SharedEvent (EventID, AccessLevel, GroupID) VALUES (?, ?, ?)");
            $stmt->bind_param("isi", $newEventID, $accessLvl, $groupID); $stmt->execute(); $stmt->close();
        }

        // Reminder
        if (!empty($_POST['reminder_time'])) {
            $remTime = $_POST['reminder_time']; $remType = $_POST['reminder_type'];
            $stmt = $conn->prepare("INSERT INTO Reminder (EventID, ReminderTime, ReminderType, Snoozable, EnableDisable) VALUES (?, ?, ?, 1, 1)");
            $stmt->bind_param("iss", $newEventID, $remTime, $remType); $stmt->execute(); $stmt->close();
        }

        $message = "Event created!"; $msg_type = "success";
    } else { $message = "Error: " . $conn->error; $msg_type = "danger"; $stmt->close(); }
}

// ==================== EDIT EVENT ====================
if (!$is_admin && $_SERVER['REQUEST_METHOD'] == 'POST' && ($_POST['action'] ?? '') == 'edit') {
    $eid = intval($_POST['event_id']);
    $stmt = $conn->prepare("UPDATE Event SET Title=?, Description=?, StartTime=?, EndTime=?, CalendarType=? WHERE EventID=? AND CreatorID=?");
    $stmt->bind_param("sssssii", $_POST['title'], $_POST['description'], $_POST['start_time'], $_POST['end_time'], $_POST['calendar_type'], $eid, $current_user_id);
    if ($stmt->execute()) { $message = "Event #$eid updated!"; $msg_type = "success"; }
    else { $message = "Error: " . $conn->error; $msg_type = "danger"; }
    $stmt->close();
}

// ==================== DELETE EVENT ====================
if (!$is_admin && isset($_GET['delete'])) {
    $did = intval($_GET['delete']);
    // Only delete if user owns it
    mysqli_query($conn, "DELETE FROM Event WHERE EventID=$did AND CreatorID=$current_user_id");
    $message = "Event #$did deleted."; $msg_type = "warning";
}

// ==================== SEARCH ====================
$search = ""; $where = "AND 1=1";
if (!empty($_GET['search'])) {
    $search = mysqli_real_escape_string($conn, $_GET['search']);
    $where = "AND (e.Title LIKE '%$search%' OR e.Description LIKE '%$search%' OR e.CalendarType LIKE '%$search%')";
}

// Fetch user's events: events they created + events they accepted invites for
$events = mysqli_query($conn,
    "SELECT e.EventID, e.Title, e.Description, e.StartTime, e.EndTime, e.CalendarType,
            IFNULL(c.CateogryName,'-') AS CategoryName,
            IFNULL(CONCAT(l.StreetAddress,', ',IFNULL(l.City,'')),'—') AS LocationStr,
            IF(pe.EventID IS NOT NULL, 'Private', IF(se.EventID IS NOT NULL, 'Shared', '-')) AS EventScope,
            IFNULL(se.AccessLevel, '-') AS AccessLevel,
            u.Username AS CreatorName,
            IF(e.CreatorID=$current_user_id, 'Creator', 'Invited') AS MyRole
     FROM Event e
     LEFT JOIN Category c ON e.CateogryID=c.CateogryID
     LEFT JOIN Location l ON e.LocationID=l.LocationID
     LEFT JOIN PrivateEvent pe ON e.EventID=pe.EventID
     LEFT JOIN SharedEvent se ON e.EventID=se.EventID
     LEFT JOIN `User` u ON e.CreatorID=u.UserID
     WHERE (e.CreatorID=$current_user_id
            OR e.EventID IN (SELECT EventID FROM Invite
                             WHERE ReceiverID=$current_user_id
                             AND Status='Accepted'
                             AND InviteType='EventRequest'))
     $where
     ORDER BY e.StartTime ASC");

// Dropdown data
$cats_r = mysqli_query($conn, "SELECT CateogryName FROM Category ORDER BY CateogryName");
$locs_r = mysqli_query($conn, "SELECT StreetAddress, City FROM Location ORDER BY City");
$groups_r = mysqli_query($conn, "SELECT g.GroupID, g.GroupName FROM `Group` g JOIN Membership m ON g.GroupID=m.GroupID WHERE m.UserID=$current_user_id");
?>

<div class="container-fluid" style="padding:25px;">
    <div class="page-header">
        <h2>&#128197; My Events</h2>
        <p>Search, create, edit, and delete your events</p>
    </div>

    <?php if ($message != "") { ?>
        <div class="alert alert-<?php echo $msg_type; ?> alert-dismissible fade show flash-msg">
            <?php echo $message; ?><button type="button" class="close" data-dismiss="alert">&times;</button></div>
    <?php } ?>

    <div class="search-box">
        <form action="" method="GET">
            <input type="hidden" name="switch_user" value="<?php echo $current_user_id; ?>">
            <div class="row">
                <div class="col-md-10"><label><b>Search My Events</b></label>
                    <input type="text" name="search" class="form-control" placeholder="Search by title, description, or type..."
                           value="<?php echo htmlspecialchars($search); ?>"></div>
                <div class="col-md-2" style="padding-top:30px; white-space:nowrap;">
                    <button type="submit" class="btn btn-primary">Search</button>
                    <a href="<?php echo userUrl('events.php'); ?>" class="btn btn-secondary">Reset</a></div>
            </div>
        </form>
    </div>

    <?php if (!$is_admin) { ?>
    <button class="btn btn-primary" style="margin-bottom:15px;" data-toggle="modal" data-target="#addEventModal">+ Create Event</button>
    <?php } ?>

    <div class="content-card">
        <div class="table-responsive">
            <table class="table table-hover table-striped">
                <thead><tr>
                    <th>ID</th><th>Title</th><th>Description</th><th>Start</th><th>End</th>
                    <th>Type</th><th>Scope</th><th>My Role</th><th>Creator</th><th>Category</th><th>Tags</th><th>Reminders</th><th>Actions</th>
                </tr></thead>
                <tbody>
                <?php if (mysqli_num_rows($events) > 0) { foreach ($events as $row) {
                    // Fetch tags
                    $tags_r = mysqli_query($conn, "SELECT Tags FROM EventTags WHERE EventID=" . $row['EventID']);
                    $tags = []; while ($t = mysqli_fetch_assoc($tags_r)) $tags[] = $t['Tags'];
                    // Fetch reminders
                    $rem_r = mysqli_query($conn, "SELECT ReminderType, ReminderTime, Status FROM Reminder WHERE EventID=" . $row['EventID']);
                    $rems = []; while ($rm = mysqli_fetch_assoc($rem_r)) $rems[] = $rm;
                ?>
                <tr>
                    <td><?php echo $row['EventID']; ?></td>
                    <td><b><?php echo $row['Title']; ?></b></td>
                    <td><small><?php echo $row['Description']; ?></small></td>
                    <td><small><?php echo $row['StartTime']; ?></small></td>
                    <td><small><?php echo $row['EndTime']; ?></small></td>
                    <td><span class="badge badge-info"><?php echo $row['CalendarType']; ?></span></td>
                    <td><span class="badge badge-<?php echo ($row['EventScope']=='Private') ? 'secondary' : (($row['EventScope']=='Shared') ? 'primary' : 'light'); ?>">
                        <?php echo $row['EventScope']; ?></span></td>
                    <td><span class="badge badge-<?php echo ($row['MyRole']=='Creator') ? 'success' : 'info'; ?>">
                        <?php echo $row['MyRole']; ?></span></td>
                    <td><?php echo $row['CreatorName']; ?></td>
                    <td><?php echo $row['CategoryName']; ?></td>
                    <td><small><?php echo $row['LocationStr']; ?></small></td>
                    <td><?php foreach($tags as $tg) echo "<span class='tag-badge'>$tg</span>"; ?></td>
                    <td><?php foreach($rems as $rm) {
                        $rc = 'rem-' . strtolower($rm['ReminderType']);
                        echo "<span class='badge $rc' style='font-size:0.65rem;'>" . $rm['ReminderType'] . "</span> ";
                    } ?></td>
                    <td style="white-space:nowrap;">
                        <?php if ($row['MyRole'] == 'Creator') { ?>
                            <button class="btn btn-sm btn-warning" onclick="openEditEvent(this)"
                                    data-id="<?php echo $row['EventID']; ?>"
                                    data-title="<?php echo htmlspecialchars($row['Title']); ?>"
                                    data-desc="<?php echo htmlspecialchars($row['Description']); ?>"
                                    data-start="<?php echo $row['StartTime']; ?>"
                                    data-end="<?php echo $row['EndTime']; ?>"
                                    data-caltype="<?php echo htmlspecialchars($row['CalendarType']); ?>">Edit</button>
                            <a href="<?php echo userUrl('events.php', 'delete=' . $row['EventID']); ?>"
                               class="btn btn-delete" onclick="return confirm('Delete event #<?php echo $row['EventID']; ?>?');">Delete</a>
                        <?php } else { ?>
                            <span style="color:#a0aec0; font-size:0.8rem;">View only</span>
                        <?php } ?>
                    </td>
                </tr>
                <?php } } else { echo "<tr><td colspan='13' class='text-center'>No events found. Create one!</td></tr>"; } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ADD EVENT MODAL -->
<div class="modal fade" id="addEventModal" tabindex="-1">
  <div class="modal-dialog modal-lg"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title">Create New Event</h5>
        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button></div>
    <form action="" method="POST"><input type="hidden" name="action" value="add">
        <input type="hidden" name="current_user_id" value="<?php echo $current_user_id; ?>">
        <div class="modal-body">
            <div class="row">
                <div class="col-md-6 form-group"><label>Title *</label>
                    <input type="text" name="title" class="form-control" required></div>
                <div class="col-md-6 form-group"><label>Calendar Type * <small>(pick or type new)</small></label>
                    <input type="text" name="calendar_type" class="form-control" list="calList" required>
                    <datalist id="calList"><option value="Work"><option value="Personal"><option value="Social"><option value="Finance"></datalist></div>
            </div>
            <div class="form-group"><label>Description</label>
                <textarea name="description" class="form-control" rows="2"></textarea></div>
            <div class="row">
                <div class="col-md-6 form-group"><label>Start Time *</label>
                    <input type="datetime-local" name="start_time" class="form-control" required></div>
                <div class="col-md-6 form-group"><label>End Time *</label>
                    <input type="datetime-local" name="end_time" class="form-control" required></div>
            </div>
            <div class="row">
                <div class="col-md-4 form-group"><label>Category <small>(pick or type new)</small></label>
                    <input type="text" name="category_input" class="form-control" list="catList2">
                    <datalist id="catList2"><?php mysqli_data_seek($cats_r, 0); foreach($cats_r as $c) echo "<option value='" . $c['CateogryName'] . "'>"; ?></datalist></div>
                <div class="col-md-4 form-group"><label>Location <small>(pick or type new)</small></label>
                    <input type="text" name="location_input" class="form-control" list="locList2">
                    <datalist id="locList2"><?php mysqli_data_seek($locs_r, 0); foreach($locs_r as $l) { $lb=$l['StreetAddress']; if(!empty($l['City'])) $lb.=', '.$l['City']; echo "<option value='".htmlspecialchars($lb)."'>"; } ?></datalist></div>
                <div class="col-md-4 form-group"><label>Tags <small>(comma-separated)</small></label>
                    <input type="text" name="tags" class="form-control" placeholder="e.g. team, sprint"></div>
            </div>
            <hr>
            <div class="row">
                <div class="col-md-4 form-group"><label>Event Visibility *</label>
                    <select name="event_type" class="form-control" id="eventTypeSelect" onchange="toggleEventType()">
                        <option value="private">Private</option>
                        <option value="shared">Shared</option>
                    </select></div>
                <div class="col-md-8" id="privateFields">
                    <div class="form-group"><label>Privacy Notes</label>
                        <input type="text" name="privacy_notes" class="form-control" placeholder="Personal notes..."></div>
                </div>
                <div class="col-md-4" id="sharedGroupField" style="display:none;">
                    <div class="form-group"><label>Share with Group</label>
                        <select name="share_group_id" class="form-control">
                            <?php mysqli_data_seek($groups_r, 0); foreach($groups_r as $g) echo "<option value='" . $g['GroupID'] . "'>" . $g['GroupName'] . "</option>"; ?>
                        </select></div>
                </div>
                <div class="col-md-4" id="sharedAccessField" style="display:none;">
                    <div class="form-group"><label>Access Level</label>
                        <select name="access_level" class="form-control">
                            <option value="view">View Only</option>
                            <option value="edit">Edit</option>
                        </select></div>
                </div>
            </div>
            <hr>
            <div class="row">
                <div class="col-md-6 form-group"><label>Reminder Time <small>(optional)</small></label>
                    <input type="datetime-local" name="reminder_time" class="form-control"></div>
                <div class="col-md-6 form-group"><label>Reminder Type</label>
                    <select name="reminder_type" class="form-control">
                        <option value="Email">Email</option><option value="Push">Push</option>
                        <option value="SMS">SMS</option><option value="InApp">InApp</option>
                    </select></div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary">Create Event</button></div>
    </form>
  </div></div>
</div>

<!-- EDIT EVENT MODAL -->
<div class="modal fade" id="editEventModal" tabindex="-1">
  <div class="modal-dialog modal-lg"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title">Edit Event</h5>
        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button></div>
    <form action="" method="POST"><input type="hidden" name="action" value="edit">
        <input type="hidden" name="current_user_id" value="<?php echo $current_user_id; ?>">
        <input type="hidden" name="event_id" id="edit_eid">
        <div class="modal-body">
            <div class="row">
                <div class="col-md-6 form-group"><label>Title *</label>
                    <input type="text" name="title" id="edit_title" class="form-control" required></div>
                <div class="col-md-6 form-group"><label>Calendar Type *</label>
                    <input type="text" name="calendar_type" id="edit_caltype" class="form-control" list="editCalList" required>
                    <datalist id="editCalList"><option value="Work"><option value="Personal"><option value="Social"><option value="Finance"></datalist></div>
            </div>
            <div class="form-group"><label>Description</label>
                <textarea name="description" id="edit_desc" class="form-control" rows="2"></textarea></div>
            <div class="row">
                <div class="col-md-6 form-group"><label>Start Time *</label>
                    <input type="datetime-local" name="start_time" id="edit_start" class="form-control" required></div>
                <div class="col-md-6 form-group"><label>End Time *</label>
                    <input type="datetime-local" name="end_time" id="edit_end" class="form-control" required></div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-warning">Save Changes</button></div>
    </form>
  </div></div>
</div>

<?php include 'footer.php'; ?>
<script>
function toggleEventType() {
    var v = document.getElementById('eventTypeSelect').value;
    document.getElementById('privateFields').style.display = (v=='private') ? 'block' : 'none';
    document.getElementById('sharedGroupField').style.display = (v=='shared') ? 'block' : 'none';
    document.getElementById('sharedAccessField').style.display = (v=='shared') ? 'block' : 'none';
}
function openEditEvent(btn) {
    document.getElementById('edit_eid').value    = btn.getAttribute('data-id');
    document.getElementById('edit_title').value  = btn.getAttribute('data-title');
    document.getElementById('edit_desc').value   = btn.getAttribute('data-desc');
    document.getElementById('edit_caltype').value = btn.getAttribute('data-caltype');
    document.getElementById('edit_start').value  = btn.getAttribute('data-start').replace(' ','T').substring(0,16);
    document.getElementById('edit_end').value    = btn.getAttribute('data-end').replace(' ','T').substring(0,16);
    $('#editEventModal').modal('show');
}
</script>
</body></html>
<?php $conn->close(); ?>
