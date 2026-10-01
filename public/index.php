<?php
require_once __DIR__ . '/../config/bootstrap.php';
session_start();

// ROUTEUR CENTRAL
$route = $_GET['route'] ?? 'login';

switch ($route) {
    // AUTHENTIFICATION
    case 'login':
        require_once __DIR__ . '/../app/Controllers/AuthController.php';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            login();
        } else {
            showLoginForm();
        }
        break;

    case 'oidc_login':
        require_once __DIR__ . '/../app/Controllers/AuthController.php';
        startOidcLogin();
        break;

    case 'callback':
        require_once __DIR__ . '/../app/Controllers/AuthController.php';
        handleOidcCallback();
        break;

    case 'logout':
        require_once __DIR__ . '/../app/Controllers/AuthController.php';
        logout();
        break;

    // BACK-OFFICE (ADMIN)
    case 'dashboard':
        require_once __DIR__ . '/../app/Controllers/DashboardController.php';
        dashboard();
        break;

    case 'flottes':
        require_once __DIR__ . '/../app/Controllers/FlotteController.php';
        overviewFlottes();
        break;

    case 'roles':
        require_once __DIR__ . '/../app/Controllers/RoleController.php';
        manageRoles();
        break;

    case 'add_supervisor':
        require_once __DIR__ . '/../app/Controllers/RoleController.php';
        addSupervisor();
        break;

    case 'update_supervisor':
        require_once __DIR__ . '/../app/Controllers/RoleController.php';
        updateSupervisor();
        break;

    case 'resend_recruteur_email':
        require_once __DIR__ . '/../app/Controllers/RoleController.php';
        resendRecruteurEmail();
        break;

    case 'deactivate_recruteur':
        require_once __DIR__ . '/../app/Controllers/RoleController.php';
        deactivateRecruteur();
        break;
    
    case 'agent_pdf':
        require_once __DIR__ . '/../app/Controllers/AgentController.php';
        downloadPdf();
        break;
    
    case 'delete_agent':
        require_once __DIR__ . '/../app/Controllers/AgentController.php';
        deleteAgent();
        break;

    

    // FRONT OFFICE
    case 'front_register':
        require_once __DIR__ . '/../app/Controllers/FrontController.php';
        showRegisterForm();
        break;

    case 'front_stats':
        require_once __DIR__ . '/../app/Controllers/FrontController.php';
        showSuperviseurStats();
        break;

    case 'submit_agent':
        require_once __DIR__ . '/../app/Controllers/FrontController.php';
        submitAgent();
        break;

    case 'search_famoco':
        require_once __DIR__ . '/../app/Controllers/FrontController.php';
        searchFamoco();
        break;

    case 'check_duplicate':
        require_once __DIR__ . '/../app/Controllers/FrontController.php';
        checkDuplicate();
        break;

    default:
        require_once __DIR__ . '/../app/Controllers/AuthController.php';
        showLoginForm();
        break;
}
?>