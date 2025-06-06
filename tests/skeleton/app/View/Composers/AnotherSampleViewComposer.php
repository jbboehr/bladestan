<?php

namespace App\View\Composers;

use Illuminate\View\View;

final class AnotherSampleViewComposer
{
    public function compose(View $view): void
    {
        $view->with('count', 1);
    }
}
