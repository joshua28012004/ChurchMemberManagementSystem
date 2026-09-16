<?php

$current_page = $_SERVER['PHP_SELF'] ?? '';

$current_page = is_string($current_page)
    ? $current_page
    : '';

?>

<div class="sidebar">

    <div class="sidebar-brand">

        <div class="brand-icon">
            <i class="bi bi-building"></i>
        </div>

        <div>
            <h3>Church CMS</h3>
            <small>Management System</small>
        </div>

    </div>


    <nav class="sidebar-nav">


        <a href="/ChurchManagement/dashboard.php"
           class="sidebar-link <?php
           echo ($current_page === '/ChurchManagement/dashboard.php')
           ? 'active' : '';
           ?>">

            <i class="bi bi-speedometer2"></i>

            <span>Dashboard</span>

        </a>


        <a href="/ChurchManagement/members/view_members.php"
           class="sidebar-link <?php
           echo (strpos($current_page, '/members/') !== false)
           ? 'active' : '';
           ?>">

            <i class="bi bi-people-fill"></i>

            <span>Members</span>

        </a>


        <a href="/ChurchManagement/donations/view_donations.php"
           class="sidebar-link <?php
           echo (strpos($current_page, '/donations/') !== false)
           ? 'active' : '';
           ?>">

            <i class="bi bi-cash-stack"></i>

            <span>Donations</span>

        </a>


        <a href="/ChurchManagement/birthdays/view_birthdays.php"
           class="sidebar-link <?php
           echo (strpos($current_page, '/birthdays/') !== false)
           ? 'active' : '';
           ?>">

            <i class="bi bi-gift-fill"></i>

            <span>Birthdays</span>

        </a>


        <a href="/ChurchManagement/events/view_events.php"
           class="sidebar-link <?php
           echo (strpos($current_page, '/events/') !== false)
           ? 'active' : '';
           ?>">

            <i class="bi bi-calendar-event-fill"></i>

            <span>Events</span>

        </a>


        <a href="/ChurchManagement/reports/reports.php"
           class="sidebar-link <?php
           echo (strpos($current_page, '/reports/') !== false)
           ? 'active' : '';
           ?>">

            <i class="bi bi-bar-chart-fill"></i>

            <span>Reports</span>

        </a>


        <div class="sidebar-divider"></div>


        <a href="/ChurchManagement/logout.php"
           class="sidebar-link logout-link">

            <i class="bi bi-box-arrow-right"></i>

            <span>Logout</span>

        </a>


    </nav>

</div>