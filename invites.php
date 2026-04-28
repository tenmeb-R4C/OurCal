<?php require_once 'db_config.php'; ?>
<!DOCTYPE html>
<html>
<head>
    <title>OurCal - Invites</title>
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

// ==================== SEND INVITE (POST) ====================
if (!$is_admin && $_SERVER['REQUEST_METHOD'] == 'POST' && ($_POST['action'] ?? '') == 'add') {
    $type = $_POST['invite_type'];
    $msg_text = $_POST['message']; $expiry = $_POST['expiry_date']; $status = "Pending";

    // Server-side safety check (in case browser validation is bypassed)
    if (empty($expiry)) {
        $message = "Please fill out the expiry date."; $msg_type = "danger"; $type = "";
    } else if ($type == 'EventRequest') {
        // Event invite: receiver comes from dropdown (group members only)
        $recv = intval($_POST['receiver_id']);
        $eid = intval($_POST['event_id']);
        if ($recv == 0 || $eid == 0) {
            $message = "Please select both a group member and an event.";
            $msg_type = "danger"; $type = "";
        } else {
            $stmt = $conn->prepare("INSERT INTO Invite (Status,ExpiryDate,InvitationMessage,InviteType,SenderID,ReceiverID,EventID,GroupID) VALUES (?,?,?,?,?,?,?,NULL)");
            $stmt->bind_param("ssssiis", $status, $expiry, $msg_text, $type, $current_user_id, $recv, $eid);
        }
    } else {
        // Group join request: receiver comes from typed username (they know it from outside)
        $recv_username = trim($_POST['receiver_username']);
        $gid = intval($_POST['group_id']);

        if (empty($recv_username) || $gid == 0) {
            $message = "Please enter a username and select a group.";
            $msg_type = "danger"; $type = "";
        } else {
            $recv_esc = mysqli_real_escape_string($conn, $recv_username);
            $recv_result = mysqli_query($conn, "SELECT UserID FROM `User` WHERE Username='$recv_esc' AND UserType='StandardUser' LIMIT 1");

            if (mysqli_num_rows($recv_result) == 0) {
                $message = "User '$recv_username' not found. Please check the username.";
                $msg_type = "danger"; $type = "";
            } else {
                $recv = mysqli_fetch_assoc($recv_result)['UserID'];
                if ($recv == $current_user_id) {
                    $message = "You cannot invite yourself."; $msg_type = "danger"; $type = "";
                } else {
                    $stmt = $conn->prepare("INSERT INTO Invite (Status,ExpiryDate,InvitationMessage,InviteType,SenderID,ReceiverID,EventID,GroupID) VALUES (?,?,?,?,?,?,NULL,?)");
                    $stmt->bind_param("ssssiis", $status, $expiry, $msg_text, $type, $current_user_id, $recv, $gid);
                }
            }
        }
    }

    if ($type != "" && isset($stmt)) {
        if ($stmt->execute()) { $message = "Invite sent!"; $msg_type = "success"; }
        else { $message = "Error: " . $conn->error; $msg_type = "danger"; }
        $stmt->close();
    }
}

