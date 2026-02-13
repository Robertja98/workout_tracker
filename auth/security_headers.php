<?php
/**
 * Security Headers
 * 
 * Include this file to add security headers to responses
 * Usage: require_once __DIR__ . '/auth/security_headers.php';
 */

// Prevent clickjacking
header('X-Frame-Options: DENY');

// Prevent MIME type sniffing
header('X-Content-Type-Options: nosniff');

// Enable XSS protection
header('X-XSS-Protection: 1; mode=block');

// Referrer policy
header('Referrer-Policy: strict-origin-when-cross-origin');

// Content Security Policy (adjust as needed)
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data:;");

// Enforce HTTPS (uncomment when using HTTPS)
// header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');

// Permissions policy
header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
