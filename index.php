<?php
session_start();
include "db.php";

// Generate CSRF token if not set
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle update form submission
if (isset($_POST['update']) && $_POST['csrf_token'] === $_SESSION['csrf_token']) {
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    $clearing_house_number = filter_var(trim($_POST['clearing_house_number'] ?? ''), FILTER_SANITIZE_STRING);
    $stockbroking_house = filter_var(trim($_POST['stockbroking_house'] ?? ''), FILTER_SANITIZE_STRING);
    $email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL) ?: '';
    $phone_number = filter_var(trim($_POST['phone_number'] ?? ''), FILTER_SANITIZE_STRING);

    // Validate inputs
    if (!$id) {
        die("<script>alert('Invalid ID provided!'); history.back();</script>");
    }
    if (!empty($phone_number) && !preg_match("/^[0-9]{10,15}$/", $phone_number)) {
        die("<script>alert('Phone number must be 10-15 digits!'); history.back();</script>");
    }
    if (empty($stockbroking_house) || strlen($stockbroking_house) < 3) {
        die("<script>alert('Stockbroking House must be at least 3 characters!'); history.back();</script>");
    }

    try {
        $updateQuery = "UPDATE users1 SET clearing_house_number = ?, stockbroking_house = ?, email = ?, phone_number = ? WHERE id = ?";
        $stmt = $conn->prepare($updateQuery);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        $stmt->bind_param("ssssi", $clearing_house_number, $stockbroking_house, $email, $phone_number, $id);
        if ($stmt->execute()) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); // Regenerate CSRF token
            echo "<script>alert('Record updated successfully, including Stockbroking House: " . htmlspecialchars($stockbroking_house) . "!'); window.location.href = window.location.href;</script>";
        } else {
            throw new Exception("Execute failed: " . $stmt->error);
        }
        $stmt->close();
    } catch (Exception $e) {
        error_log("Update Error: " . $e->getMessage());
        echo "<script>alert('Error updating record. Please try again later.'); history.back();</script>";
    }
}

