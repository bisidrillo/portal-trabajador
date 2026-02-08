<?php
require __DIR__ . '/auth.php';

configure_session();
session_destroy();
header('Location: login.php');
exit;
