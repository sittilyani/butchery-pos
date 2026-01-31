<?php
header('Content-Type: application/json');
include "../includes/config.php";
require_once '../dompdf/vendor/autoload.php'; // Composer autoload for Dompdf

use Dompdf\Dompdf;
use Dompdf\Options;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit;
}

$input = file_get_contents('php://input');
$data = json_decode($input, true);
error_log('Submit Order Input: ' . $input);

if (!$data || !isset($data['items']) || !is_array($data['items'])) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid or missing items data']);
    exit;
}

$required_fields = ['receipt_id', 'payment_method', 'payment_status', 'total_amount', 'tax_amount', 'grand_total', 'tendered_amount'];
foreach ($required_fields as $field) {
    if (!isset($data[$field])) {
        echo json_encode(['status' => 'error', 'message' => "Missing field: $field"]);
        exit;
    }
}

$receipt_id = $data['receipt_id'];
$payment_method = $data['payment_method'];
$payment_status = $data['payment_status'];
$total_amount = (float)$data['total_amount'];
$tax_amount = (float)$data['tax_amount'];
$grand_total = (float)$data['grand_total'];
$tendered_amount = (float)$data['tendered_amount'];
$transBy = $_SESSION['username'] ?? 'System';

// Check if already paid
$stmt = $conn->prepare("SELECT payment_status FROM sales WHERE receipt_id = ?");
$stmt->bind_param("s", $receipt_id);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    if ($row['payment_status'] === 'Paid') {
        echo json_encode(['status' => 'error', 'message' => 'Order is already paid']);
        exit;
    }
}
$stmt->close();

