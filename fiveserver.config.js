const config = {};

if (process.platform === 'win32') {
  config.php = 'C:\\xampp\\php\\php.exe';
} else {
  config.php = '/usr/bin/php';
}

module.exports = config;