// Handle search form submission
if (isset($_POST['search']) && $_POST['csrf_token'] === $_SESSION['csrf_token']) {
    $search = filter_var(trim($_POST['search']), FILTER_SANITIZE_STRING);
    try {
        $query = "SELECT id, serial_no, registrars_account_number, names, address, clearing_house_number, stockbroking_house, email, phone_number 
                  FROM users1 
                  WHERE names LIKE ? OR registrars_account_number LIKE ? 
                  LIMIT 50";
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        $searchTerm = "%$search%";
        $stmt->bind_param("ss", $searchTerm, $searchTerm);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();
    } catch (Exception $e) {
        error_log("Search Error: " . $e->getMessage());
        echo "<script>alert('Error searching records. Please try again later.'); history.back();</script>";
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Search and update shareholder data securely.">
    <title>Shareholders Data Portal</title>
    <style>
        :root {
            --primary: #1e88e5;
            --secondary: #43a047;
            --hover-secondary: #388e3c;
            --background: #f5f7fa;
            --card-bg: #ffffff;
            --text: #333333;
            --muted: #777777;
            --border: #e0e0e0;
            --shadow: rgba(0, 0, 0, 0.05);
        }
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background-color: var(--background);
            color: var(--text);
            line-height: 1.6;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .container {
            max-width: 1280px;
            width: 90%;
            margin: 0 auto;
            flex: 1;
            padding: 20px 0;
        }
        .header {
            background-color: var(--primary);
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px var(--shadow);
        }
        .header img {
            height: 40px;
            transition: transform 0.3s ease;
        }
        .header img:hover {
            transform: scale(1.05);
        }
        h2 {
            text-align: center;
            font-size: 2rem;
            margin: 30px 0;
            color: var(--text);
            font-weight: 600;
        }
        .search-form {
            background: var(--card-bg);
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 15px var(--shadow);
            margin-bottom: 30px;
        }
        .search-form input[type="text"] {
            width: 70%;
            padding: 12px 16px;
            border: 1px solid var(--border);
            border-radius: 6px;
            font-size: 1rem;
            outline: none;
            transition: border-color 0.3s ease;
        }
        .search-form input[type="text"]:focus {
            border-color: var(--primary);
            box-shadow: 0 0 5px rgba(30, 136, 229, 0.3);
        }
        .search-form button {
            padding: 12px 30px;
            background-color: var(--secondary);
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 1rem;
            cursor: pointer;
            margin-left: 10px;
            transition: background-color 0.3s ease, transform 0.2s ease;
        }
        .search-form button:hover:not(:disabled) {
            background-color: var(--hover-secondary);
            transform: translateY(-2px);
        }
        .search-form button:disabled {
            background-color: #cccccc;
            cursor: not-allowed;
        }
        .search-form button.loading::after {
            content: " Loading...";
            font-size: 0.9rem;
        }
        .data-table {
            background: var(--card-bg);
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 15px var(--shadow);
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 14px 16px;
            text-align: left;
            border-bottom: 1px solid var(--border);
        }
        th {
            background-color: #fafafa;
            font-weight: 600;
            color: var(--text);
            text-transform: uppercase;
            font-size: 0.9rem;
        }
        td {
            font-size: 0.95rem;
        }
        tr:hover {
            background-color: #f9f9f9;
        }
        td input[type="text"], td input[type="email"] {
            width: 100%;
            padding: 8px;
            border: 1px solid var(--border);
            border-radius: 4px;
            font-size: 0.9rem;
            outline: none;
        }
        td input[type="text"]:focus, td input[type="email"]:focus {
            border-color: var(--primary);
        }
        .action-btn {
            background-color: var(--primary);
            color: white;
            padding: 8px 16px;
            border: none;
            min-width: 120px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.9rem;
            transition: background-color 0.3s ease, transform 0.2s ease;
        }
        .action-btn:hover:not(:disabled) {
            background-color: #1565c0;
            transform: translateY(-2px);
        }
        .action-btn.loading::after {
            content: " Loading...";
            font-size: 0.8rem;
        }
        .no-results {
            text-align: center;
            padding: 20px;
            color: var(--muted);
            font-size: 1.1rem;
        }
        .note {
            background-color: #fff8e1;
            padding: 15px;
            text-align: center;
            font-weight: 500;
            margin: 20px 0;
            border-radius: 6px;
            box-shadow: 0 2px 10px var(--shadow);
        }
        .note1 {
            text-align: center;
            color: var(--muted);
            margin: 20px 0;
            font-size: 0.95rem;
        }
        .footer {
            background-color: var(--primary);
            color: white;
            text-align: center;
            padding: 20px;
            font-size: 0.9rem;
            margin-top: auto;
            box-shadow: 0 -2px 10px var(--shadow);
        }
        @media (max-width: 768px) {
            .search-form input[type="text"] {
                width: 65%;
            }
            .search-form button {
                padding: 12px 20px;
            }
            th, td {
                font-size: 0.85rem;
            }
        }
        @media (max-width: 480px) {
            .search-form {
                padding: 15px;
            }
            .search-form input[type="text"] {
                width: 100%;
                margin-bottom: 10px;
            }
            .search-form button {
                width: 100%;
                margin-left: 0;
            }
            .data-table {
                overflow-x: auto;
            }
            table {
                min-width: 600px;
            }
            th, td {
                padding: 10px;
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <img src="oando.png" alt="Oando Logo">
        <img src="first registrars logo.jpeg" alt="First Registrars Logo">
    </header>
    <main class="container">
        <h2>Shareholders Data Portal</h2>
        <form method="post" class="search-form" onsubmit="this.querySelector('button').classList.add('loading'); this.querySelector('button').disabled = true;">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <input type="text" name="search" placeholder="Enter Name or Registrar's Account Number" required aria-label="Search shareholders">
            <button type="submit">Search</button>
        </form>

        <?php if (isset($result) && $result->num_rows > 0) { ?>
            <div class="data-table">
                <form method="post" onsubmit="this.querySelector('.action-btn').classList.add('loading'); this.querySelector('.action-btn').disabled = true;">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <table role="grid">
                        <thead>
                            <tr>
                                <th scope="col">S/No</th>
                                <th scope="col">Registrar's Account Number</th>
                                <th scope="col">Names</th>
                                <th scope="col">Address</th>
                                <th scope="col">Clearing House Number</th>
                                <th scope="col">Stockbroking House</th>
                                <th scope="col">Email</th>
                                <th scope="col">Phone Number</th>
                                <th scope="col">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $result->fetch_assoc()) { ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['serial_no']); ?></td>
                                    <td><?php echo htmlspecialchars($row['registrars_account_number']); ?></td>
                                    <td><?php echo htmlspecialchars($row['names']); ?></td>
                                    <td><?php echo htmlspecialchars($row['address']); ?></td>
                                    <td>
                                        <input type="text" name="clearing_house_number" value="<?php echo htmlspecialchars($row['clearing_house_number']); ?>" aria-label="Clearing House Number">
                                    </td>
                                    <td>
                                        <input type="text" name="stockbroking_house" value="<?php echo htmlspecialchars($row['stockbroking_house']); ?>" aria-label="Stockbroking House">
                                    </td>
                                    <td>
                                        <input type="email" name="email" value="<?php echo htmlspecialchars($row['email']); ?>" aria-label="Email">
                                    </td>
                                    <td>
                                        <input type="text" name="phone_number" value="<?php echo htmlspecialchars($row['phone_number']); ?>" aria-label="Phone Number">
                                    </td>
                                    <td>
                                        <input type="hidden" name="id" value="<?php echo htmlspecialchars($row['id']); ?>">
                                        <button type="submit" name="update" class="action-btn" aria-label="Update record">Apply Update</button>
                                    </td>
                                </tr>
                            <?php } $result->free(); ?>
                        </tbody>
                    </table>
                </form>
            </div>
        <?php } else { ?>
            <p class="no-results">No results found.</p>
        <?php } ?>
    </main>
    <section class="note">
        Please note that specific Units of Oando shares have been allotted to you.
    </section>
    <section class="note1">
        <p>Important Notice: Please ensure all information is accurate before submitting updates.</p>
    </section>
    <footer class="footer">
        <p>© 2025 First Registrars Investors Services Limited. All rights reserved.</p>
    </footer>
</body>
</html>