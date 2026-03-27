CREATE DATABASE OurCal;
USE OurCal;

DROP TABLE IF EXISTS Users;
DROP TABLE IF EXISTS UserPhone;
DROP TABLE IF EXISTS UserAddress;
DROP TABLE IF EXISTS StandardUsers;
DROP TABLE IF EXISTS UsersGroup;
DROP TABLE IF EXISTS Category;
DROP TABLE IF EXISTS Location;
DROP TABLE IF EXISTS UsersEvent;
DROP TABLE IF EXISTS EventTags;
DROP TABLE IF EXISTS PrivateEvent;
DROP TABLE IF EXISTS SharedEvent;
DROP TABLE IF EXISTS Invite;
DROP TABLE IF EXISTS UserProfiles;
DROP TABLE IF EXISTS AccountSetting;
DROP TABLE IF EXISTS Reminder;
DROP TABLE IF EXISTS Availability;
DROP TABLE IF EXISTS Membership;

CREATE TABLE Users (
    User_ID INT PRIMARY KEY AUTO_INCREMENT,
    User_username VARCHAR(50) UNIQUE NOT NULL,
    User_password VARCHAR(255) NOT NULL, 
    User_email VARCHAR(100) UNIQUE NOT NULL,
    User_datejoined DATE NOT NULL,
    User_type ENUM('StandardUser', 'Admin') NOT NULL 
);

CREATE TABLE UserPhone (
    User_ID INT,
    User_phonenumber VARCHAR(20),
    PRIMARY KEY (User_ID, User_phonenumber),
    FOREIGN KEY (User_ID) REFERENCES Users(User_ID) ON DELETE CASCADE
);

CREATE TABLE UserAddress (
    User_ID INT,
    User_address VARCHAR(255),
    PRIMARY KEY (User_ID, User_address),
    FOREIGN KEY (User_ID) REFERENCES Users(User_ID) ON DELETE CASCADE
);

CREATE TABLE StandardUser (
    User_ID INT PRIMARY KEY,
    User_storagelimit INT DEFAULT 1073741824, 
    User_maxgroups INT DEFAULT 10,
    User_accountstatus ENUM('Active', 'Pending', 'Inactive') DEFAULT 'Active',
    FOREIGN KEY (User_ID) REFERENCES Users(User_ID) ON DELETE CASCADE
);

CREATE TABLE AdminUsers (
    User_ID INT PRIMARY KEY,
    User_adminlevel ENUM('SuperAdmin', 'Moderator', 'Support') NOT NULL,
    User_permissions TEXT, 
    User_joineddate DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (User_ID) REFERENCES Users(User_ID) ON DELETE CASCADE
);

CREATE TABLE UsersGroup (
    Group_ID INT PRIMARY KEY AUTO_INCREMENT,
    Group_name VARCHAR(100) NOT NULL,
    Group_description TEXT,
    Group_creationdate DATE NOT NULL,
    Group_maxmembers INT DEFAULT 50,
    Group_icon VARCHAR(255) 
);

CREATE TABLE Category (
    Category_ID INT PRIMARY KEY AUTO_INCREMENT,
    Category_name VARCHAR(50) NOT NULL UNIQUE,
    Category_colorcode VARCHAR(7) NOT NULL,
    Category_description TEXT
);

CREATE TABLE Location (
    Location_ID INT PRIMARY KEY AUTO_INCREMENT,
    Location_address VARCHAR(255) NOT NULL,
    Location_city VARCHAR(100),
    Location_zipcode VARCHAR(20)
);

CREATE TABLE UsersEvent (
    Event_ID INT PRIMARY KEY AUTO_INCREMENT,
    Event_title VARCHAR(100) NOT NULL,
    Event_description TEXT,
    Event_starttime DATETIME NOT NULL,
    Event_endtime DATETIME NOT NULL,
    Event_calendartype VARCHAR(50),
    Event_auditlogs TEXT, 
    Event_datecreated DATETIME DEFAULT CURRENT_TIMESTAMP,
    Category_ID INT,
    Location_ID INT,
    Creator_userID INT NOT NULL,
    Event_type ENUM('PrivateEvent', 'SharedEvent') NOT NULL, 
    FOREIGN KEY (Category_ID) REFERENCES Category(Category_ID) ON DELETE SET NULL,
    FOREIGN KEY (Location_ID) REFERENCES Location(Location_ID) ON DELETE SET NULL,
    FOREIGN KEY (Creator_userID) REFERENCES Users(User_ID) ON DELETE CASCADE
);

