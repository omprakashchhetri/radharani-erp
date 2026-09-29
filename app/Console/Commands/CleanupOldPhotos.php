<?php
namespace App\Console\Commands;

use App\Models\Movement\Movement;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CleanupOldPhotos extends Command
{
    protected $signature = 'photos:cleanup';
    protected $description = 'Delete movement photos older than 90 days (not ecommerce images)';

    public function handle(): void
    {
        $cutoff = now()->subDays(90);

        // Deletes the file only — never touches the movements row itself.
        // movements is insert-only except approved_by (rule 1); photo_path
        // is left pointing at a now-missing file rather than nulled, so
        // this cleanup can never be mistaken for editing history. The UI
        // checks file existence before showing the photo link/icon.
        Movement::whereNotNull('photo_path')
            ->where('created_at', '<', $cutoff)
            ->chunkById(100, function ($movements) {
                foreach ($movements as $movement) {
                    Storage::disk('public')->delete($movement->photo_path);
                }
            });

        $this->info('Old movement photos cleaned up.');
    }
}
