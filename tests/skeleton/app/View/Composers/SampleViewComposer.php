<?php

namespace App\View\Composers;

use Illuminate\Contracts\View\View;

final class SampleViewComposer
{
    public function compose(View $view): void
    {
        if (random_int(0, 100) > 50) {
            $view->with('count', 1);
        } else {
            $view->with('count', 2);
        }
    }
}
