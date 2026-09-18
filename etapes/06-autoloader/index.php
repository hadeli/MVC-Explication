<?php
session_start();
require __DIR__ . '/includes/render.php';

if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    header('Location: index.php');
    exit;
}

render('home');
