<?php
/**
 * Generate LQIP based on image ID and size.
 *
 * @param int $image_id The image ID.
 * @return string The base64-encoded LQIP string.
 */
function headlesswc_generate_lqip($image_id)
{
    // Get the full path of the image for the specified size
    $image_path = get_attached_file($image_id);
    if (!file_exists($image_path)) {
        return '';
    }

    // Load the image using WordPress image editor
    $image = wp_get_image_editor($image_path);
    if (is_wp_error($image)) {
        return '';
    }

    // Resize the image to a small size, like 20x20 pixels
    $image->resize(200, 200, true);

    // Get the editor class
    $editor_class = get_class($image);

    // Apply blur effect and generate base64
    if ($editor_class === 'WP_Image_Editor_GD') {
        // Save the resized image temporarily
        $resized_path = $image->generate_filename('lqip');
        $image->save($resized_path);

        // Load the image as a GD resource
        $gd_image = imagecreatefromjpeg($resized_path);

        // Apply Gaussian blur effect
        for ($i = 0; $i < 5; $i++) { // Adjust iterations for desired blur amount
            imagefilter($gd_image, IMG_FILTER_GAUSSIAN_BLUR);
        }

        // Output the image as a JPEG to a variable
        ob_start();
        imagejpeg($gd_image, null, 60); // Quality set to 60
        $resized_image_content = ob_get_clean();

        // Clean up
        imagedestroy($gd_image);
        unlink($resized_path); // Remove the temporary file

    } elseif ($editor_class === 'WP_Image_Editor_Imagick') {
        // For Imagick, use the Imagick object directly
        $imagick_image = $image->get_image(); // Imagick object

        // Apply blur effect using Imagick
        $imagick_image->blurImage(5, 3); // Radius and sigma

        // Output the image as a JPEG to a variable
        ob_start();
        echo $imagick_image->getImageBlob();
        $resized_image_content = ob_get_clean();
    } else {
        // Unsupported editor
        return '';
    }

    // Encode the image content to base64
    $base64_image = base64_encode($resized_image_content);

    // Return the base64 string in data URI format
    return 'data:image/jpeg;base64,' . $base64_image;
}


