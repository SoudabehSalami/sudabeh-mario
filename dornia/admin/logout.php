<?php
require __DIR__ . '/_lib.php';

unset($_SESSION['admin'], $_SESSION['admin_ip']);
session_regenerate_id(true);
redirect('admin/login.php');
