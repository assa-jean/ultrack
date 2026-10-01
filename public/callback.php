<?php
require_once __DIR__ . '/../config/bootstrap.php';
session_start();

require_once __DIR__ . '/../app/Controllers/AuthController.php';
handleOidcCallback();
