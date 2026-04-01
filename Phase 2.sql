CREATE DATABASE OurCal;
USE OurCal;

DROP TABLE IF EXISTS Membership;
DROP TABLE IF EXISTS Availability;
DROP TABLE IF EXISTS Reminder;
DROP TABLE IF EXISTS AccountSetting;
DROP TABLE IF EXISTS Profile;
DROP TABLE IF EXISTS Invite;
DROP TABLE IF EXISTS SharedEvent;
DROP TABLE IF EXISTS PrivateEvent;
DROP TABLE IF EXISTS EventTags;
DROP TABLE IF EXISTS Event;
DROP TABLE IF EXISTS Location;
DROP TABLE IF EXISTS Category;
DROP TABLE IF EXISTS `Group`;
DROP TABLE IF EXISTS Admin;
DROP TABLE IF EXISTS StandardUser;
DROP TABLE IF EXISTS UserAddress;
DROP TABLE IF EXISTS UserPhone;
DROP TABLE IF EXISTS `User`;

CREATE TABLE `User` (
    UserID INT PRIMARY KEY AUTO_INCREMENT,
    Username VARCHAR(50) UNIQUE NOT NULL,
    Password VARCHAR(255) NOT NULL, 
    Email VARCHAR(100) UNIQUE NOT NULL,
    DateJoined DATE NOT NULL,
    UserType ENUM('StandardUser', 'Admin') NOT NULL
);

CREATE TABLE UserPhone (
    UserID INT,
    PhoneNo VARCHAR(20),
    PRIMARY KEY (UserID, PhoneNo),
    FOREIGN KEY (UserID) REFERENCES `User`(UserID) ON DELETE CASCADE
);

CREATE TABLE UserAddress (
    UserID INT,
    Address VARCHAR(255),
    PRIMARY KEY (UserID, Address),
    FOREIGN KEY (UserID) REFERENCES `User`(UserID) ON DELETE CASCADE
);

CREATE TABLE StandardUser (
    UserID INT PRIMARY KEY,
    StorageLimit INT DEFAULT 1073741824, 
    MaxGroups INT DEFAULT 10,
    AccountStatus ENUM('Active', 'Pending', 'Inactive') DEFAULT 'Active',
    FOREIGN KEY (UserID) REFERENCES `User`(UserID) ON DELETE CASCADE
);

CREATE TABLE Admin (
    UserID INT PRIMARY KEY,
    AdminLevel ENUM('SuperAdmin', 'Moderator', 'Support') NOT NULL,
    Permission TEXT, 
    AccessJoinedDate DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (UserID) REFERENCES `User`(UserID) ON DELETE CASCADE
);

CREATE TABLE `Group` (
    GroupID INT PRIMARY KEY AUTO_INCREMENT,
    GroupName VARCHAR(100) NOT NULL,
    GroupDescription TEXT,
    CreationDate DATE NOT NULL,
    MaxMembers INT DEFAULT 50,
    GroupIcon VARCHAR(255) 
);

CREATE TABLE Category (
    CateogryID INT PRIMARY KEY AUTO_INCREMENT,
    CateogryName VARCHAR(50) NOT NULL UNIQUE,
    ColorCode VARCHAR(7),
    CateogryDescription TEXT,
    AdminID INT NOT NULL,
    FOREIGN KEY (AdminID) REFERENCES Admin(UserID)
);

CREATE TABLE Location (
    LocationID INT PRIMARY KEY AUTO_INCREMENT,
    StreetAddress VARCHAR(255) NOT NULL,
    ZipCode VARCHAR(20),
    City VARCHAR(100)
);

