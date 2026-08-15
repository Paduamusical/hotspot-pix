<?php require_once __DIR__.'/../api/auth.php'; $_SESSION=[]; session_destroy(); header('Location: login.php');
