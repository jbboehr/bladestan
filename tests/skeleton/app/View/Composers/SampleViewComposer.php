<?php

namespace App\View\Composers;

use Illuminate\View\View;

final class SampleViewComposer
{
    public function compose(View $view): void
    {
        $view->with('count', 1);
    }
}
