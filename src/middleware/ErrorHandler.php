<?php
/**
 * Error Handler - Centralized error handling for user-friendly messages
 * assignment_portal/src/middleware/ErrorHandler.php
 *
 * Prevents sensitive information (database paths, connection details, etc.)
 * from being exposed to users while logging full details server-side.
 */

class ErrorHandler
{
    /**
     * Log error details server-side and return a user-friendly message.
     *
     * @param Throwable $e The exception that was thrown
     * @param string $userMessage Optional custom user-facing message
     * @return string User-friendly error message
     */
    public static function handle(Throwable $e, string $userMessage = null): string
    {
        // Log full error details server-side (never shown to users)
        error_log(sprintf(
            "[%s] %s in %s:%d\nStack trace:\n%s",
            get_class($e),
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
            $e->getTraceAsString()
        ));

        // Return user-friendly message
        return $userMessage ?? self::getDefaultUserMessage($e);
    }

    /**
     * Get a default user-friendly message based on exception type.
     */
    private static function getDefaultUserMessage(Throwable $e): string
    {
        if ($e instanceof PDOException) {
            return 'A database error occurred. Please try again later.';
        }

        if ($e instanceof FileNotFoundException) {
            return 'The requested file could not be found.';
        }

        if ($e instanceof UploadException) {
            return $e->getMessage(); // Upload exceptions already have safe messages
        }

        // Generic fallback for any other exception
        return 'An unexpected error occurred. Please try again later.';
    }

    /**
     * Render an error page and exit.
     *
     * @param Throwable $e The exception
     * @param string $title Optional page title
     * @param string $userMessage Optional custom message
     */
    public static function renderErrorPage(Throwable $e, string $title = null, string $userMessage = null): void
    {
        $message = $userMessage ?? self::getDefaultUserMessage($e);
        $title = $title ?? 'Error';

        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title><?= htmlspecialchars($title) ?> — <?= APP_NAME ?></title>
            <link rel="preconnect" href="https://fonts.googleapis.com">
            <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
            <link rel="stylesheet" href="/css/app.css">
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
        </head>
        <body>
            <div class="auth-page">
                <div class="auth-card">
                    <div class="auth-logo">
                        <div class="auth-logo-icon" style="color: var(--red);">!</div>
                    </div>
                    <h1 class="auth-title"><?= htmlspecialchars($title) ?></h1>
                    <p class="auth-subtitle"><?= htmlspecialchars($message) ?></p>

                    
                    <div style="margin-top: 1.5rem; text-align: center;">
                        <a href="javascript:history.back()" class="btn btn-ghost">
                            <i class="fa fa-arrow-left"></i> Go Back
                        </a>
                        <a href="/public/index.php" class="btn btn-primary" style="margin-left: 0.5rem;">
                            <i class="fa fa-home"></i> Home
                        </a>
                    </div>
                </div>
            </div>
        </body>
        </html>
        <?php
        exit;
    }

    /**
     * Set a flash error message in the session.
     *
     * @param string $message User-friendly error message
     */
    public static function setFlashError(string $message): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['flash_error'] = $message;
    }

    /**
     * Get and clear the flash error message from the session.
     *
     * @return string|null The flash error message, or null if none exists
     */
    public static function getFlashError(): ?string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $error = $_SESSION['flash_error'] ?? null;
        unset($_SESSION['flash_error']);
        return $error;
    }
}

/**
 * Custom exception for file-related errors.
 */
class FileNotFoundException extends Exception {}

/**
 * Custom exception for upload-related errors with user-safe messages.
 */
class UploadException extends Exception {}
