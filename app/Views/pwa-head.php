<?php // Public installation metadata only; no user or device data.
$pwaUrl=static fn(string $path):string=>\App\Support\Http::escape(\App\Controllers\AuthController::url($path)); ?>
<link rel="manifest" href="<?= $pwaUrl('/manifest.webmanifest') ?>">
<link rel="apple-touch-icon" href="<?= $pwaUrl('/assets/icons/app-192.png') ?>">
<meta name="theme-color" content="#164c3b">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="Dharma Sales">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<script src="<?= $pwaUrl('/assets/js/pwa-install.js') ?>" defer></script>
