<?php
require __DIR__ . '/../config/auth.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id) {
    header('Location: index.php');
    exit;
}

$stmt = $pdo->prepare(
    'SELECT
        i.id,
        i.fee_title,
        i.amount,
        s.admission_no,
        s.first_name,
        s.last_name,
        COALESCE(SUM(p.amount), 0) AS paid_amount
     FROM fee_invoices i
     JOIN students s ON s.id = i.student_id
     LEFT JOIN fee_payments p ON p.invoice_id = i.id
     WHERE i.id = ?
     GROUP BY i.id, s.id'
);
$stmt->execute([$id]);
$invoice = $stmt->fetch();

if (!$invoice) {
    http_response_code(404);
    exit('Fee invoice nahi mila.');
}

$balance = max(0, (float) $invoice['amount'] - (float) $invoice['paid_amount']);
$error = '';

$data = [
    'amount' => '',
    'payment_date' => date('Y-m-d'),
    'payment_method' => 'Cash',
    'reference' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $data['amount'] = trim($_POST['amount'] ?? '');
    $data['payment_date'] = trim($_POST['payment_date'] ?? '');
    $data['payment_method'] = trim($_POST['payment_method'] ?? '');
    $data['reference'] = trim($_POST['reference'] ?? '');

    $paymentAmount = filter_var($data['amount'], FILTER_VALIDATE_FLOAT);
    $dateObject = DateTime::createFromFormat('Y-m-d', $data['payment_date']);
    $validDate = $dateObject && $dateObject->format('Y-m-d') === $data['payment_date'];
    $allowedMethods = ['Cash', 'Bank Transfer', 'Card', 'Online'];

    if ($paymentAmount === false || $paymentAmount <= 0) {
        $error = 'Payment amount zero se zyada hona chahiye.';
    } elseif (!$validDate) {
        $error = 'Payment date valid nahi hai.';
    } elseif (!in_array($data['payment_method'], $allowedMethods, true)) {
        $error = 'Payment method valid nahi hai.';
    } else {
        try {
            $pdo->beginTransaction();

            // Invoice ko lock karke balance dobara check karein.
            $lockStmt = $pdo->prepare(
                'SELECT amount FROM fee_invoices WHERE id = ? FOR UPDATE'
            );
            $lockStmt->execute([$id]);
            $lockedInvoice = $lockStmt->fetch();

            if (!$lockedInvoice) {
                throw new RuntimeException('Invoice nahi mila.');
            }

            $paidStmt = $pdo->prepare(
                'SELECT COALESCE(SUM(amount), 0)
                 FROM fee_payments
                 WHERE invoice_id = ?'
            );
            $paidStmt->execute([$id]);
            $alreadyPaid = (float) $paidStmt->fetchColumn();

            $currentBalance = (float) $lockedInvoice['amount'] - $alreadyPaid;

            if ($paymentAmount > $currentBalance + 0.001) {
                $pdo->rollBack();
                $error = 'Payment amount outstanding balance se zyada nahi ho sakta.';
            } else {
                $insertStmt = $pdo->prepare(
                    'INSERT INTO fee_payments
                        (invoice_id, amount, payment_date, payment_method, reference, received_by)
                     VALUES (?, ?, ?, ?, ?, ?)'
                );

                $insertStmt->execute([
                    $id,
                    $paymentAmount,
                    $data['payment_date'],
                    $data['payment_method'],
                    $data['reference'] ?: null,
                    $_SESSION['user_id']
                ]);

                $pdo->commit();

                header('Location: index.php');
                exit;
            }
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $error = 'Payment save nahi ho saki. Dobara try karein.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Record Payment — School ERP</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <header class="topbar">
        <a class="logo" href="../dashboard.php">School ERP</a>
        <a class="button button-outline" href="index.php">Fees list</a>
    </header>

    <main class="container form-container">
        <section class="form-card">
            <h1>Record Payment</h1>

            <div class="invoice-info">
                <p><strong>Student:</strong>
                    <?= e($invoice['first_name'] . ' ' . $invoice['last_name']) ?>
                    (<?= e($invoice['admission_no']) ?>)
                </p>
                <p><strong>Fee:</strong> <?= e($invoice['fee_title']) ?></p>
                <p><strong>Total:</strong> ₹<?= number_format((float) $invoice['amount'], 2) ?></p>
                <p><strong>Already paid:</strong> ₹<?= number_format((float) $invoice['paid_amount'], 2) ?></p>
                <p><strong>Balance:</strong> ₹<?= number_format($balance, 2) ?></p>
            </div>

            <?php if ($error): ?>
                <div class="alert"><?= e($error) ?></div>
            <?php endif; ?>

            <?php if ($balance <= 0): ?>
                <div class="success-message">Yeh invoice pehle hi fully paid hai.</div>
            <?php else: ?>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">

                    <div class="form-grid">
                        <div>
                            <label for="amount">Payment Amount (₹) *</label>
                            <input
                                id="amount"
                                type="number"
                                name="amount"
                                min="0.01"
                                max="<?= e(number_format($balance, 2, '.', '')) ?>"
                                step="0.01"
                                required
                                value="<?= e($data['amount']) ?>"
                            >
                        </div>

                        <div>
                            <label for="payment_date">Payment Date *</label>
                            <input
                                id="payment_date"
                                type="date"
                                name="payment_date"
                                required
                                value="<?= e($data['payment_date']) ?>"
                            >
                        </div>

                        <div>
                            <label for="payment_method">Payment Method *</label>
                            <select id="payment_method" name="payment_method">
                                <?php foreach (['Cash', 'Bank Transfer', 'Card', 'Online'] as $method): ?>
                                    <option value="<?= e($method) ?>"
                                        <?= $data['payment_method'] === $method ? 'selected' : '' ?>>
                                        <?= e($method) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label for="reference">Reference / Receipt No.</label>
                            <input
                                id="reference"
                                name="reference"
                                value="<?= e($data['reference']) ?>"
                            >
                        </div>
                    </div>

                    <div class="form-actions">
                        <button class="button button-primary" type="submit">Save Payment</button>
                        <a class="button button-outline" href="index.php">Cancel</a>
                    </div>
                </form>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>