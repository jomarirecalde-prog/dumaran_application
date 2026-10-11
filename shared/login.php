<?php
require_once '../config/config.php';

// Prevent redirect loops by checking if we're already in one
$current_url = $_SERVER['REQUEST_URI'];
$redirect_count = isset($_SESSION['redirect_count']) ? $_SESSION['redirect_count'] : 0;

// Reset redirect count if we're on a different page
if (!isset($_SESSION['last_page']) || $_SESSION['last_page'] !== $current_url) {
    $_SESSION['redirect_count'] = 0;
    $redirect_count = 0;
}
$_SESSION['last_page'] = $current_url;

// Redirect if already logged in (but avoid infinite loops)
if (isLoggedIn() && $redirect_count < 3) {
    $_SESSION['redirect_count'] = $redirect_count + 1;
    $redirect = hasRole(ROLE_ADMIN) ? 'admin/pages/dashboard.php' : 'applicant/pages/dashboard.php';
    
    // Only redirect if headers haven't been sent
    if (!headers_sent()) {
        header('Location: ' . BASE_URL . $redirect);
        exit();
    }
}

$logoUrl = '../assets/logo.png';
$absoluteLogoUrl = rtrim(BASE_URL, '/') . '/assets/logo.webp';
$campusUrl = '../assets/dumaran.jpg';
$absoluteCampusUrl = rtrim(BASE_URL, '/') . '/assets/dumaran.webp';
$debugMode = isset($_GET['debug']);

