<?php
// Compatibility redirect wrapper for legacy references
require_once __DIR__ . '/../includes/functions.php';
$id = (int)($_GET['id'] ?? 0);
if ($id > 0) {
    redirect('client/project-details.php?id=' . $id);
} else {
    redirect('client/projects.php');
}
