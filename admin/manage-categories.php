<?php
// Admin: add new job categories and delete old ones.
require_once __DIR__ . '/../includes/bootstrap.php';
require_login('admin');

$db = getDB();

// add or delete form was sent
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    if (isset($_POST['add'])) {
        $name = trim($_POST['category_name'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        if ($name === '') {
            flash('error', 'Category name is required.');
        } else {
            // the name is unique, so a duplicate makes the insert fail
            try {
                $db->prepare('INSERT INTO job_categories (category_name, description) VALUES (?, ?)')->execute([$name, $desc]);
                flash('success', 'Category added.');
            } catch (PDOException $e) {
                flash('error', 'A category with that name already exists.');
            }
        }
    } elseif (isset($_POST['delete_id'])) {
        // the jobs stay, the database just empties their category_id
        $db->prepare('DELETE FROM job_categories WHERE category_id = ?')->execute([(int) $_POST['delete_id']]);
        flash('info', 'Category removed. Jobs in that category are now uncategorized.');
    }
    redirect('manage-categories.php');
}

// every category plus how many jobs use it
$categories = $db->query("
    SELECT c.*, (SELECT COUNT(*) FROM jobs j WHERE j.category_id = c.category_id) AS job_count
    FROM job_categories c ORDER BY c.category_name
")->fetchAll();

$pageTitle = 'Manage categories — KaziLink';
$base = '../';
$active = 'categories';
include __DIR__ . '/../includes/header.php';
?>

<div class="dash-shell">
  <?php include __DIR__ . '/../includes/sidebar-admin.php'; ?>

  <main class="dash-main">
    <div class="dash-header"><div><h1>Job categories</h1><p>Categories help job seekers filter listings.</p></div></div>

    <!-- add form -->
    <div class="card" style="margin-bottom:20px;">
      <div class="card-header"><h3>Add a category</h3></div>
      <div class="card-body">
        <form method="post" class="form-row">
          <?= csrf_field() ?>
          <div class="form-group"><label>Category name</label><input type="text" name="category_name" required></div>
          <div class="form-group"><label>Description (optional)</label><input type="text" name="description"></div>
          <div style="grid-column: 1 / -1;"><button type="submit" name="add" class="btn btn-primary">Add category</button></div>
        </form>
      </div>
    </div>

    <!-- category list with a delete button for each -->
    <div class="card">
      <div class="card-body table-scroll">
        <table class="data-table">
          <thead><tr><th>Name</th><th>Description</th><th>Jobs</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($categories as $cat): ?>
            <tr>
              <td class="row-title"><?= e($cat['category_name']) ?></td>
              <td class="text-muted"><?= e($cat['description'] ?: '—') ?></td>
              <td><?= (int) $cat['job_count'] ?></td>
              <td>
                <form method="post" data-confirm="Delete this category? Jobs in it will become uncategorized.">
                  <?= csrf_field() ?>
                  <input type="hidden" name="delete_id" value="<?= (int) $cat['category_id'] ?>">
                  <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </main>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