CREATE TABLE EventTags (
    Event_ID INT,
    Event_tag VARCHAR(50),
    PRIMARY KEY (EventID, Event_tag),
    FOREIGN KEY (Event_ID) REFERENCES UsersEvent(Event_ID) ON DELETE CASCADE
);

CREATE TABLE PrivateEvent (
    Event_ID INT PRIMARY KEY,
    Privacy_notes TEXT,
    Personal_reminder DATETIME,
    Category_filter VARCHAR(50),
    FOREIGN KEY (Event_ID) REFERENCES Event(Event_ID) ON DELETE CASCADE
);

CREATE TABLE SharedEvent (
    Event_ID INT PRIMARY KEY,
    Access_level ENUM('view', 'edit') DEFAULT 'view',
    Group_ID INT NOT NULL,
    FOREIGN KEY (EventID) REFERENCES Event(EventID) ON DELETE CASCADE,
    FOREIGN KEY (Group_ID) REFERENCES UsersGroup(Group_ID) ON DELETE CASCADE
);

CREATE TABLE Invite (
    Invite_ID INT PRIMARY KEY AUTO_INCREMENT,
    Invite_status ENUM('Pending', 'Accepted', 'Declined', 'Expired') DEFAULT 'Pending',
    Invite_sentdate DATETIME DEFAULT CURRENT_TIMESTAMP,
    Invite_expirydate DATETIME NOT NULL,
    Invite_message TEXT,
    Invite_type ENUM('EventRequest', 'GroupJoinRequest') NOT NULL,
    Sender_userID INT NOT NULL,
    Receiver_userID INT NOT NULL,
    Event_ID INT NULL,
    Group_ID INT NULL,
    FOREIGN KEY (Sender_userID) REFERENCES User(User_ID) ON DELETE CASCADE,
    FOREIGN KEY (Receiver_userID) REFERENCES User(User_ID) ON DELETE CASCADE,
    FOREIGN KEY (Event_ID) REFERENCES Event(Event_ID) ON DELETE CASCADE,
    FOREIGN KEY (Group_ID) REFERENCES UsersGroup(Group_ID) ON DELETE CASCADE,
    CHECK (
        (InviteType = 'EventRequest' AND Event_ID IS NOT NULL AND Group_ID IS NULL) OR
        (InviteType = 'GroupJoinRequest' AND Group_ID IS NOT NULL AND Event_ID IS NULL)
    )
);

CREATE TABLE UserProfiles (
    User_ID INT,
    Display_name VARCHAR(50) NOT NULL,
    Bio TEXT,
    Profile_pictureURL VARCHAR(255),
    Status_message VARCHAR(100),
    PRIMARY KEY (User_ID, Display_name),
    FOREIGN KEY (Use_rID) REFERENCES Users(User_ID) ON DELETE CASCADE
);

CREATE TABLE AccountSetting (
    User_ID INT,
    User_setting VARCHAR(50),
    Account_timezone VARCHAR(50) DEFAULT 'UTC',
    Account_language VARCHAR(10) DEFAULT 'en',
    Account_themecolor VARCHAR(7) DEFAULT '#FFFFFF',
    Account_reminderstatus BOOLEAN DEFAULT TRUE,
    PRIMARY KEY (User_ID, User_setting),
    FOREIGN KEY (User_ID) REFERENCES Users(User_ID) ON DELETE CASCADE
);

