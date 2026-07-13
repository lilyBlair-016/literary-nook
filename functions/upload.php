<?php
/**
 * functions/upload.php
 * -----------------------------------------------------------------------------
 * Secure image upload handling (Module 7: $_FILES, move_uploaded_file; Module 7
 * file validation functions). Used for book cover images.
 * -----------------------------------------------------------------------------
 */

define('MAX_UPLOAD_BYTES', 2 * 1024 * 1024);                 // 2 MB
const ALLOWED_IMAGE_EXT = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

/**
 * Handle a single image upload from $_FILES[$field].
 *
 * @param string      $field  the form file-input name
 * @param string|null $error  filled with an error message on failure
 * @param string      $prefix filename prefix, e.g. 'book_' or 'avatar_'
 * @return string|null        the stored filename on success, null otherwise
 *                            (null with no $error means "no file was submitted")
 */
function handle_image_upload(string $field, ?string &$error = null, string $prefix = 'book_'): ?string
{
    $error = null;

    // No file chosen — a valid "leave as-is" case for edit forms.
    if (empty($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    $file = $_FILES[$field];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error = 'File upload failed (error code ' . (int) $file['error'] . ').';
        return null;
    }
    if ($file['size'] > MAX_UPLOAD_BYTES) {
        $error = 'Image is too large (max 2 MB).';
        return null;
    }

    // Validate that it is genuinely an image (not just a renamed file).
    $info = @getimagesize($file['tmp_name']);
    if ($info === false) {
        $error = 'That file is not a valid image.';
        return null;
    }

    // Extension check (Module 4: strtolower, substr via pathinfo).
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ALLOWED_IMAGE_EXT, true)) {
        $error = 'Allowed image types: ' . implode(', ', ALLOWED_IMAGE_EXT) . '.';
        return null;
    }

    if (!is_dir(UPLOAD_PATH)) {
        @mkdir(UPLOAD_PATH, 0777, true);
    }

    // Unique, non-guessable filename to avoid collisions / overwrites.
    $filename    = $prefix . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $destination = UPLOAD_PATH . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        $error = 'Could not save the uploaded image.';
        return null;
    }

    return $filename;
}

/** Delete a previously uploaded cover file (Module 7: unlink, file_exists). */
function delete_upload(?string $filename): void
{
    if ($filename && file_exists(UPLOAD_PATH . '/' . $filename)) {
        @unlink(UPLOAD_PATH . '/' . $filename);
    }
}
