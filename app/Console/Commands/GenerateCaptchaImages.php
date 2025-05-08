<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\CaptchaGeneratorService;

class GenerateCaptchaImages extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'captcha:generate {count=10}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate sample CAPTCHA images for testing';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @param CaptchaGeneratorService $generator
     * @return int
     */
    public function handle(CaptchaGeneratorService $generator)
    {
        $count = (int) $this->argument('count');
        
        $this->info("Generating {$count} CAPTCHA image sets...");
        
        $bar = $this->output->createProgressBar($count);
        
        for ($i = 0; $i < $count; $i++) {
            $result = $generator->generateCaptchaImages();
            
            $this->line(" Generated: ");
            $this->line(" - Background: " . $result['background']);
            $this->line(" - Slider: " . $result['slider']);
            $this->line(" - Position X: " . $result['position_x'] . "%");
            $this->line(" - Position Y: " . $result['position_y'] . "%");
            
            $bar->advance();
        }
        
        $bar->finish();
        
        $this->newLine();
        $this->info('CAPTCHA image generation completed successfully!');
        
        return Command::SUCCESS;
    }
}