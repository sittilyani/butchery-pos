<?php
// Use centralized session management
include 'session_manager.php';
updateSessionActivity();

// Include configuration
include 'config.php';


// Check if user is logged in using centralized function
$isLoggedIn = isUserLoggedIn();
$full_name = '';
$username = '';
$userrole = '';

if ($isLoggedIn) {
    // Get data from session (already stored during login)
    $full_name = isset($_SESSION['full_name']) ? $_SESSION['full_name'] : '';
    $username = isset($_SESSION['username']) ? $_SESSION['username'] : '';
    $userrole = isset($_SESSION['userrole']) ? $_SESSION['userrole'] : '';

    // If session data is not complete, fetch from database
    if (empty($full_name) || empty($userrole)) {
        $userId = $_SESSION['user_id'];

        $userQuery = "SELECT first_name, last_name, username, userrole FROM tblusers WHERE user_id = ?";
        $stmt = $conn->prepare($userQuery);
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $userResult = $stmt->get_result();

        if ($userResult->num_rows > 0) {
            $userRow = $userResult->fetch_assoc();
            $full_name = $userRow['first_name'] . ' ' . $userRow['last_name'];
            $username = $userRow['username'];
            $userrole = $userRow['userrole'];

            // Update session variables
            $_SESSION['full_name'] = $full_name;
            $_SESSION['username'] = $username;
            $_SESSION['userrole'] = $userrole;
        }

        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="apple-touch-icon" sizes="180x180" href="../assets/favicon/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="../assets/favicon/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="../assets/favicon/favicon-16x16.png">
    <link rel="manifest" href="../assets/favicon/site.webmanifest">
    <title><?php echo isset($page_title) ? htmlspecialchars($page_title) : 'Butchery - POS'; ?></title>
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css" type="text/css">
    <style>
        *{box-sizing:border-box;margin:0;padding:0}

        .main-header{background-color: #FFFF33;padding:10px 20px;box-shadow:0 2px 4px rgba(0,0,0,0.1);position:sticky;top:0;z-index:1000;min-height:70px;color:#000000;}
        .header-content{display:flex;align-items:center;justify-content:space-between;width:100%;margin:0 auto;gap:15px;flex-wrap:wrap}
        .logo-container{display:flex;align-items:center}
        .logo-container img{height:60px;width:auto}
        .user-info-header{display:flex;align-items:center;gap:10px;background:rgba(255,255,255,0.1);padding:8px 15px;border-radius:20px;color:#000000;white-space:nowrap}
        .user-info-header .user-icon{font-size:1.2em}
        .user-info-header .user-details{display:flex;flex-direction:column;align-items:flex-start}
        .user-info-header .user-name{font-weight:bold;font-size:0.95em}
        .user-info-header .user-role{font-size:0.8em;color:#000000}
        .main-nav{display:flex;align-items:center}
        .main-nav .nav-list{list-style:none;margin:0;padding:0;display:flex;align-items:center;gap:5px}
        .main-nav .nav-item,.main-nav .dropdown{color:#FFF}
        .main-nav .nav-link,.main-nav .dropdown-toggle{display:block;padding:8px 12px;text-decoration:none;color:#000000;font-weight:600;white-space:nowrap;border-radius:4px;transition:background 0.1s}
        .main-nav .nav-link:hover,.main-nav .dropdown-toggle:hover{background:rgba(255,255,255,0.1)}
        .dropdown{position:relative}
        .dropdown-content{display:none;position:absolute;background-color:#66CCFF;min-width:180px;box-shadow:0 8px 16px rgba(0,0,0,0.2);z-index:1001;border-radius:4px;top:100%;left:0;margin-top:0px}
        .dropdown-content a{color:#000;padding:12px 16px;text-decoration:none;display:block;}
        .dropdown-content a:hover{background-color:#ddd}
        .dropdown:hover .dropdown-content{display:block}
        .hamburger{display:none;background:none;border:none;cursor:pointer;padding:10px;z-index:1001;position:relative}
        .hamburger-icon{display:block;width:25px;height:3px;background-color:#000000;margin:5px 0;transition:all 0.3s}
        .date-time-display{font-size:0.85em;color:#000000;padding:5px 10px;background:rgba(255,255,255,0.1);border-radius:15px;white-space:nowrap}
        .main-content{margin:10px 20px}
        .main-content h2{color:#330099}
        .timeout-warning{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:9999;justify-content:center;align-items:center}
        .timeout-warning.show{display:flex}
        .timeout-modal{background:#fff;padding:30px;border-radius:8px;box-shadow:0 4px 20px rgba(0,0,0,0.3);text-align:center;max-width:400px;width:90%}
        .timeout-modal h3{color:#330099;margin-bottom:15px}
        .timeout-modal button{background:#330099;color:#fff;border:none;padding:10px 20px;border-radius:4px;cursor:pointer;margin-top:15px;font-size:16px}
        .timeout-modal button:hover{background:#220066}
        .logout-btn { background: #FF0000; color: white; border: none; padding: 8px 15px;  border-radius: 20px; cursor: pointer; transition: background 0.3s; display: flex; align-items: center; gap: 8px; font-size: 20px; text-decoration: none;        }
        .logout-btn:hover {background: rgba(255, 255, 255, 0.25);}

        @media(max-width:1024px){
            .header-content{gap:10px}
            .main-nav .nav-list{gap:3px}
            .main-nav .nav-link,.main-nav .dropdown-toggle{padding:6px 10px;font-size:0.9em}
            .user-info-header{padding:6px 12px;font-size:0.9em}
            .date-time-display{font-size:0.8em}
        }

        @media(max-width:768px){
            .header-content{flex-wrap:wrap;justify-content:space-between;padding:5px 0}
            .logo-container{order:1}
            .logo-container img{height:50px}
            .user-info-header{order:2;padding:5px 10px;font-size:0.85em}
            .user-info-header .user-name{font-size:0.9em}
            .user-info-header .user-role{font-size:0.75em}
            .hamburger{order:3;display:block}
            .main-nav{display:none;width:100%;order:4;background-color:#FFFF33;box-shadow:0 4px 8px rgba(0,0,0,0.1);padding:10px 0;position:absolute;top:100%;left:0;right:0;z-index:1000;max-height:80vh;overflow-y:auto}
            .main-nav.is-active{display:block!important}
            .hamburger.is-active .hamburger-icon:nth-child(1){transform:translateY(8px) rotate(45deg)}
            .hamburger.is-active .hamburger-icon:nth-child(2){opacity:0}
            .hamburger.is-active .hamburger-icon:nth-child(3){transform:translateY(-8px) rotate(-45deg)}
            .main-nav .nav-list{flex-direction:column;align-items:stretch;gap:0}
            .main-nav .nav-item,.main-nav .dropdown{width:100%;margin-left:0;border-bottom:1px solid rgba(255,255,255,0.1)}
            .main-nav .nav-item:last-child,.main-nav .dropdown:last-child{border-bottom:none}
            .main-nav .nav-link,.main-nav .dropdown-toggle{padding:15px 20px;width:100%;font-size:1em;border-radius:0}
            .dropdown .dropdown-content{position:static; display:none; width:100%;box-shadow:none;background-color:#7777cc;padding-left:20px;border-top:1px solid rgba(255,255,255,0.1);margin-top:0}
            .dropdown-content a{color:#FFF;padding:10px 16px}
            .dropdown-content a:hover{background:rgba(255,255,255,0.1)}
            .dropdown.is-active .dropdown-content{display:block}
            .date-time-display{order:5;width:100%;text-align:center;margin-top:10px;font-size:0.8em}
        }

        @media(max-width:480px){
            .logo-container img{height:45px}
            .user-info-header{padding:4px 8px;font-size:0.8em;gap:5px}
            .user-info-header .user-icon{font-size:1em}
            .user-info-header .user-name{font-size:0.85em}
            .user-info-header .user-role{font-size:0.7em}
            .main-nav .nav-link,.main-nav .dropdown-toggle{padding:12px 15px;font-size:0.95em}
            .date-time-display{font-size:0.75em;padding:4px 8px}
        }

        @media(max-width:360px){
            .user-info-header .user-details{display:none}
            .user-info-header{padding:6px}
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="main-header">
            <div class="header-content">
                <div class="logo-container">

                    <img src="../assets/images/Logo1-rb1.png" width="562" height="444" alt="">
                </div>

                <button class="hamburger" aria-label="Toggle navigation menu">
                    <span class="hamburger-icon"></span>
                    <span class="hamburger-icon"></span>
                    <span class="hamburger-icon"></span>
                </button>

                <nav class="main-nav">
                    <ul class="nav-list">
                        <?php if (in_array($userrole, ['Admin', 'Supervisor', 'Manager'])) : ?>
                            <li class="nav-item"><a href="../dashboard/admin_dashboard.php" class="nav-link">Home</a></li>
                            <li class="nav-item"><a href="../Backup/backup.php" class="nav-link">BackUp</a></li>
                            <li class="nav-item"><a href="../sales/orders.php" class="nav-link">Prescription sales</a></li>
                            <li class="nav-item"><a href="../sales/direct_orders.php" class="nav-link">Quick Sales</a></li>
                            <li class="nav-item"><a href="../views/view_credit_sales.php" class="nav-link">Creditors</a></li>
                            <li class="nav-item"><a href="../sales/view_order.php" class="nav-link">Cashier</a></li>
                        <?php endif; ?>
                        <?php if (in_array($userrole, ['Cashier','Pharmtech'])) : ?>
                            <li class="nav-item"><a href="../dashboard/others_dashboard.php" class="nav-link">Home</a></li>
                            <li class="nav-item"><a href="../Backup/backup.php" class="nav-link">BackUp</a></li>
                            <li class="nav-item"><a href="../sales/orders.php" class="nav-link">Take Orders</a></li>
                            <li class="nav-item"><a href="../sales/view_order.php" class="nav-link">Cashier</a></li>
                            <li class="nav-item"><a href="../sales/direct_orders.php" class="nav-link">Quick Sales</a></li>
                            <li class="nav-item"><a href="../views/view_credit_sales.php" class="nav-link">Creditors</a></li>
                        <?php endif; ?>

                        <?php if (in_array($userrole, ['Admin', 'Supervisor', 'Manager'])) : ?>
                        <li class="dropdown">
                            <a href="#" class="dropdown-toggle">Reports</a>
                                <div class="dropdown-content">
                                    <a href="../reports/sales_report.php">Sales Reports</a>
                                    <a href="../reports/expiry_report.php">Expiry Reports</a>
                                    <a href="../receipts/view_receipts.php">View Receipts</a>
                                </div>
                        </li>
                        <?php endif; ?>

                        <?php if (in_array($userrole, ['Admin', 'Supervisor', 'Manager', 'Cashier'])) : ?>
                        <li class="dropdown">
                            <a href="#" class="dropdown-toggle">Stock Management</a>
                            <div class="dropdown-content">
                                <a href="../stocks/products.php">Add Products</a>
                                <a href="../views/view_product.php">View Products</a>
                                <a href="../stocks/addstocks.php">Add Inventory</a>
                                <a href="../stocks/viewstocks_sum.php">View Inventory</a>
                                <a href="../stocks/stock_taking.php">Stock Taking</a>
                            </div>
                        </li>
                        <?php endif; ?>
                        <?php if (in_array($userrole, ['Admin'])) : ?>
                        <li class="dropdown">
                            <a href="#" class="dropdown-toggle">System Settings</a>
                            <div class="dropdown-content">
                                <a href="../public/userslist.php">View Users</a>
                                <a href="../categories/index.php">Add Categories</a>
                                <a href="../products/index.php">Add Products</a>
                                <a href="../staff/staffslist.php">View Staff</a>
                                <a href="../suppliers/index.php">View Suppliers</a>
                            </div>
                        </li>
                        <?php endif; ?>

                        <?php if (in_array($userrole, ['Admin', 'Supervisor', 'Manager', 'Cashier', 'Cleaner', 'Security', 'Pharmtech'])) : ?>
                        <li class="dropdown user-menu">
                            <a href="#" class="dropdown-toggle">User Account settings</a>
                            <div class="dropdown-content">
                                <a href="../public/profile.php">Profile</a>
                                <a href="../public/reset_password.php">Change Password</a>
                                <a href="../public/login.php">Logout</a>
                            </div>
                        </li>
                        <?php endif; ?>
                        <div class="user-info-header">
                            <div class="user-details">
                                <span class="user-name"><?php echo htmlspecialchars($full_name); ?></span>
                                <span class="user-role"><?php echo htmlspecialchars($userrole); ?></span>
                            </div>
                        </div>
                        <div>
                            <a href="../public/login.php" class="logout-btn">
                                    <i class="fas fa-sign-out-alt"></i>
                                    <span>Logout</span>
                                </a>
                        </div>
                    </ul>
                </nav>
                <div id="date-time" class="date-time-display"></div>
                <span id="full_name" data-username="<?php echo htmlspecialchars($full_name); ?>" style="display:none;"></span>


            </div>
        </div>

        <!-- Timeout Warning Modal -->
        <div id="timeout-warning" class="timeout-warning">
            <div class="timeout-modal">
                <h3>⚠️ Session Timeout Warning</h3>
                <p>You will be logged out in <strong><span id="countdown">60</span></strong> seconds due to inactivity.</p>
                <button onclick="stayLoggedIn()">Stay Logged In</button>
            </div>
        </div>

    <script src="../assets/js/bootstrap.bundle.js"></script>
    <script src="../assets/js/bootstrap.min.js"></script>
    <script>
        try {
            document.addEventListener('DOMContentLoaded', () => {
                const hamburger = document.querySelector('.hamburger');
                const mainNav = document.querySelector('.main-nav');
                const dropdownToggles = document.querySelectorAll('.dropdown-toggle');
                const dateTimeDisplay = document.getElementById('date-time');

                function updateDateTime() {
                    try {
                        const now = new Date();
                        const options = {
                            weekday: 'short',
                            year: 'numeric',
                            month: 'short',
                            day: 'numeric',
                            hour: '2-digit',
                            minute: '2-digit',
                            second: '2-digit'
                        };
                        dateTimeDisplay.textContent = now.toLocaleDateString('en-US', options);
                    } catch (e) {
                        console.error('Error updating date-time:', e);
                    }
                }

                if (dateTimeDisplay) {
                    updateDateTime();
                    setInterval(updateDateTime, 1000);
                }

                if (hamburger && mainNav) {
                    hamburger.addEventListener('click', () => {
                        mainNav.classList.toggle('is-active');
                        hamburger.classList.toggle('is-active');
                    });
                }

                dropdownToggles.forEach(toggle => {
                    toggle.addEventListener('click', (e) => {
                        if (window.innerWidth <= 768) {
                            e.preventDefault();
                            const parentDropdown = toggle.closest('.dropdown');
                            if (parentDropdown) {
                                const isActive = parentDropdown.classList.contains('is-active');
                                document.querySelectorAll('.dropdown.is-active').forEach(openDropdown => {
                                    openDropdown.classList.remove('is-active');
                                });
                                if (!isActive) {
                                    parentDropdown.classList.add('is-active');
                                }
                            }
                        }
                    });
                });

                document.querySelectorAll('.main-nav .nav-link, .dropdown-content a').forEach(link => {
                    link.addEventListener('click', () => {
                        if (window.innerWidth <= 768) {
                            mainNav.classList.remove('is-active');
                            hamburger.classList.remove('is-active');
                            document.querySelectorAll('.dropdown.is-active').forEach(openDropdown => {
                                openDropdown.classList.remove('is-active');
                            });
                        }
                    });
                });

                window.addEventListener('resize', () => {
                    if (window.innerWidth > 768) {
                        mainNav.classList.remove('is-active');
                        hamburger.classList.remove('is-active');
                        document.querySelectorAll('.dropdown.is-active').forEach(openDropdown => {
                            openDropdown.classList.remove('is-active');
                        });
                    }
                });
            });
        } catch (e) {
            console.error('Error in navigation script:', e);
        }

        // Auto logout after 10 minutes of inactivity
        let inactivityTimeout;
        let warningTimeout;
        let countdownInterval;
        const INACTIVITY_LIMIT = 540000; // 9 minutes
        const WARNING_DURATION = 60000; // 1 minute warning
        const TOTAL_TIMEOUT = 600000; // 10 minutes total

        function showTimeoutWarning() {
            const warningModal = document.getElementById('timeout-warning');
            const countdownElement = document.getElementById('countdown');

            if (warningModal && countdownElement) {
                warningModal.classList.add('show');

                let secondsLeft = 60;
                countdownElement.textContent = secondsLeft;

                countdownInterval = setInterval(() => {
                    secondsLeft--;
                    countdownElement.textContent = secondsLeft;

                    if (secondsLeft <= 0) {
                        clearInterval(countdownInterval);
                        logout();
                    }
                }, 1000);

                warningTimeout = setTimeout(logout, WARNING_DURATION);
            }
        }

        function hideTimeoutWarning() {
            const warningModal = document.getElementById('timeout-warning');
            if (warningModal) {
                warningModal.classList.remove('show');
            }

            if (countdownInterval) {
                clearInterval(countdownInterval);
            }

            if (warningTimeout) {
                clearTimeout(warningTimeout);
            }
        }

        function logout() {
            window.location.href = '../public/login.php?timeout=1';
        }

        function resetInactivityTimer() {
            if (inactivityTimeout) {
                clearTimeout(inactivityTimeout);
            }

            hideTimeoutWarning();
            inactivityTimeout = setTimeout(showTimeoutWarning, INACTIVITY_LIMIT);
        }

        function stayLoggedIn() {
            hideTimeoutWarning();
            resetInactivityTimer();
        }

        // Reset timer on any user activity
        document.addEventListener('mousemove', resetInactivityTimer);
        document.addEventListener('keypress', resetInactivityTimer);
        document.addEventListener('click', resetInactivityTimer);
        document.addEventListener('scroll', resetInactivityTimer);
        document.addEventListener('touchstart', resetInactivityTimer);

        // Initialize timer when page loads
        resetInactivityTimer();
    </script>
</body>
</html>