CREATE TABLE Event (
    EventID INT PRIMARY KEY AUTO_INCREMENT,
    Title VARCHAR(100) NOT NULL,
    Description TEXT,
    StartTime DATETIME NOT NULL,
    EndTime DATETIME NOT NULL,
    CalendarType VARCHAR(50),
    AuditLogs TEXT, 
    CreatorID INT NOT NULL,
    CateogryID INT,
    LocationID INT,
    FOREIGN KEY (CreatorID) REFERENCES `User`(UserID) ON DELETE CASCADE,
    FOREIGN KEY (CateogryID) REFERENCES Category(CateogryID) ON DELETE SET NULL,
    FOREIGN KEY (LocationID) REFERENCES Location(LocationID) ON DELETE SET NULL
);

CREATE TABLE EventTags (
    EventID INT,
    Tags VARCHAR(50),
    PRIMARY KEY (EventID, Tags),
    FOREIGN KEY (EventID) REFERENCES Event(EventID) ON DELETE CASCADE
);

CREATE TABLE PrivateEvent (
    EventID INT PRIMARY KEY,
    PrivacyNotes TEXT,
    PersonalReminder DATETIME,
    CategoryFilter VARCHAR(50),
    FOREIGN KEY (EventID) REFERENCES Event(EventID) ON DELETE CASCADE
);

CREATE TABLE SharedEvent (
    EventID INT PRIMARY KEY,
    AccessLevel ENUM('view', 'edit') DEFAULT 'view',
    GroupID INT NOT NULL,
    FOREIGN KEY (EventID) REFERENCES Event(EventID) ON DELETE CASCADE,
    FOREIGN KEY (GroupID) REFERENCES `Group`(GroupID) ON DELETE CASCADE
);

CREATE TABLE Invite (
    InviteID INT PRIMARY KEY AUTO_INCREMENT,
    Status ENUM('Pending', 'Accepted', 'Declined', 'Expired') DEFAULT 'Pending',
    SentDate DATETIME DEFAULT CURRENT_TIMESTAMP,
    ExpiryDate DATETIME NOT NULL,
    InvitationMessage TEXT,
    InviteType ENUM('EventRequest', 'GroupJoinRequest') NOT NULL,
    SenderID INT NOT NULL,
    ReceiverID INT NOT NULL,
    EventID INT NULL,
    GroupID INT NULL,
    FOREIGN KEY (SenderID) REFERENCES `User`(UserID) ON DELETE CASCADE,
    FOREIGN KEY (ReceiverID) REFERENCES `User`(UserID) ON DELETE CASCADE,
    FOREIGN KEY (EventID) REFERENCES Event(EventID) ON DELETE CASCADE,
    FOREIGN KEY (GroupID) REFERENCES `Group`(GroupID) ON DELETE CASCADE,
    CHECK (
        (InviteType = 'EventRequest' AND EventID IS NOT NULL AND GroupID IS NULL) OR
        (InviteType = 'GroupJoinRequest' AND GroupID IS NOT NULL AND EventID IS NULL)
    )
);

CREATE TABLE Profile (
    UserID INT,
    DisplayName VARCHAR(50) NOT NULL,
    Bio TEXT,
    ProfilePictureURL VARCHAR(255),
    StatusMessage VARCHAR(100),
    PRIMARY KEY (UserID, DisplayName),
    FOREIGN KEY (UserID) REFERENCES `User`(UserID) ON DELETE CASCADE
);

CREATE TABLE AccountSetting (
    UserID INT,
    SettingName VARCHAR(50),
    Timezone VARCHAR(50) DEFAULT 'UTC',
    Language VARCHAR(10) DEFAULT 'en',
    ThemeColor VARCHAR(7) DEFAULT '#FFFFFF',
    ReminderStatus BOOLEAN DEFAULT TRUE,
    PRIMARY KEY (UserID, SettingName),
    FOREIGN KEY (UserID) REFERENCES `User`(UserID) ON DELETE CASCADE
);

