<?php
ob_start();
include '../includes/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    while (ob_get_level()) {
        ob_end_clean();
    }
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['status' => 'error', 'message' => 'User not logged in.']);
    exit;
}

$user_id = $_SESSION['user_id'];
$transBy = "Unknown User";

$user_query = $conn->prepare("SELECT full_name FROM tblusers WHERE user_id = ?");
$user_query->bind_param("i", $user_id);
$user_query->execute();
$user_result = $user_query->get_result();
if ($user_row = $user_result->fetch_assoc()) {
    $transBy = $user_row['full_name'];
}
$user_query->close();

include '../includes/header.php';

$page_title = "Stock-taking";

// Handle GET request (search products)
if (isset($_GET['q'])) {
    while (ob_get_level()) {
        ob_end_clean();
    }
    header('Content-Type: application/json; charset=UTF-8');

    $searchQuery = $_GET['q'] ?? '';

    if (strlen($searchQuery) < 3) {
        echo json_encode([]);
        exit;
    }

    try {
        $query = "%" . $searchQuery . "%";
        $stmt = $conn->prepare("
            SELECT DISTINCT name,
                   (SELECT total_qty FROM stock_movements sm2
                    WHERE sm2.name = sm1.name
                    ORDER BY trans_date DESC, trans_id DESC LIMIT 1) as total_qty
            FROM stock_movements sm1
            WHERE name LIKE ?
            ORDER BY (SELECT total_qty FROM stock_movements sm3
                     WHERE sm3.name = sm1.name
                     ORDER BY trans_date DESC, trans_id DESC LIMIT 1) = 0, name ASC
            LIMIT 20
        ");

        if (!$stmt) {
            throw new Exception("Prepare statement failed: " . $conn->error);
        }

        $stmt->bind_param("s", $query);
        $stmt->execute();
        $result = $stmt->get_result();

        $products = [];
        while ($row = $result->fetch_assoc()) {
            $products[] = [
                'id' => 0,
                'name' => $row['name'],
                'total_qty' => (int)$row['total_qty'] ?? 0,
                'stockBalance' => (int)$row['total_qty'] ?? 0
            ];
        }

        $stmt->close();
        echo json_encode($products);

    } catch (Exception $e) {
        error_log("Error fetching products: " . $e->getMessage());
        echo json_encode([]);
    }

    exit;
}

// Handle POST request (stock adjustments)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    while (ob_get_level()) {
        ob_end_clean();
    }
    header('Content-Type: application/json; charset=UTF-8');

    $input = file_get_contents("php://input");
    $data = json_decode($input, true);

    if (!$data || !is_array($data)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid or missing input data.']);
        exit;
    }

    $conn->begin_transaction();

    try {
        $get_latest_stock_stmt = $conn->prepare("
            SELECT trans_id, total_qty, received_by, trans_date, expiry_date, batch_number
            FROM stock_movements
            WHERE name = ?
            ORDER BY trans_date DESC, trans_id DESC
            LIMIT 1
        ");

        $insert_movement_stmt = $conn->prepare("
            INSERT INTO stock_movements (
                transactionType, name, opening_bal, qty_in, qty_out,
                received_from, batch_number, expiry_date, transBy, total_qty, received_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        foreach ($data as $adjustment) {
            if (!isset($adjustment['name'], $adjustment['transactionType'], $adjustment['quantity'])) {
                throw new Exception("Invalid or missing data in one of the adjustments.");
            }

            $name = $adjustment['name'];
            $transactionType = $adjustment['transactionType'];
            $quantity = (int)$adjustment['quantity'];

            $get_latest_stock_stmt->bind_param("s", $name);
            $get_latest_stock_stmt->execute();
            $stock_result = $get_latest_stock_stmt->get_result();

            if ($stock_result->num_rows === 0) {
                $current_stock = 0;
                $expiry_date = null;
                $batch_number = '';

                $insert_initial_stmt = $conn->prepare("
                    INSERT INTO stock_movements (
                        transactionType, name, opening_bal, qty_in, qty_out,
                        received_from, batch_number, expiry_date, transBy, total_qty, received_by
                    ) VALUES ('Initial', ?, 0, 0, 0, ?, '', NULL, ?, 0, ?)
                ");

                $received_from = 'Stock Take';
                $received_by = 'Stock Take';
                $insert_initial_stmt->bind_param("ssss", $name, $received_from, $transBy, $received_by);
                $insert_initial_stmt->execute();
                $insert_initial_stmt->close();

                $get_latest_stock_stmt->bind_param("s", $name);
                $get_latest_stock_stmt->execute();
                $stock_result = $get_latest_stock_stmt->get_result();
                $stock_row = $stock_result->fetch_assoc();
            } else {
                $stock_row = $stock_result->fetch_assoc();
            }

            $current_stock = (int)$stock_row['total_qty'];
            $expiry_date = $stock_row['expiry_date'];
            $batch_number = $stock_row['batch_number'] ?? '';

            $qty_in = 0;
            $qty_out = 0;
            $opening_bal = $current_stock;
            $new_stock = $current_stock;
            $adjustmentType = strtolower($transactionType);

            if (in_array($adjustmentType, ['positive adjustment', 'returns'])) {
                $new_stock = $current_stock + $quantity;
            } elseif (in_array($adjustmentType, ['expired', 'donated', 'negative adjustments', 'quarantined', 'pqm'])) {
                $new_stock = $current_stock - $quantity;
            } else {
                throw new Exception("Invalid transaction type: " . $transactionType);
            }

            if ($new_stock < 0) {
                throw new Exception("Insufficient stock for: $name. Current stock is $current_stock.");
            }

            $received_from = 'Stock Take';
            $received_by = 'Stock Take';

            $insert_movement_stmt->bind_param(
                "ssiddssssds",
                $transactionType,
                $name,
                $opening_bal,
                $qty_in,
                $qty_out,
                $received_from,
                $batch_number,
                $expiry_date,
                $transBy,
                $new_stock,
                $received_by
            );

            if (!$insert_movement_stmt->execute()) {
                throw new Exception("Failed to insert stock movement: " . $insert_movement_stmt->error);
            }
        }

        $get_latest_stock_stmt->close();
        $insert_movement_stmt->close();
        $conn->commit();

        echo json_encode([
            'status' => 'success',
            'message' => count($data) . ' stock adjustment(s) processed successfully.'
        ]);

    } catch (Exception $e) {
        $conn->rollback();
        error_log("Stock adjustment error: " . $e->getMessage());
        echo json_encode([
            'status' => 'error',
            'message' => 'Error: ' . $e->getMessage()
        ]);
    }

    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> - Butchery POS</title>
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root{--primary:#4361ee;--secondary:#3f37c9;--success:#06d6a0;--danger:#ef476f;--warning:#ffd60a;--dark:#2b2d42;--light:#f8f9fa;--radius:12px}
        body{background:none;padding:20px 0}
        .container{max-width:1200px; margin-top: 20px;}
        .header-card,.search-card,.stock-table{background:#fff;border-radius:var(--radius);padding:25px;margin-bottom:25px;box-shadow:0 10px 30px rgba(0,0,0,.1)}
        .header-card h1{color:var(--dark);font-weight:700;margin:0;display:flex;align-items:center;gap:12px}
        .header-card h1 i{color:var(--primary)}
        .search-wrapper{position:relative}
        .search-wrapper i{position:absolute;left:15px;top:50%;transform:translateY(-50%);color:#999;font-size:18px}
        .search-wrapper input{padding:12px 15px 12px 45px;border:2px solid #e0e0e0;border-radius:8px;font-size:16px;transition:all .3s}
        .search-wrapper input:focus{border-color:var(--primary);box-shadow:0 0 0 3px rgba(67,97,238,.1)}
        .results-info{background:linear-gradient(135deg,var(--primary),var(--secondary));color:#fff;padding:15px 20px;border-radius:8px;margin-bottom:20px;display:flex;justify-content:space-between;align-items:center}
        .stock-table{padding:0;overflow:hidden}
        .table{margin:0}
        .table thead{background:linear-gradient(135deg,var(--primary),var(--secondary));color:#fff}
        .table thead th{padding:15px;font-weight:600;border:none}
        .table tbody td{padding:15px;vertical-align:middle}
        .stock-indicator{padding:6px 12px;border-radius:20px;font-weight:600;font-size:14px;display:inline-block}
        .stock-indicator.in-stock{background:#d4edda;color:#155724}
        .stock-indicator.low-stock{background:#fff3cd;color:#856404}
        .stock-indicator.out-of-stock{background:#f8d7da;color:#721c24}
        .empty-state{text-align:center;padding:60px 20px!important;color:#999}
        .empty-state i{font-size:64px;margin-bottom:20px;opacity:.5}
        .empty-state h3{color:var(--dark);margin-bottom:10px}
        .btn-submit{background:linear-gradient(135deg,var(--success),#05b48f);color:#fff;padding:12px 30px;border:none;border-radius:8px;font-weight:600;font-size:16px;transition:all .3s;box-shadow:0 4px 15px rgba(6,214,160,.3)}
        .btn-submit:hover{transform:translateY(-2px);box-shadow:0 6px 20px rgba(6,214,160,.4)}
        .btn-submit:disabled{opacity:.6;cursor:not-allowed}
        .status-message{padding:15px 20px;border-radius:8px;margin-bottom:20px;font-weight:500;display:none}
        .status-message.success-message{background:#d4edda;color:#155724;border-left:4px solid var(--success)}
        .status-message.error-message{background:#f8d7da;color:#721c24;border-left:4px solid var(--danger)}
        .form-select,.form-control{border:2px solid #e0e0e0;border-radius:6px;transition:all .3s}
        .form-select:focus,.form-control:focus{border-color:var(--primary);box-shadow:0 0 0 3px rgba(67,97,238,.1)}
    </style>
</head>
<body>
    <div class="container">
        <div class="header-card">
            <h1><i class="fas fa-clipboard-check"></i>Stock Taking & Adjustments</h1>
            <p class="text-muted mb-0 mt-2">Search for products and make stock adjustments</p>
        </div>
        <div class="search-card">
            <div class="search-wrapper">
                <i class="fas fa-search"></i>
                <input type="text" id="searchInput" class="form-control" placeholder="Search products... (minimum 3 characters)" autocomplete="off">
            </div>
        </div>
        <div id="statusMessage" class="status-message"></div>
        <div id="resultsInfo" class="results-info" style="display:none">
            <span><i class="fas fa-box-open me-2"></i><span id="resultsCount">0</span> products found</span>
            <span>Logged in as: <strong><?= htmlspecialchars($transBy) ?></strong></span>
        </div>
        <form id="stockForm" method="POST">
            <div class="stock-table">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Product Name</th>
                            <th style="width:150px">Current Stock</th>
                            <th style="width:250px">Adjustment Type</th>
                            <th style="width:150px">Quantity</th>
                        </tr>
                    </thead>
                    <tbody id="productTableBody">
                        <tr>
                            <td colspan="4" class="empty-state">
                                <i class="fas fa-search"></i>
                                <h3>Start Searching</h3>
                                <p>Enter a product name above to begin stock taking</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="text-end mt-4">
                <button type="submit" class="btn btn-submit">
                    <i class="fas fa-check-circle me-2"></i>Process Adjustments
                </button>
            </div>
        </form>
    </div>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="../assets/js/bootstrap.bundle.min.js"></script>
    <script>
        $(document).ready(function(){
            const searchInput=$('#searchInput'),productTableBody=$('#productTableBody'),form=$('#stockForm'),statusMessage=$('#statusMessage'),resultsInfo=$('#resultsInfo'),resultsCount=$('#resultsCount');
            const getStockClass=qty=>qty>10?'in-stock':qty>0?'low-stock':'out-of-stock';
            const fetchProducts=async query=>{
                if(!query||query.length<3){
                    productTableBody.html('<tr><td colspan="4" class="empty-state"><i class="fas fa-search"></i><h3>Start Searching</h3><p>Enter at least 3 characters to search</p></td></tr>');
                    resultsInfo.hide();
                    return;
                }
                try{
                    productTableBody.html('<tr><td colspan="4" class="empty-state"><i class="fas fa-spinner fa-spin"></i><h3>Searching...</h3><p>Please wait</p></td></tr>');
                    const response=await fetch(`?q=${encodeURIComponent(query)}`,{headers:{'X-Requested-With':'XMLHttpRequest'}});
                    if(!response.ok)throw new Error(`HTTP error! Status: ${response.status}`);
                    const products=await response.json();
                    renderProducts(products);
                }catch(error){
                    console.error('Fetch error:',error);
                    productTableBody.html(`<tr><td colspan="4" class="empty-state"><i class="fas fa-exclamation-triangle text-danger"></i><h3>Error Loading Products</h3><p>${error.message}</p></td></tr>`);
                    resultsInfo.hide();
                }
            };
            const renderProducts=products=>{
                if(!Array.isArray(products)||products.length===0){
                    productTableBody.html('<tr><td colspan="4" class="empty-state"><i class="fas fa-inbox"></i><h3>No Products Found</h3><p>Try adjusting your search terms</p></td></tr>');
                    resultsInfo.hide();
                    return;
                }
                let tableHTML='';
                products.forEach(product=>{
                    const stockClass=getStockClass(product.total_qty||0);
                    const stockText=product.total_qty||0;
                    tableHTML+=`<tr><td><div class="fw-bold">${product.name}</div></td><td><span class="stock-indicator ${stockClass}">${stockText}</span></td><td><select name="transactionType" class="form-select" required><option value="">Select Adjustment</option><option value="Expired">Expired</option><option value="Donated">Donated</option><option value="Negative Adjustments">Negative Adjustments</option><option value="Quarantined">Quarantined</option><option value="PQM">PQM</option><option value="Positive Adjustment">Positive Adjustment</option><option value="Returns">Returns</option></select></td><td><div class="d-flex align-items-center gap-2"><input type="number" name="quantity" min="1" class="form-control" placeholder="Qty" style="width:100px" required><input type="hidden" name="id" value="${product.id||0}"><input type="hidden" name="name" value="${product.name}"></div></td></tr>`;
                });
                productTableBody.html(tableHTML);
                resultsCount.text(products.length);
                resultsInfo.show();
            };
            let searchTimeout;
            searchInput.on('input',function(){
                clearTimeout(searchTimeout);
                const query=searchInput.val().trim();
                searchTimeout=setTimeout(()=>{fetchProducts(query);},500);
            });
            form.on('submit',async function(event){
                event.preventDefault();
                const rows=productTableBody.find('tr');
                const adjustments=[];
                let hasAdjustments=false;
                rows.each(function(){
                    const name=$(this).find('input[name="name"]').val();
                    const transactionType=$(this).find('select[name="transactionType"]').val();
                    const quantity=$(this).find('input[name="quantity"]').val();
                    if(name&&transactionType&&quantity&&parseInt(quantity)>0){
                        adjustments.push({name,transactionType,quantity:parseInt(quantity)});
                        hasAdjustments=true;
                    }
                });
                if(!hasAdjustments){
                    showMessage('Please select at least one product and enter a valid adjustment.','error');
                    return;
                }
                const submitBtn=form.find('button[type="submit"]');
                const originalText=submitBtn.html();
                submitBtn.html('<i class="fas fa-spinner fa-spin me-2"></i>Processing...').prop('disabled',true);
                try{
                    const response=await fetch(window.location.href,{method:'POST',headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},body:JSON.stringify(adjustments)});
                    if(!response.ok)throw new Error(`HTTP error! Status: ${response.status}`);
                    const result=await response.json();
                    if(result.status==='success'){
                        showMessage(result.message,'success');
                        const currentQuery=searchInput.val().trim();
                        if(currentQuery.length>=3){
                            fetchProducts(currentQuery);
                        }else{
                            searchInput.val('');
                            productTableBody.html('<tr><td colspan="4" class="empty-state"><i class="fas fa-check-circle text-success"></i><h3>Adjustments Successful!</h3><p>Stock has been updated. Search for more products to make additional adjustments.</p></td></tr>');
                            resultsInfo.hide();
                        }
                    }else{
                        showMessage(result.message,'error');
                    }
                }catch(error){
                    console.error('Submission error:',error);
                    showMessage('An unexpected error occurred: '+error.message,'error');
                }finally{
                    submitBtn.html(originalText).prop('disabled',false);
                }
            });
            const showMessage=(message,type)=>{
                statusMessage.text(message).removeClass('success-message error-message').addClass(type==='success'?'success-message':'error-message').slideDown();
                if(type==='success'){
                    setTimeout(()=>{statusMessage.slideUp();},5000);
                }
            };
            searchInput.focus();
            searchInput.on('keydown',function(e){
                if(e.key==='Enter'){
                    e.preventDefault();
                    fetchProducts($(this).val().trim());
                }
            });
        });
    </script>
</body>
</html>