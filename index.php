<?php require "helpers.php"; ?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MQTT hitEcosystem</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <link rel="icon" type="image/x-icon" href="./assets/images/logo.jpeg">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fontsource/poppins@5.2.7/index.min.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <!-- Sweet Alerts 2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.26.22/dist/sweetalert2.min.css">
    <!-- AG Grid -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/ag-grid-community/styles/ag-theme-quartz.css">

    <link rel="stylesheet" href="./assets/css/styles.css">
</head>

<body class="bg-light">

    <div class="container-xl mt-5">
        <h1>Radares</h1>

        <div id="radars-wrappers" class="row g-3 row-cols-1 row-cols-md-2 row-cols-lg-4 mt-3"></div>
    </div>

    <?php modal('sleep-report'); ?>
    <?php modal('radar'); ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI"
        crossorigin="anonymous"></script>
    <script src="https://unpkg.com/konva@10.0.0-1/konva.min.js"></script>
    <script src="https://unpkg.com/mqtt/dist/mqtt.min.js"></script>
    <!-- AM Charts 5 -->
    <script src="https://cdn.amcharts.com/lib/5/index.js"></script>
    <script src="https://cdn.amcharts.com/lib/5/percent.js"></script>
    <script src="https://cdn.amcharts.com/lib/5/xy.js"></script>
    <script src="https://cdn.amcharts.com/lib/5/themes/Animated.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.26.22/dist/sweetalert2.all.min.js"></script>
    <!-- AG Grid -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/ag-grid-enterprise/34.2.0/ag-grid-enterprise.min.js"
        integrity="sha512-aE+oj9Z0B9knKrq4Torrb8AlXMuaZNXJ9LvxXfv5stq5xbwVGuVgopQE5Ql10nQMNiFMwkSyvHFQQKkwy1xh/g=="
        crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script type="module" src="./assets/js/pages/home/main.js"></script>
</body>

</html>