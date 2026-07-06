<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../classes/User.php';

header('Content-Type: application/json');

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

// Require login
$currentUser = app_current_user();
if ($currentUser === null) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated.']);
    exit;
}

$db = app_db();
$user = User::findById($db, $currentUser['id']);

// Require admin role
if ($user === null || $user->getRole() !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied.']);
    exit;
}

$action = $_POST['action'] ?? '';

switch ($action) {

    case 'admin_approve_organizer':
        $targetUserId = (int) ($_POST['user_id'] ?? 0);
        $approveAction = (string) ($_POST['approve_action'] ?? '');

        $targetUser = User::findById($db, $targetUserId);
        if ($targetUser === null || $targetUser->getRole() !== 'organizer') {
            echo json_encode(['success' => false, 'message' => 'User not found or is not an organizer.']);
            exit;
        }

        if ($approveAction === 'approve') {
            $targetUser->setApproved(true);
            if ($targetUser->save()) {
                echo json_encode(['success' => true, 'message' => 'Organizer approved successfully.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to approve organizer.']);
            }
        } elseif ($approveAction === 'reject') {
            if ($targetUser->delete()) {
                echo json_encode(['success' => true, 'message' => 'Organizer rejected and removed.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to reject organizer.']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid approve action.']);
        }
        break;

    case 'admin_delete_user':
        $targetUserId = (int) ($_POST['user_id'] ?? 0);

        if ($targetUserId === $user->getId()) {
            echo json_encode(['success' => false, 'message' => 'You cannot delete yourself.']);
            exit;
        }

        $targetUser = User::findById($db, $targetUserId);
        if ($targetUser === null) {
            echo json_encode(['success' => false, 'message' => 'User not found.']);
            exit;
        }

        if ($targetUser->delete()) {
            echo json_encode(['success' => true, 'message' => 'User deleted successfully.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to delete user. Check for related records.']);
        }
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Unknown action.']);
        break;
}