CREATE TABLE Reminder (
    Event_ID INT,
    Reminder_time DATETIME NOT NULL,
    Reminder_type ENUM('Email', 'SMS', 'Push', 'InApp') DEFAULT 'InApp',
    Reminder_message TEXT,
    Snoozable BOOLEAN DEFAULT FALSE,
    Reminder_status ENUM('Scheduled', 'Sent', 'Dismissed') DEFAULT 'Scheduled',
    Reminder_enabledisable BOOLEAN DEFAULT TRUE,
    PRIMARY KEY (Event_ID, Reminder_time),
    FOREIGN KEY (Event_ID) REFERENCES Event(Event_ID) ON DELETE CASCADE
);

CREATE TABLE Availability (
    User_ID INT,
    Slot_ID INT AUTO_INCREMENT,
    Start_time DATETIME NOT NULL,
    End_time DATETIME NOT NULL,
    Busy_level ENUM('Free', 'Busy', 'Tentative') DEFAULT 'Free',
    Recurrence VARCHAR(50), -- e.g., 'Weekly', 'Monthly', NULL for no recurrence
    PRIMARY KEY (User_ID, Slot_ID),
    FOREIGN KEY (User_ID) REFERENCES Users(User_ID) ON DELETE CASCADE,
    CHECK (Start_time < End_time)
);

CREATE TABLE Membership (
    User_ID INT,
    Group_ID INT,
    Join_date DATE NOT NULL,
    Roles ENUM('Member', 'Moderator', 'GroupAdmin') DEFAULT 'Member',
    PRIMARY KEY (User_ID, Group_ID),
    FOREIGN KEY (User_ID) REFERENCES Users(User_ID) ON DELETE CASCADE,
    FOREIGN KEY (Group_ID) REFERENCES UsersGroup(Group_ID) ON DELETE CASCADE
);

INSERT INTO Users (User_username, User_password, User_email, User_datejoined, User_type) VALUES
('john_doe', 'UT&^DUD&$&#', 'john.doe@email.com', '2026-01-15', 'StandardUser'),
('jane_smith', 'HD&3h^ed', 'jane.smith@email.com', '2026-01-20', 'StandardUser'),
('mike_brown', 'gh%38F6', 'mike.brown@email.com', '2026-01-25', 'StandardUser'),
('sarah_anderson', 'H3487%#F', 'sarah.anderson@email.com', '2026-02-01', 'StandardUser'),
('alex_greene', 'JD^$4F7', 'alex.greene@email.com', '2026-02-05', 'StandardUser');


INSERT INTO UserPhone (User_ID, User_phonenumber) VALUES
(1, '+1-111-222-3333'),
(2, '+1-444-555-6666'),
(3, '+1-777-888-9999'),
(4, '+1-101-111-1212'),
(5, '+1-131-141-1515');

INSERT INTO UserAddress (User_ID, User_address) VALUES
(1, '123 Main Street, Apt 2B, New York, NY 11324'),
(2, '789 Oak Road, Los Angeles, CA 90001'),
(3, '321 Pine Street, Chicago, IL 60601'),
(4, '654 Elm Boulevard, Houston, TX 77001'),
(5, '987 Maple Drive, Phoenix, AZ 85001');

INSERT INTO StandardUser (User_ID, User_storagelimit, User_maxgroups, User_accountstatus) VALUES
(1, 2147483648, 15, 'Active'), 
(2, 1073741824, 10, 'Active'),  
(3, 1073741824, 10, 'Active'),
(4, 536870912, 5, 'Pending'),  
(5, 1073741824, 10, 'Active');

INSERT INTO AdminUsers (User_ID, User_adminlevel, User_permissions, User_joineddate) VALUES
(6, 'SuperAdmin', '{"users": "full", "groups": "full", "events": "full", "system": "full"}', '2026-01-01 09:00:00'),
(7, 'Moderator', '{"users": "moderate", "groups": "full", "events": "moderate"}', '2026-01-05 10:30:00'),
(8, 'Support', '{"users": "view", "tickets": "full", "events": "view"}', '2026-01-08 11:15:00');

