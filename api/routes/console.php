<?php

use Illuminate\Support\Facades\Artisan;

Artisan::command('merobiz:about', function (): void {
    $this->info('MeroBiz API is ready.');
})->purpose('Display a MeroBiz status message');
