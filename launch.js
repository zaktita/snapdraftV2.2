#!/usr/bin/env node

/**
 * Launch script to run both `php artisan serve` and `npm run dev` concurrently
 * Usage: node launch.js or npm run launch
 */

const { spawn } = require('child_process');
const path = require('path');

const isWindows = process.platform === 'win32';

console.log('🚀 Launching SnapDraft development environment...\n');

// Spawn php artisan serve
const phpProcess = spawn(isWindows ? 'php.exe' : 'php', ['artisan', 'serve'], {
    cwd: __dirname,
    stdio: 'inherit',
    shell: isWindows,
});

// Spawn npm run dev
const npmProcess = spawn(isWindows ? 'npm.cmd' : 'npm', ['run', 'dev'], {
    cwd: __dirname,
    stdio: 'inherit',
    shell: isWindows,
});

// Handle process termination
process.on('SIGINT', () => {
    console.log('\n\n⛔ Shutting down...');
    phpProcess.kill('SIGINT');
    npmProcess.kill('SIGINT');
    process.exit(0);
});

// Handle errors
phpProcess.on('error', (err) => {
    console.error('❌ PHP error:', err);
});

npmProcess.on('error', (err) => {
    console.error('❌ npm error:', err);
});

console.log('✅ Both services are running. Press Ctrl+C to stop.\n');
