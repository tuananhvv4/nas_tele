<?php
/**
 * Authentication Helper - Updated
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['role']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ../auth.php');
        exit;
    }
}

function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        header('Location: ../user/dashboard.php');
        exit;
    }
}

function getAdminId() {
    return $_SESSION['user_id'] ?? null;
}

function getAdminUsername() {
    return $_SESSION['username'] ?? 'User';
}

function getUserRole() {
    return $_SESSION['role'] ?? 'user';
}

function getUserPackage() {
    return $_SESSION['package'] ?? 'free';
}

function logout() {
    session_destroy();
    header('Location: ../auth.php');
    exit;
}