INSERT INTO UsersGroup (Group_name, Group_description, Group_creationdate, Group_maxmembers, Group_icon) VALUES
('Development Team', 'Software development team for our company', '2026-02-01', 15, '/icons/dev_team.png'),
('Family Calendar', 'Family events', '2026-02-05', 10, '/icons/family.png'),
('Marketing Department', 'Marketing campaigns', '2026-02-10', 20, '/icons/marketing.png'),
('Fitness Squad', 'Workout sessions and challenges', '2026-02-15', 25, '/icons/fitness.png'),
('Book Club', 'Monthly book discussions', '2026-02-20', 12, '/icons/bookclub.png');

INSERT INTO Category (Category_name, Category_colorcode, Category_description) VALUES
('Meeting', '#FF5733', 'Business meetings'),
('Personal', '#33FF57', 'Personal appointments'),
('Family', '#3357FF', 'Family events and gatherings'),
('Education', '#F5FF33', 'Classes'),
('Social', '#FF8C33', 'Social events');

INSERT INTO Location (Location_address, Location_city, Location_zipcode) VALUES
('123 Business Center, 5th Floor', 'New York', '10001'),
('456 Corporate Plaza', 'Los Angeles', '90001'),
('Virtual Meeting Room - Zoom', NULL, NULL),
('789 Community Center', 'Chicago', '60601'),
('321 Fitness Gym', 'Houston', '77001');

INSERT INTO UsersEvent (Event_title, Event_description, Event_starttime, Event_endtime, Event_calendartype, Event_auditlogs, Category_ID, Location_ID, Creator_userID, Event_type) VALUES
('Sprint Planning Meeting', 'Plan the next sprint tasks', '2026-03-01 10:00:00', '2026-03-01 11:30:00', 'Work', '{"created": "2026-02-25 09:00:00", "creator": 1}', 1, 1, 1, 'SharedEvent'),
('Family Dinner', 'Weekly family dinner at home', '2026-03-02 18:00:00', '2026-03-02 20:00:00', 'Personal', '{"created": "2026-02-26 14:30:00", "creator": 2}', 3, 10, 2, 'PrivateEvent'),
('Book Club Meeting', 'Discussion on "War and Peace"', '2026-03-05 19:00:00', '2026-03-05 21:00:00', 'Social', '{"created": "2026-02-29 10:15:00", "creator": 4}', 8, 5, 4, 'SharedEvent'),
('Dentist Appointment', 'Regular dental checkup', '2026-03-07 14:30:00', '2026-03-07 15:30:00', 'Personal', '{"created": "2026-03-02 16:45:00", "creator": 2}', 9, 4, 2, 'PrivateEvent'),
('Tax Deadline', 'Annual tax filing deadline', '2026-04-15 23:59:00', '2026-04-16 00:00:00', 'Finance', '{"created": "2026-03-01 12:00:00", "creator": 7}', 5, NULL, 7, 'PrivateEvent');

INSERT INTO EventTags (Event_ID, Event_tag) VALUES
(1, 'development'),
(2, 'family'),
(3, 'marketing'),
(4, 'health'),
(5, 'taxes');

INSERT INTO PrivateEvent (Event_ID, Privacy_notes, Personal_reminder, Category_filter) VALUES
(2, 'Family dinner with parents visiting from out of town', '2026-03-02 17:30:00', 'Family'),
(4, 'Personal fitness goal: complete 5km under 30 minutes', '2026-03-04 06:15:00', 'Health'),
(4, 'Dental appointment - need to bring insurance card', '2026-03-07 14:00:00', 'Health'),
(1, 'Evening relaxation and mindfulness session', '2026-03-08 16:45:00', 'Development'),
(5, 'Important tax deadline - file by midnight', '2026-04-10 09:00:00', 'Finance');

INSERT INTO SharedEvent (Event_ID, Access_level, Group_ID) VALUES
(1, 'edit', 1),
(3, 'view', 3),
(5, 'edit', 5),
(6, 'edit', 6),
(8, 'view', 1);