CREATE TABLE Reminder (
    EventID INT,
    ReminderTime DATETIME NOT NULL,
    ReminderType ENUM('Email', 'SMS', 'Push', 'InApp') DEFAULT 'InApp',
    CustomMessage TEXT,
    Snoozable BOOLEAN DEFAULT FALSE,
    Status ENUM('Scheduled', 'Sent', 'Dismissed') DEFAULT 'Scheduled',
    SnoozeInterval INT,
    EnableDisable BOOLEAN DEFAULT TRUE,
    PRIMARY KEY (EventID, ReminderTime),
    FOREIGN KEY (EventID) REFERENCES Event(EventID) ON DELETE CASCADE
);

CREATE TABLE Availability (
    UserID INT,
    SlotID INT,
    StartTime DATETIME NOT NULL,
    EndTime DATETIME NOT NULL,
    BusyLevel ENUM('Free', 'Busy', 'Tentative') DEFAULT 'Free',
    Recurrence VARCHAR(50),
    PRIMARY KEY (UserID, SlotID),
    FOREIGN KEY (UserID) REFERENCES `User`(UserID) ON DELETE CASCADE,
    CHECK (StartTime < EndTime)
);

CREATE TABLE Membership (
    UserID INT,
    GroupID INT,
    JoinDate DATE NOT NULL,
    Role ENUM('Member', 'Moderator', 'GroupAdmin') DEFAULT 'Member',
    PRIMARY KEY (UserID, GroupID),
    FOREIGN KEY (UserID) REFERENCES `User`(UserID) ON DELETE CASCADE,
    FOREIGN KEY (GroupID) REFERENCES `Group`(GroupID) ON DELETE CASCADE
);

INSERT INTO `User` (Username, Password, Email, DateJoined, UserType) VALUES
('john_doe', 'UT&^DUD&$&#', 'john.doe@email.com', '2026-01-15', 'StandardUser'),
('jane_smith', 'HD&3h^ed', 'jane.smith@email.com', '2026-01-20', 'StandardUser'),
('mike_brown', 'gh%38F6', 'mike.brown@email.com', '2026-01-25', 'StandardUser'),
('sarah_anderson', 'H3487%#F', 'sarah.anderson@email.com', '2026-02-01', 'StandardUser'),
('alex_greene', 'JD^$4F7', 'alex.greene@email.com', '2026-02-05', 'StandardUser'),
('admin_super', 'superpass123', 'super@email.com', '2026-01-01', 'Admin'),
('admin_mod', 'modpass123', 'mod@email.com', '2026-01-05', 'Admin'),
('admin_support', 'suppass123', 'support@email.com', '2026-01-08', 'Admin');

INSERT INTO UserPhone (UserID, PhoneNo) VALUES
(1, '+1-111-222-3333'),
(2, '+1-444-555-6666'),
(3, '+1-777-888-9999'),
(4, '+1-101-111-1212'),
(5, '+1-131-141-1515');

INSERT INTO UserAddress (UserID, Address) VALUES
(1, '123 Main Street, Apt 2B, New York, NY 11324'),
(2, '789 Oak Road, Los Angeles, CA 90001'),
(3, '321 Pine Street, Chicago, IL 60601'),
(4, '654 Elm Boulevard, Houston, TX 77001'),
(5, '987 Maple Drive, Phoenix, AZ 85001');

INSERT INTO StandardUser (UserID, StorageLimit, MaxGroups, AccountStatus) VALUES
(1, 2147483648, 15, 'Active'), 
(2, 1073741824, 10, 'Active'),  
(3, 1073741824, 10, 'Active'),
(4, 536870912, 5, 'Pending'),  
(5, 1073741824, 10, 'Active');

INSERT INTO Admin (UserID, AdminLevel, Permission, AccessJoinedDate) VALUES
(6, 'SuperAdmin', '{"users": "full", "groups": "full", "events": "full"}', '2026-01-01 09:00:00'),
(7, 'Moderator', '{"users": "moderate", "groups": "full", "events": "moderate"}', '2026-01-05 10:30:00'),
(8, 'Support', '{"users": "view", "tickets": "full", "events": "view"}', '2026-01-08 11:15:00');

