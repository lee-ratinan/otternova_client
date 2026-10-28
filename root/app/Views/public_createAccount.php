<?php $this->extend('_layout_public') ?>; ?>
<?= $this->section('content') ?>
    <div class="auth-card">
        <div class="auth-card-header">
            <h1 class="auth-title">Create an account</h1>
            <p class="auth-subtitle">Start your 30-day free trial — no credit card needed</p>
        </div>

        <form class="auth-form" novalidate>
            <div class="auth-field-group">
                <label for="fullName" class="form-label">Full name</label>
                <div class="auth-field">
                    <i class="ph ph-user auth-field-icon"></i>
                    <input type="text" class="form-control" id="fullName" name="fullName" placeholder="Your name" required>
                </div>
                <div class="invalid-feedback">Please enter your name.</div>
            </div>

            <div class="auth-field-group">
                <label for="email" class="form-label">Email address</label>
                <div class="auth-field">
                    <i class="ph ph-envelope auth-field-icon"></i>
                    <input type="email" class="form-control" id="email" name="email" placeholder="name@example.com" required>
                </div>
                <div class="invalid-feedback">Please enter a valid email address.</div>
            </div>

            <div class="auth-field-group">
                <label for="password" class="form-label">Password</label>
                <div class="auth-field">
                    <i class="ph ph-lock auth-field-icon"></i>
                    <input type="password" class="form-control" id="password" name="password" placeholder="Create a password" required>
                    <button class="auth-field-toggle password-toggle" type="button" tabindex="-1">
                        <i class="ph ph-eye"></i>
                    </button>
                </div>
                <div class="auth-strength" data-strength>
                    <span></span><span></span><span></span><span></span>
                </div>
                <div class="auth-strength-text" data-strength-text>Use 8+ characters with a mix of letters, numbers & symbols.</div>
            </div>

            <div class="form-check">
                <input class="form-check-input" type="checkbox" id="terms" name="terms" required>
                <label class="form-check-label" for="terms">
                    I agree to the <a href="#" class="auth-link">Terms of Service</a> and <a href="#" class="auth-link">Privacy Policy</a>
                </label>
            </div>

            <button type="submit" class="auth-submit">Create Account <i class="ph ph-arrow-right"></i></button>

            <div class="auth-divider">
                <span>or sign up with</span>
            </div>

            <div class="auth-social">
                <button type="button" class="auth-social-btn">
                    <i class="ph ph-google-logo"></i> Google
                </button>
                <button type="button" class="auth-social-btn">
                    <i class="ph ph-github-logo"></i> GitHub
                </button>
            </div>
        </form>

        <p class="auth-footer-text">
            Already have an account? <a href="auth-login.html" class="auth-link">Sign in</a>
        </p>
    </div>
<?php $this->endSection() ?>