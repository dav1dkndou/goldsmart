<?php

/**
 * Debug Delete Video - Run this file directly
 * URL: https://goldsmart.online/database/debug_delete_video.php?id=7
 */
require_once __DIR__ . '/../config/database.php';

header('Content-Type: text/html; charset=utf-8');

$videoId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

echo '<h1>Debug Delete Video</h1>';

if ($videoId <= 0) {
    echo "<p style='color:red'>Please provide video ID: ?id=7</p>";
    exit;
}

try {
    $db = Database::getInstance()->getConnection();

    // Check video exists
    echo "<h2>1. Check Video (ID: $videoId)</h2>";
    $stmt = $db->prepare('SELECT * FROM videos WHERE id = ?');
    $stmt->execute([$videoId]);
    $video = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$video) {
        echo "<p style='color:red'>Video not found!</p>";
        exit;
    }

    echo '<pre>' . print_r($video, true) . '</pre>';

    // Check related tables
    echo '<h2>2. Check Related Tables</h2>';

    // Check video_comments
    echo '<h3>video_comments:</h3>';
    try {
        $stmt = $db->prepare('SELECT COUNT(*) FROM video_comments WHERE video_id = ?');
        $stmt->execute([$videoId]);
        $count = $stmt->fetchColumn();
        echo "<p>Found: $count records</p>";
    } catch (PDOException $e) {
        echo "<p style='color:orange'>Table doesn't exist: " . $e->getMessage() . '</p>';
    }

    // Check video_rewards
    echo '<h3>video_rewards:</h3>';
    try {
        $stmt = $db->prepare('SELECT COUNT(*) FROM video_rewards WHERE video_id = ?');
        $stmt->execute([$videoId]);
        $count = $stmt->fetchColumn();
        echo "<p>Found: $count records</p>";
    } catch (PDOException $e) {
        echo "<p style='color:orange'>Table doesn't exist: " . $e->getMessage() . '</p>';
    }

    // Check video_likes
    echo '<h3>video_likes:</h3>';
    try {
        $stmt = $db->prepare('SELECT COUNT(*) FROM video_likes WHERE video_id = ?');
        $stmt->execute([$videoId]);
        $count = $stmt->fetchColumn();
        echo "<p>Found: $count records</p>";
    } catch (PDOException $e) {
        echo "<p style='color:orange'>Table doesn't exist: " . $e->getMessage() . '</p>';
    }

    // Check foreign keys on videos table
    echo '<h2>3. Check Foreign Key Constraints</h2>';
    $stmt = $db->query("
        SELECT 
            TABLE_NAME,
            COLUMN_NAME,
            CONSTRAINT_NAME,
            REFERENCED_TABLE_NAME,
            REFERENCED_COLUMN_NAME
        FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
        WHERE REFERENCED_TABLE_NAME = 'videos'
        AND TABLE_SCHEMA = DATABASE()
    ");
    $fks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($fks)) {
        echo '<p>No foreign keys referencing videos table</p>';
    } else {
        echo '<pre>' . print_r($fks, true) . '</pre>';
    }

    // Perform delete if confirmed
    if (isset($_GET['confirm']) && $_GET['confirm'] === 'yes') {
        echo '<h2>4. Performing Delete...</h2>';

        // Delete from related tables first
        $tables = ['video_comments', 'video_rewards', 'video_likes'];
        foreach ($tables as $table) {
            try {
                $stmt = $db->prepare("DELETE FROM $table WHERE video_id = ?");
                $stmt->execute([$videoId]);
                echo "<p>Deleted from $table: " . $stmt->rowCount() . ' rows</p>';
            } catch (PDOException $e) {
                echo "<p>$table: " . $e->getMessage() . '</p>';
            }
        }

        // Delete the video
        try {
            $stmt = $db->prepare('DELETE FROM videos WHERE id = ?');
            $stmt->execute([$videoId]);
            echo "<p style='color:green'>Video deleted successfully!</p>";
        } catch (PDOException $e) {
            echo "<p style='color:red'>Failed to delete video: " . $e->getMessage() . '</p>';
        }
    } else {
        echo '<h2>4. Ready to Delete</h2>';
        echo "<p><a href='?id=$videoId&confirm=yes' style='color:red; font-weight:bold;'>Click here to DELETE video $videoId</a></p>";
    }
} catch (PDOException $e) {
    echo "<p style='color:red'>Database Error: " . $e->getMessage() . '</p>';
}
