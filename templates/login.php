<?php
/** @var Auth $auth Authentication instance */
/** @var string|null $content Main content HTML */
/** @var string|null $styles Page-specific styles */
/** @var string|null $error Error message if authentication failed */
/** @var string|null $auth_message Authentication message from session */

use DiscogsHelper\Auth;
use DiscogsHelper\Logger;
use DiscogsHelper\Security\Csrf;

$auth_message = $auth_message ?? null;

$content = '
<div class="auth-container">
    <h1>Log In</h1>
    
    ' . ($error ?? '') . '
    
    ' . ($auth_message ? '<div class="auth-message">' . htmlspecialchars($auth_message) . '</div>' : '') . '
    
    <form method="POST" action="?action=login" class="auth-form">';

$csrfToken = Csrf::generate();

$content .= '
        <input type="hidden" name="csrf_token" value="' . htmlspecialchars($csrfToken) . '">
        <div>
            <label for="username">Username</label>
            <input type="text" 
                   id="username" 
                   name="username" 
                   value="' . htmlspecialchars($_POST['username'] ?? '') . '" 
                   required>
        </div>
        
        <div>
            <label for="password">Password</label>
            <input type="password" 
                   id="password" 
                   name="password" 
                   required>
        </div>
        
        <div>
            <button type="submit" class="button">Log In</button>
        </div>
    </form>
    
    <p class="auth-links">
        Need an account? <a href="?action=register">Create Account</a>
    </p>
</div>';

// Add auth message styling
$styles = '
<style>
    .auth-container {
        max-width: 400px;
        margin: 2rem auto;
        padding: 2rem;
        background: rgba(0, 0, 0, 0.03);
        border-radius: 8px;
    }
    
    .auth-form div {
        margin-bottom: 1rem;
    }
    
    .auth-form label {
        display: block;
        margin-bottom: 0.5rem;
    }
    
    .auth-form input {
        width: 100%;
    }
    
    .auth-form button {
        width: 100%;
        margin-top: 1rem;
    }
    
    .auth-links {
        text-align: center;
        margin-top: 1.5rem;
    }
    
    .error {
        color: #dc3545;
        padding: 0.5rem;
        margin-bottom: 1rem;
        border: 1px solid #dc3545;
        border-radius: 4px;
        background: rgba(220, 53, 69, 0.1);
    }

    .auth-message {
        color: #0c5460;
        padding: 0.5rem;
        margin-bottom: 1rem;
        border: 1px solid #0c5460;
        border-radius: 4px;
        background: rgba(12, 84, 96, 0.1);
    }
</style>';

require __DIR__ . '/layout.php';