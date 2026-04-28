<?php
$current_page = basename($_SERVER['PHP_SELF']);
$all_users_nav = mysqli_query($conn, "SELECT UserID, Username FROM `User` ORDER BY Username");
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top shadow">
    <div class="container-fluid">
        <a class="navbar-brand" href="<?php echo userUrl('index.php'); ?>"
           style="font-weight:700; font-size:1.4rem; letter-spacing:2px;">OurCal</a>

        <button class="navbar-toggler" type="button" data-toggle="collapse"
                data-target="#navbarNav"><span class="navbar-toggler-icon"></span></button>

        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav">
                <li class="nav-item">
                    <a class="nav-link <?php if($current_page=='index.php') echo 'active'; ?>"
                       href="<?php echo userUrl('index.php'); ?>">Dashboard</a></li>
                <li class="nav-item">
                    <a class="nav-link <?php if($current_page=='events.php') echo 'active'; ?>"
                       href="<?php echo userUrl('events.php'); ?>">My Events</a></li>
                <li class="nav-item">
                    <a class="nav-link <?php if($current_page=='groups.php') echo 'active'; ?>"
                       href="<?php echo userUrl('groups.php'); ?>">My Groups</a></li>
                <li class="nav-item">
                    <a class="nav-link <?php if($current_page=='invites.php') echo 'active'; ?>"
                       href="<?php echo userUrl('invites.php'); ?>">Invites</a></li>
            </ul>

            <!-- Right side: switcher + profile + settings -->
            <ul class="navbar-nav ml-auto align-items-center">
                <!-- User Switcher (demo: simulates switching logged-in user) -->
                <li class="nav-item" style="margin-right:8px;">
                    <form method="GET" action="<?php echo $current_page; ?>" id="switchForm" style="display:inline;">
                        <select name="switch_user" class="form-control form-control-sm"
                                style="width:auto; display:inline; background:#4a5568; color:white; border:1px solid #718096; font-size:0.8rem;"
                                onchange="document.getElementById('switchForm').submit();">
                            <?php
                            while ($su = mysqli_fetch_assoc($all_users_nav)) {
                                $sel = ($su['UserID'] == $current_user_id) ? 'selected' : '';
                                echo "<option value='" . $su['UserID'] . "' $sel>" . $su['Username'] . "</option>";
                            }
                            ?>
                        </select>
                    </form>
                </li>

                <!-- Profile icon -->
                <li class="nav-item">
                    <a class="nav-link <?php if($current_page=='profile.php') echo 'active'; ?>"
                       href="<?php echo userUrl('profile.php'); ?>" title="My Profile"
                       style="font-size:1.3rem; padding:5px 8px;">&#128100;</a></li>

                <!-- Settings icon -->
                <li class="nav-item">
                    <a class="nav-link <?php if($current_page=='settings.php') echo 'active'; ?>"
                       href="<?php echo userUrl('settings.php'); ?>" title="Account Settings"
                       style="font-size:1.3rem; padding:5px 8px;">&#9881;</a></li>

                <!-- Display name -->
                <li class="nav-item">
                    <span class="nav-link" style="color:#a0aec0; font-size:0.82rem; cursor:default;">
                        <?php echo $current_user['DisplayName']; ?>
                        <span class="badge badge-<?php echo ($current_user['UserType']=='Admin') ? 'danger' : 'success'; ?>" style="font-size:0.65rem;">
                            <?php echo $current_user['UserType']; ?>
                        </span>
                    </span></li>
            </ul>
        </div>
    </div>
</nav>

<?php if ($current_user['UserType'] == 'Admin') { ?>
<div class="alert alert-warning" style="margin:0; border-radius:0; border-left:none; border-right:none; padding:12px 25px; font-size:0.9rem;">
    <b>&#128274; You are logged in as a system Admin.</b>
    Administrative actions (user management, content moderation) are handled at the database level for this version.
    Calendar features (events, groups, invites) are reserved for standard users. Switch to a standard user from the dropdown above to use them.
</div>
<?php } ?>
