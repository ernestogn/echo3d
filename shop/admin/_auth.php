<?php
require_once __DIR__ . '/../db.php';
session_start();
if (empty($_SESSION['admin'])) {
    header('Location: login.php');
    exit;
}