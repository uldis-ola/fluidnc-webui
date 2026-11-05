<?php
// Heading
$_['heading_title']         = '3D Product Viewer';

// Text
$_['text_extension']        = 'Extensions';
$_['text_success']          = 'Success: 3D Product Viewer settings saved!';
$_['text_edit']             = 'Edit 3D Product Viewer';
$_['text_uploaded']         = 'File uploaded.';
$_['text_products']         = 'Products with a 3D model: %s';
$_['text_local']            = 'Local files (recommended, no external requests)';
$_['text_cdn']              = 'Google CDN';
$_['text_local_missing']    = 'Local model-viewer files are missing in catalog/view/javascript/product_3d/ — re-upload the module or use CDN.';
$_['text_placement_replace'] = 'Replace the image gallery';
$_['text_placement_before'] = 'Above the image gallery';
$_['text_placement_after']  = 'Below the image gallery';
$_['text_placement_manual'] = 'Manual (theme prints {{ product_3d_html }})';
$_['text_general']          = 'General';
$_['text_viewer']           = 'Viewer';
$_['text_files']            = 'Files';

// Entry
$_['entry_status']          = 'Status';
$_['entry_placement']       = 'Placement on product page';
$_['entry_selector']        = 'Gallery CSS selector';
$_['entry_height']          = 'Viewer height (px)';
$_['entry_bg_color']        = 'Background colour';
$_['entry_ar']              = 'Augmented reality (AR) button';
$_['entry_auto_rotate']     = 'Auto-rotate';
$_['entry_camera_controls'] = 'Rotate / zoom with mouse and touch';
$_['entry_shadow']          = 'Shadow intensity (0–2)';
$_['entry_exposure']        = 'Exposure (0.1–3)';
$_['entry_camera_orbit']    = 'Default camera angle';
$_['entry_poster_fallback'] = 'Use main product image as poster';
$_['entry_source']          = 'model-viewer source';
$_['entry_folder']          = 'Upload folder (inside image/)';
$_['entry_max_size']        = 'Max. file size (MB)';
$_['entry_delete_data']     = 'Delete 3D links on uninstall';

// Help
$_['help_selector']         = 'Leave empty for automatic detection (recommended — works with any theme: the module finds this product\'s images on the page). Enter a CSS selector only to force a specific place, e.g. .thumbnails';
$_['help_camera_orbit']     = 'e.g. "30deg 75deg auto" (horizontal, vertical, distance). Empty = automatic.';
$_['help_max_size']         = 'Server limit (upload_max_filesize / post_max_size): %s MB.';
$_['help_delete_data']      = 'Only the product ↔ model links table is removed. Uploaded files in image/ are never deleted.';
$_['help_folder']           = 'Must start with catalog/, e.g. catalog/3d/';

// Product form tab
$_['p3d_tab']               = '3D model';
$_['p3d_menu']              = '3D Product Viewer';
$_['p3d_model']             = '3D model (.glb / .gltf)';
$_['p3d_ios']               = 'iOS AR file (.usdz, optional)';
$_['p3d_poster']            = 'Poster image (optional)';
$_['p3d_placement']         = 'Placement';
$_['p3d_placement_default'] = '— as in module settings —';
$_['p3d_place_replace']     = 'Replace the image gallery';
$_['p3d_place_before']      = 'Above the image gallery';
$_['p3d_place_after']       = 'Below the image gallery';
$_['p3d_place_manual']      = 'Manual (theme)';
$_['p3d_placement_disabled'] = 'Do not show for this product';
$_['p3d_camera_orbit']      = 'Camera angle';
$_['p3d_auto_rotate']       = 'Auto-rotate';
$_['p3d_inherit']           = '— as in module settings —';
$_['p3d_yes']               = 'Yes';
$_['p3d_no']                = 'No';
$_['p3d_upload']            = 'Upload';
$_['p3d_choose']            = 'Choose existing…';
$_['p3d_clear']             = 'Remove';
$_['p3d_preview']           = 'Preview';
$_['p3d_uploading']         = 'Uploading…';
$_['p3d_none']              = 'No files found';
$_['p3d_hint']              = 'Tip: optimise models before upload (e.g. web-3d-pipeline/optimize.py): ≤ 5 MB, textures ≤ 2048 px. Save the product to apply changes.';
$_['p3d_max']               = 'Max. %s MB';
$_['p3d_settings']          = 'Module settings';
$_['p3d_readonly']          = 'You do not have permission to change 3D models.';

// Error
$_['error_permission']      = 'Warning: You do not have permission to modify 3D Product Viewer!';
$_['error_type']            = 'Unknown file type.';
$_['error_upload']          = 'File upload failed.';
$_['error_size']            = 'File is too large (max. %s MB).';
$_['error_extension']       = 'Allowed file types: %s';
$_['error_content']         = 'File content is not allowed.';
$_['error_glb']             = 'Invalid .glb file (glTF 2.0 binary expected).';
$_['error_gltf']            = 'Invalid .gltf file (glTF 2.0 JSON expected).';
$_['error_gltf_external']   = 'This .gltf references external files (.bin / textures). Upload a single .glb file instead.';
$_['error_usdz']            = 'Invalid .usdz file.';
$_['error_image']           = 'Poster must be a JPG, PNG or WebP image.';
$_['error_folder']          = 'Folder %s is not writable.';
