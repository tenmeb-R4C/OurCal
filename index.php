<?php require_once 'db_config.php'; ?>
<!DOCTYPE html>
<html>
<head>
    <title>OurCal - Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.1/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="css/style.css">
</head>
<body style="background-color: <?php echo $user_bg_color; ?>;">
<?php
require_once 'db_config.php';
include 'navbar.php';

$is_admin = ($current_user['UserType'] == 'Admin');

// Only run user-stat queries for standard users
if (!$is_admin) {
    $myEvents    = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM Event WHERE CreatorID=$current_user_id"))['c'];
    $invEvents   = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM Invite WHERE ReceiverID=$current_user_id AND Status='Accepted' AND InviteType='EventRequest'"))['c'];
    $totalMyEvt  = $myEvents + $invEvents;
    $myGroups    = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM Membership WHERE UserID=$current_user_id"))['c'];
    $myInvites   = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM Invite WHERE ReceiverID=$current_user_id AND Status='Pending'"))['c'];
    $myReminders = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM Reminder r JOIN Event e ON r.EventID=e.EventID WHERE e.CreatorID=$current_user_id AND r.Status='Scheduled'"))['c'];
}
?>

<div class="container-fluid" style="padding: 0;">

    <!-- Hero-style header -->
    <div class="page-header">
        <h2>Welcome back, <?php echo $current_user['DisplayName']; ?>.</h2>
        <p><?php echo $is_admin ? "System Administrator dashboard" : "Here's what's happening in your calendar"; ?></p>
    </div>

