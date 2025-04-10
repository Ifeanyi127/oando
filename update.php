<?php
include "db.php";

if (!isset($_GET['id'])) {
    die("No record ID provided!");
}

$id = intval($_GET['id']);

// Fetch record details
$query = "SELECT * FROM users WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();

if (!$row) {
    die("Record not found!");
}

// Check if the form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $clearing_house_number = $_POST['clearing_house_number'];
    $stockbroking_house = $_POST['stockbroking_house'];

    // Update database
    $updateQuery = "UPDATE users SET clearing_house_number = ?, stockbroking_house = ? WHERE id = ?";
    $updateStmt = $conn->prepare($updateQuery);
    $updateStmt->bind_param("ssi", $clearing_house_number, $stockbroking_house, $id);

    if ($updateStmt->execute()) {
        echo "<p>Data updated successfully! <a href='index.php'>Go Back</a></p>";
    } else {
        echo "<p>Error updating data.</p>";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Update Data</title>
</head>
<body>
    <h2>Update Data</h2>
    <form method="post">
        <label>Clearing House Number:</label>
        <input type="text" name="clearing_house_number" value="<?php echo htmlspecialchars($row['clearing_house_number']); ?>" required><br>

        <label>Stockbroking House:</label>
        <input type="text" name="stockbroking_house" value="<?php echo htmlspecialchars($row['stockbroking_house']); ?>" required><br>

        <button type="submit">Apply Update</button>
    </form>
</body>
</html>