// ==================== ACCEPT / DECLINE (GET) ====================
if (!$is_admin && isset($_GET['accept'])) {
    $aid = intval($_GET['accept']);
    mysqli_query($conn, "UPDATE Invite SET Status='Accepted' WHERE InviteID=$aid AND ReceiverID=$current_user_id");
    // If it's a group join request, add to Membership
    $inv = mysqli_fetch_assoc(mysqli_query($conn, "SELECT InviteType, GroupID FROM Invite WHERE InviteID=$aid"));
    if ($inv && $inv['InviteType'] == 'GroupJoinRequest' && $inv['GroupID']) {
        $today = date('Y-m-d'); $role = "Member";
        $check = mysqli_query($conn, "SELECT * FROM Membership WHERE UserID=$current_user_id AND GroupID=" . $inv['GroupID']);
        if (mysqli_num_rows($check) == 0) {
            $stmt = $conn->prepare("INSERT INTO Membership (UserID, GroupID, JoinDate, Role) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("iiss", $current_user_id, $inv['GroupID'], $today, $role);
            $stmt->execute(); $stmt->close();
        }
    }
    $message = "Invite accepted!"; $msg_type = "success";
}
if (!$is_admin && isset($_GET['decline'])) {
    $did = intval($_GET['decline']);
    mysqli_query($conn, "UPDATE Invite SET Status='Declined' WHERE InviteID=$did AND ReceiverID=$current_user_id");
    $message = "Invite declined."; $msg_type = "warning";
}

// ==================== DELETE ====================
if (!$is_admin && isset($_GET['delete'])) {
    $del = intval($_GET['delete']);
    mysqli_query($conn, "DELETE FROM Invite WHERE InviteID=$del AND (SenderID=$current_user_id OR ReceiverID=$current_user_id)");
    $message = "Invite deleted."; $msg_type = "warning";
}

// ==================== SEARCH ====================
$search = ""; $where_received = ""; $where_sent = "";
if (!empty($_GET['search'])) {
    $search = mysqli_real_escape_string($conn, $_GET['search']);
    $where_received = "AND (s.Username LIKE '%$search%' OR i.InvitationMessage LIKE '%$search%' OR e.Title LIKE '%$search%' OR g.GroupName LIKE '%$search%')";
    $where_sent     = "AND (r.Username LIKE '%$search%' OR i.InvitationMessage LIKE '%$search%' OR e.Title LIKE '%$search%' OR g.GroupName LIKE '%$search%')";
}

// Fetch received invites
$received = mysqli_query($conn,
    "SELECT i.InviteID, i.Status, i.SentDate, i.ExpiryDate, i.InvitationMessage, i.InviteType,
            s.Username AS SenderName,
            IFNULL(e.Title,'-') AS EventTitle, IFNULL(g.GroupName,'-') AS GroupName
     FROM Invite i
     JOIN `User` s ON i.SenderID=s.UserID
     LEFT JOIN Event e ON i.EventID=e.EventID
     LEFT JOIN `Group` g ON i.GroupID=g.GroupID
     WHERE i.ReceiverID=$current_user_id
     $where_received
     ORDER BY i.SentDate DESC");

// Fetch sent invites
$sent = mysqli_query($conn,
    "SELECT i.InviteID, i.Status, i.SentDate, i.ExpiryDate, i.InvitationMessage, i.InviteType,
            r.Username AS ReceiverName,
            IFNULL(e.Title,'-') AS EventTitle, IFNULL(g.GroupName,'-') AS GroupName
     FROM Invite i
     JOIN `User` r ON i.ReceiverID=r.UserID
     LEFT JOIN Event e ON i.EventID=e.EventID
     LEFT JOIN `Group` g ON i.GroupID=g.GroupID
     WHERE i.SenderID=$current_user_id
     $where_sent
     ORDER BY i.SentDate DESC");

// For send invite modal
// Event invites: only show users who share a group with me (we know each other through groups)
// Filter out Admin accounts — admins are not invitable
$group_members_r = mysqli_query($conn,
    "SELECT DISTINCT u.UserID, u.Username
     FROM `User` u
     JOIN Membership m ON u.UserID = m.UserID
     WHERE m.GroupID IN (SELECT GroupID FROM Membership WHERE UserID = $current_user_id)
     AND u.UserID != $current_user_id
     AND u.UserType = 'StandardUser'
     ORDER BY u.Username");

// My events (to invite people to) — ONLY Shared events; private events should not be invitable
$events_r = mysqli_query($conn,
    "SELECT e.EventID, e.Title FROM Event e
     JOIN SharedEvent se ON e.EventID = se.EventID
     WHERE e.CreatorID=$current_user_id
     ORDER BY e.Title");
// My groups (to invite people to join)
$groups_r = mysqli_query($conn, "SELECT g.GroupID, g.GroupName FROM `Group` g JOIN Membership m ON g.GroupID=m.GroupID WHERE m.UserID=$current_user_id");
?>

<div class="container-fluid" style="padding:25px;">
    <div class="page-header">
        <h2>&#9993; Invites</h2>
        <p>Manage your received and sent invitations</p>
    </div>

    <?php if ($message != "") { ?>
        <div class="alert alert-<?php echo $msg_type; ?> alert-dismissible fade show flash-msg">
            <?php echo $message; ?><button type="button" class="close" data-dismiss="alert">&times;</button></div>
    <?php } ?>

    <?php if (!$is_admin) { ?>
    <button class="btn btn-primary" style="margin-bottom:15px;" data-toggle="modal" data-target="#sendInviteModal">+ Send Invite</button>
    <?php } ?>

    <!-- SEARCH BAR -->
    <div class="search-box">
        <form action="" method="GET">
            <input type="hidden" name="switch_user" value="<?php echo $current_user_id; ?>">
            <div class="row">
                <div class="col-md-10"><label><b>Search Invites</b></label>
                    <input type="text" name="search" class="form-control" placeholder="Search by sender, receiver, message, event, or group..."
                           value="<?php echo htmlspecialchars($search); ?>"></div>
                <div class="col-md-2" style="padding-top:30px; white-space:nowrap;">
                    <button type="submit" class="btn btn-primary">Search</button>
                    <a href="<?php echo userUrl('invites.php'); ?>" class="btn btn-secondary">Reset</a></div>
            </div>
        </form>
    </div>

    <!-- RECEIVED INVITES -->
    <div class="section-title">Received Invites</div>
    <div class="content-card" style="margin-bottom:25px;">
        <div class="table-responsive">
            <table class="table table-hover table-striped">
                <thead><tr><th>ID</th><th>From</th><th>Type</th><th>Event/Group</th><th>Message</th><th>Status</th><th>Sent</th><th>Expires</th><th>Actions</th></tr></thead>
                <tbody>
                <?php if (mysqli_num_rows($received) > 0) { foreach ($received as $row) {
                    $badges=['Pending'=>'warning','Accepted'=>'success','Declined'=>'danger','Expired'=>'secondary'];
                    $b = $badges[$row['Status']] ?? 'info';
                ?>
                <tr>
                    <td><?php echo $row['InviteID']; ?></td>
                    <td><?php echo $row['SenderName']; ?></td>
                    <td><small><?php echo $row['InviteType']; ?></small></td>
                    <td><?php echo ($row['InviteType']=='EventRequest') ? $row['EventTitle'] : $row['GroupName']; ?></td>
                    <td><small><?php echo $row['InvitationMessage']; ?></small></td>
                    <td><span class="badge badge-<?php echo $b; ?>"><?php echo $row['Status']; ?></span></td>
                    <td><small><?php echo $row['SentDate']; ?></small></td>
                    <td><small><?php echo $row['ExpiryDate']; ?></small></td>
                    <td style="white-space:nowrap;">
                        <?php if ($row['Status'] == 'Pending') { ?>
                            <a href="<?php echo userUrl('invites.php', 'accept=' . $row['InviteID']); ?>"
                               class="btn btn-sm btn-success" style="font-size:0.75rem; padding:3px 8px;">Accept</a>
                            <a href="<?php echo userUrl('invites.php', 'decline=' . $row['InviteID']); ?>"
                               class="btn btn-sm btn-danger" style="font-size:0.75rem; padding:3px 8px;">Decline</a>
                        <?php } ?>
                        <a href="<?php echo userUrl('invites.php', 'delete=' . $row['InviteID']); ?>"
                           class="btn btn-delete" onclick="return confirm('Delete?');">Delete</a>
                    </td>
                </tr>
                <?php } } else { echo "<tr><td colspan='9' class='text-center'>No received invites.</td></tr>"; } ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- SENT INVITES -->
    <div class="section-title">Sent Invites</div>
    <div class="content-card">
        <div class="table-responsive">
            <table class="table table-hover table-striped">
                <thead><tr><th>ID</th><th>To</th><th>Type</th><th>Event/Group</th><th>Message</th><th>Status</th><th>Sent</th><th>Expires</th><th>Actions</th></tr></thead>
                <tbody>
                <?php if (mysqli_num_rows($sent) > 0) { foreach ($sent as $row) {
                    $badges=['Pending'=>'warning','Accepted'=>'success','Declined'=>'danger','Expired'=>'secondary'];
                    $b = $badges[$row['Status']] ?? 'info';
                ?>
                <tr>
                    <td><?php echo $row['InviteID']; ?></td>
                    <td><?php echo $row['ReceiverName']; ?></td>
                    <td><small><?php echo $row['InviteType']; ?></small></td>
                    <td><?php echo ($row['InviteType']=='EventRequest') ? $row['EventTitle'] : $row['GroupName']; ?></td>
                    <td><small><?php echo $row['InvitationMessage']; ?></small></td>
                    <td><span class="badge badge-<?php echo $b; ?>"><?php echo $row['Status']; ?></span></td>
                    <td><small><?php echo $row['SentDate']; ?></small></td>
                    <td><small><?php echo $row['ExpiryDate']; ?></small></td>
                    <td>
                        <a href="<?php echo userUrl('invites.php', 'delete=' . $row['InviteID']); ?>"
                           class="btn btn-delete" onclick="return confirm('Delete?');">Delete</a>
                    </td>
                </tr>
                <?php } } else { echo "<tr><td colspan='9' class='text-center'>No sent invites.</td></tr>"; } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- SEND INVITE MODAL -->
<div class="modal fade" id="sendInviteModal" tabindex="-1">
  <div class="modal-dialog modal-lg"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title">Send Invite</h5>
        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button></div>
    <form action="" method="POST"><input type="hidden" name="action" value="add">
        <input type="hidden" name="current_user_id" value="<?php echo $current_user_id; ?>">
        <div class="modal-body">
            <div class="row">
                <div class="col-md-6 form-group"><label>Invite Type *</label>
                    <select name="invite_type" class="form-control" id="invTypeSelect" onchange="toggleInvTarget()">
                        <option value="EventRequest">Event Invite (to a group member)</option>
                        <option value="GroupJoinRequest">Group Join Request (by username)</option>
                    </select></div>
                <div class="col-md-6 form-group"><label>Expiry Date *</label>
                    <input type="datetime-local" name="expiry_date" class="form-control" required></div>
            </div>

            <!-- EVENT INVITE: pick from group members (people you already know through groups) -->
            <div id="eventInviteFields">
                <div class="row">
                    <div class="col-md-6 form-group">
                        <label>Send To * <small>(members from your groups)</small></label>
                        <select name="receiver_id" class="form-control" id="eventReceiverSelect" required>
                            <option value="">-- Select Group Member --</option>
                            <?php foreach ($group_members_r as $u) echo "<option value='" . $u['UserID'] . "'>" . $u['Username'] . "</option>"; ?>
                        </select>
                    </div>
                    <div class="col-md-6 form-group"><label>Event *</label>
                        <select name="event_id" class="form-control" id="eventIdSelect" required>
                            <option value="">-- Select Your Event --</option>
                            <?php foreach ($events_r as $e) echo "<option value='" . $e['EventID'] . "'>" . $e['Title'] . "</option>"; ?>
                        </select>
                    </div>
                </div>
            </div>

            <!-- GROUP JOIN REQUEST: type a username (you know them from outside the platform) -->
            <div id="groupInviteFields" style="display:none;">
                <div class="row">
                    <div class="col-md-6 form-group">
                        <label>Username * <small>(type their OurCal username)</small></label>
                        <input type="text" name="receiver_username" class="form-control"
                               placeholder="e.g. jane_smith" id="usernameInput">
                    </div>
                    <div class="col-md-6 form-group"><label>Invite to Group *</label>
                        <select name="group_id" class="form-control" id="groupIdSelect">
                            <option value="">-- Select Your Group --</option>
                            <?php mysqli_data_seek($groups_r, 0);
                            foreach ($groups_r as $g) echo "<option value='" . $g['GroupID'] . "'>" . $g['GroupName'] . "</option>"; ?>
                        </select>
                    </div>
                </div>
            </div>

            <div class="form-group"><label>Message</label>
                <textarea name="message" class="form-control" rows="2" placeholder="Add a personal note..."></textarea></div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary">Send Invite</button></div>
    </form>
  </div></div>
</div>

<?php include 'footer.php'; ?>
<script>
function toggleInvTarget() {
    var v = document.getElementById('invTypeSelect').value;
    // Event invite: show group members dropdown + event picker
    document.getElementById('eventInviteFields').style.display = (v=='EventRequest') ? 'block' : 'none';
    // Group invite: show username text input + group picker
    document.getElementById('groupInviteFields').style.display = (v=='GroupJoinRequest') ? 'block' : 'none';

    // Toggle required ONLY for the visible mode (hidden required fields would block submission)
    document.getElementById('eventReceiverSelect').required = (v=='EventRequest');
    document.getElementById('eventIdSelect').required       = (v=='EventRequest');
    document.getElementById('usernameInput').required       = (v=='GroupJoinRequest');
    document.getElementById('groupIdSelect').required       = (v=='GroupJoinRequest');
}
</script>
</body></html>
<?php $conn->close(); ?>
