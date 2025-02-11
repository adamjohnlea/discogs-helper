<?php
/** @var Auth $auth Authentication instance */
/** @var string|null $content Main content HTML */
/** @var string|null $styles Page-specific styles */
/** @var string|null $error Error message if registration failed */

use DiscogsHelper\Security\Auth;
use DiscogsHelper\Security\Csrf;

$content = '
<div class="auth-container">
    <h1>Create Account</h1>
    
    ' . ($error ?? '') . '
    
    <form method="POST" action="?action=register" class="auth-form">
     ' . Csrf::getFormField() . '
        <div>
            <label for="username">Username</label>
            <input type="text" 
                   id="username" 
                   name="username" 
                   value="' . htmlspecialchars($_POST['username'] ?? '') . '" 
                   required>
        </div>
        
        <div>
            <label for="email">Email</label>
            <input type="email" 
                   id="email" 
                   name="email" 
                   value="' . htmlspecialchars($_POST['email'] ?? '') . '" 
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
            <label for="password_confirm">Confirm Password</label>
            <input type="password" 
                   id="password_confirm" 
                   name="password_confirm" 
                   required>
        </div>
        
        <div>
            <button type="submit" class="button">Create Account</button>
        </div>
    </form>
    
    <p class="auth-links">
        Already have an account? <a href="?action=login">Log In</a>
    </p>
</div>';

// Add specific styles for auth pages
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
</style>';

require __DIR__ . '/layout.php';