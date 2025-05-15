<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class CleanupTrashFiles extends Command
{
    protected $signature = 'files:cleanup';
    protected $description = 'Clean up files that have been in trash for 30 days';

    public function handle()
    {
        $this->info('Starting file cleanup...');
        
        // Get files that should be deleted today
        $files = DB::table('trash_files')
            ->where('delete_at', '<=', Carbon::now())
            ->get();
            
        $count = 0;
        
        foreach ($files as $file) {
            if (Storage::disk('public')->exists($file->file_path)) {
                Storage::disk('public')->delete($file->file_path);
                $count++;
            }
            
            // Remove the record from trash_files
            DB::table('trash_files')->where('id', $file->id)->delete();
        }
        
        $this->info("Cleanup complete. Deleted {$count} files.");
        
        return 0;
    }
}