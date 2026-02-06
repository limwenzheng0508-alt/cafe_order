<?php
/**
 * QR Code Helper Functions
 * Generates and manages QR codes for reservations
 */

function generate_qr_code($data, $reservation_id) {
    $qr_dir = __DIR__ . '/qrcodes';
    
    // Ensure directory exists
    if (!is_dir($qr_dir)) {
        mkdir($qr_dir, 0755, true);
    }
    
    $filename = 'reservation_' . $reservation_id . '.png';
    $filepath = $qr_dir . '/' . $filename;
    
    // If QR code already exists, return it
    if (file_exists($filepath)) {
        return $filename;
    }
    
    // Try multiple methods to generate QR code
    
    // Method 1: Try QR Server API (primary method)
    $qr_data = urlencode($data);
    $qr_url = "https://api.qrserver.com/v1/create-qr-code/?size=300x300&data={$qr_data}";
    
    $image_data = @file_get_contents($qr_url, false, stream_context_create([
        'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
        'http' => ['timeout' => 5]
    ]));
    
    if ($image_data !== false && strlen($image_data) > 0) {
        if (file_put_contents($filepath, $image_data)) {
            // Save metadata
            save_qr_metadata($qr_dir, $reservation_id, $filename, $data);
            return $filename;
        }
    }
    
    // Method 2: Use Google Charts API as fallback
    $encoded_data = urlencode($data);
    $google_url = "https://chart.googleapis.com/chart?chs=300x300&chld=L|0&cht=qr&chl={$encoded_data}";
    
    $image_data = @file_get_contents($google_url, false, stream_context_create([
        'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
        'http' => ['timeout' => 5]
    ]));
    
    if ($image_data !== false && strlen($image_data) > 0) {
        if (file_put_contents($filepath, $image_data)) {
            // Save metadata
            save_qr_metadata($qr_dir, $reservation_id, $filename, $data);
            return $filename;
        }
    }
    
    // Method 3: Create a text-based representation as fallback
    error_log("Failed to generate image QR code via API for reservation: {$reservation_id}. Creating text fallback.");
    
    $text_filename = 'reservation_' . $reservation_id . '.txt';
    $text_filepath = $qr_dir . '/' . $text_filename;
    
    $text_content = "=== RESERVATION QR CODE ===\n";
    $text_content .= "Reservation ID: {$reservation_id}\n";
    $text_content .= "Generated: " . date('Y-m-d H:i:s') . "\n";
    $text_content .= "\n--- Reservation Details ---\n";
    $text_content .= $data . "\n";
    $text_content .= "\n--- Instructions ---\n";
    $text_content .= "Please show this information to the staff.\n";
    
    file_put_contents($text_filepath, $text_content);
    
    // Save metadata
    save_qr_metadata($qr_dir, $reservation_id, $text_filename, $data);
    
    return $text_filename;
}

function save_qr_metadata($qr_dir, $reservation_id, $filename, $data) {
    $meta_filename = 'reservation_' . $reservation_id . '.meta';
    $meta_filepath = $qr_dir . '/' . $meta_filename;
    $metadata = json_encode([
        'reservation_id' => $reservation_id,
        'data' => $data,
        'created_at' => date('Y-m-d H:i:s'),
        'file' => $filename
    ]);
    file_put_contents($meta_filepath, $metadata);
}

function get_qr_code_path($filename) {
    $qr_dir = __DIR__ . '/qrcodes';
    $filepath = $qr_dir . '/' . $filename;
    
    if (file_exists($filepath)) {
        return $filepath;
    }
    
    return null;
}

function get_qr_code_url($filename) {
    return 'qrcodes/' . $filename;
}

function get_all_qr_codes() {
    $qr_dir = __DIR__ . '/qrcodes';
    $qrcodes = [];
    
    if (!is_dir($qr_dir)) {
        return $qrcodes;
    }
    
    $files = scandir($qr_dir);
    
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') continue;
        
        // Only process PNG and TXT files
        if (!preg_match('/^reservation_\d+\.(png|txt)$/', $file)) continue;
        
        // Skip metadata files
        if (strpos($file, '.meta') !== false) continue;
        
        // Extract reservation ID
        preg_match('/reservation_(\d+)/', $file, $matches);
        if (!isset($matches[1])) continue;
        
        $res_id = (int)$matches[1];
        
        if (!isset($qrcodes[$res_id])) {
            $qrcodes[$res_id] = [
                'reservation_id' => $res_id,
                'file' => null,
                'metadata' => null,
                'created_at' => filemtime($qr_dir . '/' . $file)
            ];
        }
        
        // Determine file type
        if (strpos($file, '.png') !== false) {
            $qrcodes[$res_id]['file'] = $file;
        } elseif (strpos($file, '.txt') !== false) {
            $qrcodes[$res_id]['file'] = $file;
        }
        
        // Load metadata if exists
        $meta_file = $qr_dir . '/reservation_' . $res_id . '.meta';
        if (file_exists($meta_file)) {
            $meta_content = file_get_contents($meta_file);
            $qrcodes[$res_id]['metadata'] = json_decode($meta_content, true);
        }
    }
    
    // Sort by creation time (newest first)
    usort($qrcodes, function($a, $b) {
        return $b['created_at'] - $a['created_at'];
    });
    
    return $qrcodes;
}

function cleanup_expired_qr_codes($days = 1) {
    /**
     * Delete QR codes and related files older than specified days
     * For guests, QR codes expire after 1 day
     * For logged-in users, they can access their QR codes via login
     */
    $qr_dir = __DIR__ . '/qrcodes';
    
    if (!is_dir($qr_dir)) {
        return 0;
    }
    
    $files = scandir($qr_dir);
    $deleted_count = 0;
    $cutoff_time = time() - ($days * 24 * 60 * 60);
    
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') continue;
        
        $filepath = $qr_dir . '/' . $file;
        $file_time = filemtime($filepath);
        
        // Only delete if older than specified days
        if ($file_time < $cutoff_time) {
            if (unlink($filepath)) {
                $deleted_count++;
                error_log("Deleted expired QR code file: {$file}");
            }
        }
    }
    
    return $deleted_count;
}

?>

