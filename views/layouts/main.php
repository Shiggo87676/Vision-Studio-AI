<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#11111b">
    <meta name="description" content="Bring your creative ideas to life with Vision Studio AI.">
    <title><?= e((string) $pageTitle) ?></title>
    <link rel="stylesheet" href="/assets/css/app.css">
    <script src="/assets/js/app.js" defer></script>
</head>
<body<?= !empty($dashboardLayout) ? ' class="dashboard-body"' : '' ?>>
    <?php if (!empty($dashboardLayout)): ?>
        <?php require BASE_PATH . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'dashboard-sidebar.php'; ?>
    <?php else: ?>
        <?php require BASE_PATH . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'navigation.php'; ?>
    <?php endif; ?>
    <?php require $contentFile; ?>
    <?php if (empty($dashboardLayout)): ?>
        <?php require BASE_PATH . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'footer.php'; ?>
    <?php endif; ?>
</body>
</html>