INSERT INTO Invite (Invite_status, Invite_sentdate, Invite_expirydate, Invite_message, Invite_type, Sender_userID, Receiver_userID, Event_ID, Group_ID) VALUES
('Pending', '2026-02-28 10:00:00', '2026-03-07 23:59:59', 'Please join the sprint planning meeting', 'EventRequest', 1, 2, 1, NULL),
('Accepted', '2026-02-28 11:30:00', '2026-03-07 23:59:59', 'Need your input on the marketing strategy', 'EventRequest', 3, 1, 3, NULL),
('Pending', '2026-03-01 09:00:00', '2026-03-08 23:59:59', 'Join our book club! We discuss great books monthly', 'GroupJoinRequest', 4, 2, NULL, 5),
('Accepted', '2026-03-01 14:00:00', '2026-03-08 23:59:59', 'You are invited to join the Development Team', 'GroupJoinRequest', 1, 3, NULL, 1),
('Declined', '2026-03-02 10:00:00', '2026-03-09 23:59:59', 'Please join the fitness squad for morning runs', 'GroupJoinRequest', 1, 4, NULL, 4);

INSERT INTO UserProfiles (User_ID, Display_name, Bio, Profile_pictureURL, Status_message) VALUES
(1, 'John Doe', 'Senior Software Engineer. Love running and reading.', '/profiles/john_doe.jpg', 'Available for collaboration'),
(2, 'Jane Smith', 'Product Manager. Mom of two.', '/profiles/jane_smith.jpg', 'In a meeting until 3 PM'),
(3, 'Mike Brown', 'Marketing Director. Coffee enthusiast.', '/profiles/mike_brown.jpg', 'Working on Q2 strategy'),
(4, 'Sarah Anderson', 'Software Developer.', '/profiles/sarah_anderson.jpg', 'Coding and reading'),
(5, 'Alex Greene', 'Project Manager.', '/profiles/alex_greene.jpg', 'Available for quick chats');


INSERT INTO AccountSetting (User_ID, User_setting, Account_timezone, Account_language, Account_themecolor, Account_reminderstatus) VALUES
(1, 'default_settings', 'America/New_York', 'en', '#2C3E50', TRUE),
(2, 'default_settings', 'America/Los_Angeles', 'en', '#2C3E50', TRUE),
(3, 'default_settings', 'America/Chicago', 'en', '#27AE60', FALSE),
(4, 'default_settings', 'America/Chicago', 'en', '#E67E22', TRUE),
(5, 'default_settings', 'America/Phoenix', 'en', '#9B59B6', TRUE);

INSERT INTO Reminder (Event_ID, Reminder_time, Reminder_type, Reminder_message, Snoozable, Reminder_status, Reminder_enabledisable) VALUES
(1, '2026-03-01 09:30:00', 'Email', 'Sprint planning meeting starts in 30 minutes', TRUE, 'Scheduled', TRUE),
(2, '2026-03-02 17:30:00', 'Push', 'Family dinner in 30 minutes', FALSE, 'Scheduled', TRUE),
(3, '2026-03-03 13:45:00', 'Email', 'Marketing review in 15 minutes', TRUE, 'Scheduled', TRUE),
(4, '2026-03-04 06:15:00', 'Push', 'Time for your morning run!', FALSE, 'Scheduled', TRUE),
(5, '2026-03-05 18:45:00', 'Push', 'Book club meeting in 15 minutes', TRUE, 'Scheduled', TRUE),

INSERT INTO Availability (User_ID, Start_time, End_time, Busy_level, Recurrence) VALUES
(1, '2026-03-01 09:00:00', '2026-03-01 12:00:00', 'Busy', NULL),
(2, '2026-03-01 10:00:00', '2026-03-01 15:00:00', 'Busy', NULL),
(3, '2026-03-01 09:00:00', '2026-03-01 11:00:00', 'Busy', 'Weekly'),
(4, '2026-03-04 08:00:00', '2026-03-04 12:00:00', 'Free', NULL),
(5, '2026-03-06 09:00:00', '2026-03-06 12:00:00', 'Busy', NULL);

INSERT INTO Membership (User_ID, Group_ID, Join_date, Roles) VALUES
(1, 1, '2026-02-01', 'GroupAdmin'),
(2, 1, '2026-02-01', 'Member'),
(3, 1, '2026-02-02', 'Moderator'),
(4, 2, '2026-02-06', 'Member'),
(5, 3, '2026-02-11', 'Member');


