<?php
/** @var Auth $auth Authentication instance */
/** @var Database $db Database instance */
/** @var string|null $content Main content HTML */
/** @var string|null $styles Page-specific styles */

use DiscogsHelper\Auth;
use DiscogsHelper\Database;
use DiscogsHelper\Logger;
use DiscogsHelper\Security\Csrf;
use DiscogsHelper\Session;

if (!$auth->isLoggedIn()) {
    header('Location: ?action=login');
    exit;
}

$userId = $auth->getCurrentUser()->id;
$profile = $db->getUserProfile($userId);
$user = $auth->getCurrentUser();

// Initialize content variable
$content = '';

// Display auth message if any
if (Session::hasMessage()) {
    $message = Session::getMessage();
    Logger::log('Profile_edit.php: Found message: ' . $message);
    $content .= '
    <div class="info-message">
        ' . htmlspecialchars($message) . '
    </div>';
    Logger::log('Profile_edit.php: Added message to content');
}

// Display errors if any
if (Session::hasErrors()) {
    $errors = Session::getErrors();
    Logger::log('Profile_edit.php: Displaying errors: ' . implode(', ', $errors));
    $content .= '
    <div class="error-messages">
        <ul>
            ' . implode('', array_map(fn($error) => "<li>" . htmlspecialchars($error) . "</li>", $errors)) . '
        </ul>
    </div>';
}

$content .= '
<div class="profile-edit">
    <h1>Edit Profile</h1>
    
    <form method="POST" action="?action=profile_update" class="profile-form">
        ' . Csrf::getFormField() . '   
        <section class="form-section">
            <h2>Basic Information</h2>
            
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" value="' . htmlspecialchars($user->username) . '" disabled>
                <small>Username cannot be changed</small>
            </div>

            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" value="' . htmlspecialchars($user->email) . '" disabled>
                <small>Email cannot be changed</small>
            </div>

            <div class="form-group">
                <label for="location">Location</label>
                <input type="text" name="location" id="location" 
                       value="' . htmlspecialchars($profile?->location ?? '') . '">
                <small>Optional: Your location</small>
            </div>
        </section>

        <section class="form-section">
            <h2>Discogs Integration</h2>
            
            <div class="form-group">
                <label for="discogs_username">Discogs Username</label>
                <input type="text" name="discogs_username" id="discogs_username" 
                       value="' . htmlspecialchars($profile?->discogsUsername ?? '') . '">
                <small>Optional: Your Discogs username</small>
            </div>

            <div class="form-group">
                <label for="discogs_consumer_key">Consumer Key</label>
                <input type="text" name="discogs_consumer_key" id="discogs_consumer_key" 
                       value="' . htmlspecialchars($profile?->discogsConsumerKey ?? '') . '">
                <small>Optional: Your Discogs API consumer key</small>
            </div>

            <div class="form-group">
                <label for="discogs_consumer_secret">Consumer Secret</label>
                <input type="text" name="discogs_consumer_secret" id="discogs_consumer_secret" 
                       value="' . htmlspecialchars($profile?->discogsConsumerSecret ?? '') . '">
                <small>Optional: Your Discogs API consumer secret</small>
            </div>
        </section>

        <section class="form-section">
            <h2>Change Password</h2>
            
            <div class="form-group">
                <label for="current_password">Current Password</label>
                <input type="password" name="current_password" id="current_password">
                <small>Required only if changing password</small>
            </div>

            <div class="form-group">
                <label for="new_password">New Password</label>
                <input type="password" name="new_password" id="new_password">
                <small>Leave blank to keep current password</small>
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirm New Password</label>
                <input type="password" name="confirm_password" id="confirm_password">
            </div>
        </section>

        <div class="form-actions">
            <a href="?action=profile" class="button secondary">Cancel</a>
            <button type="submit" class="button">Save Changes</button>
        </div>
    </form>
</div>';

$styles = '
<style>
    .profile-edit {
        max-width: 800px;
        margin: 0 auto;
        padding: 1rem;
    }

    .form-section {
        background: rgba(0, 0, 0, 0.03);
        border-radius: 8px;
        padding: 1.5rem;
        margin-bottom: 2rem;
    }

    .form-section h2 {
        margin-top: 0;
        padding-bottom: 0.5rem;
        border-bottom: 1px solid rgba(0, 0, 0, 0.1);
    }

    .form-group {
        margin-bottom: 1rem;
    }

    .form-group label {
        display: block;
        margin-bottom: 0.5rem;
        font-weight: bold;
    }

    .form-group input[type="text"],
    .form-group input[type="email"],
    .form-group input[type="password"] {
        width: 100%;
        padding: 0.5rem;
        border: 1px solid #ddd;
        border-radius: 4px;
    }

    .form-group input[disabled] {
        background: #f5f5f5;
        cursor: not-allowed;
    }

    .form-group small {
        display: block;
        margin-top: 0.25rem;
        color: #666;
    }

    .form-actions {
        display: flex;
        gap: 1rem;
        justify-content: center;
        margin-top: 2rem;
    }

    .button.secondary {
        background: #666;
    }

    .button.secondary:hover {
        background: #555;
    }

    .error-messages {
        max-width: 800px;
        margin: 1rem auto;
        padding: 1rem;
        background: #fee;
        border: 1px solid #fcc;
        border-radius: 4px;
        color: #c00;
    }

    .error-messages ul {
        margin: 0;
        padding-left: 1.5rem;
    }

    .error-messages li {
        margin: 0.25rem 0;
    }

    .info-message {
        max-width: 800px;
        margin: 1rem auto;
        padding: 1rem;
        background: #e3f2fd;
        border: 1px solid #90caf9;
        border-radius: 4px;
        color: #1976d2;
    }
    
    .button,
    .button.secondary {
        padding: 0.5rem 1rem;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-size: 1rem;
        line-height: 1.5;
        text-decoration: none;
        color: #fff;
        background: #007bff;
        display: inline-flex;
        align-items: center;
        height: 38px;
        box-sizing: border-box;
        margin: 0;
    }
    
    .button:hover {
        background: #0056b3;
    }
    
    .button.secondary {
        background: #666;
    }
    
    .button.secondary:hover {
        background: #555;
    }
</style>';

require __DIR__ . '/layout.php';