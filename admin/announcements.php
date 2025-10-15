<?php
require_once '../config/database.php';
require_once '../includes/admin_auth.php';
require_once '../includes/functions.php';

$page_title = 'Manage Announcements';
require_once '../includes/header.php';

// Handle announcement creation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (verify_csrf_token($_POST['csrf_token'])) {
        $title = sanitize_input($_POST['title']);
        $content = sanitize_input($_POST['content']);
        $priority = sanitize_input($_POST['priority']);
        
        $stmt = $conn->prepare("INSERT INTO announcements (title, content, priority, created_by, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->bind_param("sssi", $title, $content, $priority, $_SESSION['user_id']);
        
        if ($stmt->execute()) {
            set_flash_message('Announcement created successfully', 'success');
        } else {
            set_flash_message('Failed to create announcement', 'error');
        }
        
        $stmt->close();
        redirect('announcements.php');
    }
}

// Fetch all announcements
$query = "
    SELECT a.*, u.first_name, u.last_name 
    FROM announcements a 
    JOIN users u ON a.created_by = u.id 
    ORDER BY a.created_at DESC
";
$result = $conn->query($query);
?>

<div class="container">
    <div class="card mb-4">
        <div class="card-header">
            <h2>Create New Announcement</h2>
        </div>
        <div class="card-body">
            <form method="POST" action="" data-validate>
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                
                <div class="form-group">
                    <label class="form-label" for="title">Title</label>
                    <input type="text" id="title" name="title" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="content">Content</label>
                    <textarea id="content" name="content" class="form-control" rows="6" required></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="priority">Priority</label>
                    <select id="priority" name="priority" class="form-control" required>
                        <option value="normal">Normal</option>
                        <option value="important">Important</option>
                        <option value="urgent">Urgent</option>
                    </select>
                </div>
                
                <button type="submit" class="btn btn-primary">Post Announcement</button>
            </form>
        </div>
    </div>
    
    <div class="card">
        <div class="card-header">
            <h2>All Announcements</h2>
        </div>
        <div class="card-body">
            <?php if ($result && $result->num_rows > 0): ?>
                <?php while ($announcement = $result->fetch_assoc()): ?>
                    <div class="card mb-3" style="border-left: 4px solid <?php 
                        echo $announcement['priority'] === 'urgent' ? '#ef4444' : 
                            ($announcement['priority'] === 'important' ? '#f59e0b' : '#3b82f6'); 
                    ?>;">
                        <div class="card-body">
                            <h3><?php echo htmlspecialchars($announcement['title']); ?></h3>
                            <p style="color: var(--text-light); font-size: 0.875rem; margin-bottom: 1rem;">
                                Posted by <?php echo htmlspecialchars($announcement['first_name'] . ' ' . $announcement['last_name']); ?> 
                                on <?php echo format_date($announcement['created_at']); ?>
                                <span class="badge badge-<?php 
                                    echo $announcement['priority'] === 'urgent' ? 'danger' : 
                                        ($announcement['priority'] === 'important' ? 'warning' : 'info'); 
                                ?>" style="margin-left: 0.5rem;">
                                    <?php echo ucfirst($announcement['priority']); ?>
                                </span>
                            </p>
                            <p><?php echo nl2br(htmlspecialchars($announcement['content'])); ?></p>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p>No announcements yet.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
