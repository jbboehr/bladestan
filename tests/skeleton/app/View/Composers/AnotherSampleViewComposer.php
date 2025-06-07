<?php

namespace App\View\Composers;

use Illuminate\Contracts\View\View;

final class AnotherSampleViewComposer
{
    public function compose(View $view): void
    {
        if (random_int(0, 100) > 50) {
            $view->with('count', 3);
            if (true) {
                $view->with('foo', 'bar');
            }
        } else {
            $view->with('count', 4);
        }
    }
}
