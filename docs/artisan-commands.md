# Artisan Commands

## `files:cleanup`

Remove orphaned files — files that are not attached to any model.

```bash
php artisan files:cleanup
```

### Options

| Option      | Default | Description                                             |
|-------------|---------|---------------------------------------------------------|
| `--days=N`  | `30`    | Only delete orphans older than N days                   |
| `--dry-run` | —       | Preview what would be deleted without actually deleting |

### Examples

```bash
# Preview orphaned files older than 30 days
php artisan files:cleanup --dry-run

# Delete orphaned files older than 7 days
php artisan files:cleanup --days=7

# Delete all orphans older than 90 days
php artisan files:cleanup --days=90
```

### What Counts as Orphaned?

A file is orphaned when it has **zero** entries in the `filables` pivot table — meaning no model references it. This uses the `File::orphaned()` scope internally.

### Scheduling

Add to your `app/Console/Kernel.php` (or `routes/console.php` in Laravel 11+):

```php
Schedule::command('files:cleanup --days=30')->daily();
```

### Safety

- Only files older than the `--days` threshold are eligible
- Each file's disk contents and resized variants are deleted along with the model
- Pivot entries (if any remained) are also cleaned up
- Use `--dry-run` first to verify the impact