INSERT INTO `Group` (GroupName, GroupDescription, CreationDate, MaxMembers, GroupIcon) VALUES
('Development Team', 'Software development team for our company', '2026-02-01', 15, '/icons/dev_team.png'),
('Family Calendar', 'Family events', '2026-02-05', 10, '/icons/family.png'),
('Marketing Department', 'Marketing campaigns', '2026-02-10', 20, '/icons/marketing.png'),
('Fitness Squad', 'Workout sessions and challenges', '2026-02-15', 25, '/icons/fitness.png'),
('Book Club', 'Monthly book discussions', '2026-02-20', 12, '/icons/bookclub.png');

INSERT INTO Category (CateogryName, ColorCode, CateogryDescription, AdminID) VALUES
('Meeting', '#FF5733', 'Business meetings', 6),
('Personal', '#33FF57', 'Personal appointments', 6),
('Family', '#3357FF', 'Family events and gatherings', 7),
('Education', '#F5FF33', 'Classes', 7),
('Social', '#FF8C33', 'Social events', 8);

INSERT INTO Location (StreetAddress, City, ZipCode) VALUES
('123 Business Center, 5th Floor', 'New York', '10001'),
('456 Corporate Plaza', 'Los Angeles', '90001'),
('Virtual Meeting Room - Zoom', NULL, NULL),
('789 Community Center', 'Chicago', '60601'),
('321 Fitness Gym', 'Houston', '77001');

INSERT INTO Event (Title, Description, StartTime, EndTime, CalendarType, AuditLogs, CateogryID, LocationID, CreatorID) VALUES
('Sprint Planning Meeting', 'Plan the next sprint tasks', '2026-03-01 10:00:00', '2026-03-01 11:30:00', 'Work', '{"created": "2026-02-25 09:00:00"}', 1, 1, 1),
('Family Dinner', 'Weekly family dinner at home', '2026-03-02 18:00:00', '2026-03-02 20:00:00', 'Personal', '{"created": "2026-02-26 14:30:00"}', 3, 4, 2),
('Book Club Meeting', 'Discussion on "War and Peace"', '2026-03-05 19:00:00', '2026-03-05 21:00:00', 'Social', '{"created": "2026-02-29 10:15:00"}', 5, 5, 4),
('Dentist Appointment', 'Regular dental checkup', '2026-03-07 14:30:00', '2026-03-07 15:30:00', 'Personal', '{"created": "2026-03-02 16:45:00"}', 2, 4, 2),
('Tax Deadline', 'Annual tax filing deadline', '2026-04-15 23:59:00', '2026-04-16 00:00:00', 'Finance', '{"created": "2026-03-01 12:00:00"}', 5, NULL, 7);

INSERT INTO EventTags (EventID, Tags) VALUES
(1, 'development'),
(2, 'family'),
(3, 'marketing'),
(4, 'health'),
(5, 'taxes');

INSERT INTO PrivateEvent (EventID, PrivacyNotes, PersonalReminder, CategoryFilter) VALUES
(2, 'Family dinner with parents visiting from out of town', '2026-03-02 17:30:00', 'Family'),
(4, 'Dental appointment - need to bring insurance card', '2026-03-07 14:00:00', 'Health'),
(5, 'Important tax deadline - file by midnight', '2026-04-10 09:00:00', 'Finance');

INSERT INTO SharedEvent (EventID, AccessLevel, GroupID) VALUES
(1, 'edit', 1),
(3, 'view', 5);

