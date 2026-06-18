<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';

app_logout_user();
app_set_flash('success', 'You have been signed out.');
app_redirect('/index.php');
