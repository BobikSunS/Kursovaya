<?php require 'db.php'; 
if (!isset($_SESSION['user'])) header('Location: index.php');
$user = $_SESSION['user'];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Профиль</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body class="d-flex flex-column min-vh-100">
<nav class="navbar navbar-dark bg-primary shadow-lg">
    <div class="container-fluid">
        <a class="navbar-brand">Профиль пользователя</a>
        <div>
            <a href="calculator.php" class="btn btn-light me-2">Калькулятор</a>
            <a href="order_form.php" class="btn btn-success me-2">Оформить заказ</a>
            <a href="history.php" class="btn btn-warning me-2">История</a>
            <?php if($user['role']==='admin'): ?><a href="admin/index.php" class="btn btn-danger me-2">Админка</a><?php endif; ?>
            <a href="logout.php" class="btn btn-outline-light">Выйти</a>
        </div>
    </div>
</nav>
<div class="container mt-5 flex-grow-1 main-content">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card card-shadow">
                <div class="card-body text-center">
                    <h2>Привет, <?= htmlspecialchars($user['name'] ?: $user['login']) ?>!</h2>
                    <p class="lead">Вы вошли как: <strong><?= $user['role']==='admin'?'Администратор':'Пользователь' ?></strong></p>
                    <hr>
                    <div class="d-grid gap-3">
                        <a href="calculator.php" class="btn btn-primary btn-lg">Рассчитать доставку</a>
                        <a href="order_form.php" class="btn btn-success btn-lg">Оформить заказ</a>
                        <a href="history.php" class="btn btn-info btn-lg">История заказов</a>
                        <!-- New tracking functionality -->
                        <div class="card mt-3">
                            <div class="card-body">
                                <h5 class="card-title">Отследить заказ</h5>
                                <form method="GET" action="track.php" class="mb-3">
                                    <div class="input-group">
                                        <input type="text" name="track" class="form-control" placeholder="Введите трек-номер" required>
                                        <button type="submit" class="btn btn-primary">Отследить</button>
                                    </div>
                                </form>
                                <p class="text-muted small">Введите 12-значный трек-номер для отслеживания статуса доставки</p>
                            </div>
                        </div>
                    </div>
                    <div class="mt-4">
                        <button onclick="toggleTheme()" class="btn btn-outline-warning btn-lg">
                            Тёмная тема
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Footer -->
<footer class="footer mt-auto py-3" style="background-color: rgba(0,0,0,0.05);">
    <div class="container text-center text-muted">
        <p class="mb-1" style="opacity: 0.5; color: #999 !important;">&copy; 2025 Служба доставки. Все права защищены.</p>
        <p class="mb-1" style="opacity: 0.5; color: #999 !important;">Контактный телефон: +375-25-005-50-50</p>
        <p class="mb-0" style="opacity: 0.5; color: #999 !important;">Email: freedeliverya@gmail.com</p>
    </div>
</footer>

<script>
function toggleTheme() {
    document.body.classList.toggle('dark');
    // Save theme preference in localStorage
    const isDark = document.body.classList.contains('dark');
    localStorage.setItem('theme', isDark ? 'dark' : 'light');
    
    // Apply theme to all iframes and child elements
    applyThemeToPage(isDark ? 'dark' : 'light');
}

function applyThemeToPage(theme) {
    // This function ensures theme consistency across the site
    if (theme === 'dark') {
        document.body.classList.add('dark');
    } else {
        document.body.classList.remove('dark');
    }
}

// Apply saved theme on page load
document.addEventListener('DOMContentLoaded', function() {
    const savedTheme = localStorage.getItem('theme');
    if (savedTheme === 'dark') {
        document.body.classList.add('dark');
    }
    
    // Set up a global theme listener for cross-page consistency
    window.addEventListener('storage', function(e) {
        if (e.key === 'theme') {
            if (e.newValue === 'dark') {
                document.body.classList.add('dark');
            } else {
                document.body.classList.remove('dark');
            }
        }
    });
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>