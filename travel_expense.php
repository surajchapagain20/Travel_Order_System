<?php
// Database setup - MySQL (localhost)
$host = 'localhost';
$dbname = 'hr';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Load record for editing
$editData = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM travel_expenses WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $editData = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Handle form submission
$message = '';
$formData = $editData ?? [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $stmt = $pdo->prepare("INSERT INTO travel_expenses
            (name, position, office, purpose, from_date, to_date, vehicle, distance, fare,
             airport, road_tax, daily_rate, days, hotel, other_exp, advance, signature_date)
            VALUES
            (:name, :position, :office, :purpose, :from_date, :to_date, :vehicle, :distance, :fare,
             :airport, :road_tax, :daily_rate, :days, :hotel, :other_exp, :advance, :signature_date)");
        
        $stmt->execute([
            ':name' => $_POST['name'] ?? '',
            ':position' => $_POST['position'] ?? '',
            ':office' => $_POST['office'] ?? '',
            ':purpose' => $_POST['purpose'] ?? '',
            ':from_date' => $_POST['from_date'] ?? null,
            ':to_date' => $_POST['to_date'] ?? null,
            ':vehicle' => $_POST['vehicle'] ?? '',
            ':distance' => floatval($_POST['distance'] ?? 0),
            ':fare' => floatval($_POST['fare'] ?? 0),
            ':airport' => floatval($_POST['airport'] ?? 0),
            ':road_tax' => floatval($_POST['road_tax'] ?? 0),
            ':daily_rate' => floatval($_POST['daily_rate'] ?? 0),
            ':days' => intval($_POST['days'] ?? 0),
            ':hotel' => floatval($_POST['hotel'] ?? 0),
            ':other_exp' => floatval($_POST['other_exp'] ?? 0),
            ':advance' => floatval($_POST['advance'] ?? 0),
            ':signature_date' => $_POST['signature_date'] ?? null
        ]);
        
        $lastId = $pdo->lastInsertId();
        $message = "✅ Expense record saved successfully! ID: " . $lastId;

        // Decide whether to keep data or clear for new entry
        if (isset($_POST['save_and_new'])) {
            $formData = []; // Clear for new entry
        } else {
            $formData = $_POST; // Keep data in form
        }

    } catch (Exception $e) {
        $message = "❌ Error: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="ne">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>भत्ता तथा खर्च विवरण - Nepal Life Insurance</title>
    <style>
        body {
            font-family: 'Arial', sans-serif;
            margin: 20px;
            background-color: #f4f4f4;
        }
        .form-container {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border: 2px solid #000;
        }
        h1, h2 {
            text-align: center;
            color: #003087;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
        }
        th, td {
            border: 1px solid #000;
            padding: 8px;
            text-align: left;
        }
        .header {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 20px;
            margin-bottom: 20px;
        }
        .logo {
            width: 80px;
        }
        input[type="text"], input[type="number"], input[type="date"] {
            width: 100%;
            padding: 5px;
            border: 1px solid #ccc;
        }
        .totals {
            font-weight: bold;
        }
        .signature {
            margin-top: 40px;
        }
        .nepal-life {
            color: #003087;
            font-weight: bold;
        }
        .message {
            padding: 10px;
            margin: 10px 0;
            border-radius: 5px;
        }
        .success { background: #d4edda; color: #155724; }
        .error { background: #f8d7da; color: #721c24; }
    </style>
	<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
<?php include 'navbar.php'; ?>
    <div class="form-container">
        <div class="header">
            <img src="images/logo.png" alt="Nepal Life Logo" width="450" height="80" >
            <div>
                <h1>भत्ता तथा खर्च विवरण</h1>
            </div>
        </div>

        <?php if (isset($success)): ?>
            <div class="message success">✅ Expense record saved successfully! ID: <?= $pdo->lastInsertId() ?></div>
        <?php elseif (isset($error)): ?>
            <div class="message error">❌ Error: <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" id="expenseForm">
            <table>
                <tr>
                    <td>नाम :</td>
                    <td><input type="text" name="name" required></td>
                    <td>भ्रमण मिति :</td>
                    <td><input type="date" name="from_date" required> देखि <input type="date" name="to_date" required> सम्म</td>
                </tr>
                <tr>
                    <td>पद :</td>
                    <td><input type="text" name="position" required></td>
                    <td>भ्रमण उद्देश्य :</td>
                    <td><input type="text" name="purpose" required></td>
                </tr>
                <tr>
                    <td>कार्यालय :</td>
                    <td colspan="3"><input type="text" name="office" required></td>
                </tr>
            </table>

            <h3>१. भ्रमण विवरण (देखी -सम्म)</h3>
            <table>
                <thead>
                    <tr>
                        <th>भ्रमण साधन</th>
                        <th>जम्मा दूरी कि.मि.</th>
                        <th>भाडा/इन्धन (रू.)</th>
                        <th>कैफियत</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><input type="text" name="vehicle"></td>
                        <td><input type="number" name="distance" step="0.01" oninput="calculateTotals()"></td>
                        <td><input type="number" name="fare" step="0.01" oninput="calculateTotals()" value="0"></td>
                        <td><input type="text" name="remarks"></td>
                    </tr>
                    <tr>
                        <td colspan="3">२. एयरपोर्ट ट्याक्स तथा ट्याक्सी खर्च (हवाई यात्रामा)</td>
                        <td><input type="number" name="airport" step="0.01" oninput="calculateTotals()" value="0"></td>
                    </tr>
                    <tr>
                        <td colspan="3">३. अन्य खर्च : सडक कर</td>
                        <td><input type="number" name="road_tax" step="0.01" oninput="calculateTotals()" value="0"></td>
                    </tr>
                    <tr class="totals">
                        <td colspan="3">(क) जम्मा भाडा/इन्धन खर्च</td>
                        <td id="total_fare">0.00</td>
                    </tr>
                </tbody>
            </table>

            <table>
                <tr>
                    <td>१. दैनिक भ्रमण भत्ता रू. <input type="number" name="daily_rate" step="0.01" value="0" oninput="calculateTotals()"> प्रति दिन)</td>
                    <td><input type="number" name="days" value="0" oninput="calculateTotals()"></td>
                    <td class="totals">(ख) जम्मा दैनिक भत्ता</td>
                    <td id="total_daily">0.00</td>
                </tr>
                <tr>
                    <td colspan="2">२. होटेल खर्च (खाना तथा बास)</td>
                    <td><input type="number" name="hotel" step="0.01" oninput="calculateTotals()" value="0"></td>
                    <td></td>
                </tr>
                <tr>
                    <td colspan="2">३. अन्य खर्च :</td>
                    <td><input type="number" name="other_exp" step="0.01" oninput="calculateTotals()" value="0"></td>
                    <td></td>
                </tr>
                <tr class="totals">
                    <td colspan="2">(ग) जम्मा होटेल खर्च</td>
                    <td id="total_hotel">0.00</td>
                    <td></td>
                </tr>
            </table>

            <table>
                <tr>
                    <td>(घ) भ्रमण प्रयोजनको लागि लिएको पेशकी / समायोजन</td>
                    <td><input type="number" name="advance" step="0.01" oninput="calculateTotals()" value="0"></td>
                </tr>
                <tr class="totals">
                    <td>(ङ) भुक्तानी पाउनु पर्ने/ (तिर्नुपर्ने रकम) [क+ख+ग-घ]</td>
                    <td id="net_amount">0.00</td>
                </tr>
            </table>

            <div class="signature">
                
                मिति: <input type="date" name="signature_date">
            </div>

            <h3>अफिस प्रयोजनको लागि मात्रः</h3>
            <table>
                <tr>
                    <td>१) जम्मा भाडा/इन्धन खर्च स्वीकृत (कोड #4642201)</td>
                    <td>रू. <input type="number" name="approved_fare" step="0.01"></td>
                </tr>
                <tr>
                    <td>२) जम्मा दैनिक भत्ता (कोड #4642301)</td>
                    <td>रू. <input type="number" name="approved_daily" step="0.01"></td>
                </tr>
                <tr>
                    <td>३) जम्मा दैनिक होटेल खर्च स्वीकृत (कोड #4642401)</td>
                    <td>रू. <input type="number" name="approved_hotel" step="0.01"></td>
                </tr>
                <tr>
                    <td>४) समायोजन रकम</td>
                    <td>रू. <input type="number" name="adjustment" step="0.01"></td>
                </tr>
                <tr>
                    <td>५) भुक्तानी पाउनु पर्ने वा (तिर्नुपर्ने रकम)</td>
                    <td>रू. <input type="number" name="final_amount" step="0.01"></td>
                </tr>
            </table>

            <div style="text-align: center; margin-top: 30px;">
                <button type="submit" style="padding: 12px 30px; font-size: 16px; background: #003087; color: white; border: none; cursor: pointer;">Submit</button>
                <button
				  type="button"
				  onclick="window.location.href='View_ExpenseRecords.php';"
				  style="padding: 12px 30px; font-size: 16px; margin-left: 10px;"
				>
				  Cancel
				</button>
            </div>
        </form>
    </div>

    <script>
        function calculateTotals() {
            // Fare total
            const fare = parseFloat(document.querySelector('input[name="fare"]').value) || 0;
            const airport = parseFloat(document.querySelector('input[name="airport"]').value) || 0;
            const roadTax = parseFloat(document.querySelector('input[name="road_tax"]').value) || 0;
            const totalFare = fare + airport + roadTax;
            document.getElementById('total_fare').textContent = totalFare.toFixed(2);

            // Daily allowance
            const dailyRate = parseFloat(document.querySelector('input[name="daily_rate"]').value) || 0;
            const days = parseFloat(document.querySelector('input[name="days"]').value) || 0;
            const totalDaily = dailyRate * days;
            document.getElementById('total_daily').textContent = totalDaily.toFixed(2);

            // Hotel total
            const hotel = parseFloat(document.querySelector('input[name="hotel"]').value) || 0;
            const otherExp = parseFloat(document.querySelector('input[name="other_exp"]').value) || 0;
            const totalHotel = hotel + otherExp;
            document.getElementById('total_hotel').textContent = totalHotel.toFixed(2);

            // Net amount
            const advance = parseFloat(document.querySelector('input[name="advance"]').value) || 0;
            const net = totalFare + totalDaily + totalHotel - advance;
            document.getElementById('net_amount').textContent = net.toFixed(2);
        }

        // Initialize calculations
        window.onload = calculateTotals;
        
        // Auto calculate on input
        document.querySelectorAll('input').forEach(input => {
            input.addEventListener('input', calculateTotals);
        });
    </script>
</body>
</html>