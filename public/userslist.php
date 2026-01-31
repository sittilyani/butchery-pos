<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("location: ../public/login.php");
    exit;
}

include '../includes/config.php';
include '../includes/header.php';

$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Check if is_active column exists, if not add it
$column_check = $conn->query("SHOW COLUMNS FROM tblusers LIKE 'is_active'");
if ($column_check->num_rows == 0) {
    $conn->query("ALTER TABLE tblusers ADD COLUMN is_active TINYINT(1) DEFAULT 1");
}

if (!empty($search)) {
    $stmt = $conn->prepare("SELECT * FROM tblusers
                           WHERE user_id LIKE ? OR first_name LIKE ? OR last_name LIKE ?
                           OR gender LIKE ? OR full_name LIKE ? OR mobile LIKE ? OR userrole LIKE ?
                           ORDER BY date_created DESC LIMIT 50");
    $search_param = "%$search%";
    $stmt->bind_param("sssssss", $search_param, $search_param, $search_param, $search_param, $search_param, $search_param, $search_param);
    $stmt->execute();
    $result = $stmt->get_result();
    $users = $result->fetch_all(MYSQLI_ASSOC);
} else {
    $result = $conn->query("SELECT * FROM tblusers ORDER BY date_created DESC");
    $users = $result->fetch_all(MYSQLI_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users List - Butchery POS</title>
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root{--primary:#3498db;--secondary:#2980b9;--danger:#e74c3c;--success:#2ecc71;--warning:#f39c12;--light:#f8f9fa;--dark:#343a40;--border:#dee2e6}
        body{padding:20px 0}
        .users-container{max-width:90%;margin:0 auto;background:#fff;border-radius:12px;box-shadow:0 10px 30px rgba(0,0,0,.1);padding:30px;overflow:hidden}
        .page-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:25px;padding-bottom:20px;border-bottom:2px solid var(--border);flex-wrap:wrap;gap:15px}
        .page-header h4{color:var(--dark);font-weight:700;margin:0;display:flex;align-items:center;gap:10px}
        .page-header h4 i{color:var(--primary);font-size:1.3em}
        .header-actions{display:flex;gap:10px;flex-wrap:wrap}
        .btn-add-user{background:linear-gradient(135deg,var(--success),#27ae60);color:#fff;padding:10px 20px;border:none;border-radius:8px;font-weight:600;display:flex;align-items:center;gap:8px;transition:all .3s;box-shadow:0 4px 15px rgba(46,204,113,.3);text-decoration:none}
        .btn-add-user:hover{transform:translateY(-2px);box-shadow:0 6px 20px rgba(46,204,113,.4);color:#fff}
        .search-section{background:linear-gradient(135deg,#f5f7fa,#c3cfe2);padding:20px;border-radius:8px;margin-bottom:25px}
        .search-wrapper{display:flex;gap:10px;flex-wrap:wrap;align-items:center}
        .search-wrapper input[type="text"]{flex:1;min-width:250px;padding:10px 15px;border:2px solid var(--border);border-radius:6px;font-size:15px;transition:all .3s}
        .search-wrapper input[type="text"]:focus{border-color:var(--primary);box-shadow:0 0 0 3px rgba(52,152,219,.1);outline:none}
        .search-btn,.cancel-btn,.export-btn{padding:10px 20px;border:none;border-radius:6px;font-weight:600;cursor:pointer;transition:all .3s;display:flex;align-items:center;gap:8px}
        .search-btn{background:var(--primary);color:#fff}
        .search-btn:hover{background:var(--secondary);transform:translateY(-1px)}
        .cancel-btn{background:#6c757d;color:#fff}
        .cancel-btn:hover{background:#5a6268}
        .export-btn{background:var(--warning);color:#fff}
        .export-btn:hover{background:#e67e22}
        .user-count{background:#fff;padding:10px 15px;border-radius:6px;font-weight:600;color:var(--danger)}
        .table-wrapper{background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.05)}
        table{width:100%;margin:0;font-size:.9rem;border-collapse:collapse}
        thead{background:linear-gradient(135deg,#1a2a6c,#2b5876);color:#fff}
        thead th{padding:15px 12px;text-align:left;font-weight:600;text-transform:uppercase;font-size:.75rem;letter-spacing:.5px;white-space:nowrap}
        tbody td{padding:12px;border-bottom:1px solid var(--border);vertical-align:middle}
        tbody tr:hover{background:rgba(52,152,219,.05)}
        tbody tr:nth-child(even){background:rgba(0,0,0,.01)}
        .action-btns{display:flex;flex-wrap:wrap;gap:5px}
        .btn-sm{padding:6px 12px;border-radius:4px;font-size:.75rem;font-weight:500;transition:all .2s;display:inline-flex;align-items:center;gap:5px;border:none;cursor:pointer;text-decoration:none}
        .btn-sm i{font-size:.7rem}
        .btn-update{background:var(--warning);color:#fff}
        .btn-update:hover{background:#e67e22;transform:translateY(-1px);color:#fff}
        .btn-delete{background:var(--danger);color:#fff}
        .btn-delete:hover{background:#c0392b;transform:translateY(-1px);color:#fff}
        .btn-view{background:var(--success);color:#fff}
        .btn-view:hover{background:#27ae60;transform:translateY(-1px);color:#fff}
        .btn-reset{background:#17a2b8;color:#fff}
        .btn-reset:hover{background:#138496;transform:translateY(-1px);color:#fff}
        .alert{padding:15px 20px;border-radius:8px;margin-bottom:20px;border-left:4px solid;animation:slideIn .3s ease}
        .alert-success{background:#d4edda;color:#155724;border-color:var(--success)}
        .alert-danger{background:#f8d7da;color:#721c24;border-color:var(--danger)}
        @keyframes slideIn{from{opacity:0;transform:translateY(-10px)}to{opacity:1;transform:translateY(0)}}
        @media (max-width:768px){
            .page-header{flex-direction:column;align-items:flex-start}
            .search-wrapper{flex-direction:column;align-items:stretch}
            .search-wrapper input[type="text"]{min-width:100%}
            .table-wrapper{overflow-x:auto}
            table{min-width:800px}
        }
        @media print{
            body{background:#fff}
            .users-container{box-shadow:none}
            .search-section,.btn-add-user,.action-btns,.header-actions{display:none!important}
        }
    </style>
</head>
<body>
    <div class="users-container">
        <div class="page-header">
            <h4><i class="fas fa-users-cog"></i>Authorized Users</h4>
            <div class="header-actions">
                <a href="user_registration.php" class="btn-add-user">
                    <i class="fas fa-user-plus"></i>Add New User
                </a>
            </div>
        </div>

        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($_GET['success']) ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($_GET['error']) ?>
            </div>
        <?php endif; ?>

        <div class="search-section">
            <form method="GET" action="<?= htmlspecialchars($_SERVER["PHP_SELF"]) ?>">
                <div class="search-wrapper">
                    <input type="text" name="search" placeholder="Search by ID, name, gender, mobile, or role..."
                           value="<?= htmlspecialchars($search) ?>" autocomplete="off">
                    <button type="submit" class="search-btn">
                        <i class="fas fa-search"></i>Search
                    </button>
                    <?php if(!empty($search)): ?>
                        <button type="button" onclick="window.location.href='userslist.php'" class="cancel-btn">
                            <i class="fas fa-times"></i>Clear
                        </button>
                    <?php endif; ?>
                    <button type="button" onclick="window.print()" class="export-btn">
                        <i class="fas fa-print"></i>Print
                    </button>
                    <button type="button" onclick="exportToExcel()" class="export-btn">
                        <i class="fas fa-file-excel"></i>Excel
                    </button>
                    <div class="user-count">
                        <i class="fas fa-user-check me-1"></i>Active: <?php include '../counts/users_count.php'; ?>
                    </div>
                </div>
            </form>
        </div>

        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th style="width:3%">ID</th>
                        <th style="width:6%">Username</th>
                        <th style="width:6%">First Name</th>
                        <th style="width:6%">Last Name</th>
                        <th style="width:8%">Full Name</th>
                        <th style="width:6%">Email</th>
                        <th style="width:4%">Gender</th>
                        <th style="width:8%">Mobile</th>
                        <th style="width:8%">Role</th>
                        <th style="width:6%">Status</th>
                        <th style="width:8%">Created</th>
                        <th style="width:37%">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="12" style="text-align:center;padding:40px;color:#6c757d">
                                <i class="fas fa-inbox fa-3x mb-3" style="display:block;opacity:.3"></i>
                                <?php if(!empty($search)): ?>
                                    No users found matching "<?= htmlspecialchars($search) ?>"
                                <?php else: ?>
                                    No users found in the system
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($users as $user):
                            $is_active = isset($user['is_active']) ? $user['is_active'] : 1;
                        ?>
                            <tr style="<?= $is_active == 0 ? 'opacity:0.6;background:#f8f9fa' : '' ?>">
                                <td><strong><?= htmlspecialchars($user['user_id']) ?></strong></td>
                                <td><?= htmlspecialchars($user['username']) ?></td>
                                <td><?= htmlspecialchars($user['first_name']) ?></td>
                                <td><?= htmlspecialchars($user['last_name']) ?></td>
                                <td><?= htmlspecialchars($user['full_name']) ?></td>
                                <td><?= htmlspecialchars($user['email']) ?></td>
                                <td>
                                    <?php if($user['gender'] == 'Male'): ?>
                                        <i class="fas fa-mars text-primary"></i>
                                    <?php elseif($user['gender'] == 'Female'): ?>
                                        <i class="fas fa-venus text-danger"></i>
                                    <?php else: ?>
                                        <i class="fas fa-genderless text-secondary"></i>
                                    <?php endif; ?>
                                    <?= htmlspecialchars($user['gender']) ?>
                                </td>
                                <td><?= htmlspecialchars($user['mobile']) ?></td>
                                <td>
                                    <span class="badge" style="background:<?= $user['userrole'] == 'Admin' ? 'var(--danger)' : 'var(--primary)' ?>;color:#fff;padding:4px 8px;border-radius:12px;font-size:.75rem">
                                        <?= htmlspecialchars($user['userrole']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if($is_active == 1): ?>
                                        <span class="badge" style="background:var(--success);color:#fff;padding:4px 8px;border-radius:12px;font-size:.75rem">
                                            <i class="fas fa-check-circle"></i> Active
                                        </span>
                                    <?php else: ?>
                                        <span class="badge" style="background:var(--danger);color:#fff;padding:4px 8px;border-radius:12px;font-size:.75rem">
                                            <i class="fas fa-times-circle"></i> Disabled
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td><?= date('M d, Y', strtotime($user['date_created'])) ?></td>
                                <td>
                                    <div class="action-btns">
                                        <a href="../public/view_user2.php?user_id=<?= $user['user_id'] ?>"
                                           class="btn-sm btn-view" title="View User">
                                            <i class="fas fa-eye"></i>View
                                        </a>
                                        <a href="../public/update_user.php?id=<?= $user['user_id'] ?>"
                                           class="btn-sm btn-update" title="Update User">
                                            <i class="fas fa-edit"></i>Edit
                                        </a>
                                        <a href="../public/reset_user_password.php?id=<?= $user['user_id'] ?>"
                                           onclick="return confirm('Reset password to default (123456)?')"
                                           class="btn-sm btn-reset" title="Reset Password">
                                            <i class="fas fa-key"></i>Reset
                                        </a>
                                        <?php if($is_active == 1): ?>
                                            <a href="../public/toggle_user_status.php?id=<?= $user['user_id'] ?>&action=disable"
                                               onclick="return confirm('Are you sure you want to disable this user?')"
                                               class="btn-sm btn-warning" title="Disable User" style="background:#f39c12;color:#fff">
                                                <i class="fas fa-ban"></i>Disable
                                            </a>
                                        <?php else: ?>
                                            <a href="../public/toggle_user_status.php?id=<?= $user['user_id'] ?>&action=enable"
                                               onclick="return confirm('Are you sure you want to enable this user?')"
                                               class="btn-sm" title="Enable User" style="background:#27ae60;color:#fff">
                                                <i class="fas fa-check"></i>Enable
                                            </a>
                                        <?php endif; ?>
                                        <a href="../public/delete_user.php?id=<?= $user['user_id'] ?>"
                                           onclick="return confirm('Are you sure you want to delete this user?')"
                                           class="btn-sm btn-delete" title="Delete User">
                                            <i class="fas fa-trash-alt"></i>Delete
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if (!empty($users)): ?>
            <div class="mt-3 text-muted">
                <small><i class="fas fa-info-circle me-1"></i>Showing <?= count($users) ?> user(s)</small>
            </div>
        <?php endif; ?>
    </div>

    <script src="../assets/js/bootstrap.bundle.min.js"></script>
    <script>
        function exportToExcel(){
            const table=document.querySelector("table").cloneNode(true);
            const actionCells=table.querySelectorAll("th:last-child,td:last-child");
            actionCells.forEach(cell=>cell.remove());
            const html=table.outerHTML;
            const uri='data:application/vnd.ms-excel,'+encodeURIComponent(html);
            const link=document.createElement("a");
            link.href=uri;
            link.download="users_list_"+new Date().toISOString().split('T')[0]+".xls";
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
    </script>
</body>
</html>