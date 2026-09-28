<?php
require_once __DIR__ . '/session_auth.php';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    if (!validLoginCsrf()) {
        http_response_code(403);
        $error = 'Please reload this page and try again.';
    } elseif (!is_string($username) || !is_string($password) || !isset($demoUsers[$username]) || !password_verify($password, $demoUsers[$username])) {
        http_response_code(400);
        $error = 'Incorrect username or password.';
    } else {
        session_regenerate_id(true);
        $_SESSION = ['user' => $username, 'csrf' => bin2hex(random_bytes(32))];
        session_write_close();
        header('Location: index.php', true, 303);
        exit;
    }
} elseif (isset($_SESSION['user'])) {
    session_write_close();
    header('Location: index.php', true, 303);
    exit;
}
unset($password, $demoUsers);
session_write_close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Sign in — Felix Roster</title>
<style>
  * { box-sizing: border-box; }
  body { margin: 0; background: #F7F4EE; color: #1E2422; font-family: system-ui, sans-serif; }
  main { max-width: 400px; margin: 10vh auto; padding: 24px; }
  h1 { color: #1B4B47; }
  form { display: grid; gap: 10px; }
  input, button { padding: 12px; border: 1px solid #CFC8B5; border-radius: 8px; font: inherit; }
  button { margin-top: 12px; background: #1B4B47; color: white; font-weight: 700; cursor: pointer; }
  .error { color: #8B1E1E; }
</style>
</head>
<body>
<main>
  <h1>Sign in</h1>
  <p>You are signed out. Enter your credentials to continue.</p>
  <?php if ($error !== ''): ?><p class="error" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
  <form method="post" action="login.php">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
    <label for="username">Username</label>
    <input id="username" name="username" autocomplete="username" required autofocus>
    <label for="password">Password</label>
    <input id="password" name="password" type="password" autocomplete="current-password" required>
    <button type="submit">Sign in</button>
  </form>
</main>
</body>
</html>