$flashMessages = [];
if (isset($_GET['message']) && $_GET['message'] !== '') {
    $flashMessages[] = ['type' => 'info', 'title' => 'Notice', 'message' => $_GET['message']];
}
if (isset($_GET['error']) && $_GET['error'] !== '') {
    $flashMessages[] = ['type' => 'error', 'title' => 'Sign In Failed', 'message' => $_GET['error']];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Sign in to the Dumaran Hiring System.">
    <meta name="theme-color" content="#2050A0">
    <title>Sign In - <?= htmlspecialchars(SITE_NAME, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="icon" type="image/x-icon" href="<?= BASE_URL ?>favicon.ico">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="<?= BASE_URL ?>assets/vendor/fontawesome/css/all.min.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>assets/css/login.css" rel="stylesheet">
</head>
<body class="login-body">
    <div id="offlineBanner" class="offline-banner" role="alert" aria-live="assertive" aria-hidden="true">
        <i class="fas fa-wifi-slash" aria-hidden="true"></i>
        <span>No Internet Connection</span>
    </div>

    <div class="login-bg" aria-hidden="true">
        <div class="login-bg__mesh"></div>
        <div class="login-bg__orb login-bg__orb--1"></div>
        <div class="login-bg__orb login-bg__orb--2"></div>
        <div class="login-bg__orb login-bg__orb--3"></div>
    </div>

    <div class="login-page">
        <div class="login-split">
            <aside class="login-hero" aria-label="Welcome">
                <div class="login-hero__image">
                    <img src="<?= htmlspecialchars($campusUrl) ?>"
                         alt=""
                         fetchpriority="high"
                         decoding="async"
                         onerror="this.onerror=null;this.src='<?= htmlspecialchars($absoluteCampusUrl) ?>';">
                </div>
                <div class="login-hero__overlay"></div>
                <div class="login-hero__shapes">
                    <div class="login-hero__shape login-hero__shape--1"></div>
                    <div class="login-hero__shape login-hero__shape--2"></div>
                    <div class="login-hero__shape login-hero__shape--3"></div>
                </div>
                <div class="login-hero__content">
                    <img class="login-hero__logo"
                         src="<?= htmlspecialchars($logoUrl) ?>"
                         alt="<?= htmlspecialchars(ORG_NAME, ENT_QUOTES, 'UTF-8') ?> logo"
                         width="96"
                         height="96"
                         onerror="this.onerror=null;this.src='<?= htmlspecialchars($absoluteLogoUrl) ?>';">
                    <p class="login-hero__university"><?= htmlspecialchars(ORG_NAME, ENT_QUOTES, 'UTF-8') ?></p>
                    <h1 class="login-hero__system"><?= htmlspecialchars(SITE_NAME, ENT_QUOTES, 'UTF-8') ?></h1>
                    <p class="login-hero__tagline">Building the Future Workforce of <?= htmlspecialchars(ORG_SHORT_NAME, ENT_QUOTES, 'UTF-8') ?></p>
                    <h2 class="login-hero__welcome">Welcome to <?= htmlspecialchars(SITE_SHORT_NAME, ENT_QUOTES, 'UTF-8') ?></h2>
                    <p class="login-hero__desc">Access your account to manage applications, track selections, and connect with <?= htmlspecialchars(ORG_NAME, ENT_QUOTES, 'UTF-8') ?>'s human resource services.</p>
                </div>
            </aside>

            <main class="login-panel">
                <div class="login-card" role="region" aria-labelledby="loginTitle">
                    <div id="loginLoadingOverlay" class="login-loading-overlay" aria-hidden="true">
                        <div class="login-loading-overlay__bar" role="progressbar" aria-label="Signing in"></div>
                        <p class="login-loading-overlay__text">Authenticating your account…</p>
                    </div>

                    <div class="login-brand-mobile">
                        <img class="login-brand-mobile__logo"
                             src="<?= htmlspecialchars($logoUrl) ?>"
                             alt="<?= htmlspecialchars(ORG_NAME, ENT_QUOTES, 'UTF-8') ?> logo"
                             width="72"
                             height="72"
                             loading="lazy"
                             decoding="async"
                             onerror="this.onerror=null;this.src='<?= htmlspecialchars($absoluteLogoUrl) ?>';">
                        <p class="login-brand-mobile__university"><?= htmlspecialchars(ORG_NAME, ENT_QUOTES, 'UTF-8') ?></p>
                        <p class="login-brand-mobile__system"><?= htmlspecialchars(SITE_NAME, ENT_QUOTES, 'UTF-8') ?></p>
                        <p class="login-brand-mobile__tagline">Building the Future Workforce of <?= htmlspecialchars(ORG_SHORT_NAME, ENT_QUOTES, 'UTF-8') ?></p>
                    </div>

                    <?php if ($debugMode): ?>
                    <div style="background:#FEF3C7;border:1px solid #F59E0B;padding:10px;margin-bottom:16px;font-size:11px;font-family:monospace;text-align:left;border-radius:10px;">
                        <strong>Logo Path Debug Info:</strong><br>
                        BASE_URL: <code><?= htmlspecialchars(BASE_URL) ?></code><br>
                        HTTP_HOST: <code><?= htmlspecialchars($_SERVER['HTTP_HOST'] ?? 'N/A') ?></code><br>
                        REQUEST_URI: <code><?= htmlspecialchars($_SERVER['REQUEST_URI'] ?? 'N/A') ?></code><br>
                        Relative Path: <code><?= htmlspecialchars($logoUrl) ?></code><br>
                        Absolute Path: <code><?= htmlspecialchars($absoluteLogoUrl) ?></code><br>
                        <strong>Using:</strong> <code><?= htmlspecialchars($logoUrl) ?></code>
                    </div>
                    <?php endif; ?>

                    <header class="login-header">
                        <h2 id="loginTitle" class="login-header__title">Welcome Back</h2>
                        <p class="login-header__subtitle">Sign in using your institutional account.</p>
                    </header>

                    <?php if (!empty($flashMessages)): ?>
                    <div class="login-alerts" role="status" aria-live="polite">
                        <?php foreach ($flashMessages as $flash): ?>
                            <?php if ($flash['type'] === 'error'): ?>
                            <div class="login-alert login-alert--error">
                                <i class="fas fa-exclamation-circle login-alert__icon" aria-hidden="true"></i>
                                <div><?= htmlspecialchars($flash['message']) ?></div>
                            </div>
                            <?php else: ?>
                            <div class="login-alert login-alert--info">
                                <i class="fas fa-info-circle login-alert__icon" aria-hidden="true"></i>
                                <div><?= htmlspecialchars($flash['message']) ?></div>
                            </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <form method="POST" action="<?= BASE_URL ?>auth/login_process.php" id="loginForm" class="login-form" novalidate>
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>" aria-hidden="true">

                        <div class="input-field">
                            <div class="input-wrapper">
                                <i class="fas fa-envelope input-icon" aria-hidden="true"></i>
                                <input type="email"
                                       id="email"
                                       name="email"
                                       class="input-control"
                                       placeholder=" "
                                       autocomplete="email"
                                       inputmode="email"
                                       autocapitalize="none"
                                       spellcheck="false"
                                       required
                                       aria-required="true"
                                       aria-describedby="emailError">
                                <label for="email" class="input-label">Email Address</label>
                            </div>
                            <p id="emailError" class="field-error" role="alert"></p>
                        </div>

                        <div class="input-field">
                            <div class="input-wrapper">
                                <i class="fas fa-lock input-icon" aria-hidden="true"></i>
                                <input type="password"
                                       id="password"
                                       name="password"
                                       class="input-control"
                                       placeholder=" "
                                       autocomplete="current-password"
                                       required
                                       aria-required="true"
                                       aria-describedby="passwordError capsLockWarning">
                                <label for="password" class="input-label">Password</label>
                                <button type="button"
                                        id="togglePassword"
                                        class="input-toggle"
                                        aria-label="Show password"
                                        aria-pressed="false"
                                        aria-controls="password">
                                    <i class="fas fa-eye" aria-hidden="true"></i>
                                </button>
                            </div>
                            <p id="capsLockWarning" class="caps-lock-warning" aria-hidden="true">
                                <i class="fas fa-arrow-up" aria-hidden="true"></i>
                                Caps Lock is on
                            </p>
                            <p id="passwordError" class="field-error" role="alert"></p>
                        </div>

                        <div class="form-options">
                            <label class="remember-me" for="rememberMe">
                                <input type="checkbox" id="rememberMe" name="remember_me" value="1">
                                <span>Remember Me</span>
                            </label>
                            <a href="mailto:<?= htmlspecialchars(ORG_HR_EMAIL, ENT_QUOTES, 'UTF-8') ?>?subject=Password%20Reset%20Request%20-%20<?= rawurlencode(SITE_SHORT_NAME) ?>"
                               class="forgot-link">Forgot Password?</a>
                        </div>

                        <button class="btn-signin" type="submit" id="loginSubmitBtn">
                            <i class="fas fa-sign-in-alt btn-signin__icon" aria-hidden="true"></i>
                            <span class="btn-signin__spinner" aria-hidden="true"></span>
                            <span class="btn-signin__label">Sign In</span>
                        </button>

                        <div class="trust-indicators" aria-label="Security information">
                            <div class="trust-item trust-item--full">
                                <i class="fas fa-lock" aria-hidden="true"></i>
                                <span>Secure Login — Protected by SSL Encryption</span>
                            </div>
                            <div class="trust-item">
                                <i class="fas fa-university" aria-hidden="true"></i>
                                <span>Authorized Users Only</span>
                            </div>
                            <div class="trust-item">
                                <i class="fas fa-shield-alt" aria-hidden="true"></i>
                                <span>Privacy Protected</span>
                            </div>
                        </div>

                        <p class="register-prompt">
                            Don't have an account?
                            <a href="<?= BASE_URL ?>shared/register.php">Register here</a>
                        </p>
                    </form>
                </div>

                <footer class="login-footer">
                    <p class="login-footer__copy">&copy; <?= date('Y') ?> <?= htmlspecialchars(ORG_NAME, ENT_QUOTES, 'UTF-8') ?></p>
                    <p class="login-footer__meta">Version 1.0.0 &middot; <a href="mailto:<?= htmlspecialchars(ORG_HR_EMAIL, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(ORG_HR_EMAIL, ENT_QUOTES, 'UTF-8') ?></a></p>
                    <nav class="login-footer__links" aria-label="Legal links">
                        <a href="<?= htmlspecialchars(ORG_WEBSITE, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer">Privacy Policy</a>
                        <a href="<?= htmlspecialchars(ORG_WEBSITE, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer">Terms of Use</a>
                    </nav>
                </footer>
            </main>
        </div>
    </div>

    <?php if (!empty($flashMessages)): ?>
    <script type="application/json" id="loginFlashData"><?= json_encode($flashMessages, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?></script>
    <?php endif; ?>

    <script src="<?= BASE_URL ?>assets/js/toast.js" defer></script>
    <script src="<?= BASE_URL ?>assets/js/login.js" defer></script>
</body>
</html>