<?php if ($is_admin) { ?>

    <!-- ADMIN VIEW: read-only platform overview -->
    <div style="padding: 0 30px 30px;">
        <!-- Platform-wide read-only stats -->
        <div class="row">
            <?php
            $tUsers   = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM `User` WHERE UserType='StandardUser'"))['c'];
            $tGroups  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM `Group`"))['c'];
            $tEvents  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM Event"))['c'];
            $tInvites = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM Invite"))['c'];
            ?>
            <div class="col-6">
                <div class="stat-card card-events"><div class="stat-card-inner">
                    <div class="stat-icon-circle" style="background-color:#3d8b7a; color:white;">&#128100;</div>
                    <div class="stat-info"><div class="stat-number"><?php echo $tUsers; ?></div><div class="stat-label">Standard Users</div></div>
                </div></div>
            </div>
            <div class="col-6">
                <div class="stat-card card-groups"><div class="stat-card-inner">
                    <div class="stat-icon-circle" style="background-color:#e07a5f; color:white;">&#128101;</div>
                    <div class="stat-info"><div class="stat-number"><?php echo $tGroups; ?></div><div class="stat-label">Groups</div></div>
                </div></div>
            </div>
            <div class="col-6">
                <div class="stat-card card-invites"><div class="stat-card-inner">
                    <div class="stat-icon-circle" style="background-color:#d4a64e; color:white;">&#128197;</div>
                    <div class="stat-info"><div class="stat-number"><?php echo $tEvents; ?></div><div class="stat-label">Events</div></div>
                </div></div>
            </div>
            <div class="col-6">
                <div class="stat-card card-users"><div class="stat-card-inner">
                    <div class="stat-icon-circle" style="background-color:#5b7db1; color:white;">&#9993;</div>
                    <div class="stat-info"><div class="stat-number"><?php echo $tInvites; ?></div><div class="stat-label">Invites</div></div>
                </div></div>
            </div>
        </div>

        <div style="margin-top:25px; padding:20px; background:white; border-radius:8px;">
            <p style="color:#7a7a7a; margin:0;"><b>Your admin level:</b>
                <?php
                $lvl = mysqli_fetch_assoc(mysqli_query($conn, "SELECT AdminLevel FROM Admin WHERE UserID=$current_user_id"));
                echo $lvl ? $lvl['AdminLevel'] : '-';
                ?>
            </p>
        </div>
    </div>

<?php } else { ?>

    <div style="padding: 0 30px 30px;">

        <!-- Stat Cards -->
        <div class="row">
            <div class="col-6">
                <a href="<?php echo userUrl('events.php'); ?>" class="stat-card-link">
                    <div class="stat-card card-events">
                        <div class="stat-card-inner">
                            <div class="stat-icon-circle" style="background-color: #3d8b7a; color: white;">&#128197;</div>
                            <div class="stat-info">
                                <div class="stat-number"><?php echo $totalMyEvt; ?></div>
                                <div class="stat-label">Events</div>
                            </div>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-6">
                <a href="<?php echo userUrl('groups.php'); ?>" class="stat-card-link">
                    <div class="stat-card card-groups">
                        <div class="stat-card-inner">
                            <div class="stat-icon-circle" style="background-color: #e07a5f; color: white;">&#128101;</div>
                            <div class="stat-info">
                                <div class="stat-number"><?php echo $myGroups; ?></div>
                                <div class="stat-label">Groups</div>
                            </div>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-6">
                <a href="<?php echo userUrl('invites.php'); ?>" class="stat-card-link">
                    <div class="stat-card card-invites">
                        <div class="stat-card-inner">
                            <div class="stat-icon-circle" style="background-color: #d4a64e; color: white;">&#9993;</div>
                            <div class="stat-info">
                                <div class="stat-number"><?php echo $myInvites; ?></div>
                                <div class="stat-label">Pending</div>
                            </div>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-6">
                <a href="<?php echo userUrl('events.php'); ?>" class="stat-card-link">
                    <div class="stat-card card-users">
                        <div class="stat-card-inner">
                            <div class="stat-icon-circle" style="background-color: #5b7db1; color: white;">&#128276;</div>
                            <div class="stat-info">
                                <div class="stat-number"><?php echo $myReminders; ?></div>
                                <div class="stat-label">Reminders</div>
                            </div>
                        </div>
                    </div>
                </a>
            </div>
        </div>

        <!-- Content -->
        <div class="row">

            <!-- Upcoming Events -->
            <div class="col-lg-7" style="margin-bottom: 25px;">
                <div class="dashboard-card">
                    <div class="dashboard-card-header">
                        <span class="dashboard-card-title">&#128197; Upcoming Events</span>
                        <a href="<?php echo userUrl('events.php'); ?>" class="dashboard-card-action">View All &rarr;</a>
                    </div>
                    <div class="dashboard-card-body">
                        <?php
                        $query = "SELECT e.EventID, e.Title, e.StartTime, e.EndTime, e.CalendarType,
                                         IF(e.CreatorID=$current_user_id, 'Creator', 'Invited') AS MyRole
                                  FROM Event e
                                  WHERE e.CreatorID=$current_user_id
                                     OR e.EventID IN (SELECT EventID FROM Invite WHERE ReceiverID=$current_user_id AND Status='Accepted' AND InviteType='EventRequest')
                                  ORDER BY e.StartTime ASC LIMIT 5";
                        $result = mysqli_query($conn, $query);
                        if (mysqli_num_rows($result) > 0) { ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead><tr><th>Title</th><th>When</th><th>Type</th><th>Role</th><th>Tags</th></tr></thead>
                                <tbody>
                                <?php foreach ($result as $row) {
                                    $tags_r = mysqli_query($conn, "SELECT Tags FROM EventTags WHERE EventID=" . $row['EventID']);
                                    $tags = []; while ($t = mysqli_fetch_assoc($tags_r)) $tags[] = $t['Tags'];
                                ?>
                                <tr>
                                    <td><b><?php echo $row['Title']; ?></b></td>
                                    <td><small><?php echo date('M d \a\t g:i A', strtotime($row['StartTime'])); ?></small></td>
                                    <td><span class="badge badge-info"><?php echo $row['CalendarType']; ?></span></td>
                                    <td><span class="badge badge-<?php echo ($row['MyRole']=='Creator') ? 'success' : 'primary'; ?>"><?php echo $row['MyRole']; ?></span></td>
                                    <td><?php foreach($tags as $tg) echo "<span class='tag-badge'>$tg</span>"; ?></td>
                                </tr>
                                <?php } ?>
                                </tbody>
                            </table>
                        </div>
                        <?php } else { ?>
                        <div class="empty-state">
                            <div class="empty-state-icon">&#128197;</div>
                            <div class="empty-state-text">No upcoming events</div>
                            <a href="<?php echo userUrl('events.php'); ?>" class="btn btn-primary btn-sm" style="margin-top: 12px;">Create Your First Event</a>
                        </div>
                        <?php } ?>
                    </div>
                </div>
            </div>

            <!-- Right column -->
            <div class="col-lg-5">

                <!-- My Groups -->
                <div class="dashboard-card" style="margin-bottom: 25px;">
                    <div class="dashboard-card-header">
                        <span class="dashboard-card-title">&#128101; My Groups</span>
                        <a href="<?php echo userUrl('groups.php'); ?>" class="dashboard-card-action">Manage &rarr;</a>
                    </div>
                    <div class="dashboard-card-body">
                        <?php
                        $result = mysqli_query($conn, "SELECT g.GroupName, m.Role, m.JoinDate
                                  FROM Membership m JOIN `Group` g ON m.GroupID=g.GroupID
                                  WHERE m.UserID=$current_user_id ORDER BY m.JoinDate DESC LIMIT 5");
                        if (mysqli_num_rows($result) > 0) { ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead><tr><th>Group</th><th>Role</th><th>Joined</th></tr></thead>
                                <tbody>
                                <?php foreach ($result as $row) { ?>
                                <tr>
                                    <td><b><?php echo $row['GroupName']; ?></b></td>
                                    <td><span class="badge badge-<?php echo ($row['Role']=='GroupAdmin') ? 'danger' : (($row['Role']=='Moderator') ? 'warning' : 'primary'); ?>"><?php echo $row['Role']; ?></span></td>
                                    <td><small><?php echo date('M d, Y', strtotime($row['JoinDate'])); ?></small></td>
                                </tr>
                                <?php } ?>
                                </tbody>
                            </table>
                        </div>
                        <?php } else { ?>
                        <div class="empty-state">
                            <div class="empty-state-icon">&#128101;</div>
                            <div class="empty-state-text">Not in any groups yet</div>
                            <a href="<?php echo userUrl('groups.php'); ?>" class="btn btn-primary btn-sm" style="margin-top: 12px;">Create or Join a Group</a>
                        </div>
                        <?php } ?>
                    </div>
                </div>

                <!-- Pending Invites -->
                <div class="dashboard-card" style="margin-bottom: 25px;">
                    <div class="dashboard-card-header">
                        <span class="dashboard-card-title">&#9993; Pending Invites</span>
                        <?php if ($myInvites > 0) { ?>
                            <span class="badge badge-danger"><?php echo $myInvites; ?> new</span>
                        <?php } ?>
                        <a href="<?php echo userUrl('invites.php'); ?>" class="dashboard-card-action">View All &rarr;</a>
                    </div>
                    <div class="dashboard-card-body">
                        <?php
                        $result = mysqli_query($conn, "SELECT s.Username AS Sender, i.InviteType, i.InvitationMessage,
                                     IFNULL(e.Title, g.GroupName) AS Target
                              FROM Invite i JOIN `User` s ON i.SenderID=s.UserID
                              LEFT JOIN Event e ON i.EventID=e.EventID
                              LEFT JOIN `Group` g ON i.GroupID=g.GroupID
                              WHERE i.ReceiverID=$current_user_id AND i.Status='Pending'
                              ORDER BY i.SentDate DESC LIMIT 5");
                        if (mysqli_num_rows($result) > 0) { ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead><tr><th>From</th><th>For</th><th>Message</th></tr></thead>
                                <tbody>
                                <?php foreach ($result as $row) { ?>
                                <tr>
                                    <td><b><?php echo $row['Sender']; ?></b></td>
                                    <td><span class="badge badge-<?php echo ($row['InviteType']=='EventRequest') ? 'info' : 'warning'; ?>"><?php echo $row['Target']; ?></span></td>
                                    <td><small><?php echo $row['InvitationMessage']; ?></small></td>
                                </tr>
                                <?php } ?>
                                </tbody>
                            </table>
                        </div>
                        <?php } else { ?>
                        <div class="empty-state">
                            <div class="empty-state-icon">&#10003;</div>
                            <div class="empty-state-text">All caught up!</div>
                        </div>
                        <?php } ?>
                    </div>
                </div>

            </div>
        </div>
    </div>
<?php } // end if/else admin/user ?>
</div>

<?php include 'footer.php'; ?>
</body></html>
<?php $conn->close(); ?>
