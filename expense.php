<?php
  // Database connection
  $conn = new mysqli("localhost", "root", "", "expense_db");
  if ($conn->connect_error) {
      die("Connection failed: " . $conn->connect_error);
  }
  $edit_mode = false;
  $edit_id = 0;
  $edit_category = '';
  $edit_date = '';
  $edit_description = '';
  $edit_amount = '';

  if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
      $edit_id = $_GET['edit'];
      $stmt = $conn->prepare("SELECT * FROM transactions WHERE Transaction_id = ?");
      $stmt->bind_param("i", $edit_id);
      $stmt->execute();
      $result = $stmt->get_result();
      if ($result->num_rows > 0) {
          $edit_mode = true;
          $transaction = $result->fetch_assoc();
          $edit_category = $transaction['Category'];
          $edit_date = $transaction['Transaction_date'];
          $edit_description = $transaction['Description'];
          $edit_amount = $transaction['Amount'];
      }
      $stmt->close();
  }
  // add transaction or edit transaction
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_transaction'])) {
    $category = $_POST['category'];
    $date = $_POST['date'];
    $description = $_POST['description'];
    $amount = $_POST['amount'];
    $edit_id = isset($_POST['edit_id']) ? (int)$_POST['edit_id'] : 0;

    if ($edit_id > 0) {
        // Update transaction
        $stmt = $conn->prepare("UPDATE transactions SET Category=?, Transaction_date=?, Description=?, Amount=? WHERE Transaction_id=?");
        $stmt->bind_param("sssdi", $category, $date, $description, $amount, $edit_id);
        $stmt->execute();
        $stmt->close();
    } else {
        // add new transaction
        $stmt = $conn->prepare("INSERT INTO transactions (Category, Transaction_date, Description, Amount) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("sssd", $category, $date, $description, $amount);
        $stmt->execute();
        $stmt->close();
    }
    header("Location: expense.php");
    exit();
}

  // delete transaction
  if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
      $id = $_GET['delete'];
      $del_stmt = $conn->prepare("DELETE FROM transactions WHERE Transaction_id = ?");
      $del_stmt->bind_param("i", $id);
      $del_stmt->execute();
      $del_stmt->close();
      header("Location: expense.php");
      exit();
  }

  $income_res = $conn->query("SELECT SUM(Amount) AS total FROM transactions WHERE Category IN ('Salary', 'Income(other)')");
  $expense_res = $conn->query("SELECT SUM(Amount) AS total FROM transactions WHERE Category NOT IN ('Salary', 'Income(other)')");

  $total_income = $income_res->fetch_assoc()['total'] ?? 0;
  $total_expense = $expense_res->fetch_assoc()['total'] ?? 0;
  $balance = $total_income - $total_expense;

  $history = $conn->query("SELECT * FROM transactions ORDER BY Transaction_date DESC");

  $labels = ['Income', 'Expense'];
  $amounts = [$total_income, $total_expense];
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smart Expense Manager</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <link rel="stylesheet" href="ExpenseManagerStyles.css">
    
</head>
<body>
    <div class="dashboard">
        <div class="header">
          <h1>Smart Expense Manager</h1>
        </div>

        <div class="balance-section">
          <h3>Balance: Rs. <?php echo number_format($balance, 2); ?></h3>
        </div>

        <div class="left-column-container">
          <div class="income-expense">
            <label id="income">Income: Rs. <?php echo number_format($total_income, 2); ?></label>
            <label id="expense">Expense: Rs. <?php echo number_format($total_expense, 2); ?></label>
          </div>
        
          <div class="transaction">
            <h4><?php echo $edit_mode ? 'Edit Transaction' : 'New Transaction'; ?></h4>
            <form action="expense.php" method="POST">
              <?php if ($edit_mode): ?>
                <input type="hidden" name="edit_id" value="<?php echo $edit_id; ?>">
              <?php endif; ?>
              <select name="category" required>
                <option value="Food" <?php echo ($edit_category == 'Food') ? 'selected' : ''; ?>>Food</option>
                <option value="Bills" <?php echo ($edit_category == 'Bills') ? 'selected' : ''; ?>>Bills</option>
                <option value="Transport" <?php echo ($edit_category == 'Transport') ? 'selected' : ''; ?>>Transport</option>
                <option value="Groceries" <?php echo ($edit_category == 'Groceries') ? 'selected' : ''; ?>>Groceries</option>
                <option value="Salary" <?php echo ($edit_category == 'Salary') ? 'selected' : ''; ?>>Salary</option>
                <option value="Income(other)" <?php echo ($edit_category == 'Income(other)') ? 'selected' : ''; ?>>Income(other)</option>
                <option value="Expense(other)" <?php echo ($edit_category == 'Expense(other)') ? 'selected' : ''; ?>>Expense(other)</option>
              </select>
              <input type="date" name="date" value="<?php echo htmlspecialchars($edit_date); ?>" required>
              <input type="text" name="description" value="<?php echo htmlspecialchars($edit_description); ?>" placeholder="Description">
              <input type="number" step="0.01" name="amount" value="<?php echo htmlspecialchars($edit_amount); ?>" placeholder="Amount(Rs.)" required>
              <button type="submit" name="add_transaction" id="submit-button"><?php echo $edit_mode ? 'Update Transaction' : 'Add Transaction'; ?></button>              
              <?php if ($edit_mode): ?>
                <a href="expense.php" style="margin-left: 10px;">Cancel</a>
              <?php endif; ?>
            </form>            
          </div>
        </div>

        <div class="graph">
          <h4>Expense Breakdown</h4>
          <canvas id="expensePieChart"></canvas>
        </div> 

        <div class="history">
          <h3>Transaction History</h3>
          <table id="history-table">
            <thead>
              <tr>
                <th>Category</th>
                <th>Amount</th>
                <th>Date</th>
              </tr>
            </thead>
            <tbody id="table-body">
              <?php if ($history->num_rows > 0): ?>
                <?php while($row = $history->fetch_assoc()): ?>
                <tr>
                  <td><?php echo htmlspecialchars($row['Category']); ?></td>
                  <td>Rs. <?php echo number_format($row['Amount'], 2); ?></td>
                  <td><?php echo $row['Transaction_date']; ?></td>
                  <td>
                    <a href="expense.php?edit=<?php echo $row['Transaction_id']; ?>" class="btn-action" title="Edit">✏️</a>
                    <a href="expense.php?delete=<?php echo $row['Transaction_id']; ?>" class="btn-action" title="Delete" onclick="return confirm('Are you sure?')">🗑️</a>
                </tr>
                <?php endwhile; ?>
              <?php else: ?>
                <tr><td colspan="4" style="text-align:center;">No transactions found.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
    </div>   

    <script>
      document.addEventListener('DOMContentLoaded', function () {
        const ctx = document.getElementById('expensePieChart').getContext('2d');
        
        const labels = <?php echo json_encode($labels); ?>;
        const dataValues = <?php echo json_encode($amounts); ?>;

        if (dataValues[0] > 0 || dataValues[1] > 0) {
            new Chart(ctx, {
                type: 'pie',
                data: {
                    labels: labels,
                    datasets: [{
                        data: dataValues,
                        backgroundColor: ['#4bc0c0', '#ff6384'],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true, 
                    maintainAspectRatio: false, 
                    plugins: {
                        legend: { position: 'bottom' }
                    }
                }
            });
        } else {
            ctx.font = "14px Inter";
            ctx.textAlign = "center";
            ctx.fillText("No data available", ctx.canvas.width / 2, ctx.canvas.height / 2);
        }
      });
    </script>
</body>
</html>