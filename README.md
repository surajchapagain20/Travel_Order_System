Getting Access and Moving Around the Application

Overview

This part of the application defines the post-login landing experience and the shared navigation frame that users use to move between major HR and travel-order pages. The dashboard summarizes employee and travel-order activity, while the sidebar in navbar.php provides role-aware links to request creation, travel record review, employee administration, expense tools, backups, SMTP settings, and user management.

The section also includes the credential constants in password.php and the shared styling rules in . Together, these files shape how the app looks and how users move through it after they are already authenticated.

Dashboard Landing Screen

`dashboard.php`

The dashboard is the main landing page after login. It starts with require_once 'auth.php'; and requireLogin();, then loads db.php before reading session data and database metrics. The page uses $_SESSION['full_name'] for the welcome message and $_SESSION['role'] ?? 'Employee' for the visible role badge.

What the dashboard shows

The four stat cards act as navigation shortcuts:

add_employee.php from the Total Employees card

view_travel_orders.php from the Total Travel Orders card

view_travel_orders.php?filter=approved from Approved Orders

view_travel_orders.php?filter=pending from Pending Approval

The dashboard also loads UI assets directly in the page head:

Bootstrap 5.3.0 CSS

Bootstrap Icons 1.10.5

Chart.js

Bootstrap 5.3.0 bundle JS

Dashboard styling conventions

The page uses its own inline CSS for the landing experience:

body uses the Outfit font and a light gray background

.dashboard-header uses a dark gradient with rounded bottom corners and a shadow

.stat-card is a white, bordered card with a hover lift effect

.chart-container matches the same card styling for the charts

Shared Navigation Sidebar

`navbar.php`

navbar.php provides the fixed left sidebar used for in-app navigation. It loads auth.php, reads the active user role from $_SESSION['role'] ?? '', and determines the current page with basename($_SERVER['PHP_SELF']) so it can mark the current link with the active class.

The sidebar also changes the available links based on role:

Sidebar layout behavior

The sidebar is not just a menu; it changes the page layout:

body gets padding-left: 260px to make room for the fixed sidebar

.sidebar is fixed on the left, fills the full viewport height, and stays on screen

The user block shows an avatar icon, $_SESSION['full_name'], and the role label

The footer contains a dedicated Logout button

The active page is visually highlighted by the active class

Sidebar styling conventions

The sidebar styling is defined inline in navbar.php and uses:

a dark #0f172a background

blue accent colors for headings, icons, and active states

hover states that brighten the text and icon color

a fixed width of 260px

a scrollable navigation area via .sidebar-nav

Password Credential File

`password.php`

This file centralizes two credential constants:

The source includes a comment telling the reader to store credentials securely or use encryption, and the values shown in the file are placeholders. In the provided source, password-related handling is limited to these constants.

Shared Styling File

`includes/style_smtp.css`

This stylesheet provides a second, reusable visual system for pages that use it. It is separate from the sidebar styles in navbar.php and focuses on a polished card-and-button layout.

Styling rules in

Shared interface conventions

This stylesheet reinforces the same visual language used elsewhere in the app:

rounded containers

soft shadows

blue primary actions

readable sans-serif typography

responsive spacing for smaller screens

Navigation Flow



Key Files Reference