try {
    $conn->begin_transaction();

    // Validate stock for all items
    foreach ($data['items'] as $item) {
        $name = $item['name'];
        $quantity = (int)$item['quantity'];

        // Fetch latest stock balance
        $stock_query = $conn->prepare("
            SELECT stockBalance
            FROM stocks
            WHERE name = ?
            ORDER BY transDate DESC
            LIMIT 1
        ");
        $stock_query->bind_param("s", $name);
        $stock_query->execute();
        $stock_result = $stock_query->get_result();
        $stockBalance = 0;
        if ($stock_row = $stock_result->fetch_assoc()) {
            $stockBalance = $stock_row['stockBalance'];
        }
        $stock_query->close();

        // Check if stock is sufficient
        if ($stockBalance <= 0 || $stockBalance < $quantity) {
            throw new Exception("No enough stocks to sale for $name");
        }
    }

    // Insert into sales
    $stmt = $conn->prepare("
        INSERT INTO sales (
            receipt_id, total_amount, tax_amount, grand_total,
            payment_method, payment_status, tendered_amount
        ) VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param(
        "sdddsss",
        $receipt_id, $total_amount, $tax_amount, $grand_total,
        $payment_method, $payment_status, $tendered_amount
    );
    $stmt->execute();
    $sales_id = $conn->insert_id;
    $stmt->close();

    // Insert sale items and update stocks
    $item_stmt = $conn->prepare("
        INSERT INTO sale_items (
            sales_id, name, quantity, price, total_amount
        ) VALUES (?, ?, ?, ?, ?)
    ");
    $stock_stmt = $conn->prepare("
        INSERT INTO stocks (
            category_id, name, quantityOut, stockBalance, transBy, reorderLevel
        ) VALUES (?, ?, ?, ?, ?, ?)
    ");

    foreach ($data['items'] as $item) {
        $name = $item['name'];
        $quantity = (int)$item['quantity'];
        $price = (float)$item['price'];
        $item_total = (float)$item['total'];

        // Insert sale item
        $item_stmt->bind_param(
            "isidd",
            $sales_id, $name, $quantity, $price, $item_total
        );
        $item_stmt->execute();

        // Fetch latest stock balance and product details
        $stock_query = $conn->prepare("
            SELECT stockBalance, category_id
            FROM stocks
            WHERE name = ?
            ORDER BY transDate DESC
            LIMIT 1
        ");
        $stock_query->bind_param("s", $name);
        $stock_query->execute();
        $stock_result = $stock_query->get_result();
        $stockBalance = 0;
        $category_id = 0;
        if ($stock_row = $stock_result->fetch_assoc()) {
            $stockBalance = $stock_row['stockBalance'];
            $category_id = $stock_row['category_id'];
        }

        // Fetch category_id and reorder_level from products
        $product_query = $conn->prepare("SELECT category_id, reorder_level FROM products WHERE name = ?");
        $product_query->bind_param("s", $name);
        $product_query->execute();
        $product_result = $product_query->get_result();
        $reorder_level = null;
        if ($product_row = $product_result->fetch_assoc()) {
            $category_id = $product_row['category_id'];
            $reorder_level = $product_row['reorder_level'];
        }
        $product_query->close();
        $stock_query->close();

        // Update stock
        $newStockBalance = $stockBalance - $quantity;
        $stock_stmt->bind_param(
            "isiisi",
            $category_id, $name, $quantity, $newStockBalance, $transBy, $reorder_level
        );
        $stock_stmt->execute();
    }
    $item_stmt->close();
    $stock_stmt->close();

    // Delete drafts
    $delete_stmt = $conn->prepare("DELETE FROM sales_drafts WHERE receipt_id = ?");
    $delete_stmt->bind_param("s", $receipt_id);
    $delete_stmt->execute();
    $delete_stmt->close();

    // Generate PDF receipt
    if ($payment_status === 'Paid') {
        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'times');
        $dompdf = new Dompdf($options);

        // Build HTML for receipt
        $itemsHtml = '<table style="width:100%; border-collapse:collapse; font-size:12px; border:1px solid #000;">
            <tr>
                <th style="border:1px solid #000; padding:3px;">#</th>
                <th style="border:1px solid #000; padding:3px;">Product</th>
                <th style="border:1px solid #000; padding:3px;">Qty</th>
                <th style="border:1px solid #000; padding:3px;">Price</th>
                <th style="border:1px solid #000; padding:3px;">Total</th>
            </tr>';
        foreach ($data['items'] as $i => $item) {
            $itemsHtml .= "<tr>
                <td style=\"border:1px solid #000; padding:3px;\">" . ($i + 1) . "</td>
                <td style=\"border:1px solid #000; padding:3px;\">" . htmlspecialchars($item['name']) . "</td>
                <td style=\"border:1px solid #000; padding:3px;\">" . $item['quantity'] . "</td>
                <td style=\"border:1px solid #000; padding:3px;\">" . number_format($item['price'], 2) . "</td>
                <td style=\"border:1px solid #000; padding:3px;\">" . number_format($item['total'], 2) . "</td>
            </tr>";
        }
        $itemsHtml .= '</table>';

        $currentDate = date('Y-m-d H:i:s');
        $html = <<<EOD
<!DOCTYPE html>
<html>
<head>
    <style>
        body {
            font-family: 'Times New Roman', Times, serif;
            font-size: 10px;
            width: 79mm;
            margin: 5mm;
            line-height: 2;
        }
        .logo {
            text-align: center;
            margin-bottom: 10px;
        }
        h2 {
            text-align: center;
            margin: 5px 0;
            font-size: 12px;
        }
        .receipt-info, .totals {
            margin: 5px 0;
        }
        .receipt-info p, .totals p {
            margin: 2px 0;
        }
        table, th, td {
            border: 1px solid #000;
            border-collapse: collapse;
            text-align: left;
        }
    </style>
</head>
<body>
    <h2>Order Receipt</h2>

    <div class="receipt-info">
        <p><strong>Receipt ID:</strong> {$receipt_id}</p>
        <p><strong>Date:</strong> {$currentDate}</p>
        <p><strong>You were served by:</strong> DesBrand Cosmetics</p>
        <p><strong>Payment Method:</strong> {$payment_method}</p>
    </div>
    {$itemsHtml}
    <div class="totals">
        <p>Total Amount: KES {$total_amount}</p>
        <p>ToT (Tax) (1.5%): KES {$tax_amount}</p>
        <p>Grand Total: KES {$grand_total}</p>
        <p>Tendered: KES {$tendered_amount}</p>
        <p>Change: KES " . number_format($tendered_amount - $grand_total, 2) . "</p>
        <p>Payment Status: {$payment_status}</p>
        <p><span style="font-weight: bold; font-size: 16px; color: blue;">Till Number: 0123456</span></p>
        <p><span style="font-weight: bold; font-size: 16px; color: red;">Name: Desmond Barasa</span></p>
        <p><span style="font-style: italic;">Ask For It, We Have It</span></p>
    </div>
</body>
</html>
EOD;

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A5', 'portrait');
        $dompdf->render();

        // Ensure receipts directory exists
        $receiptsDir = dirname(__DIR__) . '/receipts';
        if (!is_dir($receiptsDir)) {
            mkdir($receiptsDir, 0755, true);
        }

        $filename = "{$receiptsDir}/{$receipt_id}-" . date('YmdHis') . ".pdf";
        file_put_contents($filename, $dompdf->output());
    }

    $conn->commit();
    echo json_encode(['status' => 'success', 'message' => 'Order submitted successfully']);
} catch (Exception $e) {
    $conn->rollback();
    error_log('Exception: ' . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
$conn->close();
?>