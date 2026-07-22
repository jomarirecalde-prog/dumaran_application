<?php
/**
 * Custom Exception Classes
 */

class ValidationException extends Exception {
    public function __construct($message = "", $code = 400) {
        parent::__construct($message, $code);
    }
}

class AuthenticationException extends Exception {
    public function __construct($message = "", $code = 401) {
        parent::__construct($message, $code);
    }
}

class AuthorizationException extends Exception {
    public function __construct($message = "Access denied", $code = 403) {
        parent::__construct($message, $code);
    }
}

class NotFoundException extends Exception {
    public function __construct($message = "Resource not found", $code = 404) {
        parent::__construct($message, $code);
    }
}
