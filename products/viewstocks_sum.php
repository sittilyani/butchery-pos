<?php
ob_start();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include "../includes/config.php";
include "../includes/header.php";

$page_title = "Available Stocks";

// Check for logged-in user
if (!isset($_SESSION['username'])) {
    $error_message = 'User not logged in. Please log in to access this page.';
    error_log("Stock Summary Error: " . $error_message);
    header('Location: ../login.php?error=' . urlencode($error_message));
    exit;
}

// Initialize variables
$search = '';
$where_clause = '';
$error = '';
$stocks = [];

// Use prepared statements to prevent SQL injection
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search_term = '%' . $_GET['search'] . '%';
    $where_clause = "WHERE (s.productname LIKE ? OR s.brandname LIKE ?)";
}

try {
    // Corrected SQL query to get the latest stock for each brandname
    $sql = "SELECT s.*
            FROM stocks s
            INNER JOIN (
                SELECT brandname, MAX(stockID) as max_stockID
                FROM stocks
                GROUP BY brandname
            ) latest ON s.brandname = latest.brandname AND s.stockID = latest.max_stockID
            $where_clause
            ORDER BY s.brandname
            LIMIT 20";

    // Prepare and execute the statement
    $stmt = $conn->prepare($sql);
    if ($where_clause) {
        $stmt->bind_param("ss", $search_term, $search_term);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    if (!$result) {
        throw new Exception("Error executing query: " . $conn->error);
    }

    $stocks = $result->fetch_all(MYSQLI_ASSOC);

} catch (Exception $e) {
    $error = $e->getMessage();
    error_log("Stock Summary Error: " . $error);
}

// Check if this is an AJAX request
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    // This is an AJAX request, so we only return the table rows
    $html = '';
    if (!empty($stocks)) {
        foreach ($stocks as $stock) {
            $html .= '<tr class="table-row">';
            $html .= '<td>' . htmlspecialchars($stock['stockID']) . '</td>';
            $html .= '<td>' . htmlspecialchars($stock['productname']) . '</td>';
            $html .= '<td>' . htmlspecialchars($stock['brandname']) . '</td>';
            $html .= '<td>' . htmlspecialchars($stock['stockBalance']) . '</td>';
            $html .= '<td>' . $stock['status'] . '</td>';
            $html .= '<td class="action-buttons">';
            $html .= '<a href="view_transactions.php?brandname=' . urlencode($stock['brandname']) . '" class="btn btn-view"><i class="fas fa-eye"></i> Bin Card</a>';
            $html .= '</td>';
            $html .= '</tr>';
        }
    } else {
        $html .= '<tr><td colspan="6" class="text-center">No results found.</td></tr>';
    }
    echo $html;
    exit;
}