INSERT INTO Invite (Status, SentDate, ExpiryDate, InvitationMessage, InviteType, SenderID, ReceiverID, EventID, GroupID) VALUES
('Pending', '2026-02-28 10:00:00', '2026-03-07 23:59:59', 'Please join the sprint planning meeting', 'EventRequest', 1, 2, 1, NULL),
('Accepted', '2026-02-28 11:30:00', '2026-03-07 23:59:59', 'Need your input on the marketing strategy', 'EventRequest', 3, 1, 3, NULL),
('Pending', '2026-03-01 09:00:00', '2026-03-08 23:59:59', 'Join our book club! We discuss great books monthly', 'GroupJoinRequest', 4, 2, NULL, 5),
('Accepted', '2026-03-01 14:00:00', '2026-03-08 23:59:59', 'You are invited to join the Development Team', 'GroupJoinRequest', 1, 3, NULL, 1),
('Declined', '2026-03-02 10:00:00', '2026-03-09 23:59:59', 'Please join the fitness squad for morning runs', 'GroupJoinRequest', 1, 4, NULL, 4);

INSERT INTO Profile (UserID, DisplayName, Bio, ProfilePictureURL, StatusMessage) VALUES
(1, 'John Doe', 'Senior Software Engineer.', '/profiles/john_doe.jpg', 'Available for collaboration'),
(2, 'Jane Smith', 'Product Manager. Mom of two.', '/profiles/jane_smith.jpg', 'In a meeting until 3 PM'),
(3, 'Mike Brown', 'Marketing Director.', '/profiles/mike_brown.jpg', 'Working on Q2 strategy'),
(4, 'Sarah Anderson', 'Software Developer.', '/profiles/sarah_anderson.jpg', 'Coding and reading'),
(5, 'Alex Greene', 'Project Manager.', '/profiles/alex_greene.jpg', 'Available for quick chats');

INSERT INTO AccountSetting (UserID, SettingName, Timezone, Language, ThemeColor, ReminderStatus) VALUES
(1, 'default_settings', 'America/New_York', 'en', '#2C3E50', TRUE),
(2, 'default_settings', 'America/Los_Angeles', 'en', '#2C3E50', TRUE),
(3, 'default_settings', 'America/Chicago', 'en', '#27AE60', FALSE),
(4, 'default_settings', 'America/Chicago', 'en', '#E67E22', TRUE),
(5, 'default_settings', 'America/Phoenix', 'en', '#9B59B6', TRUE);

INSERT INTO Reminder (EventID, ReminderTime, ReminderType, CustomMessage, Snoozable, Status, EnableDisable) VALUES
(1, '2026-03-01 09:30:00', 'Email', 'Sprint planning meeting starts in 30 minutes', TRUE, 'Scheduled', TRUE),
(2, '2026-03-02 17:30:00', 'Push', 'Family dinner in 30 minutes', FALSE, 'Scheduled', TRUE),
(3, '2026-03-03 13:45:00', 'Email', 'Marketing review in 15 minutes', TRUE, 'Scheduled', TRUE),
(4, '2026-03-04 06:15:00', 'Push', 'Time for your morning run!', FALSE, 'Scheduled', TRUE),
(5, '2026-03-05 18:45:00', 'Push', 'Book club meeting in 15 minutes', TRUE, 'Scheduled', TRUE);

INSERT INTO Availability (UserID, SlotID, StartTime, EndTime, BusyLevel, Recurrence) VALUES
(1, 1, '2026-03-01 09:00:00', '2026-03-01 12:00:00', 'Busy', NULL),
(2, 1, '2026-03-01 10:00:00', '2026-03-01 15:00:00', 'Busy', NULL),
(3, 1, '2026-03-01 09:00:00', '2026-03-01 11:00:00', 'Busy', 'Weekly'),
(4, 1, '2026-03-04 08:00:00', '2026-03-04 12:00:00', 'Free', NULL),
(5, 1, '2026-03-06 09:00:00', '2026-03-06 12:00:00', 'Busy', NULL);

INSERT INTO Membership (UserID, GroupID, JoinDate, Role) VALUES
(1, 1, '2026-02-01', 'GroupAdmin'),
(2, 1, '2026-02-01', 'Member'),
(3, 1, '2026-02-02', 'Moderator'),
(4, 2, '2026-02-06', 'Member'),
(5, 3, '2026-02-11', 'Member');


