<?php require_once 'db_config.php'; ?>
<!DOCTYPE html>
<html>
<head>
    <title>OurCal - My Groups</title>
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

// ==================== CREATE GROUP ====================
if (!$is_admin && $_SERVER['REQUEST_METHOD'] == 'POST' && ($_POST['action'] ?? '') == 'add') {
    $name = $_POST['group_name']; $desc = $_POST['group_desc'];
    $max = intval($_POST['max_members']); $today = date('Y-m-d');
    $stmt = $conn->prepare("INSERT INTO `Group` (GroupName, GroupDescription, CreationDate, MaxMembers) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("sssi", $name, $desc, $today, $max);
    if ($stmt->execute()) {
        $newGID = $conn->insert_id; $stmt->close();
        $role = "GroupAdmin";
        $stmt2 = $conn->prepare("INSERT INTO Membership (UserID, GroupID, JoinDate, Role) VALUES (?, ?, ?, ?)");
        $stmt2->bind_param("iiss", $current_user_id, $newGID, $today, $role);
        $stmt2->execute(); $stmt2->close();
        $message = "Group '$name' created and you're the admin!"; $msg_type = "success";
    } else { $message = "Error: " . $conn->error; $msg_type = "danger"; $stmt->close(); }
}

// ==================== EDIT GROUP ====================
if (!$is_admin && $_SERVER['REQUEST_METHOD'] == 'POST' && ($_POST['action'] ?? '') == 'edit') {
    $gid = intval($_POST['group_id']); $name = $_POST['group_name'];
    $desc = $_POST['group_desc']; $max = intval($_POST['max_members']);
    $stmt = $conn->prepare("UPDATE `Group` SET GroupName=?, GroupDescription=?, MaxMembers=? WHERE GroupID=?");
    $stmt->bind_param("ssii", $name, $desc, $max, $gid);
    if ($stmt->execute()) { $message = "Group updated!"; $msg_type = "success"; }
    else { $message = "Error: " . $conn->error; $msg_type = "danger"; }
    $stmt->close();
}

// ==================== INVITE MEMBER BY USERNAME ====================
if (!$is_admin && $_SERVER['REQUEST_METHOD'] == 'POST' && ($_POST['action'] ?? '') == 'invite_member') {
    $inv_username = trim($_POST['invite_username']);
    $inv_group_id = intval($_POST['invite_group_id']);
    $inv_esc = mysqli_real_escape_string($conn, $inv_username);

    // Permission check: only GroupAdmin or Moderator of this group can invite
    $perm = mysqli_query($conn, "SELECT Role FROM Membership WHERE UserID=$current_user_id AND GroupID=$inv_group_id");
    $myRole = (mysqli_num_rows($perm) > 0) ? mysqli_fetch_assoc($perm)['Role'] : "";
    if ($myRole != 'GroupAdmin' && $myRole != 'Moderator') {
        $message = "You don't have permission to invite members to this group.";
        $msg_type = "danger";
    } else {
    // Look up the user (admins can't be invited)
    $user_check = mysqli_query($conn, "SELECT UserID FROM `User` WHERE Username='$inv_esc' AND UserType='StandardUser' LIMIT 1");
    if (mysqli_num_rows($user_check) == 0) {
        $message = "User '$inv_username' not found on OurCal. Please check the username.";
        $msg_type = "danger";
    } else {
        $target_id = mysqli_fetch_assoc($user_check)['UserID'];
        if ($target_id == $current_user_id) {
            $message = "You are already in this group."; $msg_type = "danger";
        } else {
            // Check if already a member
            $mem_check = mysqli_query($conn, "SELECT * FROM Membership WHERE UserID=$target_id AND GroupID=$inv_group_id");
            if (mysqli_num_rows($mem_check) > 0) {
                $message = "'$inv_username' is already in this group."; $msg_type = "info";
            } else {
                // Check if invite already pending
                $inv_check = mysqli_query($conn, "SELECT * FROM Invite WHERE ReceiverID=$target_id AND GroupID=$inv_group_id AND Status='Pending' AND InviteType='GroupJoinRequest'");
                if (mysqli_num_rows($inv_check) > 0) {
                    $message = "An invite is already pending for '$inv_username'."; $msg_type = "info";
                } else {
                    // Send the group join request
                    $status = "Pending"; $type = "GroupJoinRequest";
                    $inv_msg = $_POST['invite_message'];
                    $expiry = date('Y-m-d H:i:s', strtotime('+7 days'));
                    $stmt = $conn->prepare("INSERT INTO Invite (Status, ExpiryDate, InvitationMessage, InviteType, SenderID, ReceiverID, EventID, GroupID) VALUES (?, ?, ?, ?, ?, ?, NULL, ?)");
                    $stmt->bind_param("ssssiis", $status, $expiry, $inv_msg, $type, $current_user_id, $target_id, $inv_group_id);
                    if ($stmt->execute()) {
                        $message = "Invite sent to '$inv_username'!"; $msg_type = "success";
                    } else {
                        $message = "Error: " . $conn->error; $msg_type = "danger";
                    }
                    $stmt->close();
                }
            }
        }
    }
    } // end permission check
}

// ==================== LEAVE GROUP ====================
if (!$is_admin && isset($_GET['leave'])) {
    $lgid = intval($_GET['leave']);
    mysqli_query($conn, "DELETE FROM Membership WHERE UserID=$current_user_id AND GroupID=$lgid");
    $message = "You left the group."; $msg_type = "warning";
}

// ==================== CHANGE MEMBER ROLE (Admin only) ====================
if (!$is_admin && $_SERVER['REQUEST_METHOD'] == 'POST' && ($_POST['action'] ?? '') == 'change_role') {
    $cr_uid  = intval($_POST['target_uid']);
    $cr_gid  = intval($_POST['target_gid']);
    $cr_role = $_POST['new_role']; // 'Member' or 'Moderator'

    // Verify viewer is GroupAdmin of this group
    $vr = mysqli_query($conn, "SELECT Role FROM Membership WHERE UserID=$current_user_id AND GroupID=$cr_gid");
    $myRole = (mysqli_num_rows($vr) > 0) ? mysqli_fetch_assoc($vr)['Role'] : "";

    // Get target's current role
    $tr = mysqli_query($conn, "SELECT m.Role, u.Username FROM Membership m JOIN `User` u ON m.UserID=u.UserID WHERE m.UserID=$cr_uid AND m.GroupID=$cr_gid");
    $target = (mysqli_num_rows($tr) > 0) ? mysqli_fetch_assoc($tr) : null;

    // Rules:
    //  - Only GroupAdmin can change roles
    //  - Cannot change your own role
    //  - Cannot change another GroupAdmin's role
    //  - new_role must be Member or Moderator (not GroupAdmin)
    if ($myRole != 'GroupAdmin') {
        $message = "Only the Group Admin can change member roles."; $msg_type = "danger";
    } else if (!$target) {
        $message = "That user is not in this group."; $msg_type = "danger";
    } else if ($cr_uid == $current_user_id) {
        $message = "You cannot change your own role."; $msg_type = "danger";
    } else if ($target['Role'] == 'GroupAdmin') {
        $message = "You cannot change another Admin's role."; $msg_type = "danger";
    } else if ($cr_role != 'Member' && $cr_role != 'Moderator') {
        $message = "Invalid role selection."; $msg_type = "danger";
    } else {
        $stmt = $conn->prepare("UPDATE Membership SET Role=? WHERE UserID=? AND GroupID=?");
        $stmt->bind_param("sii", $cr_role, $cr_uid, $cr_gid);
        if ($stmt->execute()) {
            $message = $target['Username'] . " is now a " . $cr_role . "."; $msg_type = "success";
        } else {
            $message = "Error: " . $conn->error; $msg_type = "danger";
        }
        $stmt->close();
    }
}

// ==================== KICK MEMBER ====================
if (!$is_admin && isset($_GET['kick']) && isset($_GET['kick_gid'])) {
    $kick_uid = intval($_GET['kick']);
    $kick_gid = intval($_GET['kick_gid']);

    // Get my role in this group
    $myr = mysqli_query($conn, "SELECT Role FROM Membership WHERE UserID=$current_user_id AND GroupID=$kick_gid");
    $myRole = (mysqli_num_rows($myr) > 0) ? mysqli_fetch_assoc($myr)['Role'] : "";

    // Get target's role + username
    $tgr = mysqli_query($conn, "SELECT m.Role, u.Username FROM Membership m JOIN `User` u ON m.UserID=u.UserID WHERE m.UserID=$kick_uid AND m.GroupID=$kick_gid");
    if (mysqli_num_rows($tgr) == 0) {
        $message = "That user is not in this group."; $msg_type = "danger";
    } else {
        $target = mysqli_fetch_assoc($tgr);
        $targetRole = $target['Role']; $targetName = $target['Username'];

        // Permission rules:
        //  - Cannot kick yourself (use Leave instead)
        //  - GroupAdmin can kick anyone (Members and Moderators)
        //  - Moderator can kick Members only (not other Moderators, not the Admin)
        //  - Member cannot kick anyone
        if ($kick_uid == $current_user_id) {
            $message = "You cannot kick yourself. Use 'Leave' instead."; $msg_type = "danger";
        } else if ($myRole == 'GroupAdmin') {
            mysqli_query($conn, "DELETE FROM Membership WHERE UserID=$kick_uid AND GroupID=$kick_gid");
            $message = "$targetName was removed from the group."; $msg_type = "warning";
        } else if ($myRole == 'Moderator' && $targetRole == 'Member') {
            mysqli_query($conn, "DELETE FROM Membership WHERE UserID=$kick_uid AND GroupID=$kick_gid");
            $message = "$targetName was removed from the group."; $msg_type = "warning";
        } else {
            $message = "You don't have permission to remove that member."; $msg_type = "danger";
        }
    }
}

// ==================== SEARCH ====================
$search = ""; $where = "";
if (!empty($_GET['search'])) {
    $search = mysqli_real_escape_string($conn, $_GET['search']);
    $where = "AND (g.GroupName LIKE '%$search%' OR g.GroupDescription LIKE '%$search%')";
}

// My groups
$groups = mysqli_query($conn,
    "SELECT g.GroupID, g.GroupName, g.GroupDescription, g.CreationDate, g.MaxMembers,
            m.Role, m.JoinDate,
            (SELECT COUNT(*) FROM Membership WHERE GroupID=g.GroupID) AS MemberCount
     FROM `Group` g JOIN Membership m ON g.GroupID=m.GroupID
     WHERE m.UserID=$current_user_id $where
     ORDER BY g.GroupName ASC");

// Build array so we can loop twice (table + members)
$groupsArr = [];
while ($row = mysqli_fetch_assoc($groups)) { $groupsArr[] = $row; }

?>

<div class="container-fluid" style="padding:25px;">
    <div class="page-header">
        <h2>&#128101; My Groups</h2>
        <p>Manage your group memberships and invite new members</p>
    </div>

    <?php if ($message != "") { ?>
        <div class="alert alert-<?php echo $msg_type; ?> alert-dismissible fade show flash-msg">
            <?php echo $message; ?><button type="button" class="close" data-dismiss="alert">&times;</button></div>
    <?php } ?>

    <div class="search-box">
        <form action="" method="GET">
            <input type="hidden" name="switch_user" value="<?php echo $current_user_id; ?>">
            <div class="row">
                <div class="col-md-10"><label><b>Search My Groups</b></label>
                    <input type="text" name="search" class="form-control" placeholder="Search by name or description..."
                           value="<?php echo htmlspecialchars($search); ?>"></div>
                <div class="col-md-2" style="padding-top:30px; white-space:nowrap;">
                    <button type="submit" class="btn btn-primary">Search</button>
                    <a href="<?php echo userUrl('groups.php'); ?>" class="btn btn-secondary">Reset</a></div>
            </div>
        </form>
    </div>

    <?php if (!$is_admin) { ?>
    <button class="btn btn-primary" style="margin-bottom:15px;" data-toggle="modal" data-target="#addGroupModal">+ Create New Group</button>
    <?php } ?>

    <!-- MY GROUPS TABLE -->
    <div class="section-title">Groups I Belong To</div>
    <div class="content-card">
        <div class="table-responsive">
            <table class="table table-hover table-striped">
                <thead><tr><th>Name</th><th>Description</th><th>My Role</th><th>Joined</th><th>Members</th><th>Actions</th></tr></thead>
                <tbody>
                <?php if (count($groupsArr) > 0) { foreach ($groupsArr as $row) { ?>
                <tr>
                    <td><b><?php echo $row['GroupName']; ?></b></td>
                    <td><small><?php echo $row['GroupDescription']; ?></small></td>
                    <td><span class="badge badge-<?php echo ($row['Role']=='GroupAdmin') ? 'danger' : (($row['Role']=='Moderator') ? 'warning' : 'primary'); ?>">
                        <?php echo $row['Role']; ?></span></td>
                    <td><small><?php echo $row['JoinDate']; ?></small></td>
                    <td><span class="badge badge-<?php echo ($row['MemberCount'] >= $row['MaxMembers']) ? 'danger' : 'success'; ?>">
                        <?php echo $row['MemberCount']; ?>/<?php echo $row['MaxMembers']; ?></span></td>
                    <td style="white-space:nowrap;">
                        <!-- View Members button -->
                        <button class="btn btn-sm btn-info" data-toggle="collapse"
                                data-target="#members_<?php echo $row['GroupID']; ?>">Members</button>
                        <?php if ($row['Role'] == 'GroupAdmin') { ?>
                            <button class="btn btn-sm btn-warning" onclick="openEditGroup(this)"
                                    data-id="<?php echo $row['GroupID']; ?>"
                                    data-name="<?php echo htmlspecialchars($row['GroupName']); ?>"
                                    data-desc="<?php echo htmlspecialchars($row['GroupDescription']); ?>"
                                    data-max="<?php echo $row['MaxMembers']; ?>">Edit</button>
                        <?php } ?>
                        <?php if ($row['Role'] == 'GroupAdmin' || $row['Role'] == 'Moderator') { ?>
                            <button class="btn btn-sm btn-primary" onclick="openInviteMember(<?php echo $row['GroupID']; ?>, '<?php echo htmlspecialchars($row['GroupName']); ?>')">+ Invite</button>
                        <?php } ?>
                        <a href="<?php echo userUrl('groups.php', 'leave=' . $row['GroupID']); ?>"
                           class="btn btn-delete" onclick="return confirm('Leave this group?');">Leave</a>
                    </td>
                </tr>
                <!-- Collapsible Members Row (Bootstrap collapsible - Module 9) -->
                <tr>
                    <td colspan="6" style="padding:0; border:none;">
                        <div class="collapse" id="members_<?php echo $row['GroupID']; ?>">
                            <div style="background:#f7fafc; padding:12px 20px; border-radius:6px; margin:5px 0 10px;">
                                <b>Members of <?php echo $row['GroupName']; ?>:</b><br><br>
                                <?php
                                $members = mysqli_query($conn,
                                    "SELECT u.UserID, u.Username, IFNULL(p.DisplayName, u.Username) AS DisplayName,
                                            m.Role, m.JoinDate
                                     FROM Membership m
                                     JOIN `User` u ON m.UserID=u.UserID
                                     LEFT JOIN Profile p ON u.UserID=p.UserID
                                     WHERE m.GroupID=" . $row['GroupID'] . "
                                     ORDER BY m.Role DESC, u.Username ASC");
                                $myRoleHere = $row['Role']; // viewer's role in THIS group
                                $canKickAtAll = ($myRoleHere == 'GroupAdmin' || $myRoleHere == 'Moderator');
                                $canChangeRoles = ($myRoleHere == 'GroupAdmin');
                                if (mysqli_num_rows($members) > 0) {
                                    echo "<table class='table table-sm' style='margin:0; background:white; border-radius:6px;'>";
                                    echo "<thead><tr><th>Username</th><th>Display Name</th><th>Role</th><th>Joined</th>";
                                    if ($canKickAtAll) echo "<th>Action</th>";
                                    echo "</tr></thead><tbody>";
                                    foreach ($members as $mem) {
                                        $roleBadge = ($mem['Role']=='GroupAdmin') ? 'danger' : (($mem['Role']=='Moderator') ? 'warning' : 'primary');
                                        echo "<tr>";
                                        echo "<td>" . $mem['Username'] . "</td>";
                                        echo "<td>" . $mem['DisplayName'] . "</td>";
                                        echo "<td><span class='badge badge-$roleBadge'>" . $mem['Role'] . "</span></td>";
                                        echo "<td><small>" . $mem['JoinDate'] . "</small></td>";
                                        if ($canKickAtAll) {
                                            echo "<td style='white-space:nowrap;'>";
                                            // CHANGE ROLE dropdown — only GroupAdmin sees it, and only for non-Admin OTHER members
                                            if ($canChangeRoles && $mem['UserID'] != $current_user_id && $mem['Role'] != 'GroupAdmin') {
                                                echo "<form action='' method='POST' style='display:inline-block; margin-right:5px;'>";
                                                echo "<input type='hidden' name='action' value='change_role'>";
                                                echo "<input type='hidden' name='current_user_id' value='$current_user_id'>";
                                                echo "<input type='hidden' name='target_uid' value='" . $mem['UserID'] . "'>";
                                                echo "<input type='hidden' name='target_gid' value='" . $row['GroupID'] . "'>";
                                                echo "<select name='new_role' class='form-control form-control-sm' style='display:inline-block; width:auto; font-size:0.75rem; padding:2px 4px; height:auto;' onchange='if(confirm(\"Change this members role?\")) this.form.submit(); else this.value=this.getAttribute(\"data-orig\");' data-orig='" . $mem['Role'] . "'>";
                                                $opts = ['Member', 'Moderator'];
                                                foreach ($opts as $opt) {
                                                    $sel = ($opt == $mem['Role']) ? 'selected' : '';
                                                    echo "<option value='$opt' $sel>$opt</option>";
                                                }
                                                echo "</select>";
                                                echo "</form>";
                                            }
                                            // Decide if THIS specific member can be kicked by THIS viewer
                                            $canKickThis = false;
                                            if ($mem['UserID'] != $current_user_id) {
                                                if ($myRoleHere == 'GroupAdmin') $canKickThis = true; // admin kicks anyone else
                                                else if ($myRoleHere == 'Moderator' && $mem['Role'] == 'Member') $canKickThis = true; // mods kick members only
                                            }
                                            if ($canKickThis) {
                                                $kickUrl = userUrl('groups.php', 'kick=' . $mem['UserID'] . '&kick_gid=' . $row['GroupID']);
                                                echo "<a href='$kickUrl' class='btn btn-sm btn-danger' style='font-size:0.7rem; padding:2px 8px;' onclick=\"return confirm('Remove " . $mem['Username'] . " from the group?');\">Kick Out</a>";
                                            } else if (!$canChangeRoles || $mem['UserID'] == $current_user_id || $mem['Role'] == 'GroupAdmin') {
                                                echo "<small style='color:#a0aec0;'>—</small>";
                                            }
                                            echo "</td>";
                                        }
                                        echo "</tr>";
                                    }
                                    echo "</tbody></table>";
                                } else {
                                    echo "<p>No members.</p>";
                                }
                                ?>
                            </div>
                        </div>
                    </td>
                </tr>
                <?php } } else { echo "<tr><td colspan='6' class='text-center'>You are not in any groups yet.</td></tr>"; } ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<!-- CREATE GROUP MODAL -->
<div class="modal fade" id="addGroupModal" tabindex="-1">
  <div class="modal-dialog"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title">Create New Group</h5>
        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button></div>
    <form action="" method="POST"><input type="hidden" name="action" value="add">
        <input type="hidden" name="current_user_id" value="<?php echo $current_user_id; ?>">
        <div class="modal-body">
            <div class="form-group"><label>Group Name *</label><input type="text" name="group_name" class="form-control" required></div>
            <div class="form-group"><label>Description</label><textarea name="group_desc" class="form-control" rows="3"></textarea></div>
            <div class="form-group"><label>Max Members</label><input type="number" name="max_members" class="form-control" value="50" min="2"></div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary">Create Group</button></div>
    </form>
  </div></div>
</div>

<!-- EDIT GROUP MODAL -->
<div class="modal fade" id="editGroupModal" tabindex="-1">
  <div class="modal-dialog"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title">Edit Group</h5>
        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button></div>
    <form action="" method="POST"><input type="hidden" name="action" value="edit">
        <input type="hidden" name="current_user_id" value="<?php echo $current_user_id; ?>">
        <input type="hidden" name="group_id" id="edit_gid">
        <div class="modal-body">
            <div class="form-group"><label>Group Name *</label><input type="text" name="group_name" id="edit_gname" class="form-control" required></div>
            <div class="form-group"><label>Description</label><textarea name="group_desc" id="edit_gdesc" class="form-control" rows="3"></textarea></div>
            <div class="form-group"><label>Max Members</label><input type="number" name="max_members" id="edit_gmax" class="form-control" min="2"></div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-warning">Save Changes</button></div>
    </form>
  </div></div>
</div>

<!-- INVITE MEMBER MODAL -->
<div class="modal fade" id="inviteMemberModal" tabindex="-1">
  <div class="modal-dialog"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title">Invite Member to <span id="inv_group_display"></span></h5>
        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button></div>
    <form action="" method="POST"><input type="hidden" name="action" value="invite_member">
        <input type="hidden" name="current_user_id" value="<?php echo $current_user_id; ?>">
        <input type="hidden" name="invite_group_id" id="inv_group_id">
        <div class="modal-body">
            <div class="form-group">
                <label><b>Username *</b></label>
                <input type="text" name="invite_username" class="form-control"
                       placeholder="Type their OurCal username (e.g. jane_smith)" required>
                <small class="text-muted">The person must already have an OurCal account.</small>
            </div>
            <div class="form-group">
                <label><b>Message</b></label>
                <textarea name="invite_message" class="form-control" rows="2" placeholder="Hey! Join our group..."></textarea>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary">Send Invite</button></div>
    </form>
  </div></div>
</div>

<?php include 'footer.php'; ?>
<script>
function openEditGroup(btn) {
    document.getElementById('edit_gid').value   = btn.getAttribute('data-id');
    document.getElementById('edit_gname').value = btn.getAttribute('data-name');
    document.getElementById('edit_gdesc').value = btn.getAttribute('data-desc');
    document.getElementById('edit_gmax').value  = btn.getAttribute('data-max');
    $('#editGroupModal').modal('show');
}
function openInviteMember(groupId, groupName) {
    document.getElementById('inv_group_id').value      = groupId;
    document.getElementById('inv_group_display').innerText = groupName;
    $('#inviteMemberModal').modal('show');
}
</script>
</body></html>
<?php $conn->close(); ?>