if (isset($_GET['error'])) {
     $error = urldecode($_GET['error']);
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css" type="text/css">
    <link rel="stylesheet" href="../assets/fontawesome-7.1.1/css/all.min.css" type="text/css">
    <style>
        * {
            box-sizing: border-box;
        }

        :root {
            --primary-color: #3498db;
            --secondary-color: #2980b9;
            --danger-color: #e74c3c;
            --success-color: #2ecc71;
            --warning-color: #f39c12;
            --light-color: #f8f9fa;
            --dark-color: #343a40;
            --border-color: #dee2e6;
        }

        body {
            background-color: #f4f6f9;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
        }

        .main-content {
            padding: 30px;
            max-width: 95%;
            margin: 0 auto;
        }

        .page-header {
            background: #000099;
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }

        .page-header h1 {
            margin: 0;
            font-size: clamp(1.8rem, 4vw, 2.5rem);
            font-weight: 300;
        }

        .page-header p {
            margin: 10px 0 0 0;
            opacity: 0.9;
            font-size: clamp(0.9rem, 2vw, 1.1rem);
        }

        .controls-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .button-group {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .search-container {
            position: relative;
            flex: 1;
            max-width: 400px;
            min-width: 250px;
        }

        .search-input {
            width: 100%;
            padding: 12px 45px 12px 15px;
            border: 2px solid #e1e8ed;
            border-radius: 25px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background-color: white;
        }

        .search-input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        }

        .search-icon {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #657786;
        }

        .loading-spinner {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            display: none;
        }

        .add-product-btn {
            background: #000099;
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 25px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            transition: all 0.3s ease;
            text-decoration: none;
            font-size: clamp(0.85rem, 1.5vw, 1rem);
            white-space: nowrap;
        }

        .add-product-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 153, 0.3);
            color: white;
        }

        .add-product-btn i {
            margin-right: 8px;
        }

        .products-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 25px rgba(0,0,0,0.08);
            overflow: hidden;
        }

        .table-container {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 0;
            font-size: clamp(0.8rem, 1.5vw, 0.9rem);
        }

        thead {
            background: #000099;
            color: white;
        }

        th {
            padding: 18px 15px;
            text-align: left;
            font-weight: 600;
            font-size: clamp(0.75rem, 1.5vw, 0.85rem);
            letter-spacing: 0.5px;
            white-space: nowrap;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        td {
            padding: 15px;
            border-bottom: 1px solid var(--border-color);
            vertical-align: middle;
            white-space: nowrap;
        }

        tbody tr {
            transition: all 0.2s ease;
        }

        tbody tr:hover {
            background-color: rgba(102, 126, 234, 0.05);
        }

        tbody tr:nth-child(even) {
            background-color: #fafbfc;
        }

        .btn {
            padding: 8px 15px;
            border-radius: 20px;
            font-size: clamp(0.7rem, 1.2vw, 0.8rem);
            font-weight: 500;
            margin-right: 5px;
            margin-bottom: 5px;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: none;
            cursor: pointer;
            text-decoration: none;
        }

        .btn i {
            margin-right: 5px;
            font-size: 0.8rem;
        }

        .btn-view {
            background-color: var(--success-color);
            color: white;
        }

        .btn-view:hover {
            background-color: #27ae60;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(46, 204, 113, 0.3);
        }

        .action-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
        }

        /* Tablet devices */
        @media (max-width: 1024px) {
            .main-content {
                padding: 20px;
                max-width: 98%;
            }

            .page-header {
                padding: 25px;
            }

            .controls-section {
                gap: 12px;
            }
        }

        /* Mobile devices */
        @media (max-width: 768px) {
            .main-content {
                padding: 15px;
                max-width: 100%;
            }

            .page-header {
                padding: 20px;
                text-align: center;
                margin-bottom: 20px;
            }

            .page-header h1 {
                font-size: 1.5rem;
            }

            .page-header p {
                font-size: 0.9rem;
            }

            .controls-section {
                flex-direction: column;
                align-items: stretch;
                gap: 10px;
            }

            .search-container {
                max-width: none;
                min-width: auto;
            }

            .button-group {
                flex-direction: column;
            }

            .add-product-btn {
                width: 100%;
                justify-content: center;
                padding: 14px 20px;
            }

            .table-container {
                font-size: 0.8rem;
            }

            th, td {
                padding: 10px 8px;
                font-size: 0.75rem;
            }

            .btn {
                padding: 6px 12px;
                font-size: 0.7rem;
            }

            .action-buttons {
                flex-direction: column;
                width: 100%;
            }
        }

        /* Small phones */
        @media (max-width: 480px) {
            .main-content {
                padding: 10px;
            }

            .page-header {
                padding: 15px;
                border-radius: 10px;
            }

            .page-header h1 {
                font-size: 1.3rem;
            }

            .search-input {
                padding: 10px 40px 10px 12px;
                font-size: 0.9rem;
            }

            .add-product-btn {
                padding: 12px 18px;
                font-size: 0.85rem;
            }

            th, td {
                padding: 8px 5px;
                font-size: 0.7rem;
            }

            .btn i {
                margin-right: 3px;
                font-size: 0.7rem;
            }
        }

        /* Landscape orientation */
        @media (max-height: 600px) and (orientation: landscape) {
            .page-header {
                padding: 15px;
                margin-bottom: 15px;
            }

            .page-header h1 {
                font-size: 1.5rem;
            }

            .controls-section {
                margin-bottom: 15px;
            }
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .table-row {
            animation: fadeIn 0.3s ease forwards;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .spinner {
            animation: spin 1s linear infinite;
        }
    </style>
</head>
<body>
<div class="main-content">
    <div class="page-header">
        <h1>Inventory Summary</h1>
        <p>Manage your inventory efficiently</p>
    </div>

    <div class="controls-section">
        <div class="search-container">
            <input type="text" class="search-input" id="product-search" placeholder="Search by product name or brand">
            <span class="search-icon"><i class="fas fa-search"></i></span>
            <span class="loading-spinner"><i class="fas fa-spinner spinner"></i></span>
        </div>
        <div class="button-group">
            <a href="../stocks/addstocks.php" class="add-product-btn"><i class="fas fa-plus"></i> Add Stocks</a>
            <a href="?action=generate_pdf" class="add-product-btn"><i class="fas fa-print"></i> Print PDF</a>
        </div>
    </div>

    <div class="products-container">
        <div class="table-container">
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Product Name</th>
                        <th>Brand Name</th>
                        <th>Stock Balance</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody id="stocks-table">
                    <?php foreach ($stocks as $stock): ?>
                        <tr class="table-row">
                            <td><?php echo htmlspecialchars($stock['stockID']); ?></td>
                            <td><?php echo htmlspecialchars($stock['productname']); ?></td>
                            <td><?php echo htmlspecialchars($stock['brandname']); ?></td>
                            <td><?php echo $stock['stockBalance']; ?></td>
                            <td><?php echo $stock['status']; ?></td>
                            <td class="action-buttons">
                                <a href="view_transactions.php?brandname=<?php echo urlencode($stock['brandname']); ?>" class="btn btn-view"><i class="fas fa-eye"></i> Bin Card</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="../assets/fontawesome-7.1.1/js/all.min.js"></script>
<script src="../assets/js/bootstrap.bundle.js"></script>
<script>
// Pure JavaScript implementation - no jQuery needed
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('product-search');
    const stocksTable = document.getElementById('stocks-table');
    const spinner = document.querySelector('.loading-spinner');
    const searchIcon = document.querySelector('.search-icon');
    let typingTimer;
    const doneTypingInterval = 500; // time in ms

    searchInput.addEventListener('input', function() {
        clearTimeout(typingTimer);
        const searchValue = this.value.trim();

        spinner.style.display = 'block';
        searchIcon.style.display = 'none';

        typingTimer = setTimeout(function() {
            // Create XMLHttpRequest
            const xhr = new XMLHttpRequest();
            xhr.open('GET', 'viewstocks_sum.php?search=' + encodeURIComponent(searchValue), true);
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

            xhr.onload = function() {
                if (xhr.status === 200) {
                    stocksTable.innerHTML = xhr.responseText;
                } else {
                    stocksTable.innerHTML = '<tr><td colspan="6" class="text-center text-danger">Error fetching data. Please try again.</td></tr>';
                    console.error('Error: ' + xhr.status);
                }
                spinner.style.display = 'none';
                searchIcon.style.display = 'block';
            };

            xhr.onerror = function() {
                stocksTable.innerHTML = '<tr><td colspan="6" class="text-center text-danger">Network error. Please check your connection.</td></tr>';
                spinner.style.display = 'none';
                searchIcon.style.display = 'block';
            };

            xhr.send();
        }, doneTypingInterval);
    });
});
</script>
</body>
</html>