SELECT 
    u.User_username,
    u.User_email,
    s.User_storagelimit,
    s.User_accountstatus
FROM Users u
JOIN StandardUser s ON u.User_ID = s.User_ID
WHERE s.User_accountstatus = 'Active' 
  AND s.User_storagelimit > 1073741824;


SELECT 
    e.Event_title,
    e.Event_starttime,
    u.User_username AS Creator_Name,
    c.Category_name
FROM UsersEvent e
JOIN Users u ON e.Creator_userID = u.User_ID
JOIN Category c ON e.Category_ID = c.Category_ID;

SELECT 
    e.Event_title,
    e.Event_starttime,
    c.Category_name
FROM UsersEvent e
LEFT JOIN Category c ON e.Category_ID = c.Category_ID
WHERE c.Category_name IS NULL;


SELECT 
    g.Group_name,
    COUNT(m.User_ID) AS Member_Count,
    g.Group_maxmembers
FROM UsersGroup g
LEFT JOIN Membership m ON g.Group_ID = m.Group_ID
GROUP BY g.Group_ID, g.Group_name, g.Group_maxmembers
ORDER BY Member_Count DESC;


SELECT 
    g.Group_name,
    COUNT(m.User_ID) AS Member_Count,
    g.Group_creationdate
FROM UsersGroup g
JOIN Membership m ON g.Group_ID = m.Group_ID
GROUP BY g.Group_ID, g.Group_name, g.Group_creationdate
HAVING COUNT(m.User_ID) > 1
ORDER BY Member_Count DESC;

SELECT 
    e.Event_title,
    e.Event_starttime,
    s.Access_level,
    g.Group_name,
    u.User_username AS Creator
FROM UsersEvent e
JOIN SharedEvent s ON e.Event_ID = s.Event_ID
JOIN UsersGroup g ON s.Group_ID = g.Group_ID
JOIN Users u ON e.Creator_userID = u.User_ID
WHERE e.Event_type = 'SharedEvent'
  AND s.Access_level = 'edit'
ORDER BY e.Event_starttime;

SELECT 
    u.User_username,
    COUNT(DISTINCT e.Event_ID) AS Events_Created,
    COUNT(DISTINCT i.Invite_ID) AS Invites_Sent,
    COUNT(DISTINCT a.Slot_ID) AS Availability_Slots
FROM Users u
LEFT JOIN UsersEvent e ON u.User_ID = e.Creator_userID
LEFT JOIN Invite i ON u.User_ID = i.Sender_userID
LEFT JOIN Availability a ON u.User_ID = a.User_ID
WHERE u.User_type = 'StandardUser'
GROUP BY u.User_ID, u.User_username
ORDER BY Events_Created DESC, Invites_Sent DESC;

SELECT 
    e.Event_title,
    e.Event_starttime,
    r.Reminder_time,
    r.Reminder_type,
    r.Reminder_status
FROM UsersEvent e
JOIN Reminder r ON e.Event_ID = r.Event_ID
WHERE e.Event_starttime > NOW()
  AND r.Reminder_enabledisable = TRUE
  AND r.Reminder_status = 'Scheduled'
ORDER BY e.Event_starttime;

SELECT 
    c.Category_name,
    COUNT(e.Event_ID) AS Event_Count,
    c.Category_colorcode
FROM Category c
LEFT JOIN UsersEvent e ON c.Category_ID = e.Category_ID
GROUP BY c.Category_ID, c.Category_name, c.Category_colorcode
HAVING COUNT(e.Event_ID) > 0
ORDER BY Event_Count DESC, Category_name ASC;

SELECT 
    u.User_username,
    u.User_email,
    g.Group_name,
    m.Roles,
    m.Join_date,
    g.Group_creationdate
FROM Membership m
JOIN Users u ON m.User_ID = u.User_ID
JOIN UsersGroup g ON m.Group_ID = g.Group_ID
WHERE u.User_type = 'StandardUser'
  AND m.Join_date >= '2026-02-01'
  AND g.Group_maxmembers >= 10
ORDER BY g.Group_name ASC, m.Roles DESC, u.User_username ASC;


