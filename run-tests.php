<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $output = shell_exec('npm test 2>&1');
    echo '<pre>' . htmlspecialchars($output) . '</pre>';
} else {
    echo '<form method="post"><button type="submit">Run Tests</button></form>';
}