SELECT 
    u.Username,
    u.Email,
    s.StorageLimit,
    s.AccountStatus
FROM `User` u
JOIN StandardUser s ON u.UserID = s.UserID
WHERE s.AccountStatus = 'Active' 
  AND s.StorageLimit > 1073741824;

SELECT 
    e.Title,
    e.StartTime,
    u.Username AS Creator_Name,
    c.CateogryName
FROM Event e
JOIN `User` u ON e.CreatorID = u.UserID
JOIN Category c ON e.CateogryID = c.CateogryID;


SELECT 
    e.Title,
    e.StartTime,
    c.CateogryName
FROM Event e
LEFT JOIN Category c ON e.CateogryID = c.CateogryID
WHERE c.CateogryName IS NULL;


SELECT 
    g.GroupName,
    COUNT(m.UserID) AS Member_Count,
    g.MaxMembers
FROM `Group` g
LEFT JOIN Membership m ON g.GroupID = m.GroupID
GROUP BY g.GroupID, g.GroupName, g.MaxMembers
ORDER BY Member_Count DESC;


SELECT 
    g.GroupName,
    COUNT(m.UserID) AS Member_Count,
    g.CreationDate
FROM `Group` g
JOIN Membership m ON g.GroupID = m.GroupID
GROUP BY g.GroupID, g.GroupName, g.CreationDate
HAVING COUNT(m.UserID) > 1
ORDER BY Member_Count DESC;


SELECT 
    e.Title,
    e.StartTime,
    s.AccessLevel,
    g.GroupName,
    u.Username AS Creator
FROM Event e
JOIN SharedEvent s ON e.EventID = s.EventID
JOIN `Group` g ON s.GroupID = g.GroupID
JOIN `User` u ON e.CreatorID = u.UserID
WHERE s.AccessLevel = 'edit'
ORDER BY e.StartTime;


SELECT 
    u.Username,
    COUNT(DISTINCT e.EventID) AS Events_Created,
    COUNT(DISTINCT i.InviteID) AS Invites_Sent,
    COUNT(DISTINCT a.SlotID) AS Availability_Slots
FROM `User` u
LEFT JOIN Event e ON u.UserID = e.CreatorID
LEFT JOIN Invite i ON u.UserID = i.SenderID
LEFT JOIN Availability a ON u.UserID = a.UserID
WHERE u.UserType = 'StandardUser'
GROUP BY u.UserID, u.Username
ORDER BY Events_Created DESC, Invites_Sent DESC;

SELECT 
    e.Title,
    e.StartTime,
    r.ReminderTime,
    r.ReminderType,
    r.Status
FROM Event e
JOIN Reminder r ON e.EventID = r.EventID
WHERE e.StartTime > NOW()
  AND r.EnableDisable = TRUE
  AND r.Status = 'Scheduled'
ORDER BY e.StartTime;


SELECT 
    c.CateogryName,
    COUNT(e.EventID) AS Event_Count,
    c.ColorCode
FROM Category c
LEFT JOIN Event e ON c.CateogryID = e.CateogryID
GROUP BY c.CateogryID, c.CateogryName, c.ColorCode
HAVING COUNT(e.EventID) > 0
ORDER BY Event_Count DESC, c.CateogryName ASC;


SELECT 
    u.Username,
    u.Email,
    g.GroupName,
    m.Role,
    m.JoinDate,
    g.CreationDate
FROM Membership m
JOIN `User` u ON m.UserID = u.UserID
JOIN `Group` g ON m.GroupID = g.GroupID
WHERE u.UserType = 'StandardUser'
  AND m.JoinDate >= '2026-02-01'
  AND g.MaxMembers >= 10
ORDER BY g.GroupName ASC, m.Role DESC, u.Username ASC;


