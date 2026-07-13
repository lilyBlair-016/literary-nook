<?php
/**
 * authentication/logout.php — End the session and clear the remember cookie.
 */
require_once __DIR__ . '/../config/config.php';

logout_user();   // clears remember cookie, empties + destroys the session

// Start a brand-new session (with a fresh id) purely to carry the goodbye
// flash. Regenerating the id emits a valid session cookie AFTER the deletion
// cookie above, so the message survives the redirect.
session_start();
session_regenerate_id(true);
set_flash('You have been logged out. See you soon!', 'info');
redirect('index.php');
